<?php
/**
 * SEOMate plugin for Craft CMS 3.x
 *
 * @link      https://www.vaersaagod.no/
 * @copyright Copyright (c) 2018 Værsågod
 */

namespace vaersaagod\seomate\helpers;

use craft\elements\Entry;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;
use craft\records\Section;
use vaersaagod\seomate\SEOMate;

use Craft;

/**
 * Sitemap Helper
 *
 * @author    Værsågod
 * @package   SEOMate
 * @since     1.0.0
 */
class SitemapHelper
{
    public static function getIndexSitemapUrls($handle, $definition)
    {
        $settings = SEOMate::$plugin->getSettings();
        $limit = $settings->sitemapLimit;
        $urls = [];

        if (isset($definition['elementType']) && class_exists($definition['elementType'])) {
            $elementClass = $definition['elementType'];
            $criteria = $definition['criteria'] ?? [];
            $query = $elementClass::find();
            Craft::configure($query, $criteria);
        } else {
            $elementClass = \craft\elements\Entry::class;
            $criteria = ['section' => $handle];
            $query = $elementClass::find();
            Craft::configure($query, $criteria);
        }

        $count = $query->limit(null)->count();
        $pages = ceil($count / $limit);
        $lastEntry = SitemapHelper::getLastEntry($query);

        for ($i = 1; $i <= $pages; $i++) {
            $urls[] = [
                'loc' => UrlHelper::siteUrl($settings->sitemapName . '_' . $handle . '_' . $i . '.xml'),
                'lastmod' => $lastEntry ? $lastEntry->dateUpdated->format('c') : DateTimeHelper::currentUTCDateTime()->format('c')
            ];
        }

        return $urls;
    }

    public static function getCustomIndexSitemapUrl()
    {
        $settings = SEOMate::$plugin->getSettings();
        
        return [
            'loc' => UrlHelper::siteUrl($settings->sitemapName . '_custom.xml'),
            'lastmod' => DateTimeHelper::currentUTCDateTime()->format('c')
        ];
    }

    public static function getElementsSitemapUrls($handle, $definition, $page)
    {
        $settings = SEOMate::$plugin->getSettings();
        $limit = $settings->sitemapLimit;
        $urls = [];
        $elements = null;

        if (isset($definition['elementType']) && class_exists($definition['elementType'])) {
            $elementClass = $definition['elementType'];
            $criteria = $definition['criteria'] ?? [];
            $query = $elementClass::find();
            $params = $definition['params'] ?? [];
            Craft::configure($query, $criteria);
        } else {
            $elementClass = \craft\elements\Entry::class;
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
    
    public static function getCustomSitemapUrls($customUrls)
    {
        $settings = SEOMate::$plugin->getSettings();
        $urls = [];
        
        foreach ($customUrls as $key => $params) {
            $urls[] = array_merge([
                'loc' => UrlHelper::siteUrl($key),
                'lastmod' => DateTimeHelper::currentUTCDateTime()->format('c'),
            ], $params);
        }
        
        return $urls;
    }

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

    public static function getLastEntry($query)
    {
        $last = $query->orderBy('elements.dateUpdated DESC')->one();
        return $last;
    }
}
