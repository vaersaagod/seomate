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
use craft\elements\db\ElementQueryInterface;
use craft\elements\Entry;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;

use vaersaagod\seomate\SEOMate;

use yii\base\Exception;

/**
 * Sitemap Helper
 *
 * @author    Værsågod
 * @package   SEOMate
 * @since     1.0.0
 */
class SitemapHelper
{
    /**
     * Returns urls for sitemap index
     */
    public static function getIndexSitemapUrls(string $handle, array $definition): array
    {
        $settings = SEOMate::$plugin->getSettings();
        $limit = $settings->sitemapLimit;
        $urls = [];

        /** @var Element $elementClass */
        if (isset($definition['elementType']) && class_exists($definition['elementType'])) {
            $elementClass = $definition['elementType'];
            $criteria = $definition['criteria'] ?? [];
        } else {
            $elementClass = Entry::class;
            $criteria = ['section' => $handle];
        }
        
        $query = $elementClass::find();
        Craft::configure($query, $criteria);

        $count = $query->limit(null)->count();
        $pages = ceil($count / $limit);
        $lastEntry = self::getLastEntry($query);

        for ($i = 1; $i <= $pages; ++$i) {
            try {
                $urls[] = [
                    'loc' => UrlHelper::siteUrl($settings->sitemapName . '-' . $handle . '-' . $i . '.xml'),
                    'lastmod' => $lastEntry ? $lastEntry->dateUpdated->format('c') : DateTimeHelper::currentUTCDateTime()->format('c'),
                ];
            } catch (Exception $exception) {
                Craft::error($exception->getMessage(), __METHOD__);
            }
        }

        return $urls;
    }

    /**
     * Returns sitemap url for custom sitemap for including in sitemap index
     */
    public static function getCustomIndexSitemapUrl(): array
    {
        $settings = SEOMate::$plugin->getSettings();
        return self::getSitemapUrl($settings->sitemapName . '-custom.xml');
    }

    /**
     * Returns sitemap url for sitemap with the given name
     * @param $name
     */
    public static function getSitemapUrl($name): array
    {
        try {
            return [
                'loc' => UrlHelper::siteUrl($name),
                'lastmod' => DateTimeHelper::currentUTCDateTime()->format('c'),
            ];
        } catch (Exception $exception) {
            Craft::error($exception->getMessage(), __METHOD__);
        }

        return [];
    }

    /**
     * Returns URLs for element sitemap based on sitemap handle, definition and page
     */
    public static function getElementsSitemapUrls(string $handle, array $definition, int $page): array
    {
        $settings = SEOMate::$plugin->getSettings();
        $limit = $settings->sitemapLimit;
        $urls = [];

        /** @var Element $elementClass */
        if (isset($definition['elementType']) && class_exists($definition['elementType'])) {
            $elementClass = $definition['elementType'];
            $criteria = $definition['criteria'] ?? [];
            $query = $elementClass::find();
            $params = $definition['params'] ?? [];
        } else {
            $elementClass = Entry::class;
            $criteria = ['section' => $handle];
            $query = $elementClass::find();
            $params = $definition;
        }
        
        Craft::configure($query, $criteria);

        $elements = $query->limit($limit)->offset(($page - 1) * $limit)->all();

        if ($elements) {
            foreach ($elements as $element) {
                $urls[] = array_merge([
                    'loc' => $element->url,
                    'lastmod' => $element->dateUpdated->format('c'),
                ], $params);
            }
        }

        return $urls;
    }

    /**
     * Returns URLs for custom sitemap
     */
    public static function getCustomSitemapUrls(array $customUrls): array
    {
        $urls = [];

        foreach ($customUrls as $key => $params) {
            try {
                $urls[] = array_merge([
                    'loc' => UrlHelper::siteUrl($key),
                    'lastmod' => DateTimeHelper::currentUTCDateTime()->format('c'),
                ], $params);
            } catch (Exception $exception) {
                Craft::error($exception->getMessage(), __METHOD__);
            }
        }

        return $urls;
    }

    /**
     * Helper method for adding URLs to sitemap
     */
    public static function addUrlsToSitemap(\DOMDocument $document, \DOMElement $sitemap, string $nodeName, array $urls): void
    {
        foreach ($urls as $url) {
            try {
                $topNode = $document->createElement($nodeName);
                $sitemap->appendChild($topNode);
            } catch (\Throwable $throwable) {
                Craft::error($throwable->getMessage(), __METHOD__);
            }

            foreach ($url as $key => $val) {
                try {
                    $node = $document->createElement($key, $val);
                    $topNode->appendChild($node);
                } catch (\Throwable $throwable) {
                    Craft::error($throwable->getMessage(), __METHOD__);
                }
            }
        }
    }

    /**
     * Returns last entry from query
     *
     *
     */
    public static function getLastEntry(ElementQueryInterface $query): mixed
    {
        return $query->orderBy('elements.dateUpdated DESC')->one();
    }

    /**
     * Checks if the supplied config array is a multi-site config. Returns true if
     * any of the keys are '*' or matches a site handle.
     *
     * @param $array
     */
    public static function isMultisiteConfig($array): bool
    {
        if (isset($array['*'])) {
            return true;
        }
        
        $sites = Craft::$app->getSites()->getAllSites();
        
        foreach ($sites as $site) {
            if (isset($array[$site->handle])) {
                return true;
            }
        }
        
        return false;
    }
}
