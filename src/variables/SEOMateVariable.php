<?php
/**
 * SEOMate plugin for Craft CMS 3.x
 *
 * -
 *
 * @link      https://www.vaersaagod.no/
 * @copyright Copyright (c) 2018 Værsågod
 */

namespace vaersaagod\seomate\variables;

use craft\helpers\Template;
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
     * @return \Twig_Markup
     */
    public function renderMetaTag($key, $value): \Twig_Markup
    {
        return SEOMate::$plugin->meta->renderMetaTag($key, $value);
    }

    /**
     * @param array $breadcrumbArray
     * @return \Twig_Markup
     */
    public function breadcrumbSchema($breadcrumbArray): \Twig_Markup
    {
        $breadcrumbList = SEOMate::$plugin->schema->breadcrumb($breadcrumbArray);
        return Template::raw($breadcrumbList->toScript());
    }
}
