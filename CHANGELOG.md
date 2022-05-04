# SEOMate Changelog

## 4.0.0 - 2022-05-04
### Added
- Added Craft 4 support.  

## 1.1.13 - 2022-03-24
### Added
- Added conditionals to output template to be able to override url’s at the template level without overriding canonical url.

## 1.1.12 - 2021-12-06
### Fixed
- Fixed an issue where Categories with URLs would display a double set of Preview/View buttons on their edit pages  

## 1.1.11 - 2021-09-01

### Fixed
- Fixed an issue where it was not possible to enable SEO Preview on a per-section basis by setting the `previewEnabled` setting to an array of section handles  

## 1.1.10 - 2021-08-09

### Fixed
- Fixed an issue where the SEOMate cache would not respect paginated requests. Fixes #52.
- Fixed an issue where an exception would be thrown if meta images were eager-loaded. Fixes #19.

## 1.1.9 - 2021-03-01  

### Changed  
- Changed requirement for `spatie/schema-org` to include `^3.0` for PHP versions above 7.3.   

## 1.1.8 - 2021-02-16  

### Fixed  
- Fixes an issue where an exception would be thrown if Craft was unable to transform the meta image (for example if the file did not exist). Fixes #48  

## 1.1.7 - 2020-09-16

### Fixed
- Fixes an issue that prevented SEOMate from outputting alternate urls.

## 1.1.6 - 2020-09-15

### Fixed
- Fixes issues that would occur if SEOMate was used on a site that was disabled.

## 1.1.5 - 2020-08-21

### Added
- Added self-referential hreflang as alternate (fixes #43).
- Added support for twig in sitename config setting (fixes #21).

### Fixed
- Fixed an issue where alternate urls for disabled elements were output (fixes #41).
- Fixed an issue where SEOMate could neglect to include pagination info in canonical URLs (fixes #44).

## 1.1.4 - 2020-04-12

### Fixed
- Fixed an issue with encoding of meta attributes. Fixes #39.

## 1.1.3 - 2020-03-02

### Fixed
- Fixed a caching issue with native image transforms and `generateTransformsBeforePageLoad`. Fixes #35.

## 1.1.2 - 2020-02-17
### Added
- Added `craft.seomate.getMeta()` template variable that returns meta data based on the current page. Takes an seomate config object for additional configuration. Fixes #31.
- Added support for Imager X.

### Fixed
- Fixed an issue where seomate would throw an exception if a value in `defaultMeta` did not exist in the context. Fixes #24.

## 1.1.1 - 2019-09-21
### Fixed
- Fixes indentation and whitespace issues in default meta template. Fixes #22.
- The "SEO Preview" Preview Target is no longer available when editing entries without URLs

## 1.1.0 - 2019-08-10
### Added
- Added new `previewLabel` config setting
- Added SEO Preview as a Preview Target for Craft 3.2+. Fixes #16.

## 1.0.7 - 2019-07-04
### Added
- Added support for configuring custom sitemaps urls and additional sitemap urls on a per site basis.
- Added support for adding additional sitemaps, generated outside of SEOMate, to the main sitemap index (Thanks @jrm98). 

## 1.0.6 - 2019-07-04
### Fixed
- Fixes an issue where the "Close SEO Preview" button label could be applied to buttons inside the field panel in SEO Preview (fixes #13)

## 1.0.5 - 2019-07-03
### Added
- Added SEO Preview for Craft Commerce Products

### Changed
- Changed the behaviour of the output meta template, empty tags are no longer being rendered.

### Fixed
- Fixes an issue where elements without urls in some sites would create an error when getting alternate url (fixes #10)
- Fixes an issue where the "SEO Preview" button was missing for Entries in Craft 3.2.x (fixes #12)

## 1.0.4 - 2019-07-01
### Fixed
- Fixes an issue where SEO Preview settings could override native Live Preview settings (fixes #7). 

## 1.0.3 - 2019-06-30
### Added
- Added support for using matrix field syntax in `defaultMeta` (fixes #9). 

## 1.0.2 - 2019-05-19
### Fixed
- Fixed missing multi-byte handling of maxLength enforcement (Thanks @rungta). 
- Fixed namespace for PreviewAsset class (Thanks @Mosnar). 

## 1.0.1 - 2019-04-30
### Changed
- Changed order of when additional meta is added to make it overrideable from templates (Fixes #2). 

## 1.0.0 - 2019-04-26
### Added
- Initial public release
