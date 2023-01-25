# Plugin Vite Changelog

## 4.0.8 - 2023.01.25
### Changed
* Updated the `craft.vite.asset()` function to work with Vite 3.x or later, where assets are stored as top-level entries in the `manifest.json` ([#56](https://github.com/nystudio107/craft-vite/issues/56)) ([#31](https://github.com/nystudio107/craft-vite/issues/31))
* You can now include CSS manually if it's a top-level entry in your `vite.config.js` (rather than being imported into your JavaScript) via `craft.vite.asset("src/css/app.css")` ([#31](https://github.com/nystudio107/craft-vite/issues/31))

## 4.0.7 - 2022.11.08
### Changed
* Allow setting the `manifestPath` automatically based on the AssetBundle for all requests, not just CP requests

## 4.0.6 - 2022.08.29
### Fixed
* Move the call to `parent::init()` in `VitePluginService` down to after `useDevServer` is set based on the check for the `VITE_PLUGIN_DEVSERVER` environment variable ([#244](https://github.com/nystudio107/craft-retour/issues/244))

## 4.0.5 - 2022.08.29
### Fixed
* Ensure that `useDevServer` is properly set to `false` even if somehow the incoming request is not a CP request ([#244](https://github.com/nystudio107/craft-retour/issues/244))

## 4.0.4 - 2022.08.26
### Changed
* Use `App::env()` for reading environment variables ([#11](https://github.com/nystudio107/craft-plugin-vite/pull/11))
* Add `allow-plugins` so CI tests will work ([#12](https://github.com/nystudio107/craft-plugin-vite/pull/12))

### Fixed
* Only inject the error entry if the dev server is running (not just whether `devMode` is enabled or not) ([#244](https://github.com/nystudio107/craft-retour/issues/244))

## 4.0.3 - 2022.07.16
### Changed
* Fixed an issue where `checkDevServer` didn't work with Vite 3, because they removed the intercepting of `__vite_ping` ([#37](https://github.com/nystudio107/craft-vite/issues/37))

## 4.0.2 - 2022.06.29
### Changed
* Adds a boolean as a second param to the `craft.vite.asset(url, true)` so that assets in the vite public folder can be referenced correctly from Twig ([#9](https://github.com/nystudio107/craft-plugin-vite/pull/9))

## 4.0.1 - 2022.05.15
### Fixed
* Fixed an issue where the plugin couldn't detect the Vite dev server by testing `__vite_ping` instead of `@vite/client` to determine whether the dev server is running or not ([#33](https://github.com/nystudio107/craft-vite/issues/33)) ([#8](https://github.com/nystudio107/craft-plugin-vite/issues/8))

## 4.0.0 - 2022.05.07
### Added
* Initial release for Craft CMS 4

## 4.0.0-beta.3 - 2022.04.26
### Changed
* Don't log the full exception on a Guzzle error, just log the message

## 4.0.0-beta.2 - 2022.03.22
### Changed
* Only clear caches in `init()` if we're using the dev server
* Cache the status of the devServer for the duration of the request

## 4.0.0-beta.1 - 2022.02.07

### Added

* Initial Craft CMS 4 compatibility

## 1.0.22 - 2022.01.21

### Fixed

* Removed errant debugging code

## 1.0.21 - 2022.01.20

### Added

* Added support for [subresource integrity](https://developer.mozilla.org/en-US/docs/Web/Security/Subresource_Integrity)
  via plugins like [vite-plugin-manifest-sri](https://github.com/ElMassimo/vite-plugin-manifest-sri)

## 1.0.20 - 2022.01.17

### Added

* Added a `vite-script-loaded` event dispatched to `document` so listeners can be notified after a script
  loads ([#310](https://github.com/nystudio107/craft-imageoptimize/issues/310))

## 1.0.19 - 2021.12.16

### Fixed

* Fixed a regression caused by [#5](https://github.com/nystudio107/craft-plugin-vite/pull/5) to ensure assets load on
  production ([#6](https://github.com/nystudio107/craft-plugin-vite/issues/6))

## 1.0.18 - 2021.12.15

### Added

* Added the `.entry()` function to retrieve and entry from the `manifest.json` to a JavaScript file, CSS file, or asset

## 1.0.17 - 2021.12.14

### Fixed

* Fixed an issue where the needle/haystack logic was reversed in `strpos()` which could cause it to not match properly
  in some setups ([#5](https://github.com/nystudio107/craft-plugin-vite/pull/5))

## 1.0.16 - 2021.10.28

### Changed

* refactor(manifest): No longer search `dynamicImports` for CSS to extract, since the Vite loader takes care of that for
  us ([#2](https://github.com/nystudio107/craft-plugin-vite/pull/2))

## 1.0.15 - 2021.10.21

### Fixed

* Fixed an issue with potentially duplicated `modulepreload` links by adding tags via an associative
  array ([#16](https://github.com/nystudio107/craft-vite/issues/16))

## 1.0.14 - 2021.09.18

### Added

* Added `craft.vite.asset()` to retrive assets such as images that are imported in JavaScript or CSS

## 1.0.13 - 2021.08.25

### Changed

* Changed the `DEVMODE_CACHE_DURATION` to `1` second ([#3](https://github.com/nystudio107/craft-plugin-vite/issues/3))

## 1.0.12 - 2021.08.19

### Changed

* Only do setup in the `VitePluginServce::init()` method for CP requests

## 1.0.11 - 2021.08.10

### Added

* Added [Preload Directives Generation](https://vitejs.dev/guide/features.html#preload-directives-generation) that will
  automatically generate `<link rel="modulepreload">` directives for entry chunks and their direct
  imports ([PR#2](https://github.com/nystudio107/craft-plugin-vite/pull/2))

## 1.0.10 - 2021.07.14

### Added

* Added a `craft.vite.devServerRunning()` method to allow you to determine if the Vite dev server is running or not from
  your Twig templates ([#10](https://github.com/nystudio107/craft-vite/issues/10))

## 1.0.9 - 2021.07.14

### Changed

* Switched the `checkDevServer` test file to `@vite/client` to accommodate with the change in Vite `^2.4.0` to use
  the `.mjs` extension ([#11](https://github.com/nystudio107/craft-vite/issues/11))

## 1.0.8 - 2021.06.29

### Changed

* Roll back the automatic inclusion of `@vite/client.js` ([#9](https://github.com/nystudio107/craft-vite/issues/9))

## 1.0.7 - 2021.06.28

### Changed

* Always include the `@vite/client.js` script if the dev server is
  running ([#9](https://github.com/nystudio107/craft-vite/issues/9))

## 1.0.6 - 2021.05.21

### Added

* Added a `includeReactRefreshShim` setting that will automatically include the
  required [shim for `react-refresh`](https://vitejs.dev/guide/backend-integration.html#backend-integration) when the
  Vite dev server is running ([#5](https://github.com/nystudio107/craft-vite/issues/5))

### Changed

* Removed custom user/agent header that was a holdover from `curl`
* Re-worked how the various JavaScript shims are stored and injected

## 1.0.5 - 2021.05.20

### Changed

* Refactored the code from a monolithic `ViteService` to helpers, as appropriate

### Fixed

* Fixed an issue where it was outputting `type="nomodule"` for legacy scripts, when it should have just been `nomodule`

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

* Added the `devServerInternal` setting back in, along with `checkDevServer` for people who want the fallback
  behavior ([#2](https://github.com/nystudio107/craft-vite/issues/2))

### Changed

* Refactored `extractCssFiles()` to be simpler

## 1.0.2 - 2021-05-07

### Changed

* Crawl the `manifest.json` dependency graph recursively to look for CSS files

### Fixed

* Don't call any AssetManager methods in the component `init()` method during console requests

## 1.0.1 - 2021-05-06

### Changed

* Removed entirely the `devServerInternal` setting, which isn't necessary (we just depend on you setting
  the `useDevServer` flag correctly instead), and added setup complexity

## 1.0.0 - 2021.05.03

### Added

- Initial release
