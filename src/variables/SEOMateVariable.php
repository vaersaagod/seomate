<?php
/**
 * SEOMate plugin for Craft CMS 3.x
 *
 * @link      https://www.vaersaagod.no/
 * @copyright Copyright (c) 2019 Værsågod
 */

namespace vaersaagod\seomate\variables;

use craft\helpers\Template;
use Twig\Markup;
use vaersaagod\seomate\SEOMate;

/**
 * SEOMate Variable
 *
 * https://craftcms.com/docs/plugins/variables
 *
 * @author    Værsågod
 * @package   SEOMate
 * @since     1.0.0
 */
class SEOMateVariable
{
    // Public Methods
    // =========================================================================

    /**
     * @param string $key
     * @param string $value
     * @return Markup
     */
    public function renderMetaTag($key, $value): Markup
    {
        return SEOMate::$plugin->render->renderMetaTag($key, $value);
    }

    /**
     * @param array $breadcrumbArray
     * @return Markup
     */
    public function breadcrumbSchema($breadcrumbArray): Markup
    {
        $breadcrumbList = SEOMate::$plugin->schema->breadcrumb($breadcrumbArray);
        return Template::raw($breadcrumbList->toScript());
    }

    /**
     * @param array $config
     * @return array
     */
    public function getMeta($config = [])
    {
        $context = ['seomate' => $config];
        
        $meta = SEOMate::$plugin->meta->getContextMeta($context);
        $canonicalUrl = SEOMate::$plugin->urls->getCanonicalUrl($context);
        $alternateUrls = SEOMate::$plugin->urls->getAlternateUrls($context);

        return [
            'meta' => $meta,
            'canonicalUrl' => $canonicalUrl,
            'alternateUrls' => $alternateUrls,
        ];
    }
}
