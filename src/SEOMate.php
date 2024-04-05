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
use craft\base\Plugin;
use craft\elements\Category;
use craft\elements\Entry;
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
    // Static Properties
    // =========================================================================

    /**
     * @var SEOMate
     */
    public static SEOMate $plugin;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public string $schemaVersion = '1.0.0';

    // Public Methods
    // =========================================================================

    public function init(): void
    {
        parent::init();
        
        self::$plugin = $this;
        $settings = $this->getSettings();

        // Register services
        $this->setComponents([
            'meta' => MetaService::class,
            'urls' => UrlsService::class,
            'render' => RenderService::class,
            'sitemap' => SitemapService::class,
            'schema' => SchemaService::class,
        ]);

        // Register tamplate variables
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
                    'action' => [SEOMate::$plugin, 'invalidateCaches'],
                ];
            }
        );

        // After save element event handler
        Event::on(Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT,
            static function(ElementEvent $event) {
                $element = $event->element;
                
                if ($element instanceof Element) {
                    if (!$event->isNew) {
                        CacheHelper::deleteMetaCacheForElement($element);
                    }
                    
                    $siteId = $element->siteId ?? null;
                    CacheHelper::deleteCacheForSitemapIndex($siteId);
                    CacheHelper::deleteCacheForElementSitemapsByElement($element);
                }
            }
        );

        if ($settings->sitemapEnabled) {
            // Register sitemap urls
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
                    /** @var Element $element */
                    $element = $event->sender;
                    if (!$element->getUrl()) {
                        return;
                    }
                    if (is_array($settings->previewEnabled)) {
                        $sourceHandle = null;
                        if ($element instanceof Entry) {
                            $sourceHandle = $element->getSection()?->handle;
                        } else if ($element instanceof Category) {
                            $sourceHandle = $element->getGroup()->handle;
                        }
                        if (!empty($sourceHandle) && !in_array($sourceHandle, $settings->previewEnabled, true)) {
                            return;
                        }
                    }
                    $event->previewTargets[] = [
                        'label' => $settings->previewLabel ?: Craft::t('seomate', 'SEO Preview'),
                        'url' => UrlHelper::siteUrl('seomate/preview', [
                            'elementId' => $element->id,
                            'siteId' => $element->siteId,
                        ]),
                    ];
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
     *
     * @return string
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws \Throwable
     */
    public function onRegisterMetaHook(array &$context): string
    {

        $craft = Craft::$app;
        $settings = $this->getSettings();

        if (isset($context['seomatePreviewElement'])) {
            $context['seomate']['element'] = $context['seomate']['element'] ?? $context['seomatePreviewElement'];
        }

        $meta = $this->meta->getContextMeta($context);
        $canonicalUrl = $this->urls->getCanonicalUrl($context);
        $alternateUrls = $this->urls->getAlternateUrls($context);

        $context['seomate']['meta'] = $meta;
        $context['seomate']['canonicalUrl'] = $canonicalUrl;
        $context['seomate']['alternateUrls'] = $alternateUrls;

        if ($settings['metaTemplate'] !== '') {
            return $craft->view->renderTemplate($settings['metaTemplate'], $context);
        }

        $oldTemplateMode = $craft->getView()->getTemplateMode();
        $craft->getView()->setTemplateMode(View::TEMPLATE_MODE_CP);
        $output = $craft->getView()->renderTemplate('seomate/_output/meta', $context);
        $craft->getView()->setTemplateMode($oldTemplateMode);

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
