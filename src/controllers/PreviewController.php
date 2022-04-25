<?php
/**
 * SEOMate plugin for Craft CMS 3.x
 *
 * @link      https://www.vaersaagod.no/
 * @copyright Copyright (c) 2019 Værsågod
 */

namespace vaersaagod\seomate\controllers;

use vaersaagod\seomate\SEOMate;

use Craft;
use craft\base\Element;
use craft\elements\Category;
use craft\elements\Entry;
use craft\helpers\DateTimeHelper;
use craft\models\EntryDraft;
use craft\models\Section;
use craft\web\Controller;
use craft\web\Response;
use craft\web\View;

use craft\commerce\elements\Product;
use craft\commerce\helpers\Product as ProductHelper;

use yii\web\Response as YiiResponse;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\ServerErrorHttpException;

use DateTime;

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
     * @return Response|YiiResponse
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

        // Get meta
        $view = $this->getView();
        $view->getTwig()->disableStrictVariables();
        $view->setTemplateMode(View::TEMPLATE_MODE_SITE);

        $meta = SEOMate::$plugin->meta->getContextMeta(\array_merge($view->getTwig()->getGlobals(), [
            'seomate' => [
                'element' => $element,
                'config' => [
                    'cacheEnabled' => false,
                ],
            ],
        ]));

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
     * @return Response
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
     * @param Product $product
     *
     * @throws ForbiddenHttpException|InvalidConfigException
     */
    private function _enforceProductPermissions(Product $product): void
    {
        $this->requirePermission('commerce-manageProductType:' . $product->getType()->uid);
    }

    /**
     * @param Product $product
     * @return Response|YiiResponse
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
            throw new ServerErrorHttpException('The product ' . $product->id . ' doesn\'t have a URL for the site ' . $product->siteId . '.');
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
}
