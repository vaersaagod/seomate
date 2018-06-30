<?php
/**
 * SEOMate plugin for Craft CMS 3.x
 *
 * -
 *
 * @link      https://www.vaersaagod.no/
 * @copyright Copyright (c) 2018 Værsågod
 */

namespace vaersaagod\seomate\services;

use aelvan\imager\helpers\ImagerHelpers;
use craft\elements\db\MatrixBlockQuery;
use craft\helpers\Template;
use craft\helpers\UrlHelper;
use vaersaagod\seomate\assetbundles\SEOMate\SEOMateAsset;
use vaersaagod\seomate\helpers\CacheHelper;
use vaersaagod\seomate\helpers\SEOMateHelper;
use vaersaagod\seomate\SEOMate;

use Craft;
use craft\base\Component;

/**
 * SEOMateService Service
 *
 * All of your plugin’s business logic should go in services, including saving data,
 * retrieving data, etc. They provide APIs that your controllers, template variables,
 * and other plugins can interact with.
 *
 * https://craftcms.com/docs/plugins/services
 *
 * @author    Værsågod
 * @package   SEOMate
 * @since     1.0.0
 */
class SEOMateMetaService extends Component
{

    public function getContextMeta($context)
    {
        $craft = Craft::$app;
        $settings = SEOMate::$plugin->getSettings();

        $overrideObject = $context['seomate'] ?? null;

        if ($overrideObject && isset($overrideObject['config'])) {
            SEOMateHelper::updateSettings($settings, $overrideObject['config']);
        }

        if ($overrideObject && isset($overrideObject['element'])) {
            $element = $overrideObject['element'];
        } else {
            $element = $craft->urlManager->getMatchedElement();
        }

        if ($element && $settings->cacheEnabled && CacheHelper::hasMetaCacheForElement($element)) {
            return CacheHelper::getMetaCacheForElement($element);
        }

        $meta = [];

        if ($element) {
            $meta = $this->getElementMeta($element, $overrideObject);
        }

        // Overwrite with pre-generated values from template
        if ($overrideObject && isset($overrideObject['meta'])) {
            $this->overrideMeta($meta, $overrideObject['meta']);
        }

        // Add default meta if available
        if (isset($settings->defaultMeta) && is_array($settings->defaultMeta) && count($settings->defaultMeta) > 0) {
            $meta = $this->processDefaultMeta($meta, $context, $settings);
        }

        // Autofill missing attributes
        $meta = $this->autofillMeta($meta, $settings);

        // Parse assets if applicable
        if (!$settings->returnImageAsset) {
            $meta = $this->transformMetaAssets($meta, $settings);
        }


        // Apply restrictions
        if ($settings->applyRestrictions) {
            $meta = $this->applyMetaRestrictions($meta, $settings);
        }

        // Add sitename if desirable
        if ($settings->includeSitenameInTitle) {
            $meta = $this->addSitename($meta, $settings);
        }

        // Additional mate data
        // todo : Should this be moved out to event handler?
        // todo : Maybe not necessary? Should maybe be in defaultMeta instead?
        if (isset($settings->additionalMeta) && is_array($settings->additionalMeta) && count($settings->additionalMeta) > 0) {
            $meta = $this->processAdditionalMeta($meta, $context, $settings);
        }

        // Cache it
        if ($element && $settings->cacheEnabled) {
            CacheHelper::setMetaCacheForElement($element, $meta);
        }

        return $meta;
    }

    public function getAlternateUrls($context)
    {
        $craft = Craft::$app;
        $settings = SEOMate::$plugin->getSettings();

        $overrideObject = $context['seomate'] ?? null;

        if ($overrideObject && isset($overrideObject['config'])) {
            SEOMateHelper::updateSettings($settings, $overrideObject['config']);
        }

        $alternateUrls = [];
        $matchedElement = $craft->urlManager->getMatchedElement();
        $currentSite = $craft->getSites()->getCurrentSite();

        if (!$matchedElement || !$currentSite) {
            return [];
        }

        foreach ($craft->getSites()->getAllSites() as $site) {
            if ($site->id !== $currentSite->id) {
                $url = $craft->getElements()->getElementUriForSite($matchedElement->getId(), $site->id);
                $url = ($url === '__home__') ? '' : $url;

                if (!UrlHelper::isAbsoluteUrl($url)) {
                    try {
                        $url = UrlHelper::siteUrl($url, null, null, $site->id);
                    } catch (Exception $e) {
                        $url = '';
                        Craft::error($e->getMessage(), __METHOD__);
                    }
                }

                if ($url && $url !== '') {
                    $alternateUrls[] = [
                        'url' => $url,
                        'language' => strtolower(str_replace('_', '-', $site->language))
                    ];
                }
            }
        }

        return $alternateUrls;
    }

