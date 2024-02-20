<?php
/**
 * SEOMate plugin for Craft CMS 5.x
 *
 * @link      https://www.vaersaagod.no/
 * @copyright Copyright (c) 2024 Værsågod
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
     * @return Response
     * @throws \Throwable
     */
    public function actionIndex(): Response
    {
        return $this->returnXml(
            SEOMate::$plugin->sitemap->index()
        );
    }

    /**
     * Action for returning element sitemaps
     *
     * @return Response
     * @throws \Throwable
     */
    public function actionElement(): Response
    {
        $params = Craft::$app->getUrlManager()->getRouteParams();

        return $this->returnXml(
            SEOMate::$plugin->sitemap->elements($params['handle'], $params['page'])
        );
    }

    /**
     * Action for returning custom sitemaps
     *
     * @return Response
     */
    public function actionCustom(): Response
    {
        return $this->returnXml(
            SEOMate::$plugin->sitemap->custom()
        );
    }

    /**
     * Action for submitting sitemap to search engines
     *
     * @return void
     * @throws ExitException
     * @throws \Throwable
     */
    public function actionSubmit(): void
    {
        SEOMate::$plugin->sitemap->submit();
        Craft::$app->end();
    }

    /**
     * Action for returning the XSLT sitemap stylesheet
     *
     * @return Response
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \yii\base\Exception
     */
    public function actionXsl(): Response
    {
        $xml = Craft::$app->getView()->renderTemplate('seomate/_sitemaps/xsl.twig', [], View::TEMPLATE_MODE_CP);
        $this->response->headers->set('X-Robots-Tag', 'noindex');
        return $this->returnXml($xml);
    }

    /**
     * Helper function for returning an XML response
     *
     * @param string $data
     * @return Response
     */
    private function returnXml(string $data): Response
    {
        $this->response->content = $data;
        $this->response->format = \yii\web\Response::FORMAT_XML;
        return $this->response;
    }
}
