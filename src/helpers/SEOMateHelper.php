<?php
/**
 * SEOMate plugin for Craft CMS 5.x
 *
 * @link      https://www.vaersaagod.no/
 * @copyright Copyright (c) 2024 Værsågod
 */

namespace vaersaagod\seomate\helpers;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\commerce\elements\Product;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\db\AssetQuery;
use craft\elements\db\EntryQuery;
use craft\elements\Entry;
use craft\elements\User;
use craft\errors\SiteNotFoundException;
use craft\helpers\UrlHelper;

use Illuminate\Support\Collection;

use vaersaagod\seomate\models\Settings;
use vaersaagod\seomate\SEOMate;

use yii\base\InvalidConfigException;

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
     * Updates Settings model with override values
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
    public static function getElementProfile(Element $element, Settings $settings): ?string
    {
        if (empty($settings->profileMap)) {
            return null;
        }

        $fieldMap = self::expandMap($settings->profileMap);

        if ($element instanceof Entry) {
            $typeHandle = $element->getType()->handle;
            $sectionHandle = $element->getSection()?->handle;
            $mapIds = [
                "entryType:$typeHandle",
                $sectionHandle ? "section:$sectionHandle" : null,
                $typeHandle,
                $sectionHandle,
            ];
        } else if ($element instanceof Category) {
            $groupHandle = $element->getGroup()->handle;
            $mapIds = [
                "categoryGroup:$groupHandle",
                $groupHandle,
            ];
        } else if ($element instanceof User) {
            $mapIds = [
                "user",
            ];
        } else if ($element instanceof Product) {
            $productTypeHandle = $element->getType()->handle;
            $mapIds = [
                "productType:$productTypeHandle",
                $productTypeHandle,
            ];
        } else {
            return null;
        }

        $mapIds = array_values(array_unique(array_filter($mapIds)));

        if (empty($mapIds)) {
            return null;
        }

        foreach ($mapIds as $mapId) {
            if (!empty($fieldMap[$mapId])) {
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
        $settings = SEOMate::getInstance()->getSettings();
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
                $first = false;
            } elseif ($currentScope !== null) {
                $currentScope = $currentScope[$part] ?? null;
            }
        }

        return [$currentScope, $handleParts[count($handleParts) - 1]];
    }

    /**
     * @param ElementInterface|array $scope
     * @param string|\Closure        $handle
     * @param string                 $type
     *
     * @return Asset|string|null
     */
    public static function getPropertyDataByScopeAndHandle(ElementInterface|array $scope, string|\Closure $handle, string $type): Asset|string|null
    {
        if ($handle instanceof \Closure) {
            try {
                $result = $handle($scope);
            } catch (\Throwable $throwable) {
                Craft::error('An error occurred when calling closure: '. $throwable->getMessage(), __METHOD__);
                return null;
            }
            if ($type === 'text') {
                return static::getStringPropertyValue($result);
            }
            if ($type === 'image') {
                return static::getImagePropertyValue($result);
            }
            return null;
        }

        if (str_contains(trim($handle), '{')) {
            try {
                $result = Craft::$app->getView()->renderObjectTemplate($handle, $scope);
            } catch (\Throwable $throwable) {
                Craft::error('An error occurred when trying to render object template: '. $throwable->getMessage(), __METHOD__);
                return null;
            }
            if ($type === 'text') {
                return static::getStringPropertyValue($result);
            }
            // If this is an "image" meta tag type, assume that the object template has rendered an asset ID
            if ($type === 'image' && $assetId = (int)$result) {
                $asset = Asset::find()->id($assetId)->one();
                return static::getImagePropertyValue($asset);
            }
            return null;
        }

        if (!empty($scope[$handle])) {
            if ($type === 'text') {
                return static::getStringPropertyValue($scope[$handle]);
            }
            if ($type === 'image') {
                return static::getImagePropertyValue($scope[$handle]);
            }
        } elseif (strpos($handle, ':')) {

            // Assume subfield, in the format fieldHandle.typeHandle:subFieldHandle – check that the format looks correct, just based on the delimiters used
            $delimiters = preg_replace('/[^\\.:]+/', '', $handle);
            if ($delimiters !== '.:') {
                // This is not something we can work with :/
                Craft::warning("Invalid syntax encountered for sub fields in SEOMate field profile config: \"$handle\". The correct syntax is \"fieldHandle.typeHandle:subFieldHandle\"");

                return null;
            }

            // Get field, block type and subfield handles
            [$fieldHandle, $blockTypeHandle, $subFieldHandle] = explode('.', str_replace(':', '.', $handle));
            if (!$fieldHandle || !$blockTypeHandle || !$subFieldHandle) {
                return null;
            }

            // Make sure that the field is in scope, in some form or another
            $value = $scope[$fieldHandle] ?? null;
            if (empty($value)) {
                return null;
            }

            // Fetch the blocks
            if ($value instanceof EntryQuery) {
                $query = (clone $value)->type($blockTypeHandle);
                if ($type === 'image') {
                    $query->with([sprintf('%s:%s', $blockTypeHandle, $subFieldHandle)]);
                }
                $blocks = $query->all();
            } else {
                $blocks = Collection::make($value)
                    ->filter(static function(mixed $block) use ($blockTypeHandle) {
                        return $block instanceof Entry && $block->getType()->handle === $blockTypeHandle;
                    })
                    ->all();
            }

            if (empty($blocks)) {
                return null;
            }

            /** @var Entry[] $blocks */
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
     *
     * @return string|null
     */
    public static function getStringPropertyValue(mixed $input): ?string
    {
        if (empty($input)) {
            return null;
        }

        $value = (string)$input;

        // Replace all control characters, newlines and returns with a literal space
        $value = preg_replace('/(?<!\s)[[:cntrl:]]|(?<=[[:cntrl:]])\s/', ' ', $value);
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
     *
     * @return Asset|null
     */
    public static function getImagePropertyValue(mixed $input): ?Asset
    {
        if (empty($input)) {
            return null;
        }

        if ($input instanceof Asset) {
            $input = [$input];
        }

        if ($input instanceof AssetQuery) {
            $collection = (clone $input)->kind(Asset::KIND_IMAGE)->collect();
        } else if (is_array($input)) {
            $collection = Collection::make($input);
        } else if ($input instanceof Collection) {
            $collection = $input;
        }

        if (!isset($collection) || $collection->isEmpty()) {
            return null;
        }

        $settings = SEOMate::getInstance()->getSettings();

        return $collection->first(static function(mixed $asset) use ($settings) {
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
        if ([] === $array) {
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
            return $scheme.'://'.$siteUrlParts['host'].$url;
        }

        // huh, relative url? Seems unlikely, but... If we've come this far.
        return $scheme.'://'.$siteUrlParts['host'].'/'.$url;
    }

    /**
     * Returns true if the element a) has a URL and b) is eligble to be SEO-previewed as per the `previewEnabled` setting
     *
     * @param ElementInterface $element
     * @return bool
     * @throws InvalidConfigException
     */
    public static function isElementPreviewable(ElementInterface $element): bool
    {
        if (!$element->getUrl() || empty($element->id)) {
            // Anything that doesn't have a URL shouldn't have a SEO preview, and if it doesn't have an ID stuff won't work.
            return false;
        }

        $settings = SEOMate::getInstance()->getSettings();
        $previewEnabled = $settings->previewEnabled;

        if (empty($previewEnabled)) {
            return false;
        }

        if (is_bool($previewEnabled)) {
            return $previewEnabled;
        }

        if (is_string($previewEnabled)) {
            $previewEnabled = explode(',', preg_replace('/\s+/', '', $previewEnabled));
        }

        $previewEnabled = array_values(array_filter($previewEnabled));

        if (empty($previewEnabled)) {
            return false;
        }

        // ...if the `previewEnabled` setting is an array, it's essentially a whitelist of stuff we want to preview
        if ($element instanceof Entry) {
            $typeHandle = $element->getType()->handle;
            $sectionHandle = $element->getSection()?->handle;
            $sourceHandles = [
                "entryType:$typeHandle",
                $sectionHandle ? "section:$sectionHandle" : null,
                $typeHandle,
                $sectionHandle,
            ];
        } else if ($element instanceof Category) {
            $categoryGroupHandle = $element->getGroup()->handle;
            $sourceHandles = [
                "categoryGroup:$categoryGroupHandle",
                $categoryGroupHandle,
            ];
        } else if ($element instanceof Product) {
            $productTypeHandle = $element->getType()->handle;
            $sourceHandles = [
                "productType:$productTypeHandle",
                $productTypeHandle,
            ];
        } else if ($element instanceof User) {
            $sourceHandles = [
                'user',
            ];
        } else {
            return false;
        }

        $sourceHandles = array_values(array_unique(array_filter($sourceHandles)));

        foreach ($sourceHandles as $sourceHandle) {
            if (in_array($sourceHandle, $previewEnabled, true)) {
                return true;
            }
        }

        return false;

    }

    /**
     * @param mixed $url
     * @return string
     */
    public static function stripTokenParams(mixed $url): string
    {
        if (empty($url) || !is_string($url)) {
            return '';
        }
        $parsedUrl = parse_url($url) ?: [];
        $queryString = $parsedUrl['query'] ?? null;
        if (empty($queryString)) {
            return $url;
        }
        parse_str($queryString, $queryParams);
        $queryParamsToRemove = [
            Craft::$app->getConfig()->getGeneral()->tokenParam,
            Craft::$app->getConfig()->getGeneral()->siteToken,
            'x-craft-live-preview',
            'x-craft-preview',
        ];
        foreach ($queryParamsToRemove as $queryParamToRemove) {
            unset($queryParams[$queryParamToRemove]);
        }
        $newQueryString = http_build_query($queryParams);
        $url = trim(str_replace($queryString, $newQueryString, $url));
        if (empty($newQueryString)) {
            $url = rtrim($url, '?');
        }
        return $url;
    }
}
