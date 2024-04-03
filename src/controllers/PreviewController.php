<?php
/**
 * SEOMate plugin for Craft CMS 5.x
 *
 * @link      https://www.vaersaagod.no/
 * @copyright Copyright (c) 2024 Værsågod
 */

namespace vaersaagod\seomate\controllers;

use Craft;

use craft\base\Element;
use craft\commerce\elements\Product;
use craft\commerce\helpers\Product as ProductHelper;
use craft\elements\Category;
use craft\elements\Entry;
use craft\web\Controller;
use craft\web\Response;
use craft\web\View;

use vaersaagod\seomate\SEOMate;

use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response as YiiResponse;
use yii\web\ServerErrorHttpException;

/**
 * Preview Controller
 *
 * https://craftcms.com/docs/plugins/controllers
 *
 * @author    Værsågod
 * @package   SEOMate
 * @since     1.0.0
 */
class PreviewController extends Controller
{
    /**
     * @inheritdoc
     */
    protected int|bool|array $allowAnonymous = ['preview'];

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws ServerErrorHttpException
     */
    public function actionPreview(): Response|YiiResponse
    {
        $elementId = Craft::$app->getRequest()->getParam('elementId');
        $siteId = Craft::$app->getRequest()->getParam('siteId');

        /** @var Element|null $element */
        $element = Craft::$app->getElements()->getElementById((int)$elementId, null, $siteId);
        if (!$element || !$element->uri) {
            return $this->asRaw('');
        }

        $site = Craft::$app->getSites()->getSiteById($element->siteId);
        if (!$site) {
            throw new ServerErrorHttpException('Invalid site ID: ' . $element->siteId);
        }

        if ($site->language) {
            Craft::$app->language = $site->language;
            Craft::$app->set('locale', Craft::$app->getI18n()->getLocaleById($site->language));
        }

        // Have this element override any freshly queried elements with the same ID/site
        Craft::$app->getElements()->setPlaceholderElement($element);

        // Disable caching
        \Craft::$app->getConfig()->getGeneral()->enableTemplateCaching = false;
        SEOMate::getInstance()->settings->cacheEnabled = false;

        // Get meta
        $view = $this->getView();
        $view->getTwig()->disableStrictVariables();
        $view->setTemplateMode(View::TEMPLATE_MODE_SITE);
        $context = $view->getTwig()->getGlobals();

        $meta = null;

        if ($element instanceof Entry) {
            // If this is an entry, get the metadata from the rendered entry template
            // This ensures that custom meta templates and template overrides will be rendered
            try {
                if ($template = $element->getSection()?->getSiteSettings()[$element->siteId]['template'] ?? null) {
                    $variables = array_merge($context, [
                        'entry' => $element,
                        'seomatePreviewElement' => $element,
                    ]);
                    $html = $view->renderTemplate($template, $variables);
                    $meta = $this->_getMetaFromHtml($html);
                }
            } catch (\Throwable $e) {
                \Craft::error($e, __METHOD__);
            }
        }

        if (!$meta) {
            // Fall back to getting the metadata directly from the meta service
            $context = array_merge($context, [
                'seomate' => [
                    'element' => $element,
                    'config' => [
                        'cacheEnabled' => false,
                    ],
                ],
            ]);
            $meta = SEOMate::getInstance()->meta->getContextMeta($context);
        }

        // Render previews
        $view->setTemplateMode(View::TEMPLATE_MODE_CP);

        return $this->renderTemplate('seomate/preview', [
            'element' => $element,
            'meta' => $meta,
        ]);
    }

    /**
     * Previews an Entry or a Category
     *
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException if the requested entry version cannot be found
     * @throws Exception
     * @throws InvalidConfigException
     * @throws ForbiddenHttpException
     */
    public function actionIndex(): Response
    {
        $this->requirePostRequest();

        $productId = Craft::$app->getRequest()->getParam('productId');

        // What kind of element is it?
        if ($productId !== null) {
            $product = ProductHelper::populateProductFromPost();
            $this->_enforceProductPermissions($product);

            return $this->_showProduct($product);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @throws ForbiddenHttpException|InvalidConfigException
     */
    private function _enforceProductPermissions(Product $product): void
    {
        $this->requirePermission('commerce-manageProductType:' . $product->getType()->uid);
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     * @throws ServerErrorHttpException
     */
    private function _showProduct(Product $product): Response|YiiResponse
    {
        $productType = $product->getType();
        if (!$productType) {
            throw new ServerErrorHttpException('Product type not found.');
        }

        $siteSettings = $productType->getSiteSettings();
        if (!isset($siteSettings[$product->siteId]) || !$siteSettings[$product->siteId]->hasUrls) {
            throw new ServerErrorHttpException('The product ' . $product->getId() . " doesn't have a URL for the site " . $product->siteId . '.');
        }

        $site = Craft::$app->getSites()->getSiteById($product->siteId);
        if (!$site) {
            throw new ServerErrorHttpException('Invalid site ID: ' . $product->siteId);
        }

        Craft::$app->language = $site->language;
        // Have this product override any freshly queried products with the same ID/site
        Craft::$app->getElements()->setPlaceholderElement($product);

        // Get meta
        $view = $this->getView();
        $view->getTwig()->disableStrictVariables();
        $view->setTemplateMode(View::TEMPLATE_MODE_SITE);

        $meta = SEOMate::$plugin->meta->getContextMeta(\array_merge($view->getTwig()->getGlobals(), [
            'seomate' => [
                'element' => $product,
                'config' => [
                    'cacheEnabled' => false,
                ],
            ],
        ]));

        // Render previews
        $view->setTemplateMode(View::TEMPLATE_MODE_CP);
        return $this->renderTemplate('seomate/preview', [
            'product' => $product,
            'meta' => $meta,
        ]);
    }

    /**
     * @param string|null $html
     * @return array|null
     */
    private function _getMetaFromHtml(?string $html): ?array
    {
        if (empty($html)) {
            return null;
        }
        $tags = [];
        $libxmlUseInternalErrors = libxml_use_internal_errors(true);
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', Craft::$app->getView()->getTwig()->getCharset());
        if (!is_string($html)) {
            return null;
        }
        $doc = new \DOMDocument();
        $doc->loadHTML($html);
        $xpath = new \DOMXPath($doc);
        $nodes = $xpath->query('//head/meta');
        /** @var \DOMElement $node */
        foreach ($nodes as $node) {
            $key = $node->getAttribute('name') ?: $node->getAttribute('property');
            $value = $node->getAttribute('content');
            if ($key && $value) {
                $tags[$key] = $value;
            }
        }
        if ($title = $doc->getElementsByTagName('title')->item(0)?->nodeValue) {
            $tags['title'] = $title;
        }
        libxml_use_internal_errors($libxmlUseInternalErrors);
        return $tags;
    }
}
