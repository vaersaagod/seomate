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
use craft\base\Element;
use craft\errors\SiteNotFoundException;
use craft\helpers\UrlHelper;
use craft\models\Site;

use vaersaagod\seomate\SEOMate;
use vaersaagod\seomate\helpers\SEOMateHelper;

use yii\base\Exception;

/**
 * @author    Værsågod
 * @package   SEOMate
 * @since     1.0.0
 */
class UrlsService extends Component
{
    /**
     * Gets the canonical URL from context
     * 
     * @param $context
     * @return null|string
     */
    public function getCanonicalUrl($context)
    {
        $craft = Craft::$app;
        $settings = SEOMate::$plugin->getSettings();

        $overrideObject = $context['seomate'] ?? [];

        if (isset($overrideObject['config'])) {
            SEOMateHelper::updateSettings($settings, $overrideObject['config']);
        }

        if (isset($overrideObject['canonicalUrl']) && $overrideObject['canonicalUrl'] !== '') {
            return $overrideObject['canonicalUrl'];
        }

        /** @var Element $element */
        if (isset($overrideObject['element'])) {
            $element = $overrideObject['element'];
        } else {
            $element = $craft->urlManager->getMatchedElement();
        }

        if ($element && $element->getUrl()) {
            $siteId = $element->siteId;
            $path = $element->uri === '__home__' ? '' : $element->uri;
        } else {
            $siteId = null;
            try {
                $currentSite = $craft->getSites()->getCurrentSite();
                $siteId = $currentSite->id;
            } catch (SiteNotFoundException $e) {
                Craft::error($e->getMessage(), __METHOD__);
            }
            $path = strip_tags(html_entity_decode($craft->getRequest()->getPathInfo(), ENT_NOQUOTES, 'UTF-8'));
        }

        $page = Craft::$app->getRequest()->getPageNum();
        if ($page <= 1) {
            return UrlHelper::siteUrl($path, null, null, $siteId);
        }

        $pageTrigger = Craft::$app->getConfig()->getGeneral()->getPageTrigger();
        $useQueryParam = strpos($pageTrigger, '?') === 0;
        if ($useQueryParam) {
            $param = trim($pageTrigger, '?=');
            return UrlHelper::siteUrl($path, [$param => $page], null, $siteId);
        }

        $path .= '/' . $pageTrigger . $page;
        return UrlHelper::siteUrl($path, null, null, $siteId);
    }

    /**
     * Gets the alternate URLs from context
     * 
     * @param $context
     * @return array
     */
    public function getAlternateUrls($context): array
    {
        $craft = Craft::$app;
        $settings = SEOMate::$plugin->getSettings();
        $alternateUrls = [];

        try {
            $currentSite = $craft->getSites()->getCurrentSite();
        } catch (SiteNotFoundException $e) {
            Craft::error($e->getMessage(), __METHOD__);
            return [];
        }

        $overrideObject = $context['seomate'] ?? null;

        if ($overrideObject && isset($overrideObject['config'])) {
            SEOMateHelper::updateSettings($settings, $overrideObject['config']);
        }

        if (!$settings->outputAlternate) {
            return [];
        }

        /** @var Element $element */
        if ($overrideObject && isset($overrideObject['element'])) {
            $element = $overrideObject['element'];
        } else {
            $element = $craft->urlManager->getMatchedElement();
        }

        if (!$element) {
            return [];
        }

        $fallbackSite = null;

        if (is_string($settings->alternateFallbackSiteHandle) && !empty($settings->alternateFallbackSiteHandle)) {
            $fallbackSite = $craft->getSites()->getSiteByHandle($settings->alternateFallbackSiteHandle);

            if ($fallbackSite && $fallbackSite->id !== null) {
                $url = $craft->getElements()->getElementUriForSite($element->getId(), $fallbackSite->id);

                if ($url) {
                    $url = $this->prepAlternateUrlForSite($url, $fallbackSite);
                } else {
                    $url = $this->prepAlternateUrlForSite('', $fallbackSite);
                }

                if ($url && $url !== '') {
                    $alternateUrls[] = [
                        'url' => $url,
                        'language' => 'x-default'
                    ];
                }
            }
        }

        foreach ($craft->getSites()->getAllSites() as $site) {
            if ($fallbackSite === null || $fallbackSite->id !== $site->id) {
                $url = $craft->getElements()->getElementUriForSite($element->getId(), $site->id);
                $enabledSites = $craft->getElements()->getEnabledSiteIdsForElement($element->getId());
                
                if ($url !== false && $url !== null && in_array($site->id, $enabledSites, false)) {
                    $url = $this->prepAlternateUrlForSite($url, $site);

                    if ($url && $url !== '') {
                        $alternateUrls[] = [
                            'url' => $url,
                            'language' => strtolower(str_replace('_', '-', $site->language))
                        ];
                    }
                }
            }
        }

        return $alternateUrls;
    }

    /**
     * Returns a fully qualified site URL from uri and site
     * 
     * @param string $uri
     * @param Site $site
     * @return string
     */
    private function prepAlternateUrlForSite($uri, $site): string
    {
        $url = ($uri === '__home__') ? '' : $uri;
        
        if (!UrlHelper::isAbsoluteUrl($url)) {
            try {
                $url = UrlHelper::siteUrl($url, null, null, $site->id);
            } catch (Exception $e) {
                $url = '';
                Craft::error($e->getMessage(), __METHOD__);
            }
        }

        return $url;
    }
}
