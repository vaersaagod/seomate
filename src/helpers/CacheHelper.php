<?php
/**
 * SEOMate plugin for Craft CMS 3.x
 *
 * @link      https://www.vaersaagod.no/
 * @copyright Copyright (c) 2019 Værsågod
 */

namespace vaersaagod\seomate\helpers;

use Craft;
use craft\elements\Entry;
use vaersaagod\seomate\SEOMate;
use yii\caching\TagDependency;

/**
 * SEOMate Helper
 *
 * @author    Værsågod
 * @package   SEOMate
 * @since     1.0.0
 */
class CacheHelper
{
    const SEOMATE_TAG = 'seomate_tag';

    const ELEMENT_TAG = 'seomate_meta_element_tag';
    const ELEMENT_KEY_PREFIX = 'seomate_meta_element';

    const SITEMAP_INDEX_KEY = 'seomate_sitemap_index';
    const ELEMENT_SITEMAP_KEY_PREFIX = 'seomate_element_sitemap';
    const SITEMAP_INDEX_TAG = 'seomate_sitemap_index_tag';
    const SITEMAP_ELEMENT_TAG = 'seomate_sitemap_element_tag';
    const ELEMENT_SITEMAP_CLASS_PREFIX = 'seomate_element_sitemap_class';
    const ELEMENT_SITEMAP_HANDLE_PREFIX = 'seomate_element_sitemap_handle';


    /**
     * Clears all SEOMate caches
     */
    public static function clearAllCaches()
    {
        $cache = Craft::$app->getCache();
        TagDependency::invalidate($cache, self::SEOMATE_TAG);
    }

    /**
     * Checks if meta data cache for element exists
     *
     * @param $element
     * @return bool
     */
    public static function hasMetaCacheForElement($element): bool
    {
        $cache = Craft::$app->getCache();
        return $cache->get(self::getElementKey($element)) ? true : false;
    }

    /**
     * Returns meta data cache for element
     *
     * @param $element
     * @return mixed
     */
    public static function getMetaCacheForElement($element)
    {
        $cache = Craft::$app->getCache();
        return $cache->get(self::getElementKey($element));
    }

    /**
     * Deletes meta data cache for element
     *
     * @param $element
     */
    public static function deleteMetaCacheForElement($element)
    {
        $cache = Craft::$app->getCache();
        $cache->delete(self::getElementKey($element));
    }

    /**
     * Creates cache for element meta data
     *
     * @param $element
     * @param $meta
     */
    public static function setMetaCacheForElement($element, $meta)
    {
        $settings = SEOMate::$plugin->getSettings();

        $cache = Craft::$app->getCache();
        $cacheDuration = $settings->cacheDuration;

        $dependency = new TagDependency([
            'tags' => [
                self::SEOMATE_TAG,
                self::ELEMENT_TAG,
            ],
        ]);

        $cache->set(self::getElementKey($element), $meta, $cacheDuration, $dependency);
    }


    /**
     * Checks if cache for sitemap index exists
     *
     * @param $siteId
     * @return bool
     */
    public static function hasCacheForSitemapIndex($siteId): bool
    {
        $cache = Craft::$app->getCache();
        return $cache->get(self::SITEMAP_INDEX_KEY . '_site' . $siteId) ? true : false;
    }

    /**
     * Returns cached sitemap index
     *
     * @param $siteId
     * @return mixed
     */
    public static function getCacheForSitemapIndex($siteId)
    {
        $cache = Craft::$app->getCache();
        return $cache->get(self::SITEMAP_INDEX_KEY . '_site' . $siteId);
    }

    /**
     * Deletes sitemap index cache
     *
     * @param $siteId
     */
    public static function deleteCacheForSitemapIndex($siteId)
    {
        $cache = Craft::$app->getCache();
        $cache->delete(self::SITEMAP_INDEX_KEY . '_site' . $siteId);
    }

    /**
     * Creates cache for sitemap index
     *
     * @param $siteId
     * @param $data
     */
    public static function setCacheForSitemapIndex($siteId, $data)
    {
        $settings = SEOMate::$plugin->getSettings();

        $cache = Craft::$app->getCache();
        $cacheDuration = $settings->cacheDuration;

        $dependency = new TagDependency([
            'tags' => [
                self::SEOMATE_TAG,
                self::SITEMAP_ELEMENT_TAG,
            ],
        ]);

        $cache->set(self::SITEMAP_INDEX_KEY . '_site' . $siteId, $data, $cacheDuration, $dependency);
    }

