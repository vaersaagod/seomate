<?php
/**
 * SEOMate plugin for Craft CMS 3.x
 *
 * @link      https://www.vaersaagod.no/
 * @copyright Copyright (c) 2019 Værsågod
 */

namespace vaersaagod\seomate\helpers;

use Craft;
use craft\elements\Asset;
use craft\errors\SiteNotFoundException;
use craft\helpers\UrlHelper;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
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
     * 
     * @param Settings $settings
     * @param $overrides
     */
    public static function updateSettings(&$settings, $overrides)
    {
        foreach ($overrides as $key => $val) {
            $settings[$key] = $val;
        }
    }

    /**
     * Gets the profile to use from element and settings
     * 
     * @param $element
     * @param $settings
     * @return mixed|null
     */
    public static function getElementProfile($element, $settings)
    {
        if (!isset($settings->profileMap) || !\is_array($settings->profileMap)) {
            return null;
        }

        $fieldMap = self::expandMap($settings->profileMap);
        $mapIds = [];

        if (method_exists($element, 'refHandle')) {
            $refHandle = strtolower($element->refHandle());

            switch ($refHandle) {
                case 'entry':
                    $mapIds[] = $element->section->handle;
                    break;
                case 'category':
                    $mapIds[] = $element->group->handle;
                    break;
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
     * 
     * @param $key
     * @return string
     */
    public static function getMetaTypeByKey($key): string
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
     * Finds a field in scope by handle
     * 
     * @param array $scope
     * @param string $handle
     * @return null|string
     */
    public static function getTargetFieldByHandleFromScope($scope, $handle)
    {
        if (strrpos($handle, '.') === false) {
            return $scope[$handle];
        }

        $handleParts = explode('.', $handle);

        $currentScope = null;
        $first = true; // a wee bit ugly, but it's to avoid that a wrong target is reached if one part is null.

        foreach ($handleParts as $part) {
            if ($first === true) {
                $currentScope = $scope[$part];
            } else if ($currentScope !== null) {
                $currentScope = $currentScope[$part] ?? null;
            }

            $first = false;
        }

        return $currentScope;
    }

    /**
     * Checks if give Asset is in the list of $settings->validImageExtensions
     *
     * @param Asset $asset
     * @return bool
     */
    public static function isValidImageAsset($asset): bool
    {
        $settings = SEOMate::$plugin->getSettings();

        if (\in_array(strtolower($asset->extension), $settings->validImageExtensions, true)) {
            return true;
        }

        return false;
    }

    /**
     * Expands config setting map where key is exandable
     *
     * @param array $map
     * @return array
     */
    public static function expandMap($map): array
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
     *
     * @param array $array
     * @return bool
     */
    public static function isAssocArray($array): bool
    {
        if (array() === $array) {
            return false;
        }

        return array_keys($array) !== range(0, \count($array) - 1);
    }

    /**
     * Renders a string template with context
     *
     * @param string $string
     * @param array $context
     * @return string
     */
    public static function renderString($string, $context): string
    {
        try {
            return Craft::$app->getView()->renderString($string, $context);
        } catch (LoaderError $e) {
            Craft::error($e->getMessage(), __METHOD__);
        } catch (SyntaxError $e) {
            Craft::error($e->getMessage(), __METHOD__);
        }

        return '';
    }

    /**
     * @param string $url
     * @return string|null
     */
    public static function ensureAbsoluteUrl($url)
    {
        if (UrlHelper::isAbsoluteUrl($url)) {
            return $url;
        }

        // Get the base url and assume it's what we want to use
        try {
            $siteUrl = UrlHelper::baseSiteUrl();
            $siteUrlParts = parse_url($siteUrl);
            $scheme = $siteUrlParts['scheme'] ?? (Craft::$app->getRequest()->isSecureConnection ? 'https' : 'http');

            if (UrlHelper::isProtocolRelativeUrl($url)) {
                return UrlHelper::urlWithScheme($url, $scheme);
            }

            if (strpos($url, '/') === 0) {
                return $scheme . '://' . $siteUrlParts['host'] . $url;
            }

            // huh, relative url? Seems unlikely, but... If we've come this far.
            return $scheme . '://' . $siteUrlParts['host'] . '/' . $url;
        } catch (SiteNotFoundException $e) {
            Craft::error($e->getMessage(), __METHOD__);
        }

        return null;
    }
}
