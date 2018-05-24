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

use Spatie\SchemaOrg\Schema;
use vaersaagod\seomate\SEOMate as Plugin;

use Craft;

/**
 * SEOMate Schema Variable
 *
 * @author    Værsågod
 * @package   SEOMate
 * @since     1.0.0
 */
class SchemaVariable
{
    public function __call($name, $arguments)
    {
        return Schema::$name();
    }
}
