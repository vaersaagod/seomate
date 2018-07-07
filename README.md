SEOMate plugin for Craft CMS 3.x
===

## Requirements

This plugin requires Craft CMS 3.0.0-beta.23 or later.

## Installation

To install the plugin, follow these instructions.

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require vaersaagod/seomate

3. In the Control Panel, go to Settings → Plugins and click the “Install” button for SEOMate.

## SEOMate Overview

-Insert text here-

## Configuring SEOMate

Example configureation file:

```
<?php

return [
    '*' => [
        'siteName' => [
            'default' => 'Default site',
            'engelsk' => 'Alternative site',
        ],
            
        'includeSitenameInTitle' => true,
        'cacheEnabled' => false,
        'cacheDuration' => 3600,
        'sitenamePosition' => 'after',
        'sitenameSeparator' => '|',
        'defaultProfile' => 'default',
        'truncateLength' => true,
        'altTextFieldHandle' => 'altText',
        
        'defaultMeta' => [
            'title' => ['globalSeo.seoTitle'],
            'description' => ['globalSeo.seoDescription'],
            'image' => ['globalSeo.seoImages']
        ],

        'fieldProfiles' => [
            'default' => [
                'title' => ['seoTitle', 'title'],
                'description' => ['seoDescription', 'summary', 'listText'],
                'image' => ['seoImages', 'mainImage']
            ],
            'portfolio' => [
                'title' => ['seoTitle','title'],
                'description' => ['seoDescription', 'summary'],
                'image' => ['seoImages', 'images', 'testMatrix:images.images'],
            ],
        ],
        
        'profileMap' => [
            'portfolio' => 'portfolio',
        ],
        
        'additionalMeta' => [
            'og:type' => 'website',
            'twitter:card' => 'summary_large_image',
            'og:see_also' => ['{{ globalSeo.seoTitle ?? "" }}', '{{ globalSeo.seoTitle ?? "" }}']
            /*
            'fb:profile_id' => '{{ settings.facebookProfileId }}',
            'twitter:site' => '@{{ settings.twitterHandle }}',
            'twitter:author' => '@{{ settings.twitterHandle }}',
            'twitter:creator' => '@{{ settings.twitterHandle }}',
            */
        ],
        
        'sitemapEnabled' => true,
        'sitemapLimit' => 10,
        'sitemapConfig' => [
            'elements' => [
                'test' => [
                    'elementType' => \craft\elements\Entry::class,
                    'criteria' => ['section' => ['lorem', 'testing']],
                    'params' => ['changefreq' => 'daily', 'priority' => 0.5],
                ],
                'loremcategories' => [
                    'elementType' => \craft\elements\Category::class,
                    'criteria' => ['group' => 'loremCategories'],
                    'params' => ['changefreq' => 'weekly', 'priority' => 0.2],
                ],
                'portfolio' => ['changefreq' => 'weekly', 'priority' => 0.5],
                'testing' => ['changefreq' => 'weekly', 'priority' => 0.5],
            ],
            'custom' => [
                '/' => ['changefreq' => 'weekly', 'priority' => 1],
                '/custom-1' => ['changefreq' => 'weekly', 'priority' => 1],
                '/custom/2' => ['changefreq' => 'weekly', 'priority' => 1],
            ]
        ],
        
    ]
];


```

## Using SEOMate

-Insert text here-

## SEOMate Roadmap

Some things to do, and ideas for potential features:

* Release it

Brought to you by [Værsågod](https://www.vaersaagod.no/)
