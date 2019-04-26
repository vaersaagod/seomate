<?php
/**
 * SEOMate plugin for Craft CMS 3.x
 *
 * @link      https://www.vaersaagod.no/
 * @copyright Copyright (c) 2019 Værsågod
 */

namespace vaersaagod\seomate\variables;

use Spatie\SchemaOrg\Schema;

/**
 * SEOMate Schema Variable
 *
 * @author    Værsågod
 * @package   SEOMate
 * @since     1.0.0
 */
class SchemaVariable
{
    /**
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return Schema::$name();
    }
}
