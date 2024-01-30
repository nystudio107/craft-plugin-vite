# Plugin Vite Changelog

## 4.0.9 - 2024.01.30
### Added
* If the `devServer` is running, the `ViteService::fetch()` method will try to use the `devServerInternal` URL first, falling back on the `devServerPublic` so that `craft.vite.inline()` can pull from the `devServer` if it is running ([#22](https://github.com/nystudio107/craft-plugin-vite/issues/22))
* Add `phpstan` and `ecs` code linting
* Add `code-analysis.yaml` GitHub action

### Changed
* PHPstan code cleanup
* ECS code cleanup

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
