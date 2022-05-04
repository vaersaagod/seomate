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
     * @throws \Throwable
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

            if ($elements && \is_array($elements) && $elements !== []) {
                foreach ($elements as $key => $definition) {
                    $indexSitemapUrls = SitemapHelper::getIndexSitemapUrls($key, $definition);
                    SitemapHelper::addUrlsToSitemap($document, $topNode, 'sitemap', $indexSitemapUrls);
                }
            }

            if ($custom && \is_array($custom) && $custom !== []) {
                $customUrl = SitemapHelper::getCustomIndexSitemapUrl();

                if (SitemapHelper::isMultisiteConfig($custom)) {
                    try {
                        $currentSiteHandle = Craft::$app->getSites()->getCurrentSite()->handle;

                        if (isset($custom['*']) || isset($custom[$currentSiteHandle])) {
                            SitemapHelper::addUrlsToSitemap($document, $topNode, 'sitemap', [$customUrl]);
                        }
                    } catch (SiteNotFoundException $siteNotFoundException) {
                        Craft::error($siteNotFoundException->getMessage(), __METHOD__);
                    }
                } else {
                    SitemapHelper::addUrlsToSitemap($document, $topNode, 'sitemap', [$customUrl]);
                }
            }

            if ($additionalSitemaps && \is_array($additionalSitemaps) && $additionalSitemaps !== []) {
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
                    } catch (SiteNotFoundException $siteNotFoundException) {
                        Craft::error($siteNotFoundException->getMessage(), __METHOD__);
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
     * @param $page
     * @throws \Throwable
     */
    public function elements(string $handle, $page): string
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

            if ($customUrls && (is_countable($customUrls) ? count($customUrls) : 0) > 0) {
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
                    } catch (SiteNotFoundException $siteNotFoundException) {
                        Craft::error($siteNotFoundException->getMessage(), __METHOD__);
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
     * @throws \Throwable
     */
    public function submit(): void
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
                    } catch (\Exception $exception) {
                        Craft::error('Error submitting index sitemap for site "' . $site->name . '" to: ' . $submitUrl . ' :: ' . $exception->getMessage(), __METHOD__);
                    }
                }
            }
        }
    }

    /**
     * Returns the top node DOMElement for DOMDocument
     */
    private function getTopNode(\DOMDocument &$document, string $type = 'urlset'): \DOMElement
    {
        $node = null;
        try {
            $node = $document->createElement($type);
            $node->setAttribute(
                'xmlns',
                'http://www.sitemaps.org/schemas/sitemap/0.9'
            );
            $node->setAttribute(
                'xmlns:xhtml',
                'http://www.w3.org/1999/xhtml'
            );
        } catch (\Throwable $throwable) {
            Craft::error($throwable->getMessage());
        }
        
        return $node;
    }
}
