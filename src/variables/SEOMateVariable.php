<?php
/**
 * SEOMate plugin for Craft CMS 5.x
 *
 * @link      https://www.vaersaagod.no/
 * @copyright Copyright (c) 2024 Værsågod
 */

namespace vaersaagod\seomate\variables;

use craft\helpers\Template;
use Twig\Markup;
use vaersaagod\seomate\helpers\SEOMateHelper;
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
    public function renderMetaTag(string $key, string|array $value): Markup
    {
        return SEOMate::getInstance()->render->renderMetaTag($key, $value);
    }

    public function breadcrumbSchema(array $breadcrumbArray): Markup
    {
        $breadcrumbArray = array_map(static function (array $crumb) {
            $crumb['url'] = SEOMateHelper::stripTokenParams($crumb['url']);
            return $crumb;
        }, $breadcrumbArray);
        $breadcrumbList = SEOMate::getInstance()->schema->breadcrumb($breadcrumbArray);
        return Template::raw($breadcrumbList->toScript());
    }

    /**
     * @throws \Throwable
     */
    public function getMeta(array $config = []): array
    {
        $context = array_merge(['seomate' => $config], \Craft::$app->getView()->getTwig()->getGlobals());
        
        $meta = SEOMate::getInstance()->meta->getContextMeta($context);
        $canonicalUrl = SEOMate::getInstance()->urls->getCanonicalUrl($context);
        $alternateUrls = SEOMate::getInstance()->urls->getAlternateUrls($context);

        return [
            'meta' => $meta,
            'canonicalUrl' => $canonicalUrl,
            'alternateUrls' => $alternateUrls,
        ];
    }
}
