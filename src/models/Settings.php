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
 * This is a model used to define the plugin's settings.
 *
 * Models are containers for data. Just about every time information is passed
 * between services, controllers, and templates in Craft, it’s passed via a model.
 *
 * https://craftcms.com/docs/plugins/models
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
            'width' => 1120,
            'height' => 600,
            'format' => 'jpg',
        ],
        'twitter:image' => [
            'width' => 1200,
            'height' => 630,
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
        'og:title,og:description,og:image,og:image:width,og:image:height,og:image:type,og:type' => '<meta property="{{ key }}" content="{{ value }}">',
    ];
    
    // Public Methods
    // =========================================================================

    /**
     * Returns the validation rules for attributes.
     *
     * Validation rules are used by [[validate()]] to check if attribute values are valid.
     * Child classes may override this method to declare different validation rules.
     *
     * More info: http://www.yiiframework.com/doc-2.0/guide-input-validation.html
     *
     * @return array
     */
    public function rules()
    {
        return [

        ];
    }
}
