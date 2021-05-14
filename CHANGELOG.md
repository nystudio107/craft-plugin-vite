# Plugin Vite Changelog

## 1.0.4 - 2021.05.14
### Added
* Moved the live reload through Twig errors to the ViteService so that plugins can get it too
* Added `.inline()` to allow for inlining of local or remote files in your templates, with a caching layer

### Changed
* Use `registerJsFile()` instead of `registerScript()`
* Make the cache last for 30 seconds with `devMode` on
* Refactored to `ViteVariableInterface` & `ViteVariableTrait`

## 1.0.3 - 2021.05.08
### Added
* Added the `devServerInternal` setting back in, along with `checkDevServer` for people who want the fallback behavior (https://github.com/nystudio107/craft-vite/issues/2)

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
