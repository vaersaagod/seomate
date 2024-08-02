<?php
/**
 * SEOMate plugin for Craft CMS 5.x
 *
 * @link      https://www.vaersaagod.no/
 * @copyright Copyright (c) 2024 Værsågod
 */

namespace vaersaagod\seomate\services;

use aelvan\imager\Imager;
use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\elements\Asset;
use craft\errors\SiteNotFoundException;
use craft\models\ImageTransform;
use spacecatninja\imagerx\ImagerX;

use vaersaagod\seomate\helpers\CacheHelper;
use vaersaagod\seomate\helpers\SEOMateHelper;
use vaersaagod\seomate\models\Settings;
use vaersaagod\seomate\SEOMate;

/**
 * @author    Værsågod
 * @package   SEOMate
 * @since     1.0.0
 */
class MetaService extends Component
{
    /**
     * Gets all meta data based on context
     */
    public function getContextMeta(array $context): array
    {
        $craft = Craft::$app;
        $settings = SEOMate::getInstance()->getSettings();

        $overrideObject = $context['seomate'] ?? null;

        if ($overrideObject && isset($overrideObject['config'])) {
            SEOMateHelper::updateSettings($settings, $overrideObject['config']);
        }

        if ($overrideObject && isset($overrideObject['element'])) {
            $element = $overrideObject['element'];
        } else {
            $element = $craft->urlManager->getMatchedElement();
        }

        // Check if we have a cache
        if ($element && $settings->cacheEnabled && CacheHelper::hasMetaCacheForElement($element)) {
            return CacheHelper::getMetaCacheForElement($element);
        }

        $meta = [];

        // Get element meta data
        if ($element) {
            $meta = $this->getElementMeta($element, $overrideObject);
        }

        // Additional meta data
        if (!empty($settings->additionalMeta)) {
            $meta = $this->processAdditionalMeta($meta, $context, $settings);
        }
        
        // Overwrite with pre-generated values from template
        if ($overrideObject && isset($overrideObject['meta'])) {
            $this->overrideMeta($meta, $overrideObject['meta']);
        }

        // Add default meta if available
        if (!empty($settings->defaultMeta)) {
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
        
        // Filter and encode
        $meta = $this->applyMetaFilters($meta);

        // Add sitename if desirable
        if ($settings->includeSitenameInTitle) {
            $meta = $this->addSitename($meta, $context, $settings);
        }
        
        // Cache it
        if ($element && $settings->cacheEnabled) {
            CacheHelper::setMetaCacheForElement($element, $meta);
        }

        return $meta;
    }

    /**
     * Gets all element meta data
     *
     * @param array|null $overrides
     *
     */
    public function getElementMeta(Element $element, array $overrides = null): array
    {
        $settings = SEOMate::getInstance()->getSettings();

        if ($overrides && isset($overrides['config'])) {
            SEOMateHelper::updateSettings($settings, $overrides['config']);
        }

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
     * Gets element meta data based on profile
     */
    public function generateElementMetaByProfile(Element $element, array $profile): array
    {
        $r = [];

        foreach ($profile as $key => $value) {
            $keyType = SEOMateHelper::getMetaTypeByKey($key);
            $r[$key] = $this->getElementPropertyDataByFields($element, $keyType, $value);
        }

        return $r;
    }

    /**
     * Gets the value for a metadata property in *element*, from a list of fields and type.
     */
    public function getElementPropertyDataByFields(Element $element, string $type, array $fields): Asset|string
    {
        foreach ($fields as $fieldDef) {
            $value = SEOMateHelper::getPropertyDataByScopeAndHandle($element, $fieldDef, $type);
            
            if (!empty($value)) {
                return $value;
            }
        }

        return '';
    }

    /**
     * Gets the value for a metadata property in *context*, from a list of fields and type.
     */
    public function getContextPropertyDataByFields(array $context, string $type, array $fields): Asset|string
    {
        foreach ($fields as $fieldDef) {
            if (is_string($fieldDef) && !str_contains(trim($fieldDef), '{')) {
                // Get the deepest scope possible, and the remaining field handle.
                [$primaryScope, $fieldDef] = SEOMateHelper::reduceScopeAndHandle($context, $fieldDef);
                
                if ($primaryScope === null) {
                    continue;
                }
            } else {
                $primaryScope = $context;
            }
            
            $value = SEOMateHelper::getPropertyDataByScopeAndHandle($primaryScope, $fieldDef, $type);
            
            if (!empty($value)) {
                return $value;
            }
        }

        return '';
    }

    /**
     * Transforms meta data assets.
     *
     * @param Settings|null $settings
     *
     */
    public function transformMetaAssets(array $meta, Settings $settings = null): array
    {
        if ($settings === null) {
            $settings = SEOMate::getInstance()->getSettings();
        }

        $imageTransformMap = $settings->imageTransformMap;

        foreach ($imageTransformMap as $key => $value) {
            if (isset($meta[$key]) && $meta[$key] !== '') {
                $transform = $value;
                $asset = $meta[$key];
                
                if ($asset) {
                    try {
                        $meta[$key] = $this->getTransformedUrl($asset, $transform, $settings);
                    } catch (\Throwable $throwable) {
                        Craft::error($throwable->getMessage(), __METHOD__);
                    }

                    $alt = null;

                    if ($settings->altTextFieldHandle && $asset[$settings->altTextFieldHandle] && ((string)$asset[$settings->altTextFieldHandle] !== '')) {
                        $alt = $asset[$settings->altTextFieldHandle];
                    }

                    if ($key === 'og:image') {
                        if ($alt) {
                            $meta[$key . ':alt'] = $alt;
                        }

                        if (isset($transform['format'])) {
                            $meta[$key . ':type'] = 'image/' . ($transform['format'] === 'jpg' ? 'jpeg' : $transform['format']);
                        }

                        // todo: Ideally, we should get these from the final transform
                        if (isset($transform['width'])) {
                            $meta[$key . ':width'] = $transform['width'];
                        }

                        if (isset($transform['height'])) {
                            $meta[$key . ':height'] = $transform['height'];
                        }
                    }

                    if ($key === 'twitter:image' && $alt) {
                        $meta[$key . ':alt'] = $alt;
                    }
                }
            }
        }

        return $meta;
    }

    /**
     * Transforms asset and returns URL.
     *
     * @param null|Settings $settings
     *
     * @throws SiteNotFoundException
     */
    public function getTransformedUrl(Asset|string $asset, array $transform, Settings $settings = null): string
    {
        if ($settings === null) {
            $settings = SEOMate::getInstance()->getSettings();
        }

        $plugins = Craft::$app->getPlugins();
        $imagerPlugin = $plugins->getPlugin('imager-x');
        
        $transformedUrl = '';

        if ($settings->useImagerIfInstalled && $imagerPlugin instanceof ImagerX) {
            try {
                $transformedAsset = $imagerPlugin->imagerx->transformImage($asset, $transform, [], []);

                if ($transformedAsset) {
                    $transformedUrl = $transformedAsset->getUrl();
                }
            } catch (\Throwable $throwable) {
                Craft::error($throwable->getMessage(), __METHOD__);
            }
        } else {
            $generateTransformsBeforePageLoad = Craft::$app->config->general->generateTransformsBeforePageLoad;
            Craft::$app->config->general->generateTransformsBeforePageLoad = true;

            try {
                $imageTransform = new ImageTransform();
                $validKeys = array_keys($imageTransform->getAttributes());
                
                $transform = array_filter($transform, static function($k) use ($validKeys) {
                    return in_array($k, $validKeys, true);
                }, ARRAY_FILTER_USE_KEY);

                $transformedUrl = $asset->getUrl($transform);
            } catch (\Throwable $throwable) {
                Craft::error($throwable->getMessage(), __METHOD__);
            }
            
            Craft::$app->config->general->generateTransformsBeforePageLoad = $generateTransformsBeforePageLoad;
        }

        if (!$transformedUrl) {
            return '';
        }

        return SEOMateHelper::ensureAbsoluteUrl($transformedUrl);
    }

    /**
     * Applies override meta data
     */
    public function overrideMeta(array &$meta, array $overrideMeta): void
    {
        foreach ($overrideMeta as $key => $value) {
            $meta[$key] = $value;
        }
    }

    /**
     * Autofills missing meta data based on autofillMap config setting
     *
     * @param null|Settings $settings
     */
    public function autofillMeta(array $meta, Settings $settings = null): array
    {
        if ($settings === null) {
            $settings = SEOMate::getInstance()->getSettings();
        }

        $autofillMap = SEOMateHelper::expandMap($settings->autofillMap);

        foreach ($autofillMap as $key => $value) {
            if (!isset($meta[$key]) && isset($meta[$value])) {
                $meta[$key] = $meta[$value];
            }
        }

        return $meta;
    }

    /**
     * Applies restrictions to meta data.
     *
     * Currently, only maxLength is enforced.
     *
     * @param array $meta
     * @param null|Settings $settings
     * @return array
     */
    public function applyMetaRestrictions(array $meta, Settings $settings = null): array
    {
        if ($settings === null) {
            $settings = SEOMate::getInstance()->getSettings();
        }

        $restrictionsMap = SEOMateHelper::expandMap($settings->metaPropertyTypes);

        foreach ($meta as $key => $value) {
            if (isset($restrictionsMap[$key])) {
                $restrictions = $restrictionsMap[$key];

                if ($restrictions['type'] === 'text' && isset($restrictions['maxLength']) && \strlen($value) > $restrictions['maxLength']) {
                    $meta[$key] = mb_substr($value, 0, $restrictions['maxLength'] - strlen($settings->truncateSuffix)) . $settings->truncateSuffix;
                }
            }
        }

        return $meta;
    }
    
    /**
     * Apply any filters and encoding
     */
    public function applyMetaFilters(array $meta): array
    {
        foreach ($meta as $key => $value) {
            if (is_string($value) && !str_starts_with($value, 'http') && !str_starts_with($value, '//')) {
                $meta[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8', false);
            }
        }

        return $meta;
    }

    /**
     * Adds sitename to meta properties that should have it, as defined
     * by sitenameTitleProperties config setting.
     *
     * @param null|Settings $settings
     */
    public function addSitename(array $meta, array $context, Settings $settings = null): array
    {
        if ($settings === null) {
            $settings = SEOMate::getInstance()->getSettings();
        }

        $siteName = '';

        try {
            if (\is_array($settings->siteName)) {
                $siteName = $settings->siteName[Craft::$app->getSites()->getCurrentSite()->handle] ?? '';
            } elseif ($settings->siteName && \is_string($settings->siteName)) {
                $siteName = $settings->siteName;
            } else {
                $siteName = Craft::$app->getSites()->getCurrentSite()->name ?? '';
            }
        } catch (SiteNotFoundException $siteNotFoundException) {
            Craft::error($siteNotFoundException->getMessage(), __METHOD__);
        }

        if ($siteName !== '') {
            try {
                $siteName = Craft::$app->getView()->renderString($siteName, $context);
            } catch (\Throwable) {
                // Ignore, and continue with the current sitename value
            }
            
            $preString = $settings->sitenamePosition === 'before' ? $siteName . ' ' . $settings->sitenameSeparator . ' ' : '';
            $postString = $settings->sitenamePosition === 'after' ? ' ' . $settings->sitenameSeparator . ' ' . $siteName : '';

            foreach ($settings->sitenameTitleProperties as $property) {
                $metaValue = $preString . ($meta[$property] ?? '') . $postString;
                $meta[$property] = \trim($metaValue, sprintf(' %s', $settings->sitenameSeparator));
            }
        }

        return $meta;
    }

    /**
     * Process and return default meta data
     *
     * @param null|Settings $settings
     */
    public function processDefaultMeta(array $meta, array $context = [], Settings $settings = null): array
    {
        if ($settings === null) {
            $settings = SEOMate::getInstance()->getSettings();
        }

        foreach ($settings->defaultMeta as $key => $value) {
            if (!isset($meta[$key]) || $meta[$key] === '') {
                $keyType = SEOMateHelper::getMetaTypeByKey($key);
                $meta[$key] = $this->getContextPropertyDataByFields($context, $keyType, $value);
            }
        }

        return $meta;
    }

    /**
     * Processes and returns additional meta data
     *
     * @param null|Settings $settings
     */
    public function processAdditionalMeta(array $meta, array $context = [], Settings $settings = null): array
    {
        if ($settings === null) {
            $settings = SEOMate::getInstance()->getSettings();
        }
        
        foreach ($settings->additionalMeta as $key => $value) {
            if ($value instanceof \Closure) {
                $r = $value($context);
                $value = $r;
            }

            if (is_array($value)) {
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
