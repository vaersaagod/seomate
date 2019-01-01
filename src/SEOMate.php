<?php
/**
 * SEOMate plugin for Craft CMS 3.x
 *
 * -
 *
 * @link      https://www.vaersaagod.no/
 * @copyright Copyright (c) 2018 Værsågod
 */

namespace vaersaagod\seomate;

use Craft;
use craft\base\Plugin;
use craft\helpers\Json;
use craft\web\UrlManager;
use craft\web\twig\variables\CraftVariable;
use craft\events\RegisterUrlRulesEvent;
use craft\events\ElementEvent;
use craft\events\RegisterCacheOptionsEvent;
use craft\services\Elements;
use craft\utilities\ClearCaches;
use craft\web\View;

use vaersaagod\seomate\assets\PreviewAsset;
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
            function (Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('schema', SchemaVariable::class);
                $variable->set('seomate', SEOMateVariable::class);
            }
        );

        // Template Hook
        Craft::$app->view->hook(
            'seomateMeta',
            [$this, 'onRegisterMetaHook']
        );

        // Adds SEOMate to the Clear Caches tool
        Event::on(ClearCaches::class, ClearCaches::EVENT_REGISTER_CACHE_OPTIONS,
            function (RegisterCacheOptionsEvent $event) {
                $event->options[] = [
                    'key' => 'seomate-cache',
                    'label' => Craft::t('seomate', 'SEOMate cache'),
                    'action' => [SEOMate::$plugin, 'invalidateCaches'],
                ];
            }
        );

        // After save element event handler
        Event::on(Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT,
            function (ElementEvent $event) {
                $element = $event->element;
                if (!$event->isNew) {
                    CacheHelper::deleteMetaCacheForElement($element);
                }
            }
        );

        // Add in our Twig extensions
        Craft::$app->view->registerTwigExtension(new SEOMateTwigExtension());

        // Add routes to sitemap if enabled
        if ($settings->sitemapEnabled) {
            Event::on(
                UrlManager::class,
                UrlManager::EVENT_REGISTER_SITE_URL_RULES,
                [$this, 'onRegisterSiteUrlRules']
            );
        }

        // Register preview asset bundle
        $view = Craft::$app->getView();
        $view->hook('cp.entries.edit', [$this, 'registerPreviewAssetsBundle']);
        $view->hook('cp.categories.edit', [$this, 'registerPreviewAssetsBundle']);
    }

    /**
     *
     */
    public function invalidateCaches()
    {
        CacheHelper::clearAllCaches();
    }

    /**
     * @param $context
     *
     * @return string
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     */
    public function onRegisterMetaHook(&$context): string
    {
        $craft = \Craft::$app;
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
     * @param RegisterUrlRulesEvent $event
     */
    public function onRegisterSiteUrlRules(RegisterUrlRulesEvent $event)
    {
        $settings = $this->getSettings();

        if ($settings->sitemapEnabled) {
            $sitemapName = $settings->sitemapName;

            $event->rules[$sitemapName . '.xml'] = 'seomate/sitemap/index';
            $event->rules[$sitemapName . '_<handle:\w*>_<page:\d*>.xml'] = 'seomate/sitemap/element';
            $event->rules[$sitemapName . '_custom.xml'] = 'seomate/sitemap/custom';
        }
    }

    /**
     * @param array $context
     * @throws \yii\base\InvalidConfigException
     */
    public function registerPreviewAssetsBundle(array $context = [])
    {
        $element = $context['entry'] ?? $context['category'] ?? null;
        if (!$element) {
            return;
        }
        // Get fields to include
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
