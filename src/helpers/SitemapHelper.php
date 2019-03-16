<?php
/**
 * SEOMate plugin for Craft CMS 3.x
 *
 * @link      https://www.vaersaagod.no/
 * @copyright Copyright (c) 2018 Værsågod
 */

namespace vaersaagod\seomate\helpers;

use Craft;
use craft\base\Element;
use craft\elements\db\ElementQueryInterface;
use craft\elements\Entry;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;
use vaersaagod\seomate\SEOMate;

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
     * @param string $handle
     * @param array $definition
     * @return array
     * @throws \yii\base\Exception
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
            $urls[] = [
                'loc' => UrlHelper::siteUrl($settings->sitemapName . '-' . $handle . '-' . $i . '.xml'),
                'lastmod' => $lastEntry ? $lastEntry->dateUpdated->format('c') : DateTimeHelper::currentUTCDateTime()->format('c')
            ];
        }

        return $urls;
    }

    /**
     * @return array
     * @throws \yii\base\Exception
     */
    public static function getCustomIndexSitemapUrl(): array
    {
        $settings = SEOMate::$plugin->getSettings();

        return [
            'loc' => UrlHelper::siteUrl($settings->sitemapName . '-custom.xml'),
            'lastmod' => DateTimeHelper::currentUTCDateTime()->format('c')
        ];
    }

    /**
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
        $elements = null;

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
     * @param array $customUrls
     * @return array
     * @throws \yii\base\Exception
     */
    public static function getCustomSitemapUrls($customUrls): array
    {
        $urls = [];

        foreach ($customUrls as $key => $params) {
            $urls[] = array_merge([
                'loc' => UrlHelper::siteUrl($key),
                'lastmod' => DateTimeHelper::currentUTCDateTime()->format('c'),
            ], $params);
        }

        return $urls;
    }

    /**
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
     * @param ElementQueryInterface $query
     * @return mixed
     */
    public static function getLastEntry($query)
    {
        return $query->orderBy('elements.dateUpdated DESC')->one();
    }
}
