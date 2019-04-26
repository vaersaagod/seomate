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
use craft\helpers\Template;

use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;

use vaersaagod\seomate\SEOMate;
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

        return Template::raw($r);
    }
}
