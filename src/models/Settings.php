<?php
/**
 * SEOMate plugin for Craft CMS 3.x
 *
 * @link      https://www.vaersaagod.no/
 * @copyright Copyright (c) 2019 Værsågod
 */

namespace vaersaagod\seomate\models;

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


    public bool $cacheEnabled = true;

    public int|string $cacheDuration = 3600;

    public array|bool $previewEnabled = true;

    public string|null $previewLabel = null;

    public string|array|null $siteName = null;

    public string $metaTemplate = '';

    public bool $includeSitenameInTitle = true;

    public array $sitenameTitleProperties = ['title'];

    public string $sitenamePosition = 'after';

    public string $sitenameSeparator = '|';

    public string|null $defaultProfile = null;

    public bool $outputAlternate = true;

    public string|null $alternateFallbackSiteHandle = null;

    public string|null $altTextFieldHandle = null;

    public array $defaultMeta = [];

    public array $fieldProfiles = [];

    public array $profileMap = [];

    public array $additionalMeta = [];

    public array $metaPropertyTypes = [
        'title,og:title,twitter:title' => [
            'type' => 'text',
            'minLength' => 10,
            'maxLength' => 60,
        ],
        'description,og:description,twitter:description' => [
            'type' => 'text',
            'minLength' => 50,
            'maxLength' => 300,
        ],
        'image,og:image,twitter:image' => [
            'type' => 'image',
        ],
    ];

    public bool $applyRestrictions = false;

    public array $validImageExtensions = ['jpg', 'jpeg', 'gif', 'png'];

    public string $truncateSuffix = '...';

    public bool $returnImageAsset = false;

    public bool $useImagerIfInstalled = true;

    public array $imageTransformMap = [
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

    public array $autofillMap = [
        'og:title' => 'title',
        'og:description' => 'description',
        'og:image' => 'image',
        'twitter:title' => 'title',
        'twitter:description' => 'description',
        'twitter:image' => 'image',
    ];

    public array $tagTemplateMap = [
        'default' => '<meta name="{{ key }}" content="{{ value }}"/>',
        'title' => '<title>{{ value }}</title>',
        '/^og:/,/^fb:/' => '<meta property="{{ key }}" content="{{ value }}">',
    ];

    public bool $sitemapEnabled = false;

    public string $sitemapName = 'sitemap';

    public int $sitemapLimit = 500;

    public array $sitemapConfig = [];

    public array $sitemapSubmitUrlPatterns = [
        'http://www.google.com/webmasters/sitemaps/ping?sitemap=',
        'http://www.bing.com/webmaster/ping.aspx?siteMap=',
    ];


    public function rules(): array
    {
        return [

        ];
    }
}
