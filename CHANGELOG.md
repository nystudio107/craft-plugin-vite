# Plugin Vite Changelog

## 5.0.2 - 2024.08.13
### Added
* Add a `craft.vite.integrity()` method that will extract the integrity hash (for building a Content Security Policy)
* Added an `includeScriptOnloadHandler` config setting that allows you to disable the adding of an `onload` handler on the `<script>` tags (useful when implementing a Content Security Policy)

### Changed
* Filter out empty attributes so they don't render on the `<script>` tags

### Fixed
* Use `strrpos` instead of `strpos` when attempting to extract a file name without the hash ([#28](https://github.com/nystudio107/craft-plugin-vite/pull/28))

## 5.0.1 - 2024.06.12
### Added
* By default, only load the Vite AssetBundle if the request is a CP request or a preview request. This can be overridden via the `useForAllRequests` VitePluginService property ([#27](https://github.com/nystudio107/craft-plugin-vite/issues/27))

### Fixed
* Normalize file system paths before fetching them with `file_get_contents()` ([#25](https://github.com/nystudio107/craft-plugin-vite/pull/25))

## 5.0.0 - 2024.04.15
### Added
* Stable release for Craft CMS 5

## 5.0.0-beta.3 - 2024.03.02
### Fixed
* Fixed an issue where `craft.vite.entry()` would fail if you were using Vite 5 or later, due to the `ManifestHelper::fileNameWithoutHash()` function not working correctly ([#24](https://github.com/nystudio107/craft-plugin-vite/issues/24))

## 5.0.0-beta.2 - 2024.01.30
### Added
* If the `devServer` is running, the `ViteService::fetch()` method will try to use the `devServerInternal` URL first, falling back on the `devServerPublic` so that `craft.vite.inline()` can pull from the `devServer` if it is running ([#22](https://github.com/nystudio107/craft-plugin-vite/issues/22))
* Add `phpstan` and `ecs` code linting
* Add `code-analysis.yaml` GitHub action

### Changed
* PHPstan code cleanup
* ECS code cleanup

## 5.0.0-beta.1 - 2024.01.21
### Added
- Initial beta release
