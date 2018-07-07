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

use vaersaagod\seomate\SEOMate;
use craft\helpers\Template;

use Craft;

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
    
    public function renderMetaTag($key, $value)
    {
        return SEOMate::$plugin->meta->renderMetaTag($key, $value);
    }

    public function breadcrumbSchema($breadcrumbArray)
    {
        $breadcrumbList = SEOMate::$plugin->schema->breadcrumb($breadcrumbArray);
        return Template::raw($breadcrumbList->toScript());
    }
}
