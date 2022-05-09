SEOMate plugin for Craft CMS
===

SEO, mate! It's important. That's why SEOMate provides the tools you need to craft
all the meta tags, sitemaps and JSON-LD microdata you need - in one highly configurable,
open and friendly package - with a super-light footprint. 

SEOMate aims to do less! Unlike other SEO plugins for Craft, there are no control panel 
settings or fieldtypes. Instead, you configure everything from the plugin's 
config file, which makes it easy and quick to set up, bootstrap and version control your 
configuration. All the data is pulled from native Craft fields, which makes for less 
maintenance over time, _and keeps you in control of your data_. 

Additionally, SEOMate adds a super-awesome SEO/social media preview to your Control Panel. The SEO preview taps into
Craft's native Preview Targets, giving your clients a nice and familiar interface for previewing how their content will appear on Google, Facebook and Twitter.  

  
![Screenshot](resources/plugin_logo.png)

## Requirements

This plugin requires Craft CMS 4.0.0 or later.  

## Installation

To install the plugin, either install it from the plugin store, or follow these instructions:

1. Install with composer via `composer require vaersaagod/seomate` from your project directory.
2. Install the plugin in the Craft Control Panel under Settings → Plugins, or from the command line via `./craft install/plugin seomate`.
3. For SEOMate to do anything, you need to [configure it](#configuring). But first, continue reading!

---

## SEOMate Overview

SEOMate focuses on providing developers with the tools they need to craft their 
site's SEO in three main areas; **meta data**, **sitemaps**, and **JSON-LD microdata**.

### Meta data
SEOMate doesn't provide any field types or custom tables that store your meta data.
Instead, you use the native field types that comes with Craft, and just tell SEOMate
where to look for meta data. 

You do this by configuring profiles for the different field setups in your site, and 
then map sections and category groups to these profiles, or set the desired profile from 
your templates.

The key config settings for meta data is `fieldProfiles`, `profileMap`, `defaultProfile`, 
`defaultMeta` and `additionalMeta`. Refer to the ["Adding meta data"](#adding-meta-data) 
section on how to include the meta data in your page, and override it at the template level.

### Sitemaps
SEOMate lets you create completely configuration based sitemaps for all your content.
The sitemaps are automatically updated with new elements, and will automatically be
split into multiple sitemaps for scalability. 

To enable sitemaps, set the `sitemapEnabled` config setting to `true` and configure the
contents of your sitemaps with `sitemapConfig`. Refer to the ["Enabling sitemaps"](#enabling-sitemaps)
section on how to enable and set up your sitemaps.

### JSON-LD
SEOMate provides a thin wrapper around the excellent [`spatie/schema-org`](https://github.com/spatie/schema-org) 
package used for generating JSON-LD data structures. SEOMate exposes the `craft.schema`
template variable, that directly ties into the fluent [Schema API](https://github.com/spatie/schema-org/blob/master/src/Schema.php).

_This method uses the exact same approach and signature as [Rias' Schema plugin](https://github.com/Rias500/craft-schema).
If you're only looking for a way to output JSON-LD, we suggest you use that plugin instead_.  

### Things that SEOMate doesn't do...
So much! 

---

## Adding meta data

Out of the box, SEOMate doesn't add anything to your markup. To get started, add
`{% hook 'seomateMeta' %}` to the `<head>` of your layout. Then, if you haven't already,
create a config file named `seomate.php` in your `config` folder alongside your other 
Craft config files. This file can use [multi-environment configs](https://docs.craftcms.com/v3/config/environments.html#config-files), 
exactly the same as any other config file in Craft, but in the following examples we'll
skip that part to keep things a bit more tidy. 

All the config settings are documented in the [`Configuring`](#configuring) section, 
and there're quite a few. But to get you going, start by adding a field
profile in `fieldProfiles` and set that profile as the default with `defaultProfile`:  

```php
<?php

return [
    'defaultProfile' => 'standard',
    
    'fieldProfiles' => [
        'standard' => [
            'title' => ['seoTitle', 'heading', 'title'],
            'description' => ['seoDescription', 'summary'],
            'image' => ['seoImage', 'mainImage']
        ]
    ],
];
```

This tells SEOMate to use the field profile `standard` to get element meta data from
by default. So, everytime a template that has an element (ie. `entry` or `category`) is loaded,
SEOMate will start by checking if there is a field named `seoTitle` that has a value that it
can use for the title meta tag. If there a field named `seoTitle` does not exist, or there is one and
it's empty, SEOMate continues to check if there is a field named `heading`, and does the same thing.
If `heading` is empty, it checks for `title`. And so on, for every key in the field profile.

Now, let's say we have a section with handle `news` that has a slightly different field setup than
our other sections, so we want to pull data from some other fields. We'll add another field profile to `fieldProfiles`, 
and make a mapping between the profile and the section handle in `profileMap`:

```php
<?php

return [
    'defaultProfile' => 'standard',
    
    'fieldProfiles' => [
        'standard' => [
            'title' => ['seoTitle', 'heading', 'title'],
            'description' => ['seoDescription', 'summary'],
            'image' => ['seoImage', 'mainImage']
        ],
        'newsprofile' => [
            'title' => ['seoTitle', 'heading', 'title'],
            'og:title' => ['ogTitle', 'heading', 'title'],
            'description' => ['seoDescription', 'newsExcerpt', 'introText'],
            'image' => ['seoImage', 'heroImage', 'newsBlocks.image:image']
            'og:image' => ['ogImage', 'heroImage', 'newsBlocks.image:image']
            'twitter:image' => ['twitterImage', 'heroImage', 'newsBlocks.image:image']
        ]
    ],
    
    'profileMap' => [
        'news' => 'newsprofile',
    ],    
];
```

The mapping between the section and the profile is simple enough, the key in `profileMap` can
be a section handle or a category group handle, and the value is the key for the profile in 
`fieldProfiles`.

In this profile we also we have also used a couple of other SEOMate features. 

First, notice that we have chosen to specify a field profile for `og:title`, `og:image` 
and `twitter:image` that we didn't have in the default profile. By default, the `autofillMap` 
defines that if no value are set for `og:title` and `twitter:title`, we want to autofill those 
meta tags with the value  from `title`. So in the `standard` profile, those values will be 
autofilled, while in the `newsprofile` we choose to customize some of them.

Secondly, we can specify to pull a value from a Matrix sub field by using the syntax 
`matrixFieldHandle.blockTypeHandle:subFieldHandle`.

This is all fine for templates that have an element associated with them. But what about the ones
that don't? Or, what if there is no valid image in any of those image fields in the profile? That's
where `defaultMeta` comes into play. Let's say that we have a global with handle `globalSeo`, with 
fields that we want to fall back on if everything else fails:  

```php
<?php

return [
    'defaultMeta' => [
        'title' => ['globalSeo.seoTitle'],
        'description' => ['globalSeo.seoDescription'],
        'image' => ['globalSeo.seoImages']
    ],
        
    'defaultProfile' => 'standard',
        
    'fieldProfiles' => [
        'standard' => [
            'title' => ['seoTitle', 'heading', 'title'],
            'description' => ['seoDescription', 'summary'],
            'image' => ['seoImage', 'mainImage']
        ],
        'newsprofile' => [
            'title' => ['seoTitle', 'heading', 'title'],
            'og:title' => ['ogTitle', 'heading', 'title'],
            'description' => ['seoDescription', 'newsExcerpt', 'introText'],
            'image' => ['seoImage', 'heroImage', 'newsBlocks.image:image']
            'og:image' => ['ogImage', 'heroImage', 'newsBlocks.image:image']
            'twitter:image' => ['twitterImage', 'heroImage', 'newsBlocks.image:image']
        ]
    ],
    
    'profileMap' => [
        'news' => 'newsprofile',
    ],    
];
```
 
The `defaultMeta` setting works almost exactly the same as `fieldProfiles`, except that it
looks for objects and fields in you current Twig `context`, hence the use of globals.

Lastly, we want to add some additional meta data like `og:type` and `twitter:card`, and for 
that we have... `additionalMeta`:
   
```php
<?php

return [
    'defaultMeta' => [
        'title' => ['globalSeo.seoTitle'],
        'description' => ['globalSeo.seoDescription'],
        'image' => ['globalSeo.seoImages']
    ],
        
    'defaultProfile' => 'standard',
        
    'fieldProfiles' => [
        'standard' => [
            'title' => ['seoTitle', 'heading', 'title'],
            'description' => ['seoDescription', 'summary'],
            'image' => ['seoImage', 'mainImage']
        ],
        'newsprofile' => [
            'title' => ['seoTitle', 'heading', 'title'],
            'og:title' => ['ogTitle', 'heading', 'title'],
            'description' => ['seoDescription', 'newsExcerpt', 'introText'],
            'image' => ['seoImage', 'heroImage', 'newsBlocks.image:image']
            'og:image' => ['ogImage', 'heroImage', 'newsBlocks.image:image']
            'twitter:image' => ['twitterImage', 'heroImage', 'newsBlocks.image:image']
        ]
    ],
    
    'profileMap' => [
        'news' => 'newsprofile',
    ],
    
    'additionalMeta' => [
        'og:type' => 'website',
        'twitter:card' => 'summary_large_image',
        
        'fb:profile_id' => '{{ settings.facebookProfileId }}',
        'twitter:site' => '@{{ settings.twitterHandle }}',
        'twitter:author' => '@{{ settings.twitterHandle }}',
        'twitter:creator' => '@{{ settings.twitterHandle }}',
        
        'og:see_also' => function ($context) {
            $someLinks = [];
            $matrixBlocks = $context['globalSeo']?->someLinks?->all();
            
            if (!empty($matrixBlocks)) {
                foreach ($matrixBlocks as $matrixBlock) {
                    $someLinks[] = $matrixBlock->someLinkUrl ?? '';
                }
            }
            
            return $someLinks;
        },
    ],
];
```

The `additionalMeta` setting takes either a string or an array, or a function that returns
either of those, as the value for each property. Any Twig in the values are parsed, in the current 
context. 


### Customizing the meta output template

SEOMate comes with a generic template that outputs the meta data it generates. You can override this 
with your own template using the `metaTemplate` config setting.


### Overriding meta data and settings from your templates

You can override the meta data and config settings directly from your templates by creating a
`seomate` object and overriding accordingly:   

```twig
{% set seomate = {
    profile: 'specialProfile',
    element: craft.entries.section('newsListing').one(),
    canonicalUrl: someOtherUrl,
    
    config: {
        includeSitenameInTitle: false
    },
    
    meta: {
        title: 'Custom title',
        'twitter:author': '@someauthor'     
    },
} %}
```

All relevant config settings can be overridden inside the `config` key, and all meta data
inside the `meta` key. You can also tell seomate to use a specific profile with the `profile` setting. 
And to use some other element as the base element to get meta data from, or provide one if the current 
template doesn't have one, in the `element` key. And you can customize the canonicalUrl as needed. 
And... more.


---

## Enabling sitemaps

To enable sitemaps for your site, you need to set the `sitemapEnabled` config setting to `true`, 
and configure the contents of your sitemaps with `sitemapConfig`. In its simplest form, you can supply 
an array of section handles to the elements key, with the sitemap settings you want:

```php
'sitemapEnabled' => true,
'sitemapLimit' => 100,
'sitemapConfig' => [
    'elements' => [
        'news' => ['changefreq' => 'weekly', 'priority' => 1],
        'projects' => ['changefreq' => 'weekly', 'priority' => 0.5],
    ],
],
``` 

A sitemap index will be created at `sitemap.xml` at the root of your site, with links to 
sitemaps for each section, split into chunks based on `sitemapLimit`.

You can also do more complex element criterias, and manually add custom paths:

```php
'sitemapEnabled' => true,
'sitemapLimit' => 100,
'sitemapConfig' => [
    'elements' => [
        'news' => ['changefreq' => 'weekly', 'priority' => 1],
        'projects' => ['changefreq' => 'weekly', 'priority' => 0.5],
        'frontpages' => [
            'elementType' => \craft\elements\Entry::class,
            'criteria' => ['section' => ['homepage', 'newsFrontpage', 'projectsFrontpage']],
            'params' => ['changefreq' => 'daily', 'priority' => 1],
        ],
        'newscategories' => [
            'elementType' => \craft\elements\Category::class,
            'criteria' => ['group' => 'newsCategories'],
            'params' => ['changefreq' => 'weekly', 'priority' => 0.2],
        ],
        'semisecret' => [
            'elementType' => \craft\elements\Entry::class,
            'criteria' => ['section' => 'semiSecret', 'notThatSecret' => true],
            'params' => ['changefreq' => 'daily', 'priority' => 0.5],
        ],
    ],
    'custom' => [
        '/cookies' => ['changefreq' => 'weekly', 'priority' => 1],
        '/terms-and-conditions' => ['changefreq' => 'weekly', 'priority' => 1],
    ],
],
``` 

Using the expanded criteria syntax, you can add whatever elements to your sitemaps.

---

## Configuring

SEOMate can be configured by creating a file named `seomate.php` in your Craft config folder, 
and overriding settings as needed. 

### cacheEnabled [bool]
*Default: `'true'`*  
Enables/disables caching of generated meta data. The cached data will be automatically
cleared when an element is saved, but it can also be completely deleted through Craft's
clear cache tool.

### cacheDuration [int|string]
*Default: `3600`*  
Duration of meta cache in seconds.

### previewEnabled [bool|array]
*Default: `true`*  
Enable SEO previews in the Control Panel for everything (true), 
nothing (false) or an array of section and/or category group handles.  

### previewLabel [string|null]
*Default: "SEO Preview"*  
Defines the text label for the SEO Preview button and Preview Target (Craft 3.2.x only) inside the Control Panel.  

### siteName [string|array|null]
*Default: `null`*  
Defines the site name to be used in meta data. Can be a plain string, or an array
with site handles as keys. Example:

```php  
'siteName' => 'My site'

// or

'siteName' => [
    'default' => 'My site',
    'other' => 'Another site',
]
```

If not set, SEOMate will try to get any site name defined in Craft's general config 
for the current site. If that doesn't work, the current site's name will be used.   

### metaTemplate [string]
*Default: `''`*  
SEOMate comes with a default meta template the outputs the configured meta tags. But,
every project is different, so if you want to customize the output you can use this 
setting to provide a custom template (it needs to be in your site's template path). 

### includeSitenameInTitle [bool]
*Default: `true`*  
Enables/disabled if the site name should be displayed as part of the meta title.

### sitenameTitleProperties [array]
*Default: `['title']`*  
Defines which meta title properties the site name should be added to. By default, 
the site name is only added to the `title` meta tag.

Example that also adds it to `og:title` and `twitter:title` tags:

```php 
'sitenameTitleProperties' => ['title', 'og:title', 'twitter:title']
```

### sitenamePosition [string]
*Default: `'after'`*  
Defines if the site name should be placed `before` or `after` the rest of the
meta content.

### sitenameSeparator [string]
*Default: `'|'`*  
The separator between the meta tag content and the site name.

### outputAlternate [bool]
*Default: `true`*  
Enables/disables output of alternate URLs. Alternate URLs are meant to provide
search engines with alternate URLs _for localized versions of the current page's content_. 

If you have a normal multi-locale website, you'll probably want to leave this setting
enabled. If you're running a multi-site website, where the sites are distinct, you'll
probably want to disable this. 

For more information about alternate URLs, (refer to this article)[https://support.google.com/webmasters/answer/189077].   

### alternateFallbackSiteHandle [string|null]
*Default: `null`*  
Sets the site handle for the site that should be the fallback for unmatched languages, ie
the alternate URL with `hreflang="x-default"`. 

Usually, this should be the globabl site that doesn't target a specific country. Or a site 
with a holding page where the user can select language. For more information about alternate URLs,
(refer to this article)[https://support.google.com/webmasters/answer/189077].   

### altTextFieldHandle [string|null]
*Default: `null`*  
If you have a field for alternate text on your assets, you should set this 
to your field's handle. This will pull and output the text for the `og:image:alt`
and `twitter:image:alt` properties.

### defaultProfile [string|null]
*Default: `''`*  
Sets the default meta data profile to use (see the `fieldProfiles` config setting).

### fieldProfiles [array]
*Default: `[]`*  
Field profiles defines waterfalls for which fields should be used to fill which
meta tags. You can have as many or as few profiles as you want. You can define a default 
profile using the `defaultProfile` setting, and you can map your sections and category 
groups using the `profileMap` setting. You can also override which profile to use, directly 
from your templates.

Example:

```php
'defaultProfile' => 'default',

'fieldProfiles' => [
    'default' => [
        'title' => ['seoTitle', 'heading', 'title'],
        'description' => ['seoDescription', 'summary'],
        'image' => ['seoImage', 'mainImage']
    ],
    'products' => [
        'title' => ['seoTitle', 'heading', 'title'],
        'description' => ['seoDescription', 'productDescription', 'summary'],
        'image' => ['seoImage', 'mainImage', 'heroMedia:media.image']
    ],
    'landingPages' => [
        'title' => ['seoTitle', 'heading', 'title'],
        'description' => ['seoDescription'],
        'image' => ['seoImage', 'heroArea:video.image', 'heroArea:singleImage.image', 'heroArea:twoImages.images', 'heroArea:slideshow.images']
    ],
],
```  

Field waterfalls are parsed from left to right. Empty or missing fields are ignored, 
and SEOMate continues to look for a valid value in the next field.

### profileMap [array]
*Default: `[]`*  
The profile map provides a way to map sections and category groups to profiles
defined in `fieldProfiles`. If a section or category group is not found in this
map, the profile defined in `defaultProfile` will be used.

```php
'profileMap' => [
    'products' => 'products',
    'frontpage' => 'landingPages',
    'campaigns' => 'landingPages',
],
```

### defaultMeta [array]
*Default: `[]`*  
This setting defines the default meta data that will be used if no valid meta data
was found for the current element (ie, non of the fields provided in the field profile
existed or had valid values). 

The waterfall uses the current _context_ to search for meta data. In the example
below, we're falling back to using fields in two globals with handle `globalSeo` 
and `settings`:

```php
'defaultMeta' => [
    'title' => ['globalSeo.seoTitle'],
    'description' => ['globalSeo.seoDescription', 'settings.companyInfo'],
    'image' => ['globalSeo.seoImages']
],
```

### additionalMeta [array]
*Default: `[]`*  

The additional meta setting defines all other meta data that you want SEOMate
to output. This is a convenient way to add more global meta data, that is used
throughout the site. Please note that you don't have to use this, you could also
just add the meta data directly to your meta, or html head, template.

The key defines the meta data property to output, and the value could be either
a plain text, some twig that will be parsed based on the current context, an array
which will result in multiple tags of this property being output, or a function.

In the example below, some properties are plain text (`og:type` and `twitter:card`),
some contains twig (for instance `fb:profile_id`), and for `og:see_also` we provide
a function that returns an array. 

```php
'additionalMeta' => [
    'og:type' => 'website',
    'twitter:card' => 'summary_large_image',
    
    'fb:profile_id' => '{{ settings.facebookProfileId }}',
    'twitter:site' => '@{{ settings.twitterHandle }}',
    'twitter:author' => '@{{ settings.twitterHandle }}',
    'twitter:creator' => '@{{ settings.twitterHandle }}',
    
    'og:see_also' => function ($context) {
        $someLinks = [];
        $matrixBlocks = $context['globalSeo']->someLinks->all() ?? null;
        
        if ($matrixBlocks && count($matrixBlocks) > 0) {
            foreach ($matrixBlocks as $matrixBlock) {
                $someLinks[] = $matrixBlock->someLinkUrl ?? '';
            }
        }
        
        return $someLinks;
    },
],
```

### metaPropertyTypes [array]
*Default: (see below)*  
This setting defines the type and limitations of the different meta tags. Currently,
there are two valid types, `text` and `image`. 

Example/default value:
```php
[
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
]
```

### applyRestrictions [bool]
*Default: `false`*  
Enables/disables enforcing of restrictions defined in `metaPropertyTypes`.

### validImageExtensions [array]
*Default: `['jpg', 'jpeg', 'gif', 'png']`*  
Valid filename extensions for image property types. 

### truncateSuffix [string]
*Default: `'…'`*  
Suffix to add to truncated meta values.

### returnImageAsset [bool]
*Default: `false`*  
By default, assets will be transformed by SEOMate, and the resulting URL is
cached and passed to the template. 

By enabling this setting, the asset itself will instead be returned to the 
template. This can be useful if you want to perform more complex transforms,
or output more meta tags where you need more asset data, that can only be done
at the template level. Please note that you'll probably want to provide a custom 
`metaTemplate`, and that caching will not work (you should instead use your own 
template caching).  

### useImagerIfInstalled [bool]
*Default: `true`*  
If [Imager](https://github.com/aelvan/Imager-Craft) is installed, SEOMate will 
automatically use it for transforms (they're mates!), but you can disable this 
setting to use native Craft transforms instead. 

### imageTransformMap [array]
*Default: (see below)*  
Defines the image transforms that are to be used for the different meta image
properties. All possible options of Imager or native Craft transforms can be used. 

Default value:
```php
[
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
]
```

Example where the Facebook and Twitter images has been sharpened, desaturated
and given a stylish blue tint (requires Imager):

```php 
'imageTransformMap' => [
    'image' => [
        'width' => 1200,
        'height' => 675,
        'format' => 'jpg'
    ],
    'og:image' => [
        'width' => 1200,
        'height' => 630,
        'format' => 'jpg',
        'effects' => [
            'sharpen' => true,
            'modulate' => [100, 0, 100], 
            'colorBlend' => ['rgb(0, 0, 255)', 0.5]
        ]
    ],
    'twitter:image' => [
        'width' => 1200,
        'height' => 600,
        'format' => 'jpg',
        'effects' => [
            'sharpen' => true,
            'modulate' => [100, 0, 100], 
            'colorBlend' => ['rgb(0, 0, 255)', 0.5]
        ]
    ],
],
```

### autofillMap [array]
*Default: (see below)*  
Map of properties that should be automatically filled by another property,
_if they're empty after the profile has been parsed_. 

Default value:
```php
[
    'og:title' => 'title',
    'og:description' => 'description',
    'og:image' => 'image',
    'twitter:title' => 'title',
    'twitter:description' => 'description',
    'twitter:image' => 'image',
]
```

### tagTemplateMap [array]
*Default: (see below)*  
Map of output templates for the meta properties. 

Example/default value:
```php
[
    'default' => '<meta name="{{ key }}" content="{{ value }}">',
    'title' => '<title>{{ value }}</title>',
    '/^og:/,/^fb:/' => '<meta property="{{ key }}" content="{{ value }}">',
]
```

### sitemapEnabled [bool]
*Default: `false`*  
Enables/disables sitemaps.

### sitemapName [string]
*Default: `'sitemap'`*  
Name of sitemap. By default it will be called `sitemap.xml`.

### sitemapLimit [int]
*Default: `500`*  
Number of URLs per sitemap. SEOMate will automatically make a sitemap index
and split up your sitemap into chunks with a maximum number of URLs as per
this setting. A lower number could ease the load on your server when the 
sitemap is being generated.

### sitemapConfig [array]
*Default: `[]`*  
Defines the content of the sitemaps. The configuration consists of two main 
keys, `elements` and `custom`. In `elements`, you can define sitemaps that 
will automatically query for elements in certain sections or based on custom 
criterias. 

In `custom` you add paths that are added to a separate custom sitemap, and you 
may also add links to manually generated sitemaps in `additionalSitemaps`. Both 
of these settings can be a flat array of custom urls or sitemap paths that you 
want to add, or a nested array where the keys are site handles, to specify 
custom urls/sitemaps that are site specific, or `'*'`, for additional ones. 
See the example below. 

In the example below, we get all elements from the sections with handles 
`projects` and `news`, query for entries in four specific 
sections and all categories in group `newsCategories`. In addition to these, 
we add two custom urls, and two additional sitemaps.

```php
'sitemapConfig' => [
    'elements' => [
        'projects' => ['changefreq' => 'weekly', 'priority' => 0.5],
        'news' => ['changefreq' => 'weekly', 'priority' => 0.5],
        
        'indexpages' => [
            'elementType' => \craft\elements\Entry::class,
            'criteria' => ['section' => ['frontpage', 'newsListPage', 'membersListPage', 'aboutPage']],
            'params' => ['changefreq' => 'daily', 'priority' => 0.5],
        ],
        'newscategories' => [
            'elementType' => \craft\elements\Category::class,
            'criteria' => ['group' => 'newsCategories'],
            'params' => ['changefreq' => 'weekly', 'priority' => 0.2],
        ],
    ],
    'custom' => [
        '/custom-1' => ['changefreq' => 'weekly', 'priority' => 1],
        '/custom-2' => ['changefreq' => 'weekly', 'priority' => 1],
    ],
    'additionalSitemaps' => [
        '/sitemap-from-other-plugin.xml',
        '/manually-generated-sitemap.xml'
    ]
],
```

Example with site specific custom urls and additional sitemaps:

```php
'sitemapConfig' => [
    /* ... */ 
    
    'custom' => [
        '*' => [
            '/custom-global-1' => ['changefreq' => 'weekly', 'priority' => 1],
            '/custom-global-2' => ['changefreq' => 'weekly', 'priority' => 1],
        ],
        'english' => [
            '/custom-english' => ['changefreq' => 'weekly', 'priority' => 1],
        ],
        'norwegian' => [
            '/custom-norwegian' => ['changefreq' => 'weekly', 'priority' => 1],
        ]
    ],
    'additionalSitemaps' => [
        '*' => [
            '/sitemap-from-other-plugin.xml',
            '/sitemap-from-another-plugin.xml',
        ],
        'english' => [
            '/manually-generated-english-sitemap.xml',
        ],
        'norwegian' => [
            '/manually-generated-norwegian-sitemap.xml',
        ]
    ]
],
``` 

**Using the expanded criteria syntax, you can query for whichever type of element, 
as long as they are registered as a valid element type in Craft.**

The main sitemap index will be available on the root of your site, and named
according to the `sitemapName` config setting (`sitemap.xml` by default). The actual
sitemaps will be named using the pattern `sitemap_<elementKey>_<page>.xml` for 
elements and `sitemap_custom.xml` for the custom urls.

### sitemapSubmitUrlPatterns [array]
*Default: (see below)*
URL patterns that your sitemaps are submitted to. 

Example/default value:
```php
'sitemapSubmitUrlPatterns' => [
    'http://www.google.com/webmasters/sitemaps/ping?sitemap=',
    'http://www.bing.com/webmaster/ping.aspx?siteMap=',
];
```


---

## Template variables

### craft.seomate.getMeta([config=[]])
Returns an object with the same meta data that is passed to the meta data 
template. 

```twig
{% set metaData = craft.seomate.getMeta() %}
Meta Title: {{ metaData.meta.title }} 
Canonical URL: {{ metaData.canonicalUrl }} 
```

You can optionally pass in a config object the same way you would in your template 
overrides, to customize the data, or use a custom element as the source:

```twig
{% set metaData = craft.seomate.getMeta({
    profile: 'specialProfile',
    element: craft.entries.section('newsListing').one(),
    canonicalUrl: someOtherUrl,
    
    config: {
        includeSitenameInTitle: false
    },
    
    meta: {
        title: 'Custom title',
        'twitter:author': '@someauthor'     
    },
}) %}
```

### craft.schema
You can access all the different schemas in the [`spatie/schema-org`](https://github.com/spatie/schema-org) 
package through this variable endpoint. If you're using PHPStorm and the Symfony plugin, 
you can get full autocompletion by assigning type hinting (see example below)

Example:

```twig   
{# @var schema \Spatie\SchemaOrg\Schema #}
{% set schema = craft.schema %}

{{ schema.recipe
    .dateCreated(entry.dateCreated)
    .dateModified(entry.dateUpdated)
    .datePublished(entry.postDate)
    .copyrightYear(entry.postDate | date('Y'))
    .name(entry.title)
    .headline(entry.title)
    .description(entry.summary | striptags)
    .url(entry.url)
    .mainEntityOfPage(entry.url)
    .inLanguage('nb_no')
    .author(schema.organization
        .name('The Happy Chef')
        .url('https://www.thehappychef.xyz/')
    )
    .recipeCategory(categories)
    .recipeCuisine(entry.cuisine)
    .keywords(ingredientCategories | merge(categories) | join(', '))
    .recipeIngredient(ingredients)
    .recipeInstructions(steps)
    .recipeYield(entry.portions ~ ' porsjoner')
    .cookTime('PT'~entry.cookTime~'M')
    .prepTime('PT'~entry.prepTime~'M')
    .image(schema.imageObject
        .url(image.url)
        .width(schema.QuantitativeValue.value(image.getWidth()))
        .height(schema.QuantitativeValue.value(image.getHeight()))
    )
| raw }}
```

_Again, if you're only looking for a way to output JSON-LD, we suggest you 
use [Rias' Schema plugin](https://github.com/Rias500/craft-schema) instead_.

### craft.seomate.renderMetaTag(key, value)
Renders a meta tag based on `key` and `value`. Uses the `tagTemplateMap` config
setting to determine how the markup should look. 

Does exactly the same thing as the `renderMetaTag` twig function.

### craft.seomate.breadcrumbSchema(breadcrumb)
A convenient method for outputting a JSON-LD breadcrumb. The method takes an
array of objects with properties for `url` and `name`, and outputs a valid 
Schema.org JSON-LD data structure.

Example:
```twig
{% set breadcrumb = [
    {
        'url': siteUrl,
        'name': 'Frontpage'
    },
    {
        'url': currentCategory.url,
        'name': currentCategory.title
    },
    {
        'url': entry.url,
        'name': entry.title
    }
] %}

{{ craft.seomate.breadcrumbSchema(breadcrumb) }}
```

---

## Twig functions

### renderMetaTag(key, value)
Renders a meta tag based on `key` and `value`. Uses the `tagTemplateMap` config
setting to determine how the markup should look.

Does exactly the same thing as the `craft.seomate.renderMetaTag` template variable.


---

## Price, license and support

The plugin is released under the MIT license, meaning you can do what ever you want with it as long 
as you don't blame us. **It's free**, which means there is absolutely no support included, but you 
might get it anyway. Just post an issue here on github if you have one, and we'll see what we can do. 

## Changelog

See [CHANGELOG.MD](https://raw.githubusercontent.com/vaersaagod/seomate/master/CHANGELOG.md).

## Credits

Brought to you by [Værsågod](https://www.vaersaagod.no)

Icon designed by [Freepik from Flaticon](https://www.flaticon.com/authors/freepik).
