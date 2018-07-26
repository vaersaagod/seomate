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

use Craft;
use craft\base\Component;
use craft\helpers\UrlHelper;

use vaersaagod\seomate\helpers\SitemapHelper;
use vaersaagod\seomate\SEOMate;


/**
 * SEOMateService Service
 *
 * @author    Værsågod
 * @package   SEOMate
 * @since     1.0.0
 */
class SitemapService extends Component
{
    /**
     * @return string
     * @throws \yii\base\Exception
     */
    public function index(): string
    {
        $settings = SEOMate::$plugin->getSettings();

        $document = new \DOMDocument('1.0', 'utf-8');
        $topNode = $this->getTopNode($document, 'sitemapindex');
        $document->appendChild($topNode);

        $config = $settings->sitemapConfig;

        if ($config && \is_array($config)) {
            $elements = $config['elements'] ?? null;
            $custom = $config['custom'] ?? null;

            if ($elements && \is_array($elements) && \count($elements) > 0) {
                foreach ($elements as $key => $definition) {
                    $indexSitemapUrls = SitemapHelper::getIndexSitemapUrls($key, $definition);
                    SitemapHelper::addUrlsToSitemap($document, $topNode, 'sitemap', $indexSitemapUrls);
                }
            }

            if ($custom && \is_array($custom) && \count($custom) > 0) {
                $customUrl = SitemapHelper::getCustomIndexSitemapUrl();
                SitemapHelper::addUrlsToSitemap($document, $topNode, 'sitemap', [$customUrl]);
            }
        }

        return $document->saveXML();
    }

    /**
     * @param string $handle
     * @param $page
     * @return string
     */
    public function elements($handle, $page): string
    {
        $settings = SEOMate::$plugin->getSettings();

        $document = new \DOMDocument('1.0', 'utf-8');
        $topNode = $this->getTopNode($document, 'urlset');
        $document->appendChild($topNode);

        $config = $settings->sitemapConfig;

        if ($config && \is_array($config)) {
            $definition = $config['elements'][$handle] ?? null;

            if ($definition) {
                $elementsSitemapUrls = SitemapHelper::getElementsSitemapUrls($handle, $definition, $page);
                SitemapHelper::addUrlsToSitemap($document, $topNode, 'url', $elementsSitemapUrls);
            }
        }

        return $document->saveXML();
    }

    /**
     * @return string
     * @throws \yii\base\Exception
     */
    public function custom(): string
    {
        $settings = SEOMate::$plugin->getSettings();

        $document = new \DOMDocument('1.0', 'utf-8');
        $topNode = $this->getTopNode($document, 'urlset');
        $document->appendChild($topNode);

        $config = $settings->sitemapConfig;

        if ($config && \is_array($config)) {
            $customUrls = $config['custom'] ?? null;

            if ($customUrls && count($customUrls) > 0) {
                $customSitemapUrls = SitemapHelper::getCustomSitemapUrls($customUrls);
                SitemapHelper::addUrlsToSitemap($document, $topNode, 'url', $customSitemapUrls);
            }
        }

        return $document->saveXML();
    }

    /**
     * @throws \yii\base\Exception
     */
    public function submit()
    {
        $settings = SEOMate::$plugin->getSettings();
        $pingUrls = $settings->sitemapSubmitUrlPatterns;
        $sitemapPath = $settings->sitemapName . '.xml';

        foreach ($pingUrls as $url) {
            $sites = Craft::$app->getSites()->getAllSites();

            foreach ($sites as $site) {
                $siteId = $site->id;
                $sitemapUrl = UrlHelper::siteUrl($sitemapPath, null, null, $siteId);

                if (!empty($sitemapUrl)) {
                    $submitUrl = $url . $sitemapUrl;
                    $client = Craft::createGuzzleClient();

                    try {
                        $client->post($submitUrl);
                        Craft::info('Index sitemap for site "' . $site->name . ' submitted to: ' . $submitUrl, __METHOD__);
                    } catch (\Exception $e) {
                        Craft::error('Error submitting index sitemap for site "' . $site->name . '" to: ' . $submitUrl . ' :: ' . $e->getMessage(), __METHOD__);
                    }
                }
            }
        }
    }

    /**
     * @param \DOMDocument $document
     * @param string $type
     * @return \DOMElement
     */
    private function getTopNode(&$document, $type = 'urlset'): \DOMElement
    {
        $node = $document->createElement($type);
        $node->setAttribute(
            'xmlns',
            'http://www.sitemaps.org/schemas/sitemap/0.9'
        );
        $node->setAttribute(
            'xmlns:xhtml',
            'http://www.w3.org/1999/xhtml'
        );

        return $node;
    }
}
