<?php
/**
 * SEOMate plugin for Craft CMS 3.x
 *
 * @link      https://www.vaersaagod.no/
 * @copyright Copyright (c) 2019 Værsågod
 */

namespace vaersaagod\seomate\controllers;

use Craft;
use craft\web\Controller;

use craft\web\Response;
use craft\web\View;
use vaersaagod\seomate\SEOMate;
use yii\base\ExitException;

/**
 * Sitemap Controller
 *
 * https://craftcms.com/docs/plugins/controllers
 *
 * @author    Værsågod
 * @package   SEOMate
 * @since     1.0.0
 */
class SitemapController extends Controller
{
    protected int|bool|array $allowAnonymous = true;

    /**
     * Action for returning index sitemap
     *
     * @throws \Throwable
     */
    public function actionIndex(): Response|\yii\console\Response
    {
        return $this->returnXml(
            SEOMate::$plugin->sitemap->index()
        );
    }

    /**
     * Action for returning element sitemaps
     *
     * @throws \Throwable
     */
    public function actionElement(): Response|\yii\console\Response
    {
        $params = Craft::$app->getUrlManager()->getRouteParams();

        return $this->returnXml(
            SEOMate::$plugin->sitemap->elements($params['handle'], $params['page'])
        );
    }

    /**
     * Action for returning the custom sitemap
     */
    public function actionCustom(): Response|\yii\console\Response
    {
        return $this->returnXml(
            SEOMate::$plugin->sitemap->custom()
        );
    }

    /**
     * Action for submitting sitemap to search engines
     *
     * @throws ExitException
     * @throws \Throwable
     */
    public function actionSubmit(): void
    {
        SEOMate::$plugin->sitemap->submit();
        Craft::$app->end();
    }

    /**
     * @return Response|\yii\console\Response
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \yii\base\Exception
     */
    public function actionXsl(): Response|\yii\console\Response
    {
        $xml = \Craft::$app->getView()->renderTemplate('seomate/_sitemaps/xsl.twig', [], View::TEMPLATE_MODE_CP);

        $headers = Craft::$app->response->headers;
        $headers->add('Content-Type', 'text/xml; charset=utf-8');
        $headers->add('X-Robots-Tag', 'noindex');

        return $this->asRaw($xml);
    }

    /**
     * Helper function for returning an XML response
     *
     *
     */
    private function returnXml(string $data): Response|\yii\console\Response
    {
        $response = Craft::$app->getResponse();
        $response->content = $data;
        $response->format = \yii\web\Response::FORMAT_XML;
        return $response;
    }
}
