<?php
/**
 * SEOMate plugin for Craft CMS 3.x
 *
 * @link      https://www.vaersaagod.no/
 * @copyright Copyright (c) 2019 Værsågod
 */

namespace vaersaagod\seomate\services;

use Craft;
use craft\base\Component;
use craft\errors\SiteNotFoundException;
use craft\helpers\UrlHelper;

use vaersaagod\seomate\helpers\CacheHelper;
use vaersaagod\seomate\helpers\SitemapHelper;
use vaersaagod\seomate\SEOMate;
use yii\base\Exception;


/**
 * SitemapService Service
 *
 * @author    Værsågod
 * @package   SEOMate
 * @since     1.0.0
 */
class SitemapService extends Component
{
    /**
     * Returns index sitemap
     *
     * @return string
     * @throws Exception
     */
    public function index(): string
    {
        $settings = SEOMate::$plugin->getSettings();
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;

        if ($settings->cacheEnabled && CacheHelper::hasCacheForSitemapIndex($siteId)) {
            return CacheHelper::getCacheForSitemapIndex($siteId);
        }

        $document = new \DOMDocument('1.0', 'utf-8');
        $topNode = $this->getTopNode($document, 'sitemapindex');
        $document->appendChild($topNode);
        $comment = $document->createComment('Created on: ' . date('Y-m-d H:i:s'));
        $topNode->appendChild($comment);

        $config = $settings->sitemapConfig;

        if (!empty($config)) {
            $elements = $config['elements'] ?? null;
            $custom = $config['custom'] ?? null;
            $additionalSitemaps = $config['additionalSitemaps'] ?? null;

            if ($elements && \is_array($elements) && \count($elements) > 0) {
                foreach ($elements as $key => $definition) {
                    $indexSitemapUrls = SitemapHelper::getIndexSitemapUrls($key, $definition);
                    SitemapHelper::addUrlsToSitemap($document, $topNode, 'sitemap', $indexSitemapUrls);
                }
            }

            if ($custom && \is_array($custom) && \count($custom) > 0) {
                $customUrl = SitemapHelper::getCustomIndexSitemapUrl();

                if (SitemapHelper::isMultisiteConfig($custom)) {
                    try {
                        $currentSiteHandle = Craft::$app->getSites()->getCurrentSite()->handle;

                        if (isset($custom['*']) || isset($custom[$currentSiteHandle])) {
                            SitemapHelper::addUrlsToSitemap($document, $topNode, 'sitemap', [$customUrl]);
                        }
                    } catch (SiteNotFoundException $e) {
                        Craft::error($e->getMessage(), __METHOD__);
                    }
                } else {
                    SitemapHelper::addUrlsToSitemap($document, $topNode, 'sitemap', [$customUrl]);
                }
            }

            if ($additionalSitemaps && \is_array($additionalSitemaps) && \count($additionalSitemaps) > 0) {
                $additionalUrls = [];
                if (SitemapHelper::isMultisiteConfig($additionalSitemaps)) {
                    
                    if (isset($additionalSitemaps['*'])) {
                        $additionalUrls = array_merge($additionalUrls, $additionalSitemaps['*']);
                    }
                    
                    try {
                        $currentSiteHandle = Craft::$app->getSites()->getCurrentSite()->handle;

                        if (isset($additionalSitemaps[$currentSiteHandle])) {
                            $additionalUrls = array_merge($additionalUrls, $additionalSitemaps[$currentSiteHandle]);
                        }
                    } catch (SiteNotFoundException $e) {
                        Craft::error($e->getMessage(), __METHOD__);
                    }
                    
                    
                } else {
                    $additionalUrls = array_merge($additionalUrls, $additionalSitemaps);
                }
                
                foreach ($additionalUrls as $sitemap) {
                    $addtnlSitemap = SitemapHelper::getSitemapUrl($sitemap);
                    SitemapHelper::addUrlsToSitemap($document, $topNode, 'sitemap', [$addtnlSitemap]);
                }
            }

            $data = $document->saveXML();
            CacheHelper::setCacheForSitemapIndex($siteId, $data);
        }


        return $data ?? $document->saveXML();
    }

    /**
     * Returns element sitemap by handle and page
     *
     * @param string $handle
     * @param $page
     * @return string
     * @throws Exception
     */
    public function elements($handle, $page): string
    {
        $settings = SEOMate::$plugin->getSettings();
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;

        $document = new \DOMDocument('1.0', 'utf-8');
        $topNode = $this->getTopNode($document);
        $document->appendChild($topNode);
        $comment = $document->createComment('Created on: ' . date('Y-m-d H:i:s'));
        $topNode->appendChild($comment);

        $config = $settings->sitemapConfig;

        if (!empty($config)) {
            $definition = $config['elements'][$handle] ?? null;

            if (!$definition) {
                return $document->saveXML();
            }

            if ($settings->cacheEnabled && CacheHelper::hasCacheForElementSitemap($siteId, $handle, $page)) {
                return CacheHelper::getCacheForElementSitemap($siteId, $handle, $page);
            }

            $elementsSitemapUrls = SitemapHelper::getElementsSitemapUrls($handle, $definition, $page);
            SitemapHelper::addUrlsToSitemap($document, $topNode, 'url', $elementsSitemapUrls);
            $data = $document->saveXML();

            CacheHelper::setCacheForElementSitemap($siteId, $data, $handle, $definition, $page);
        }

        return $data ?? $document->saveXML();
    }

    /**
     * Returns custom sitemap
     *
     * @return string
     */
    public function custom(): string
    {
        $settings = SEOMate::$plugin->getSettings();

        $document = new \DOMDocument('1.0', 'utf-8');
        $topNode = $this->getTopNode($document);
        $document->appendChild($topNode);

        $config = $settings->sitemapConfig;

        if (!empty($config)) {
            $customUrls = $config['custom'] ?? null;

            if ($customUrls && count($customUrls) > 0) {
                if (SitemapHelper::isMultisiteConfig($customUrls)) {
                    try {
                        $currentSiteHandle = Craft::$app->getSites()->getCurrentSite()->handle;

                        if (isset($customUrls[$currentSiteHandle])) {
                            $customSitemapUrls = SitemapHelper::getCustomSitemapUrls($customUrls[$currentSiteHandle]);
                            SitemapHelper::addUrlsToSitemap($document, $topNode, 'url', $customSitemapUrls);
                        }

                        if (isset($customUrls['*'])) {
                            $customSitemapUrls = SitemapHelper::getCustomSitemapUrls($customUrls['*']);
                            SitemapHelper::addUrlsToSitemap($document, $topNode, 'url', $customSitemapUrls);
                        }
                    } catch (SiteNotFoundException $e) {
                        Craft::error($e->getMessage(), __METHOD__);
                    }
                } else {
                    $customSitemapUrls = SitemapHelper::getCustomSitemapUrls($customUrls);
                    SitemapHelper::addUrlsToSitemap($document, $topNode, 'url', $customSitemapUrls);
                }
            }
        }

        return $document->saveXML();
    }

    /**
     * Submits sitemap index to search engines
     *
     * @throws Exception
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
     * Returns the top node DOMElement for DOMDocument
     *
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
