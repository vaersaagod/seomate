# SEOMate Changelog

## 3.0.0-beta.5 - 2024-04-06  
### Fixed
- Fixed a PHP exception that would occur when using the `{% seomateMeta %}` hook in nested entry templates ([#85](https://github.com/vaersaagod/seomate/issues/85))

### Added
- Added the ability to create field profiles specific to a particular section, entry type, category group or Commerce product type ([#86](https://github.com/vaersaagod/seomate/pull/86))  
- Added the ability to be specific in the `previewEnabled` setting about which sections, entry types, category groups and/or Commerce product types should be SEO-previewable
- Added support for custom meta templates and template overrides in SEO Preview to categories, nested entries and Commerce products, in addition to regular ol' section entries

### Changed
- Removed support for "SEO Preview" for elements using legacy live preview  

## 3.0.0-beta.4 - 2024-04-04

### Fixed
- Fixed some additional cases where SEOMate could attempt to use string values as callables  

## 3.0.0-beta.3 - 2024-04-03

### Fixed  
- Fixed a bug where SEOMate could attempt to use string values as callables ([#84](https://github.com/vaersaagod/seomate/issues/84))

### Changed
- Bumped the `craftcms/cms` requirement to `^5.0.0`

## 3.0.0-beta.2 - 2024-02-21  

### Added  
- Added support for object templates and closures in default meta and field profile definitions.

### Fixed
- Fixed various formatting and type issues, and minor bugs.

## 3.0.0-beta.1 - 2024-02-20  

### Added  
- Added support for Craft 5
