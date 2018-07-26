<?php
/**
 * SEOMate plugin for Craft CMS 3.x
 *
 * -
 *
 * @link      https://www.vaersaagod.no/
 * @copyright Copyright (c) 2018 Værsågod
 */

namespace vaersaagod\seomate\twigextensions;

use Craft;
use vaersaagod\seomate\SEOMate;


/**
 * @author    Værsågod
 * @package   SEOMate
 * @since     1.0.0
 */
class SEOMateTwigExtension extends \Twig_Extension
{
    // Public Methods
    // =========================================================================

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName(): string
    {
        return 'SEOMate';
    }

    /**
     * Returns an array of Twig filters, used in Twig templates via:
     *
     *      {{ 'something' | someFilter }}
     *
     * @return array
     */
    public function getFilters(): array
    {
        return [
            //new \Twig_SimpleFilter('someFilter', [$this, 'someInternalFunction']),
        ];
    }

    /**
     * Returns an array of Twig functions, used in Twig templates via:
     *
     *      {% set this = someFunction('something') %}
     *
     * @return array
     */
    public function getFunctions(): array
    {
        return [
            new \Twig_SimpleFunction('renderMetaTag', [$this, 'renderMetaTag']),
        ];
    }

    /**
     * @param string $key
     * @param string $value
     * @return \Twig_Markup
     */
    public function renderMetaTag($key, $value): \Twig_Markup
    {
        return SEOMate::$plugin->meta->renderMetaTag($key, $value);
    }
}
