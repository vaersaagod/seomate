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
use craft\base\ElementInterface;
use craft\commerce\elements\Product;
use craft\elements\Category;
use craft\elements\Entry;
use craft\web\Controller;
use craft\web\View;

use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

use vaersaagod\seomate\SEOMate;

use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\web\Response;
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
    protected int|bool|array $allowAnonymous = ['index'];

    /**
     * @return Response
     * @throws Exception
     * @throws InvalidConfigException
     * @throws ServerErrorHttpException
     */
    public function actionIndex(): Response
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

        try {
            $meta = $this->_getMetaFromElementPageTemplate($element, $context);
        } catch (\Throwable $e) {
            Craft::error("An error occurred when attempting to render meta data for element page template: " . $e->getMessage(), __METHOD__);
        }

        if (empty($meta)) {
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
     * @param ElementInterface $element
     * @param array $context
     * @return array|null
     * @throws Exception
     * @throws InvalidConfigException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    private function _getMetaFromElementPageTemplate(ElementInterface $element, array $context = []): ?array
    {

        if (!$element instanceof Element) {
            return null;
        }

        $refHandle = null;
        if (method_exists($element, 'refHandle')) {
            $refHandle = $element->refHandle();
        }

        if (empty($refHandle)) {
            return null;
        }

        $pageTemplate = null;

        if ($element instanceof Entry) {
            if (!empty($element->sectionId)) {
                $pageTemplate = $element->getSection()?->getSiteSettings()[$element->siteId]['template'] ?? null;
            } else if (!empty($element->fieldId)) { // Nested entry
                $pageTemplate = $element->getField()->siteSettings[$element->getSite()->uid]['template'] ?? null;
            }
        } else if ($element instanceof Category) {
            $pageTemplate = $element->getGroup()->getSiteSettings()[$element->siteId]['template'] ?? null;
        } else if ($element instanceof Product) {
            $pageTemplate = $element->getType()->getSiteSettings()[$element->siteId]['template'] ?? null;
        }

        if (empty($pageTemplate) || !is_string($pageTemplate)) {
            return null;
        }

        $variables = array_merge($context, [
            $refHandle => $element,
            'seomatePreviewElement' => $element,
        ]);
        $html = Craft::$app->getView()->renderTemplate($pageTemplate, $variables);

        return $this->_getMetaFromHtml($html);
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
