<?php
/**
 * SEOMate plugin for Craft CMS 5.x
 *
 * @link      https://www.vaersaagod.no/
 * @copyright Copyright (c) 2024 Værsågod
 */

namespace vaersaagod\seomate;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Plugin;
use craft\events\ElementEvent;
use craft\events\RegisterCacheOptionsEvent;
use craft\events\RegisterPreviewTargetsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\services\Elements;
use craft\utilities\ClearCaches;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use craft\web\View;

use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

use vaersaagod\seomate\helpers\CacheHelper;
use vaersaagod\seomate\helpers\SEOMateHelper;
use vaersaagod\seomate\models\Settings;
use vaersaagod\seomate\services\MetaService;
use vaersaagod\seomate\services\RenderService;
use vaersaagod\seomate\services\SchemaService;
use vaersaagod\seomate\services\SitemapService;
use vaersaagod\seomate\services\UrlsService;
use vaersaagod\seomate\twigextensions\SEOMateTwigExtension;
use vaersaagod\seomate\variables\SchemaVariable;
use vaersaagod\seomate\variables\SEOMateVariable;

use yii\base\Event;

/**
 * @author    Værsågod
 * @package   SEOMate
 * @since     1.0.0
 *
 * @property  MetaService $meta
 * @property  UrlsService $urls
 * @property  RenderService $render
 * @property  SitemapService $sitemap
 * @property  SchemaService $schema
 * @property  Settings $settings
 * @method    Settings getSettings()
 */
class SEOMate extends Plugin
{

    /**
     * @var string
     */
    public string $schemaVersion = '1.0.0';

    public static function config(): array
    {
        return [
            'components' => [
                'meta' => MetaService::class,
                'urls' => UrlsService::class,
                'render' => RenderService::class,
                'sitemap' => SitemapService::class,
                'schema' => SchemaService::class,
            ],
        ];
    }

    /**
     * @return void
     */
    public function init(): void
    {

        parent::init();
        
        // Register template variables
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            static function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('schema', SchemaVariable::class);
                $variable->set('seomate', SEOMateVariable::class);
            }
        );

        // Add in our Twig extensions
        Craft::$app->view->registerTwigExtension(new SEOMateTwigExtension());
        
        // Template Hook
        Craft::$app->view->hook(
            'seomateMeta',
            [$this, 'onRegisterMetaHook']
        );

        // Adds SEOMate to the Clear Caches tool
        Event::on(ClearCaches::class, ClearCaches::EVENT_REGISTER_CACHE_OPTIONS,
            static function(RegisterCacheOptionsEvent $event) {
                $event->options[] = [
                    'key' => 'seomate-cache',
                    'label' => Craft::t('seomate', 'SEOMate cache'),
                    'action' => [SEOMate::getInstance(), 'invalidateCaches'],
                ];
            }
        );

        // After save element event handler
        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_SAVE_ELEMENT,
            static function(ElementEvent $event) {
                $element = $event->element;
                if (!$element instanceof ElementInterface) {
                    return;
                }
                if (!$event->isNew) {
                    CacheHelper::deleteMetaCacheForElement($element);
                }
                CacheHelper::deleteCacheForSitemapIndex($element->siteId ?? null);
                CacheHelper::deleteCacheForElementSitemapsByElement($element);
            }
        );

        $settings = $this->getSettings();

        // Register sitemap urls?
        if ($settings->sitemapEnabled) {
            Event::on(
                UrlManager::class,
                UrlManager::EVENT_REGISTER_SITE_URL_RULES,
                static function(RegisterUrlRulesEvent $event) use ($settings) {
                    $sitemapName = $settings->sitemapName;

                    $event->rules[$sitemapName . '.xml'] = 'seomate/sitemap/index';
                    $event->rules[$sitemapName . '-<handle:\w*>-<page:\d*>.xml'] = 'seomate/sitemap/element';
                    $event->rules[$sitemapName . '-custom.xml'] = 'seomate/sitemap/custom';
                    $event->rules['sitemap.xsl'] = 'seomate/sitemap/xsl';
                }
            );
        }

        // Register preview target?
        if (!empty($settings->previewEnabled)) {
            Event::on(
                Element::class,
                Element::EVENT_REGISTER_PREVIEW_TARGETS,
                static function(RegisterPreviewTargetsEvent $event) use ($settings) {
                    try {
                        $element = $event->sender;
                        if (!$element instanceof Element || !SEOMateHelper::isElementPreviewable($element)) {
                            return;
                        }
                        $event->previewTargets[] = [
                            'label' => $settings->previewLabel ?: Craft::t('seomate', 'SEO Preview'),
                            'url' => UrlHelper::siteUrl('seomate/preview', [
                                'elementId' => $element->id,
                                'siteId' => $element->siteId,
                            ]),
                        ];
                    } catch (\Throwable $e) {
                        Craft::error("An exception occurred when attempting to register the \"SEO Preview\" preview target: " . $e->getMessage(), __METHOD__);
                    }
                }
            );

            // Register preview site route
            $request = Craft::$app->getRequest();
            if ($request->getIsSiteRequest() && !$request->getIsConsoleRequest()) {
                Event::on(
                    UrlManager::class,
                    UrlManager::EVENT_REGISTER_SITE_URL_RULES,
                    static function(RegisterUrlRulesEvent $event) {
                        $event->rules['seomate/preview'] = 'seomate/preview';
                    }
                );
            }
        }
    }

    /**
     * Invalidates all caches
     */
    public function invalidateCaches(): void
    {
        CacheHelper::clearAllCaches();
    }

    /**
     * Process 'seomateMeta' hook
     *
     * @param array $context
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     */
    public function onRegisterMetaHook(array &$context): string
    {

        if (isset($context['seomatePreviewElement'])) {
            $context['seomate']['element'] = $context['seomate']['element'] ?? $context['seomatePreviewElement'];
        }

        $meta = $this->meta->getContextMeta($context);
        $canonicalUrl = $this->urls->getCanonicalUrl($context);
        $alternateUrls = $this->urls->getAlternateUrls($context);

        $context['seomate']['meta'] = $meta;
        $context['seomate']['canonicalUrl'] = $canonicalUrl;
        $context['seomate']['alternateUrls'] = $alternateUrls;

        $settings = $this->getSettings();

        if (!empty($settings->metaTemplate)) {
            return Craft::$app->view->renderTemplate($settings->metaTemplate, $context);
        }

        $oldTemplateMode = Craft::$app->getView()->getTemplateMode();
        Craft::$app->getView()->setTemplateMode(View::TEMPLATE_MODE_CP);
        $output = Craft::$app->getView()->renderTemplate('seomate/_output/meta', $context);
        Craft::$app->getView()->setTemplateMode($oldTemplateMode);

        return $output;
    }

    /**
     * @return Settings
     */
    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }
}
