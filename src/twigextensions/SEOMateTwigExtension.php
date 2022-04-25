<?php
/**
 * SEOMate plugin for Craft CMS 3.x
 *
 * @link      https://www.vaersaagod.no/
 * @copyright Copyright (c) 2019 Værsågod
 */

namespace vaersaagod\seomate\twigextensions;

use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

use vaersaagod\seomate\SEOMate;


/**
 * @author    Værsågod
 * @package   SEOMate
 * @since     1.0.0
 */
class SEOMateTwigExtension extends AbstractExtension
{
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
     * Returns an array of Twig functions, used in Twig templates via:
     *
     * @return array
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('renderMetaTag', [$this, 'renderMetaTag']),
        ];
    }

    /**
     * @param string $key
     * @param string|array $value
     * @return Markup
     */
    public function renderMetaTag(string $key, string|array $value): Markup
    {
        return SEOMate::$plugin->render->renderMetaTag($key, $value);
    }
}
