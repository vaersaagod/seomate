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
     * 
     * @param string $handle
     * @param array $definition
     * @return array
     */
    public static function getIndexSitemapUrls($handle, $definition): array
    {
        $settings = SEOMate::$plugin->getSettings();
        $limit = $settings->sitemapLimit;
        $urls = [];

        /** @var Element $elementClass */
        if (isset($definition['elementType']) && class_exists($definition['elementType'])) {
            $elementClass = $definition['elementType'];
            $criteria = $definition['criteria'] ?? [];
            $query = $elementClass::find();
            Craft::configure($query, $criteria);
        } else {
            $elementClass = Entry::class;
            $criteria = ['section' => $handle];
            $query = $elementClass::find();
            Craft::configure($query, $criteria);
        }

        $count = $query->limit(null)->count();
        $pages = ceil($count / $limit);
        $lastEntry = self::getLastEntry($query);

        for ($i = 1; $i <= $pages; $i++) {
            try {
                $urls[] = [
                    'loc' => UrlHelper::siteUrl($settings->sitemapName . '-' . $handle . '-' . $i . '.xml'),
                    'lastmod' => $lastEntry ? $lastEntry->dateUpdated->format('c') : DateTimeHelper::currentUTCDateTime()->format('c')
                ];
            } catch (Exception $e) {
                Craft::error($e->getMessage(), __METHOD__);
            }
        }

        return $urls;
    }

    /**
     * Returns sitemap url for custom sitemap for including in sitemap index
     *
     * @return array
     */
    public static function getCustomIndexSitemapUrl(): array
    {
        $settings = SEOMate::$plugin->getSettings();
        return self::getSitemapUrl($settings->sitemapName . '-custom.xml');
    }

    /**
     * Returns sitemap url for sitemap with the given name
     * @param $name
     * @return array
     */
    public static function getSitemapUrl($name): array
    {
        try {
            return [
                'loc' => UrlHelper::siteUrl($name),
                'lastmod' => DateTimeHelper::currentUTCDateTime()->format('c')
            ];
        } catch (Exception $e) {
            Craft::error($e->getMessage(), __METHOD__);
        }

        return [];
    }

    /**
     * Returns URLs for element sitemap based on sitemap handle, definition and page
     * 
     * @param string $handle
     * @param array $definition
     * @param $page
     * @return array
     */
    public static function getElementsSitemapUrls($handle, $definition, $page): array
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
            Craft::configure($query, $criteria);
        } else {
            $elementClass = Entry::class;
            $criteria = ['section' => $handle];
            $query = $elementClass::find();
            $params = $definition;
            Craft::configure($query, $criteria);
        }

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
     * 
     * @param array $customUrls
     * @return array
     */
    public static function getCustomSitemapUrls($customUrls): array
    {
        $urls = [];

        foreach ($customUrls as $key => $params) {
            try {
                $urls[] = array_merge([
                    'loc' => UrlHelper::siteUrl($key),
                    'lastmod' => DateTimeHelper::currentUTCDateTime()->format('c'),
                ], $params);
            } catch (Exception $e) {
                Craft::error($e->getMessage(), __METHOD__);
            }
        }

        return $urls;
    }

    /**
     * Helper method for adding URLs to sitemap 
     * 
     * @param \DOMDocument $document
     * @param \DOMElement $sitemap
     * @param string $nodeName
     * @param array $urls
     */
    public static function addUrlsToSitemap(&$document, &$sitemap, $nodeName, $urls)
    {
        foreach ($urls as $url) {
            $topNode = $document->createElement($nodeName);
            $sitemap->appendChild($topNode);

            foreach ($url as $key => $val) {
                $node = $document->createElement($key, $val);
                $topNode->appendChild($node);
            }
        }
    }

    /**
     * Returns last entry from query
     * 
     * @param ElementQueryInterface $query
     * @return mixed
     */
    public static function getLastEntry($query)
    {
        return $query->orderBy('elements.dateUpdated DESC')->one();
    }
}
