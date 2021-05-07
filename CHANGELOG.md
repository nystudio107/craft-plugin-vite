# Plugin Vite Changelog

## 1.0.3 - UNRELEASED
### Changed
* Refactored `extractCssFiles()` to be simpler

## 1.0.2 - 2021-05-07
### Changed
* Crawl the `manifest.json` dependency graph recursively to look for CSS files

### Fixed
* Don't call any AssetManager methods in the component `init()` method during console requests

## 1.0.1 - 2021-05-06
### Changed
* Removed entirely the `devServerInternal` setting, which isn't necessary (we just depend on you setting the `useDevServer` flag correctly instead), and added setup complexity

## 1.0.0 - 2021.05.03
### Added
- Initial release
