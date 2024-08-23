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
use craft\base\ElementInterface;
use craft\errors\SiteNotFoundException;
use craft\helpers\UrlHelper;

use vaersaagod\seomate\helpers\SEOMateHelper;
use vaersaagod\seomate\SEOMate;

use yii\base\Exception;
use yii\base\InvalidConfigException;

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
     *
     * @return string|null
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function getCanonicalUrl($context): ?string
    {
        $craft = Craft::$app;
        $settings = SEOMate::$plugin->getSettings();

        $overrideObject = $context['seomate'] ?? [];

        if (isset($overrideObject['config'])) {
            SEOMateHelper::updateSettings($settings, $overrideObject['config']);
        }

        if (isset($overrideObject['canonicalUrl']) && $overrideObject['canonicalUrl'] !== '') {
            return SEOMateHelper::stripTokenParams($overrideObject['canonicalUrl']);
        }

        /** @var Element $element */
        $element = $overrideObject['element'] ?? $craft->urlManager->getMatchedElement();

        if ($element && $element->getUrl()) {
            $siteId = $element->siteId;
            $path = $element->uri === '__home__' ? '' : $element->uri;
        } else {
            try {
                $siteId = $craft->getSites()->getCurrentSite()->id;
            } catch (SiteNotFoundException $siteNotFoundException) {
                $siteId = null;
                Craft::error($siteNotFoundException->getMessage(), __METHOD__);
            }

            $path = strip_tags(html_entity_decode($craft->getRequest()->getPathInfo(), ENT_NOQUOTES, 'UTF-8'));
        }

        $page = Craft::$app->getRequest()->getPageNum();
        if ($page <= 1) {
            return SEOMateHelper::stripTokenParams(UrlHelper::siteUrl($path, null, null, $siteId));
        }

        $pageTrigger = Craft::$app->getConfig()->getGeneral()->getPageTrigger();
        $useQueryParam = str_starts_with($pageTrigger, '?');
        if ($useQueryParam) {
            $param = trim($pageTrigger, '?=');
            return SEOMateHelper::stripTokenParams(UrlHelper::siteUrl($path, [$param => $page], null, $siteId));
        }

        $path .= '/' . $pageTrigger . $page;
        return SEOMateHelper::stripTokenParams(UrlHelper::siteUrl($path, null, null, $siteId));
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

        $overrideObject = $context['seomate'] ?? null;

        if ($overrideObject && isset($overrideObject['config'])) {
            SEOMateHelper::updateSettings($settings, $overrideObject['config']);
        }

        if (!$settings->outputAlternate || !Craft::$app->isMultiSite) {
            return [];
        }

        if ($overrideObject && isset($overrideObject['element'])) {
            $element = $overrideObject['element'];
        } else {
            $element = $craft->urlManager->getMatchedElement();
        }

        if (!$element instanceof ElementInterface || empty($element->canonicalId)) {
            return [];
        }

        $siteElements = $element::find()
            ->id($element->canonicalId)
            ->siteId('*')
            ->collect()
            ->filter(static fn(ElementInterface $element) => !empty($element->getUrl()));

        if (
            !empty($settings->alternateFallbackSiteHandle) &&
            $fallbackSite = Craft::$app->getSites()->getSiteByHandle($settings->alternateFallbackSiteHandle, false)
        ) {
            /** @var ElementInterface|null $fallbackSiteElement */
            $fallbackSiteElement = $siteElements->firstWhere('siteId', $fallbackSite->id);
            if ($fallbackSiteElement) {
                $alternateUrls[] = [
                    'url' => SEOMateHelper::stripTokenParams($fallbackSiteElement->getUrl()),
                    'language' => 'x-default',
                ];
                $siteElements = $siteElements->where('siteId', '!=', $fallbackSite->id);
            }
        }

        /** @var ElementInterface $siteElement */
        foreach ($siteElements->all() as $siteElement) {
            $alternateUrls[] = [
                'url' => SEOMateHelper::stripTokenParams($siteElement->getUrl()),
                'language' => strtolower(str_replace('_', '-', $siteElement->getLanguage())),
            ];
        }

        return $alternateUrls;
    }

}
