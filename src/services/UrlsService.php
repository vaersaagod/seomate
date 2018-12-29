<?php
/**
 * SEOMate plugin for Craft CMS 3.x
 *
 * @link      https://www.vaersaagod.no/
 * @copyright Copyright (c) 2018 Værsågod
 */

namespace vaersaagod\seomate\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\helpers\UrlHelper;

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
     * @param $context
     * @return null|string
     */
    public function getCanonicalUrl($context)
    {
        $craft = Craft::$app;
        $settings = SEOMate::$plugin->getSettings();

        $overrideObject = $context['seomate'] ?? null;

        if ($overrideObject && isset($overrideObject['config'])) {
            SEOMateHelper::updateSettings($settings, $overrideObject['config']);
        }

        if ($overrideObject && isset($overrideObject['canonicalUrl']) && $overrideObject['canonicalUrl'] !== '') {
            return $overrideObject['canonicalUrl'];
        }

        /** @var Element $element */

        if ($overrideObject && isset($overrideObject['element'])) {
            $element = $overrideObject['element'];
        } else {
            $element = $craft->urlManager->getMatchedElement();
        }

        if ($element && $element->getUrl()) {
            return $element->getUrl();
        }

        return UrlHelper::url($craft->getRequest()->getFullPath());
    }

    /**
     * @param $context
     * @return array
     * @throws \craft\errors\SiteNotFoundException
     * @throws \yii\base\Exception
     */
    public function getAlternateUrls($context): array
    {
        $craft = Craft::$app;
        $settings = SEOMate::$plugin->getSettings();
        $alternateUrls = [];
        $currentSite = $craft->getSites()->getCurrentSite();

        $overrideObject = $context['seomate'] ?? null;

        if ($overrideObject && isset($overrideObject['config'])) {
            SEOMateHelper::updateSettings($settings, $overrideObject['config']);
        }

        /** @var Element $element */
        if ($overrideObject && isset($overrideObject['element'])) {
            $element = $overrideObject['element'];
        } else {
            $element = $craft->urlManager->getMatchedElement();
        }

        if (!$element || !$currentSite) {
            return [];
        }

        foreach ($craft->getSites()->getAllSites() as $site) {
            if ($site->id !== $currentSite->id) {
                $url = $craft->getElements()->getElementUriForSite($element->getId(), $site->id);

                if ($url !== false) { // if element was not available in the given site, this happens
                    $url = ($url === '__home__') ? '' : $url;

                    if (!UrlHelper::isAbsoluteUrl($url)) {
                        try {
                            $url = UrlHelper::siteUrl($url, null, null, $site->id);
                        } catch (Exception $e) {
                            $url = '';
                            Craft::error($e->getMessage(), __METHOD__);
                        }
                    }

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
}