    /**
     * Checks if cache for element sitemap exists
     *
     * @param $siteId
     * @param $handle
     * @param $page
     * @return bool
     */
    public static function hasCacheForElementSitemap($siteId, $handle, $page): bool
    {
        $cache = Craft::$app->getCache();
        return $cache->get(self::getElementSitemapKey($siteId, $handle, $page)) ? true : false;
    }

    /**
     * Returns cache for element sitemap
     *
     * @param $siteId
     * @param $handle
     * @param $page
     * @return mixed
     */
    public static function getCacheForElementSitemap($siteId, $handle, $page)
    {
        $cache = Craft::$app->getCache();
        return $cache->get(self::getElementSitemapKey($siteId, $handle, $page));
    }

    /**
     * Deletes all element sitemaps
     */
    public static function deleteCacheForAllElementSitemaps()
    {
        $cache = Craft::$app->getCache();
        TagDependency::invalidate($cache, self::SITEMAP_ELEMENT_TAG);
    }

    /**
     * Deletes element sitemaps by element
     *
     * @param $element
     */
    public static function deleteCacheForElementSitemapsByElement($element)
    {
        $elementClass = \get_class($element);
        $siteId = $element->siteId ?? null;
        
        $cache = Craft::$app->getCache();
        TagDependency::invalidate($cache, self::getElementSitemapTagForClass($siteId, $elementClass));
    }

    /**
     * Creates cache for element sitemap
     *
     * @param $siteId
     * @param $data
     * @param $handle
     * @param $definition
     * @param $page
     */
    public static function setCacheForElementSitemap($siteId, $data, $handle, $definition, $page)
    {
        $settings = SEOMate::$plugin->getSettings();

        $cache = Craft::$app->getCache();
        $cacheDuration = $settings->cacheDuration;

        $tags = array_merge([self::SEOMATE_TAG, self::SITEMAP_ELEMENT_TAG], self::getElementSitemapTags($siteId, $handle, $definition));

        $dependency = new TagDependency([
            'tags' => $tags,
        ]);

        $cache->set(self::getElementSitemapKey($siteId, $handle, $page), $data, $cacheDuration, $dependency);
    }


    /**
     * Creates key for element meta
     *
     * @param $element
     * @return string
     */
    private static function getElementKey($element): string
    {
        $site = Craft::$app->getSites()->getSiteById($element->siteId, true);
        $pageNum = !Craft::$app->getRequest()->getIsConsoleRequest() ? Craft::$app->getRequest()->getPageNum() : null;
        return self::ELEMENT_KEY_PREFIX . '_' . $site->handle . '_' . $element->id . ($pageNum ? '_' . $pageNum : '');
    }

    /**
     * Creates key for element sitemap
     *
     * @param $siteId
     * @param $handle
     * @param $page
     * @return string
     */
    private static function getElementSitemapKey($siteId, $handle, $page): string
    {
        return self::ELEMENT_SITEMAP_KEY_PREFIX . '_' . $handle . '_' . $page . '_site' . $siteId;
    }

    /**
     * Gets tags for element sitemaps based on definition
     *
     * @param $siteId
     * @param $handle
     * @param $definition
     * @return array
     */
    private static function getElementSitemapTags($siteId, $handle, $definition): array
    {
        $tags = [];

        if (isset($definition['elementType']) && class_exists($definition['elementType'])) {
            $elementClass = $definition['elementType'];
            //$criteria = $definition['criteria'] ?? [];
        } else {
            $elementClass = Entry::class;
            //$criteria = ['section' => $handle];
        }

        $tags[] = self::getElementSitemapTagForClass($siteId, $elementClass);
        $tags[] = self::ELEMENT_SITEMAP_HANDLE_PREFIX . '_' . $handle . '_site' . $siteId;

        // tbd : add more specific tags for criteria params?

        return $tags;
    }

    /**
     * Creates tag for element
     *
     * @param $siteId
     * @param $class
     * @return string
     */
    private static function getElementSitemapTagForClass($siteId, $class): string
    {
        return self::ELEMENT_SITEMAP_CLASS_PREFIX . '_' . str_replace('\\', '-', $class) . '_site' . $siteId;
    }

}
