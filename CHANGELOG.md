# SEOMate Changelog

## Unreleased - 2024-08-23
### Changed
- SEOMate now strips preview and token params from canonical and alternate URLs
- SEOMate now uses elements' canonical ID when querying for alternates
### Added
- Added `seomate.home` (the current site's URL, stripped of preview and token params)

## 3.0.0 - 2024-08-02
### Added
- Added support for [object templates](https://craftcms.com/docs/5.x/system/object-templates.html) and PHP closures in default meta and field profile definitions. 
- Added the ability to create field profiles specific to a particular section, entry type, category group or Commerce product type ([#86](https://github.com/vaersaagod/seomate/pull/86))
- Added the ability to be specific in the `previewEnabled` setting about which sections, entry types, category groups and/or Commerce product types should be SEO-previewable
- Added support for custom meta templates and template overrides in SEO Preview to categories, nested entries and Commerce products, in addition to regular ol' section entries 
- Added support for PHP closures returning `true` or `false` for the `outputAlternate` config setting
### Changed
- Bumped the `craftcms/cms` requirement to `^5.0.0`
- Removed support for the "SEO Preview" for elements using legacy live preview
### Fixed
- Fixed several cases where SEOMate could attempt to use string values as callables
