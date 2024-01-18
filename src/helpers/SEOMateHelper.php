<?php
/**
 * SEOMate plugin for Craft CMS 3.x
 *
 * @link      https://www.vaersaagod.no/
 * @copyright Copyright (c) 2019 Værsågod
 */

namespace vaersaagod\seomate\helpers;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\elements\Asset;
use craft\elements\db\AssetQuery;
use craft\elements\db\MatrixBlockQuery;
use craft\elements\MatrixBlock;
use craft\errors\SiteNotFoundException;
use craft\helpers\UrlHelper;

use Illuminate\Support\Collection;

use vaersaagod\seomate\models\Settings;
use vaersaagod\seomate\SEOMate;

/**
 * SEOMate Helper
 *
 * @author    Værsågod
 * @package   SEOMate
 * @since     1.0.0
 */
class SEOMateHelper
{
    /**
     * Updates Settings model wit override values
     */
    public static function updateSettings(Settings $settings, array $overrides): void
    {
        foreach ($overrides as $key => $val) {
            $settings[$key] = $val;
        }
    }

    /**
     * Gets the profile to use from element and settings
     */
    public static function getElementProfile(Element $element, Settings $settings): mixed
    {
        if (!isset($settings->profileMap) || !\is_array($settings->profileMap)) {
            return null;
        }

        $fieldMap = self::expandMap($settings->profileMap);
        $mapIds = [];

        if (method_exists($element, 'refHandle')) {
            $refHandle = strtolower($element->refHandle());

            if ($refHandle == 'entry') {
                $mapIds[] = $element->section->handle;
            } elseif ($refHandle == 'category') {
                $mapIds[] = $element->group->handle;
            }
        }

        if (\count($mapIds) === 0) {
            return null;
        }

        foreach ($mapIds as $mapId) {
            if (isset($fieldMap[$mapId])) {
                return $fieldMap[$mapId];
            }
        }

        return null;
    }

    /**
     * Returns the meta type from key
     */
    public static function getMetaTypeByKey(string $key): string
    {
        $settings = SEOMate::$plugin->getSettings();
        $typeMap = self::expandMap($settings->metaPropertyTypes);

        if (isset($typeMap[$key])) {
            if (\is_array($typeMap[$key])) {
                return $typeMap[$key]['type'];
            }

            return $typeMap[$key];
        }

        return 'text';
    }

    /**
     * Reduces a nested scope to the deepest possible target scope, and return it and
     * the remaining handle.
     */
    public static function reduceScopeAndHandle(array $scope, string $handle): array
    {
        if (strrpos($handle, '.') === false) {
            return [$scope, $handle];
        }

        $currentScope = null;
        $handleParts = explode('.', $handle);
        $first = true; // a wee bit ugly, but it's to avoid that a wrong target is reached if one part is null.

        for ($i = 0, $iMax = count($handleParts) - 1; $i < $iMax; ++$i) {
            $part = $handleParts[$i];

            if (strrpos($part, ':') !== false) {
                return [$currentScope, implode('.', array_slice($handleParts, $i))];
            }

            if ($first) {
                $currentScope = $scope[$part] ?? null;
            } elseif ($currentScope !== null) {
                $currentScope = $currentScope[$part] ?? null;
            }
        }

        return [$currentScope, $handleParts[count($handleParts) - 1]];
    }

    /**
     * @param ElementInterface|array $scope
     * @param string $handle
     * @param string $type
     * @return Asset|string|null
     * @throws \craft\errors\DeprecationException
     */
    public static function getPropertyDataByScopeAndHandle(ElementInterface|array $scope, string $handle, string $type): Asset|string|null
    {

        if ($scope[$handle] ?? null) {

            // Simple field
            if ($type === 'text') {

                return static::getStringPropertyValue($scope[$handle]);

            } elseif ($type === 'image') {

                return static::getImagePropertyValue($scope[$handle]);

            }

        } elseif (strpos($handle, ':')) {

            // Assume Matrix sub field, in the format matrixFieldHandle.blockTypeHandle:subFieldHandle – check that the format looks correct, just based on the delimiters used
            $delimiters = preg_replace('/[^\\.:]+/', '', $handle);
            if ($delimiters !== '.:') {
                if ($delimiters === ':.') {
                    // Old syntax "matrixFieldHandle:blockTypeHandle.subFieldHandle" is used. We kind of messed up when we built SEOmate initially by not using the same syntax as Craft does when eager-loading Matrix sub fields.
                    // We still allow the old format, but we might need to remove support for it later – so let's log a deprecation message
                    Craft::$app->getDeprecator()->log(__METHOD__, 'Support for the `matrixFieldHandle:blockTypeHandle.subFieldHandle` Matrix sub field syntax in `config/seomate.php` has been deprecated. Use the syntax `matrixFieldHandle.blockTypeHandle:subFieldHandle` instead.');
                } else {
                    // This is not something we can work with :/
                    Craft::warning("Invalid syntax encountered for Matrix sub fields in SEOMate field profile config: \"$handle\". The correct syntax is \"matrixFieldHandle.blockTypeHandle:subFieldHandle\"");
                    return null;
                }
            }

            // Get field, block type and sub field handles
            [$matrixFieldHandle, $blockTypeHandle, $subFieldHandle] = explode('.', str_replace(':', '.', $handle));
            if (!$matrixFieldHandle || !$blockTypeHandle || !$subFieldHandle) {
                return null;
            }

            // Make sure that the Matrix field is in scope, in some form or another
            $value = $scope[$matrixFieldHandle] ?? null;
            if (empty($value)) {
                return null;
            }

            // Fetch the blocks
            if ($value instanceof MatrixBlockQuery) {
                $query = (clone $value)->type($blockTypeHandle);
                if ($type === 'image') {
                    $query->with([sprintf('%s:%s', $blockTypeHandle, $subFieldHandle)]);
                }
                $blocks = $query->all();
            } else {
                $blocks = Collection::make($value)
                    ->filter(static function (mixed $block) use ($blockTypeHandle) {
                        return $block instanceof MatrixBlock && $block->getType()->handle === $blockTypeHandle;
                    })
                    ->all();
            }

            if (empty($blocks)) {
                return null;
            }

            /** @var MatrixBlock[] $blocks */
            foreach ($blocks as $block) {

                if ($type === 'text') {

                    if ($value = static::getStringPropertyValue($block->$subFieldHandle ?? null)) {
                        return $value;
                    }

                } else if ($type === 'image') {

                    if ($asset = static::getImagePropertyValue($block->$subFieldHandle ?? null)) {
                        return $asset;
                    }

                }
            }

        }

        return null;
    }

