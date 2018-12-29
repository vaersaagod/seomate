<?php
/**
 * SEOMate plugin for Craft CMS 3.x
 *
 * -
 *
 * @link      https://www.vaersaagod.no/
 * @copyright Copyright (c) 2018 Værsågod
 */

namespace vaersaagod\seomate\models;

use vaersaagod\seomate\SEOMate;

use Craft;
use craft\base\Model;

/**
 * SEOMate Settings Model
 *
 * @author    Værsågod
 * @package   SEOMate
 * @since     1.0.0
 */
class Settings extends Model
{
    // Public Properties
    // =========================================================================

    public $siteName = null;

    public $metaTemplate = '';
    public $cacheEnabled = true;
    public $cacheDuration = 3600;
    public $includeSitenameInTitle = true;
    public $sitenameTitleProperties = ['title'];
    public $sitenamePosition = 'after';
    public $sitenameSeparator = '|';
    public $defaultProfile = null;
    public $outputAlternate = true;
    public $altTextFieldHandle = null;

    public $defaultMeta = [];
    public $fieldProfiles = [];
    public $profileMap = [];
    public $additionalMeta = [];

    public $metaPropertyTypes = [
        'title,og:title,twitter:title' => [
            'type' => 'text',
            'minLength' => 10,
            'maxLength' => 60
        ],
        'description,og:description,twitter:description' => [
            'type' => 'text',
            'minLength' => 50,
            'maxLength' => 300
        ],
        'image,og:image,twitter:image' => [
            'type' => 'image'
        ],
    ];

    public $applyRestrictions = false;
    public $validImageExtensions = ['jpg', 'gif', 'png'];
    public $truncateLength = false;
    public $truncateSuffix = '...';

    public $returnImageAsset = false;
    public $useImagerIfInstalled = true;
    public $imageTransformMap = [
        'image' => [
            'width' => 1200,
            'height' => 675,
            'format' => 'jpg',
        ],
        'og:image' => [
            'width' => 1200,
            'height' => 630,
            'format' => 'jpg',
        ],
        'twitter:image' => [
            'width' => 1200,
            'height' => 600,
            'format' => 'jpg',
        ],
    ];

    public $autofillMap = [
        'og:title' => 'title',
        'og:description' => 'description',
        'og:image' => 'image',
        'twitter:title' => 'title',
        'twitter:description' => 'description',
        'twitter:image' => 'image',
    ];

    public $tagTemplateMap = [
        'default' => '<meta name="{{ key }}" content="{{ value }}"/>',
        'title' => '<title>{{ value }}</title>',
        '/^og:/,/^fb:/' => '<meta property="{{ key }}" content="{{ value }}">',
    ];

    public $sitemapEnabled = false;
    public $sitemapName = 'sitemap';
    public $sitemapLimit = 500;
    public $sitemapConfig = [];
    public $sitemapSubmitUrlPatterns = [
        'http://www.google.com/webmasters/sitemaps/ping?sitemap=',
        'http://www.bing.com/webmaster/ping.aspx?siteMap=',
    ];

    // Public Methods
    // =========================================================================

    /**
     * @return array
     */
    public function rules(): array
    {
        return [

        ];
    }
}