    public function renderMetaTag($key, $value)
    {
        $craft = Craft::$app;
        $settings = SEOMate::$plugin->getSettings();

        $tagTemplateMap = SEOMateHelper::expandMap($settings->tagTemplateMap);
        $template = $tagTemplateMap['default'] ?? '';

        if (isset($tagTemplateMap[$key])) {
            $template = $tagTemplateMap[$key];
        }

        $r = '';

        if (is_array($value)) {
            foreach ($value as $val) {
                $r .= Craft::$app->getView()->renderString($template, [ 'key' => $key, 'value' => $val ]);
            }
        } else {
            $r .= Craft::$app->getView()->renderString($template, [ 'key' => $key, 'value' => $value ]);
        }

        return Template::raw($r);
    }

    public function getElementMeta($element, $overrides = null)
    {
        $craft = Craft::$app;
        $settings = SEOMate::$plugin->getSettings();

        if ($overrides && $overrides['config']) {
            SEOMateHelper::updateSettings($settings, $overrides['config']);
        }

        $profile = null;

        if ($overrides && isset($overrides['profile'])) {
            $profile = $overrides['profile'];
        } else {
            $profile = SEOMateHelper::getElementProfile($element, $settings);
        }

        if ($profile === null) {
            $profile = $settings->defaultProfile ?? null;
        }

        $meta = [];

        if ($profile && isset($settings->fieldProfiles[$profile])) {
            $fieldProfile = $settings->fieldProfiles[$profile];

            $meta = $this->generateElementMetaByProfile($element, $fieldProfile);
        } else {
            // todo : fallback meta
        }

        return $meta;
    }

    public function generateElementMetaByProfile($element, $profile)
    {
        $r = [];

        foreach ($profile as $key => $value) {
            $keyType = SEOMateHelper::getMetaTypeByKey($key);
            $r[$key] = $this->getElementPropertyDataByFields($element, $keyType, $value);
        }

        return $r;
    }


    public function getElementPropertyDataByFields($element, $type, $fields)
    {
        if ($type === 'text') {
            if (is_array($fields)) {
                foreach ($fields as $fieldName) {
                    if (isset($element[$fieldName]) && $element[$fieldName] !== null && $element[$fieldName] !== '') {
                        return trim(strip_tags((string)$element[$fieldName]));
                    }
                }
            }
        }

        if ($type === 'image') {
            if (is_array($fields)) {
                foreach ($fields as $fieldName) {

                    if ($element[$fieldName] ?? null) {
                        $assets = $element[$fieldName]->all();

                        foreach ($assets as $asset) {
                            return $asset;
                        }
                    } else if (!!\strpos($fieldName, ':')) {

                        // Assume Matrix field config in the format $fieldHandle:$blockTypeHandle.$fieldHandle
                        $matrixFieldPathSegments = \explode(':', $fieldName);
                        $fieldName = \array_shift($matrixFieldPathSegments) ?: null;

                        if (!$fieldName || empty($matrixFieldPathSegments) || !($element[$fieldName] ?? null) || !($element[$fieldName] instanceof MatrixBlockQuery)) {
                            continue;
                        }

                        $blockPathSegments = \explode('.', $matrixFieldPathSegments[0]);
                        if (!($blockTypeHandle = $blockPathSegments[0] ?? null) || !($blockFieldHandle = $blockPathSegments[1] ?? null)) {
                            continue;
                        }

                        $blocks = $element[$fieldName]
                            ->type($blockTypeHandle)
                            ->with(["{$blockTypeHandle}:{$blockFieldHandle}"])
                            ->all();

                        foreach ($blocks as $block) {
                            if ($asset = $block[$blockFieldHandle][0] ?? null) {
                                return $asset;
                            }
                        }

                    }
                }
            }
        }

        return '';
    }

    public function getContextPropertyDataByFields($context, $type, $fields)
    {
        if (is_array($fields)) {
            foreach ($fields as $fieldName) {
                $field = SEOMateHelper::getTargetFieldByHandleFromScope($context, $fieldName);

                if ($type === 'text') {
                    if ($field !== null && $field !== '') {
                        return trim(strip_tags((string)$field));
                    }
                }

                if ($type === 'image') {
                    if ($field !== null) {
                        $assets = $field->all();

                        foreach ($assets as $asset) {
                            return $asset;
                        }
                    }
                }
            }
        }

        return '';
    }

