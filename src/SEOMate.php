<?php
/**
 * SEOMate plugin for Craft CMS 3.x
 *
 * @link      https://www.vaersaagod.no/
 * @copyright Copyright (c) 2019 Værsågod
 */

namespace vaersaagod\seomate;

use Craft;
use craft\base\Element;
use craft\base\Plugin;
use craft\elements\Entry;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\web\UrlManager;
use craft\web\twig\variables\CraftVariable;
use craft\events\ElementEvent;
use craft\events\RegisterCacheOptionsEvent;
use craft\events\RegisterPreviewTargetsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\services\Elements;
use craft\utilities\ClearCaches;
use craft\web\View;

use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

use vaersaagod\seomate\assets\preview\PreviewAsset;
use vaersaagod\seomate\helpers\CacheHelper;
use vaersaagod\seomate\helpers\SEOMateHelper;
use vaersaagod\seomate\services\MetaService;
use vaersaagod\seomate\services\RenderService;
use vaersaagod\seomate\services\SchemaService;
use vaersaagod\seomate\services\SitemapService;
use vaersaagod\seomate\services\UrlsService;
use vaersaagod\seomate\variables\SchemaVariable;
use vaersaagod\seomate\variables\SEOMateVariable;
use vaersaagod\seomate\twigextensions\SEOMateTwigExtension;
use vaersaagod\seomate\models\Settings;

use yii\base\Event;
use yii\base\Exception;
use yii\base\InvalidConfigException;

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
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $schemaVersion = '1.0.0';

    // Public Methods
    // =========================================================================

    public function init()
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
            static function (Event $event) {
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
            static function (RegisterCacheOptionsEvent $event) {
                $event->options[] = [
                    'key' => 'seomate-cache',
                    'label' => Craft::t('seomate', 'SEOMate cache'),
                    'action' => [SEOMate::$plugin, 'invalidateCaches'],
                ];
            }
        );

        // After save element event handler
        Event::on(Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT,
            static function (ElementEvent $event) {
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
                function (RegisterUrlRulesEvent $event) use ($settings) {
                    $sitemapName = $settings->sitemapName;

                    $event->rules[$sitemapName . '.xml'] = 'seomate/sitemap/index';
                    $event->rules[$sitemapName . '-<handle:\w*>-<page:\d*>.xml'] = 'seomate/sitemap/element';
                    $event->rules[$sitemapName . '-custom.xml'] = 'seomate/sitemap/custom';
                }
            );
        }

        if ($settings->previewEnabled) {

            // Register preview asset bundle for legacy Live Preview (Categories and Craft Commerce)
            $view = Craft::$app->getView();
            $view->hook('cp.categories.edit', [$this, 'registerPreviewAssetsBundle']);
            $view->hook('cp.commerce.product.edit.details', [$this, 'registerPreviewAssetsBundle']);

            // Register Preview Target for Craft 3.2.x
            $craft32 = \version_compare(Craft::$app->getVersion(), '3.2', '>=');
            if ($craft32) {

                /** @var Settings $settings */
                $settings = $this->getSettings();

                // Register preview target
                Event::on(
                    Element::class,
                    Element::EVENT_REGISTER_PREVIEW_TARGETS,
                    function (RegisterPreviewTargetsEvent $event) use ($settings) {
                        /** @var Element $element */
                        $element = $event->sender;
                        if (!$element->getUrl()) {
                            return false;
                        }
                        if (\is_array($settings->previewEnabled)) {
                            if (!$element instanceof Entry || !\in_array($element->getSection()->handle, $settings->previewEnabled)) {
                                return false;
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
                        function (RegisterUrlRulesEvent $event) {
                            $event->rules['seomate/preview'] = 'seomate/preview/preview';
                        }
                    );
                }

            } else {

                // Fall back to legacy Live Preview for older installs
                $view->hook('cp.entries.edit', [$this, 'registerPreviewAssetsBundle']);
            }
        }
    }

    /**
     * Invalidates all caches
     */
    public function invalidateCaches()
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
     * @throws Exception
     */
    public function onRegisterMetaHook(&$context): string
    {
        $craft = Craft::$app;
        $settings = $this->getSettings();

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
     * Registers assets bundle for preview
     *
     * @param array $context
     * @throws InvalidConfigException
     */
    public function registerPreviewAssetsBundle(array $context = [])
    {
        $element = $context['entry'] ?? $context['category'] ?? $context['product'] ?? null;
        if (!$element) {
            return;
        }
        // Get fields to include
        /** @var Settings $settings */
        $settings = $this->getSettings();
        $profile = SEOMateHelper::getElementProfile($element, $settings) ?? $settings->defaultProfile ?? null;
        $fieldProfile = Json::encode($settings->fieldProfiles[$profile] ?? []);
        $js = <<<JS
                    SEOMATE_FIELD_PROFILE = {$fieldProfile};
JS;
        Craft::$app->getView()->registerJs($js, View::POS_HEAD);
        Craft::$app->getView()->registerAssetBundle(PreviewAsset::class);
    }

    // Protected Methods
    // =========================================================================

    /**
     * Creates and returns the model used to store the plugin’s settings.
     *
     * @return \craft\base\Model|null
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }
}
