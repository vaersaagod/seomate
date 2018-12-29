<?php
/**
 * SEOMate plugin for Craft CMS 3.x
 *
 * @link      https://www.vaersaagod.no/
 * @copyright Copyright (c) 2018 Værsågod
 */

namespace vaersaagod\seomate\helpers;

use Craft;
use craft\elements\Asset;
use craft\helpers\UrlHelper;
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
     * @param Settings $settings
     * @param $overrides
     */
    public static function updateSettings(&$settings, $overrides)
    {
        foreach ($overrides as $key => $val) {
            // todo : special handling of nested properties
            $settings[$key] = $val;
        }
    }

    /**
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

            // todo : add additional handles

            switch ($refHandle) {
                case 'entry':
                    $mapIds[] = $element->section->handle;
                    break;
                case 'category':
                    $mapIds[] = $element->group->handle;
                    break;
            }
        }

        // todo: TBD, should we somehow support elements that don't have a refHandle? 

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
     * @param $key
     * @return mixed|string
     */
    public static function getMetaTypeByKey($key)
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
            } else {
                if ($currentScope !== null) {
                    $currentScope = $currentScope[$part] ?? null;
                }
            }

            $first = false;
        }

        return $currentScope;
    }

    /**
     * @param Asset $asset
     * @return bool
     */
    public static function isValidImageAsset($asset) {
        $settings = SEOMate::$plugin->getSettings();
        
        if (\in_array(strtolower($asset->extension), $settings->validImageExtensions, true)) {
            return true;
        }
        
        return false;
    }

    /**
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
     * @param string $string
     * @param $context
     * @return string
     */
    public static function renderString($string, $context): string
    {
        return Craft::$app->getView()->renderString($string, $context);
    }

    /**
     * @param string $url
     * @return string
     * @throws \craft\errors\SiteNotFoundException
     */
    public static function ensureAbsoluteUrl($url): string
    {
        if (UrlHelper::isAbsoluteUrl($url)) {
            return $url;
        }

        // Get the base url and assume that that's what we wanna use.
        $siteUrl = UrlHelper::baseSiteUrl();
        $siteUrlParts = parse_url($siteUrl);

        if (UrlHelper::isProtocolRelativeUrl($url)) {
            return UrlHelper::urlWithScheme($url, $siteUrlParts['scheme']);
        }

        if (strpos($url, '/') === 0) {
            return $siteUrlParts['scheme'] . '://' . $siteUrlParts['host'] . $url;
        }

        // huh, relative url? Seems unlikely, but... If we've come this far.
        return $siteUrlParts['scheme'] . '://' . $siteUrlParts['host'] . '/' . $url;
    }
}
