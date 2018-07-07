<?php
/**
 * SEOMate plugin for Craft CMS 3.x
 *
 * -
 *
 * @link      https://www.vaersaagod.no/
 * @copyright Copyright (c) 2018 Værsågod
 */

namespace vaersaagod\seomate\controllers;

use craft\web\Response;
use vaersaagod\seomate\SEOMate;

use Craft;
use craft\web\Controller;

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

    protected $allowAnonymous = true;
    
    public function actionIndex()
    {
        return $this->returnXml(
			SEOMate::$plugin->sitemap->index()
		);
    }
    
    public function actionElement()
    {
        $params = Craft::$app->getUrlManager()->getRouteParams();
        
        return $this->returnXml(
			SEOMate::$plugin->sitemap->elements($params['handle'], $params['page'])
		);
    }
    
    public function actionCustom()
    {
        return $this->returnXml(
			SEOMate::$plugin->sitemap->custom()
		);
    }
    
    public function actionSubmit()
    {
        SEOMate::$plugin->sitemap->submit();
        Craft::$app->end();
    }
    
    private function returnXml ($data)
	{
		$response = Craft::$app->getResponse();
		$response->content = $data;
		$response->format = Response::FORMAT_XML;
		return $response;
	}
}
