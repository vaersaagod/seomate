<?php
/**
 * SEOMate plugin for Craft CMS 3.x
 *
 * @link      https://www.vaersaagod.no/
 * @copyright Copyright (c) 2018 Værsågod
 */

namespace vaersaagod\seomate\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\elements\Asset;
use craft\elements\db\MatrixBlockQuery;

use vaersaagod\seomate\models\Settings;
use vaersaagod\seomate\SEOMate;
use vaersaagod\seomate\helpers\CacheHelper;
use vaersaagod\seomate\helpers\SEOMateHelper;
use yii\web\ServerErrorHttpException;


/**
 * @author    Værsågod
 * @package   SEOMate
 * @since     1.0.0
 */
class MetaService extends Component
{
    
    /**
     * @param $context
     * @return array
     * @throws \craft\errors\SiteNotFoundException
     */
    public function getContextMeta($context): array
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
        if ($settings->defaultMeta !== null && \count($settings->defaultMeta) > 0) {
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
        if ($settings->additionalMeta !== null && \count($settings->additionalMeta) > 0) {
            $meta = $this->processAdditionalMeta($meta, $context, $settings);
        }

        // Cache it
        if ($element && $settings->cacheEnabled) {
            CacheHelper::setMetaCacheForElement($element, $meta);
        }

        return $meta;
    }
    
    /**
     * @param Element $element
     * @param null|array $overrides
     * @return array
     */
    public function getElementMeta($element, $overrides = null): array
    {
        $settings = SEOMate::$plugin->getSettings();

        if ($overrides && isset($overrides['config'])) {
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
        }

        return $meta;
    }

    /**
     * @param Element $element
     * @param array $profile
     * @return array
     */
    public function generateElementMetaByProfile($element, $profile): array
    {
        $r = [];

        foreach ($profile as $key => $value) {
            $keyType = SEOMateHelper::getMetaTypeByKey($key);
            $r[$key] = $this->getElementPropertyDataByFields($element, $keyType, $value);
        }

        return $r;
    }

    /**
     * @param Element $element
     * @param string $type
     * @param array $fields
     * @return null|string
     */
    public function getElementPropertyDataByFields($element, $type, $fields)
    {
        if (!\is_array($fields)) {
            $fields = [$fields];
        }

        foreach ($fields as $fieldName) {
            if ($element[$fieldName] ?? null) { // Root field
                
                if ($type === 'text') {

                    if ($value = \trim(\strip_tags((string)($element[$fieldName] ?? '')))) {
                        return $value;
                    }

                } else if ($type === 'image') {
                    $assets = $element[$fieldName]->all() ?? null;
                    
                    if ($assets) {
                        foreach ($assets as $asset) {
                            if (SEOMateHelper::isValidImageAsset($asset)) {
                                return $asset;
                            }
                        }
                    }
                }

            } else if ((bool)\strpos($fieldName, ':')) {

                // Assume Matrix field, in the config format $fieldHandle:$blockTypeHandle.$fieldHandle
                // First, get the Matrix field's handle, and test if that attribute actually is a MatrixBlockQuery instance
                $matrixFieldPathSegments = \explode(':', $fieldName);
                $fieldName = \array_shift($matrixFieldPathSegments) ?: null;
                if (!$fieldName || empty($matrixFieldPathSegments) || !($element[$fieldName] ?? null) || !($element[$fieldName] instanceof MatrixBlockQuery)) {
                    continue;
                }

                // Nice one, there's actually a Matrix field for that attribute.
                // Now get the block type and field handles
                $blockPathSegments = \explode('.', $matrixFieldPathSegments[0]);
                if (!($blockTypeHandle = $blockPathSegments[0] ?? null) || !($blockFieldHandle = $blockPathSegments[1] ?? null)) {
                    continue;
                }

                // Need to clone the element query before filtering on type, because using + mutating the actual element query would propagate to whatever happens in the actual entry template
                $blockQuery = clone $element[$fieldName];

                if ($type === 'text') {

                    $blocks = $blockQuery
                        ->type($blockTypeHandle)
                        ->all();

                    foreach ($blocks as $block) {
                        if ($value = \trim(\strip_tags((string)($block[$blockFieldHandle] ?? '')))) {
                            return $value;
                        }
                    }

                } else if ($type === 'image') {
                    // TODO : For some reason, I couldn't get this eager loading to work. :/  - @ndre
                    $blocks = $blockQuery
                        ->type($blockTypeHandle)
                        /*->with(["{$blockTypeHandle}:{$blockFieldHandle}"])*/
                        ->all();
                    
                    foreach ($blocks as $block) {
                        $assets = $block[$blockFieldHandle]->all() ?? null;
                        
                        if ($assets) {
                            foreach ($assets as $asset) {
                                if (SEOMateHelper::isValidImageAsset($asset)) {
                                    return $asset;
                                }
                            }
                        }
                    }
                }
            }
        }

        return '';
    }

