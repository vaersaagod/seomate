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
use craft\helpers\Template as TemplateHelper;
use craft\web\View;

use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;

use vaersaagod\seomate\SEOMate;
use vaersaagod\seomate\events\MetaTemplateEvent;
use vaersaagod\seomate\helpers\SEOMateHelper;

/**
 * RenderService Service
 * 
 * @author    Værsågod
 * @package   SEOMate
 * @since     1.0.0
 */
class RenderService extends Component
{

    // Constants
    // =========================================================================

    /**
     * @event MetaTemplateEvent The event that is triggered when registering CP template roots
     */
    const EVENT_SEOMATE_BEFORE_RENDER_META_TEMPLATE = 'seomateBeforeRenderMetaTemplate';

    /**
     * @event MetaTemplateEvent The event that is triggered when registering site template roots
     */
    const EVENT_SEOMATE_AFTER_RENDER_META_TEMPLATE = 'seomateAfterRenderMetaTemplate';

    /**
     * Renders the meta template
     *
     * @param array $context
     * @return string
     * @throws LoaderError
     * @throws SyntaxError
     * @throws \Twig\Error\RuntimeError
     * @throws \yii\base\Exception
     */
    public function renderMetaTemplate(array $context): string
    {

        $view = Craft::$app->getView();
        $oldTemplateMode = $view->getTemplateMode();

        $seomate = SEOMate::$plugin;
        $settings = $seomate->getSettings();

        // Figure out which template to render
        $template = $settings->metaTemplate;
        if (!$template) {
            // Render the default SEOM
            $view->setTemplateMode(View::TEMPLATE_MODE_CP);
            $template = 'seomate/_output/meta';
        }

        // Trigger a "seomateBeforeRenderMetaTemplate" event
        if ($this->hasEventHandlers(self::EVENT_SEOMATE_BEFORE_RENDER_META_TEMPLATE)) {
            $event = new MetaTemplateEvent([
                'context' => $context,
                'template' => $template,
            ]);
            $this->trigger(self::EVENT_SEOMATE_BEFORE_RENDER_META_TEMPLATE, $event);
            $context = $event->context;
        }

        // Get meta data, etc and add it to the context
        $meta = $seomate->meta->getContextMeta($context);
        $canonicalUrl = $seomate->urls->getCanonicalUrl($context);
        $alternateUrls = $seomate->urls->getAlternateUrls($context);
        $context['seomate']['meta'] = $meta;
        $context['seomate']['canonicalUrl'] = $canonicalUrl;
        $context['seomate']['alternateUrls'] = $alternateUrls;

        // Render it
        $output = $view->renderTemplate($template, $context);

        // Reset the template mode
        $view->setTemplateMode($oldTemplateMode);

        // Trigger a "seomateAfterRenderMetaTemplate" event
        if ($this->hasEventHandlers(self::EVENT_SEOMATE_AFTER_RENDER_META_TEMPLATE)) {
            $event = new MetaTemplateEvent([
                'context' => $context,
                'template' => $template,
                'output' => $output,
            ]);
            $this->trigger(self::EVENT_SEOMATE_AFTER_RENDER_META_TEMPLATE, $event);
            $output = $event->output;
        }

        return $output;
    }

    /**
     * Renders meta tag by key and value. 
     * Uses tag template from tagTemplateMap config setting.
     * 
     * @param string $key
     * @param string|array $value
     * @return \Twig\Markup
     */
    public function renderMetaTag($key, $value) 
    {
        $settings = SEOMate::$plugin->getSettings();
        $tagTemplateMap = SEOMateHelper::expandMap($settings->tagTemplateMap);
        
        // Set default template
        $template = $tagTemplateMap['default'] ?? '';

        // Check if the key matches a regexp template key
        foreach ($tagTemplateMap as $tagTemplateKey => $tagTemplateValue) {
            if (strpos($tagTemplateKey, '/') === 0) {
                if (preg_match($tagTemplateKey, $key)) {
                    $template = $tagTemplateValue;
                }
            }
        } 
        
        // Check if we have an exact match. This will overwrite any regexp match.
        if (isset($tagTemplateMap[$key])) {
            $template = $tagTemplateMap[$key];
        }

        $r = '';

        if (!\is_array($value)) {
            $value = [$value];
        }
        
        try {
            foreach ($value as $val) {
                $r .= Craft::$app->getView()->renderString($template, ['key' => $key, 'value' => $val]);
            }
        } catch (LoaderError $e) {
            Craft::error($e->getMessage(), __METHOD__);
        } catch (SyntaxError $e) {
            Craft::error($e->getMessage(), __METHOD__);
        }

        return TemplateHelper::raw($r);
    }
}
