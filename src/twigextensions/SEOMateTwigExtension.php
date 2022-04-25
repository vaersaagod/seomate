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
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('renderMetaTag', fn(string $key, array|string $value): Markup => $this->renderMetaTag($key, $value)),
        ];
    }

    public function renderMetaTag(string $key, string|array $value): Markup
    {
        return SEOMate::$plugin->render->renderMetaTag($key, $value);
    }
}