    /**
     * @param $context
     * @param string $type
     * @param array $fields
     * @return mixed
     */
    public function getContextPropertyDataByFields($context, $type, $fields)
    {
        if (\is_array($fields)) {
            foreach ($fields as $fieldName) {
                $field = SEOMateHelper::getTargetFieldByHandleFromScope($context, $fieldName);

                if ($type === 'text') {
                    if ($field !== null && $field !== '') {
                        return trim(strip_tags((string)$field));
                    }
                }

                if ($type === 'image') {
                    if ($field !== null) {
                        $assets = $field->all() ?? null;

                        if ($assets) {
                            foreach ($assets as $asset) {
                                if (SEOMateHelper::isValidImageAsset($asset)) {
                                    return $asset;
                                }
                            }
                        }
                    }
                }
            }
        }

        return '';
    }

    /**
     * @param array $meta
     * @param null|Settings $settings
     * @return mixed
     * @throws \craft\errors\SiteNotFoundException
     */
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

                    $alt = null;

                    if ($settings->altTextFieldHandle && $asset[$settings->altTextFieldHandle] && ((string)$asset[$settings->altTextFieldHandle] !== '')) {
                        $alt = $asset->getAttributes()[$settings->altTextFieldHandle];
                    }

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
                        if ($alt) {
                            $meta[$key . ':alt'] = $alt;
                        }
                    }
                    if ($key === 'twitter:image') {
                        if ($alt) {
                            $meta[$key . ':alt'] = $alt;
                        }
                    }

                }
            }
        }

        return $meta;
    }

    /**
     * @param Asset|string $asset
     * @param array $transform
     * @param null|Settings $settings
     * @return string
     * @throws \craft\errors\SiteNotFoundException
     */
    public function getTransformedUrl($asset, $transform, $settings = null): string
    {
        if ($settings === null) {
            $settings = SEOMate::$plugin->getSettings();
        }

        $imagerPlugin = Craft::$app->plugins->getPlugin('imager');
        $transformedUrl = '';

        if ($settings->useImagerIfInstalled && $imagerPlugin) {
            // todo : should we set more defaults?
            if (!\is_string($asset) && !isset($transform['position']) && isset($asset['focalPoint'])) {
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

    /**
     * @param array $meta
     * @param array $overrideMeta
     */
    public function overrideMeta(&$meta, $overrideMeta)
    {
        foreach ($overrideMeta as $key => $value) {
            $meta[$key] = $value;
        }
    }

    /**
     * @param array $meta
     * @param null|Settings $settings
     * @return mixed
     */
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

    /**
     * @param array $meta
     * @param null|Settings $settings
     * @return mixed
     */
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
                    if (\strlen($value) > $restrictions['maxLength']) {
                        $meta[$key] = substr($value, 0, $restrictions['maxLength'] - 3) . '…';
                    }
                }
            }
        }

        return $meta;
    }

    /**
     * @param array $meta
     * @param null|Settings $settings
     * @return mixed
     * @throws \craft\errors\SiteNotFoundException
     */
    public function addSitename($meta, $settings = null)
    {
        if ($settings === null) {
            $settings = SEOMate::$plugin->getSettings();
        }

        if (\is_array($settings->siteName)) {
            $siteName = $settings->siteName[Craft::$app->getSites()->getCurrentSite()->handle] ?? '';
        } else {
            if ($settings->siteName && \is_string($settings->siteName)) {
                $siteName = $settings->siteName;
            } else {
                try {
                    $info = Craft::$app->getInfo();
                } catch (ServerErrorHttpException $e) {
                    $info = null;
                }

                $sonfigSiteName = Craft::$app->getConfig()->getGeneral()->siteName;

                if (\is_array($sonfigSiteName)) {
                    $sonfigSiteName = $sonfigSiteName[Craft::$app->getSites()->getCurrentSite()->handle] ?? reset($sonfigSiteName);
                }

                $siteName = $sonfigSiteName ?? $info->name ?? '';
            }
        }

        if ($siteName !== '') {
            $preString = $settings->sitenamePosition === 'before' ? $siteName . ' ' . $settings->sitenameSeparator . ' ' : '';
            $postString = $settings->sitenamePosition === 'after' ? ' ' . $settings->sitenameSeparator . ' ' . $siteName : '';

            foreach ($settings->sitenameTitleProperties as $property) {
                $metaValue = $preString . ($meta[$property] ?? '') . $postString;
                $meta[$property] = \trim($metaValue, " {$settings->sitenameSeparator}");
            }
        }

        return $meta;
    }

    /**
     * @param array $meta
     * @param array $context
     * @param null|Settings $settings
     * @return mixed
     */
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

    /**
     * @param array $meta
     * @param array $context
     * @param null|Settings $settings
     * @return mixed
     */
    public function processAdditionalMeta($meta, $context = [], $settings = null)
    {
        if ($settings === null) {
            $settings = SEOMate::$plugin->getSettings();
        }

        foreach ($settings->additionalMeta as $key => $value) {
            if (\is_callable($value)) {
                $r = $value($context);
                $value = $r;
            }
            
            if (\is_array($value)) {
                foreach ($value as $subValue) {
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