    public function transformMetaAssets($meta, $settings = null)
    {
        if ($settings === null) {
            $settings = SEOMate::$plugin->getSettings();
        }

        $imageTransformMap = $settings->imageTransformMap;

        foreach ($imageTransformMap as $key => $value) {
            if (isset($meta[$key]) && $meta[$key] !== null && $meta[$key] !== '') {

                $transform = $imageTransformMap[$key];
                $asset = $meta[$key] ?? null;
                if ($asset) {
                    $meta[$key] = $this->getTransformedUrl($asset, $transform, $settings);

                    // todo : need to figure out something better
                    if ($key === 'og:image') {
                        if (isset($transform['format'])) {
                            $meta[$key . ':type'] = 'image/jpg';
                        }
                        if (isset($transform['width'])) {
                            $meta[$key . ':width'] = $transform['width'];
                        }
                        if (isset($transform['height'])) {
                            $meta[$key . ':height'] = $transform['height'];
                        }
                    }
                }
            }
        }

        return $meta;
    }

    public function getTransformedUrl($asset, $transform, $settings = null)
    {
        if ($settings === null) {
            $settings = SEOMate::$plugin->getSettings();
        }

        $imagerPlugin = Craft::$app->plugins->getPlugin('imager');
        $transformedUrl = '';

        if ($settings->useImagerIfInstalled && $imagerPlugin) {
            // todo : should we set more defaults?
            if (!isset($transform['position']) && !is_string($asset) && isset($asset['focalPoint'])) {
                $transform['position'] = $asset['focalPoint'];
            }

            $transformedAsset = $imagerPlugin->imager->transformImage($asset, $transform, [], []);

            if ($transformedAsset) {
                $transformedUrl = $transformedAsset->getUrl();
            }
        } else {
            $transformedUrl = $asset->getUrl($transform);
        }

        $transformedUrl = SEOMateHelper::ensureAbsoluteUrl($transformedUrl);

        return $transformedUrl;
    }

    public function overrideMeta(&$meta, $overrideMeta)
    {
        foreach ($overrideMeta as $key => $value) {
            $meta[$key] = $value;
        }
    }

    public function autofillMeta($meta, $settings = null)
    {
        if ($settings === null) {
            $settings = SEOMate::$plugin->getSettings();
        }

        $autofillMap = SEOMateHelper::expandMap($settings->autofillMap);

        foreach ($autofillMap as $key => $value) {
            if ((!isset($meta[$key]) || $meta[$key] === null) && isset($meta[$value])) {
                $meta[$key] = $meta[$value];
            }
        }

        return $meta;
    }

    public function applyMetaRestrictions($meta, $settings = null)
    {
        if ($settings === null) {
            $settings = SEOMate::$plugin->getSettings();
        }

        $restrictionsMap = SEOMateHelper::expandMap($settings->metaPropertyTypes);

        foreach ($meta as $key => $value) {
            if (isset($restrictionsMap[$key])) {
                $restrictions = $restrictionsMap[$key];

                // todo : needs much more robust handling, this is proof of concept

                if ($restrictions['type'] === 'text' && isset($restrictions['maxLength'])) {
                    if (strlen($value) > $restrictions['maxLength']) {
                        $meta[$key] = substr($value, 0, $restrictions['maxLength'] - 3) . '…';
                    }
                }
            }
        }

        return $meta;
    }

    public function addSitename($meta, $settings = null)
    {
        if ($settings === null) {
            $settings = SEOMate::$plugin->getSettings();
        }

        // todo : revisit, is this good enough? More fallback?

        if (is_array($settings->siteName)) {
            $siteName = $settings->siteName[Craft::$app->getSites()->getCurrentSite()->handle] ?? '';
        } else {
            $siteName = $settings->siteName ?? '';
        }

        $preString = $settings->sitenamePosition === 'before' ? $siteName . ' ' . $settings->sitenameSeparator . ' ' : '';
        $postString = $settings->sitenamePosition === 'after' ? ' ' . $settings->sitenameSeparator . ' ' . $siteName : '';

        foreach ($settings->sitenameTitleProperties as $property) {
            $meta[$property] = $preString . $meta[$property] . $postString;
        }

        return $meta;
    }

    public function processDefaultMeta($meta, $context = [], $settings = null)
    {
        if ($settings === null) {
            $settings = SEOMate::$plugin->getSettings();
        }

        foreach ($settings->defaultMeta as $key => $value) {
            if (!isset($meta[$key]) || $meta[$key] === null || $meta[$key] === '') {
                $keyType = SEOMateHelper::getMetaTypeByKey($key);
                $meta[$key] = $this->getContextPropertyDataByFields($context, $keyType, $value);
            }
        }

        return $meta;
    }

    public function processAdditionalMeta($meta, $context = [], $settings = null)
    {
        if ($settings === null) {
            $settings = SEOMate::$plugin->getSettings();
        }

        foreach ($settings->additionalMeta as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subValue) {
                    // todo : Not good. Not good at all. :/
                    $renderedValue = SEOMateHelper::renderString($subValue, $context);

                    if ($renderedValue && $renderedValue !== '') {
                        $meta[$key][] = $renderedValue;
                    }
                }
            } else {
                $meta[$key] = SEOMateHelper::renderString($value, $context);
            }
        }

        return $meta;
    }
}