    /**
     * Return a meta-safe string value from raw input
     *
     * @param mixed $input
     * @return string|null
     */
    public static function getStringPropertyValue(mixed $input): ?string
    {
        if (empty($input)) {
            return null;
        }

        $value = (string)$input;

        // Replace all control characters, newlines and returns with a literal space
        $value = preg_replace('/[[:cntrl:]](?! )/', ' ', $value);
        $value = preg_replace('/[[:cntrl:]]/', '', $value);

        // Add literal spaces after linebreak elements and closing paragraph tags, to avoid words being joined together after stripping tags
        $value = preg_replace('/((<\/p>|<br( ?)(\/?)>)(?=\S))/iu', '$1 ', $value);

        // Strip tags, trim and return
        return trim(strip_tags($value)) ?: null;
    }

    /**
     * Return a meta-safe image asset from raw input
     *
     * @param mixed $input
     * @return Asset|null
     */
    public static function getImagePropertyValue(mixed $input): ?Asset
    {
        if (empty($input)) {
            return null;
        }

        if ($input instanceof AssetQuery) {
            $collection = (clone $input)->kind(Asset::KIND_IMAGE)->collect();
        } else {
            $collection = Collection::make($input);
        }

        if ($collection->isEmpty()) {
            return null;
        }

        $settings = SEOMate::getInstance()->getSettings();

        return $collection->first(static function (mixed $asset) use ($settings) {
            return $asset instanceof Asset && $asset->kind === Asset::KIND_IMAGE && in_array(strtolower($asset->getExtension()), $settings->validImageExtensions, true);
        });
    }

    /**
     * Expands config setting map where key is exandable
     */
    public static function expandMap(array $map): array
    {
        $r = [];

        foreach ($map as $k => $v) {
            $keys = explode(',', $k);

            foreach ($keys as $key) {
                $r[trim($key)] = $v;
            }
        }

        return $r;
    }

    /**
     * Checks if array is associative
     */
    public static function isAssocArray(array $array): bool
    {
        if (array() === $array) {
            return false;
        }

        return array_keys($array) !== range(0, \count($array) - 1);
    }

    /**
     * Renders a string template with context
     */
    public static function renderString(string $string, array $context): string
    {
        try {
            return Craft::$app->getView()->renderString($string, $context);
        } catch (\Throwable $throwable) {
            Craft::error($throwable->getMessage(), __METHOD__);
        }

        return '';
    }

    /**
     * @throws SiteNotFoundException
     */
    public static function ensureAbsoluteUrl(string $url): string
    {
        if (UrlHelper::isAbsoluteUrl($url)) {
            return $url;
        }

        // Get the base url and assume it's what we want to use
        $siteUrl = UrlHelper::baseSiteUrl();
        $siteUrlParts = parse_url($siteUrl);
        $scheme = $siteUrlParts['scheme'] ?? (Craft::$app->getRequest()->isSecureConnection ? 'https' : 'http');

        if (UrlHelper::isProtocolRelativeUrl($url)) {
            return UrlHelper::urlWithScheme($url, $scheme);
        }

        if (str_starts_with($url, '/')) {
            return $scheme . '://' . $siteUrlParts['host'] . $url;
        }

        // huh, relative url? Seems unlikely, but... If we've come this far.
        return $scheme . '://' . $siteUrlParts['host'] . '/' . $url;
    }
}
