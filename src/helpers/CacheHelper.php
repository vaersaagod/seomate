<?php
/**
 * SEOMate plugin for Craft CMS 5.x
 *
 * @link      https://www.vaersaagod.no/
 * @copyright Copyright (c) 2024 Værsågod
 */

namespace vaersaagod\seomate\helpers;

use Craft;
use craft\elements\Entry;
use craft\helpers\ConfigHelper;

use vaersaagod\seomate\SEOMate;

use yii\base\InvalidConfigException;
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
    /**
     * @var string
     */
    public const SEOMATE_TAG = 'seomate_tag';

    /**
     * @var string
     */
    public const ELEMENT_TAG = 'seomate_meta_element_tag';

    /**
     * @var string
     */
    public const ELEMENT_KEY_PREFIX = 'seomate_meta_element';

    /**
     * @var string
     */
    public const SITEMAP_INDEX_KEY = 'seomate_sitemap_index';

    /**
     * @var string
     */
    public const ELEMENT_SITEMAP_KEY_PREFIX = 'seomate_element_sitemap';

    /**
     * @var string
     */
    public const SITEMAP_INDEX_TAG = 'seomate_sitemap_index_tag';

    /**
     * @var string
     */
    public const SITEMAP_ELEMENT_TAG = 'seomate_sitemap_element_tag';

    /**
     * @var string
     */
    public const ELEMENT_SITEMAP_CLASS_PREFIX = 'seomate_element_sitemap_class';

    /**
     * @var string
     */
    public const ELEMENT_SITEMAP_HANDLE_PREFIX = 'seomate_element_sitemap_handle';

    /** @var bool */
    private static bool $_cacheEnabled;

    /**
     * @return bool
     * @throws \yii\web\BadRequestHttpException
     */
    public static function getIsCacheEnabled(): bool
    {
        if (!isset(self::$_cacheEnabled)) {
            $request = Craft::$app->getRequest();
            self::$_cacheEnabled =
                SEOMate::getInstance()->getSettings()->cacheEnabled &&
                !$request->getIsConsoleRequest() &&
                !$request->getIsPreview() &&
                !$request->getHadToken();
        }
        return self::$_cacheEnabled;
    }

    /**
     * Clears all SEOMate caches
     */
    public static function clearAllCaches(): void
    {
        $cache = Craft::$app->getCache();
        TagDependency::invalidate($cache, self::SEOMATE_TAG);
    }

    /**
     * Checks if meta data cache for element exists
     *
     * @param $element
     * @return bool
     * @throws \yii\web\BadRequestHttpException
     */
    public static function hasMetaCacheForElement($element): bool
    {
        if (!self::getIsCacheEnabled()) {
            return false;
        }
        return (bool)Craft::$app->getCache()?->get(self::getElementKey($element));
    }

    /**
     * Returns meta data cache for element
     *
     * @param $element
     * @return mixed
     * @throws \yii\web\BadRequestHttpException
     */
    public static function getMetaCacheForElement($element): mixed
    {
        if (!self::getIsCacheEnabled()) {
            return false;
        }
        return Craft::$app->getCache()?->get(self::getElementKey($element));
    }

    /**
     * Deletes meta data cache for element
     *
     * @param $element
     */
    public static function deleteMetaCacheForElement($element): void
    {
        Craft::$app->getCache()?->delete(self::getElementKey($element));
    }

    /**
     * @param $element
     * @param $meta
     * @return void
     * @throws InvalidConfigException
     * @throws \yii\web\BadRequestHttpException
     */
    public static function setMetaCacheForElement($element, $meta): void
    {
        if (!self::getIsCacheEnabled()) {
            return;
        }
        $settings = SEOMate::getInstance()->getSettings();

        $cacheDuration = ConfigHelper::durationInSeconds($settings->cacheDuration);

        $dependency = new TagDependency([
            'tags' => [
                self::SEOMATE_TAG,
                self::ELEMENT_TAG,
            ],
        ]);

        Craft::$app->getCache()?->set(self::getElementKey($element), $meta, $cacheDuration, $dependency);
    }

    /**
     * Checks if cache for sitemap index exists
     *
     * @param $siteId
     * @return bool
     * @throws \yii\web\BadRequestHttpException
     */
    public static function hasCacheForSitemapIndex($siteId): bool
    {
        if (!self::getIsCacheEnabled()) {
            return false;
        }
        return (bool)Craft::$app->getCache()?->get(self::SITEMAP_INDEX_KEY . '_site' . $siteId);
    }

    /**
     * Returns cached sitemap index
     *
     * @param $siteId
     * @return mixed
     * @throws \yii\web\BadRequestHttpException
     */
    public static function getCacheForSitemapIndex($siteId): mixed
    {
        if (!self::getIsCacheEnabled()) {
            return false;
        }
        return Craft::$app->getCache()?->get(self::SITEMAP_INDEX_KEY . '_site' . $siteId);
    }

    /**
     * Deletes sitemap index cache
     *
     * @param $siteId
     */
    public static function deleteCacheForSitemapIndex($siteId): void
    {
        Craft::$app->getCache()?->delete(self::SITEMAP_INDEX_KEY . '_site' . $siteId);
    }

    /**
     * Creates cache for sitemap index
     *
     * @param $siteId
     * @param $data
     * @return void
     * @throws InvalidConfigException
     * @throws \yii\web\BadRequestHttpException
     */
    public static function setCacheForSitemapIndex($siteId, $data): void
    {
        if (!self::getIsCacheEnabled()) {
            return;
        }
        $settings = SEOMate::getInstance()->getSettings();

        $cacheDuration = ConfigHelper::durationInSeconds($settings->cacheDuration);

        $dependency = new TagDependency([
            'tags' => [
                self::SEOMATE_TAG,
                self::SITEMAP_ELEMENT_TAG,
            ],
        ]);

        Craft::$app->getCache()?->set(self::SITEMAP_INDEX_KEY . '_site' . $siteId, $data, $cacheDuration, $dependency);
    }

    /**
     * Checks if cache for element sitemap exists
     *
     * @param $siteId
     * @param $handle
     * @param $page
     * @return bool
     * @throws \yii\web\BadRequestHttpException
     */
    public static function hasCacheForElementSitemap($siteId, $handle, $page): bool
    {
        if (!self::getIsCacheEnabled()) {
            return false;
        }
        return (bool)Craft::$app->getCache()?->get(self::getElementSitemapKey($siteId, $handle, $page));
    }

    /**
     * Returns cache for element sitemap
     *
     * @param $siteId
     * @param $handle
     * @param $page
     * @return mixed
     * @throws \yii\web\BadRequestHttpException
     */
    public static function getCacheForElementSitemap($siteId, $handle, $page): mixed
    {
        if (!self::getIsCacheEnabled()) {
            return false;
        }
        return Craft::$app->getCache()?->get(self::getElementSitemapKey($siteId, $handle, $page));
    }

    /**
     * Deletes all element sitemaps
     */
    public static function deleteCacheForAllElementSitemaps(): void
    {
        $cache = Craft::$app->getCache();
        TagDependency::invalidate($cache, self::SITEMAP_ELEMENT_TAG);
    }

    /**
     * Deletes element sitemaps by element
     *
     * @param $element
     */
    public static function deleteCacheForElementSitemapsByElement($element): void
    {
        $elementClass = $element::class;
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
     * @return void
     * @throws InvalidConfigException
     * @throws \yii\web\BadRequestHttpException
     */
    public static function setCacheForElementSitemap($siteId, $data, $handle, $definition, $page): void
    {
        if (!self::getIsCacheEnabled()) {
            return;
        }

        $settings = SEOMate::getInstance()->getSettings();

        $cacheDuration = ConfigHelper::durationInSeconds($settings->cacheDuration);

        $tags = array_merge([self::SEOMATE_TAG, self::SITEMAP_ELEMENT_TAG], self::getElementSitemapTags($siteId, $handle, $definition));

        $dependency = new TagDependency([
            'tags' => $tags,
        ]);

        Craft::$app->getCache()?->set(self::getElementSitemapKey($siteId, $handle, $page), $data, $cacheDuration, $dependency);
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
        $pageNum = Craft::$app->getRequest()->getIsConsoleRequest() ? null : Craft::$app->getRequest()->getPageNum();
        return self::ELEMENT_KEY_PREFIX . '_' . ($site->handle ?? 'unknown') . '_' . $element->id . ($pageNum ? '_' . $pageNum : '');
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
        } else {
            $elementClass = Entry::class;
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
