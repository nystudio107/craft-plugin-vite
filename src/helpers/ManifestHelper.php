<?php
/**
 * Vite plugin for Craft CMS 3.x
 *
 * Allows the use of the Vite.js next generation frontend tooling with Craft CMS
 *
 * @link      https://nystudio107.com
 * @copyright Copyright (c) 2021 nystudio107
 */

namespace nystudio107\pluginvite\helpers;

use Craft;
use craft\helpers\Json as JsonHelper;


/**
 * @author    nystudio107
 * @package   Vite
 * @since     1.0.5
 */
class ManifestHelper
{
    // Constants
    // =========================================================================

    const LEGACY_EXTENSION = '-legacy.';

    // Protected Static Properties
    // =========================================================================

    /**
     * @var array|null
     */
    protected static $manifest;

    /**
     * @var array|null
     */
    protected static $assetFiles;

    // Public Static Methods
    // =========================================================================

    /**
     * Fetch and memoize the manifest file
     *
     * @param string $manifestPath
     */
    public static function fetchManifest(string $manifestPath)
    {
        // Grab the manifest
        $pathOrUrl = (string)Craft::parseEnv($manifestPath);
        $manifest = FileHelper::fetch($pathOrUrl, [JsonHelper::class, 'decodeIfJson']);
        // If no manifest file is found, log it
        if ($manifest === null) {
            Craft::error('Manifest not found at ' . $manifestPath, __METHOD__);
        }
        // Ensure we're dealing with an array
        self::$manifest = (array)$manifest;
    }

    /**
     * Return an array of tags from the manifest, for both modern and legacy builds
     *
     * @param string $path
     * @param bool $asyncCss
     * @param array $scriptTagAttrs
     * @param array $cssTagAttrs
     *
     * @return array
     */
    public static function manifestTags(string $path, bool $asyncCss = true, array $scriptTagAttrs = [], array $cssTagAttrs = []): array
    {
        // Get the modern tags for this $path
        return self::extractManifestTags($path, $asyncCss, $scriptTagAttrs, $cssTagAttrs);
    }

    /**
     * Return an array of data describing the  script, module link, and CSS link tags for the
     * script from the manifest.json file
     *
     * @param string $path
     * @param bool $asyncCss
     * @param array $scriptTagAttrs
     * @param array $cssTagAttrs
     * @param bool $legacy
     *
     * @return array
     */
    public static function extractManifestTags(string $path, bool $asyncCss = true, array $scriptTagAttrs = [], array $cssTagAttrs = [], bool $legacy = false): array
    {
        if (self::$manifest === null) {
            return [];
        }
        $tags = [];
        // Set the async CSS args
        $asyncCssOptions = [];
        if ($asyncCss) {
            $asyncCssOptions = [
                'media' => 'print',
                'onload' => "this.media='all'",
            ];
        }
        // Set the script args
        $scriptOptions = [
            'type' => 'module',
            'crossorigin' => true,
        ];
        if ($legacy) {
            $scriptOptions = [
                'nomodule' => true,
            ];
        }
        // Iterate through the manifest
        foreach (self::$manifest as $manifestKey => $entry) {
            // If it's not an entry, skip it
            if (!isset($entry['isEntry']) || !$entry['isEntry']) {
                continue;
            }
            // If there's no file, skip it
            if (!isset($entry['file'])) {
                continue;
            }
            // If the $path isn't in the $manifestKey, and vice versus, skip it
            if (strpos($manifestKey, $path) === false && strpos($path, $manifestKey) === false) {
                continue;
            }
            // Handle optional `integrity` tags
            $integrityAttributes = [];
            if (isset($entry['integrity'])) {
                $integrityAttributes = [
                    'integrity' => $entry['integrity'],
                ];
            }
            // Add an onload event so listeners can know when the event has fired
            $tagOptions = array_merge(
                $scriptOptions,
                [
                    'onload' => "e=new CustomEvent('vite-script-loaded', {detail:{path: '$manifestKey'}});document.dispatchEvent(e);"
                ],
                $integrityAttributes,
                $scriptTagAttrs
            );
            // Include the entry script
            $tags[$manifestKey] = [
                'type' => 'file',
                'url' => $entry['file'],
                'options' => $tagOptions
            ];
            // Include any imports
            $importFiles = [];
            // Only include import tags for the non-legacy scripts
            if (!$legacy) {
                self::extractImportFiles(self::$manifest, $manifestKey, $importFiles);
                foreach ($importFiles as $importKey => $importFile) {
                    $tags[$importFile] = [
                        'crossorigin' => $tagOptions['crossorigin'] ?? true,
                        'type' => 'import',
                        'url' => $importFile,
                        'integrity' => self::$manifest[$importKey]['integrity'] ?? '',
                    ];
                }
            }
            // Include any CSS tags
            $cssFiles = [];
            self::extractCssFiles(self::$manifest, $manifestKey, $cssFiles);
            foreach ($cssFiles as $cssFile) {
                $tags[$cssFile] = [
                    'type' => 'css',
                    'url' => $cssFile,
                    'options' => array_merge([
                        'rel' => 'stylesheet',
                    ], $asyncCssOptions, $cssTagAttrs)
                ];
            }
        }

        return $tags;
    }

    /**
     * Extract any import files from entries recursively
     *
     * @param array $manifest
     * @param string $manifestKey
     * @param array $importFiles
     *
     * @return array
     */
    protected static function extractImportFiles(array $manifest, string $manifestKey, array &$importFiles): array
    {
        $entry = $manifest[$manifestKey] ?? null;
        if (!$entry) {
            return [];
        }

        $imports = $entry['imports'] ?? [];
        foreach ($imports as $import) {
            $importFiles[$import] = $manifest[$import]['file'];
            self::extractImportFiles($manifest, $import, $importFiles);
        }

        return $importFiles;
    }

    /**
     * Extract any CSS files from entries recursively
     *
     * @param array $manifest
     * @param string $manifestKey
     * @param array $cssFiles
     *
     * @return array
     */
    protected static function extractCssFiles(array $manifest, string $manifestKey, array &$cssFiles): array
    {
        $entry = $manifest[$manifestKey] ?? null;
        if (!$entry) {
            return [];
        }
        $cssFiles = array_merge($cssFiles, $entry['css'] ?? []);
        $imports = $entry['imports'] ?? [];
        foreach ($imports as $import) {
            self::extractCssFiles($manifest, $import, $cssFiles);
        }

        return $cssFiles;
    }

    /**
     * Return an array of tags from the manifest, for both modern and legacy builds
     *
     * @param string $path
     * @param bool $asyncCss
     * @param array $scriptTagAttrs
     * @param array $cssTagAttrs
     *
     * @return array
     */
    public static function legacyManifestTags(string $path, bool $asyncCss = true, array $scriptTagAttrs = [], array $cssTagAttrs = []): array
    {
        // Get the legacy tags for this $path
        $parts = pathinfo($path);
        $legacyPath = $parts['dirname']
            . '/'
            . $parts['filename']
            . self::LEGACY_EXTENSION
            . $parts['extension'];

        return self::extractManifestTags($legacyPath, $asyncCss, $scriptTagAttrs, $cssTagAttrs, true);
    }

    // Protected Static Methods
    // =========================================================================

    /**
     * Extract an entry file URL from all of the entries in the manifest
     *
     * @return string
     */
    public static function extractEntry(string $path): string
    {
        foreach (self::$manifest as $entryKey => $entry) {
            if (strpos($entryKey, $path) !== false) {
                return $entry['file'] ?? '';
            }
            // Check CSS
            $styles = $entry['css'] ?? [];
            foreach ($styles as $style) {
                $styleKey = self::filenameWithoutHash($style);
                if (strpos($styleKey, $path) !== false) {
                    return $style;
                }
            }
            // Check assets
            $assets = $entry['assets'] ?? [];
            foreach ($assets as $asset) {
                $assetKey = self::filenameWithoutHash($asset);
                if (strpos($assetKey, $path) !== false) {
                    return $asset;
                }
            }
        }

        return '';
    }

    /**
     * Return a file name from the passed in $path, with any version hash removed from it
     *
     * @param string $path
     * @return string
     */
    protected static function filenameWithoutHash(string $path): string
    {
        // Get just the file name
        $filenameParts = explode('/', $path);
        $filename = end($filenameParts);
        // If there is a version hash, remove it
        $filenameParts = explode('.', $filename);
        $dotSegments = count($filenameParts);
        if ($dotSegments > 2) {
            unset($filenameParts[$dotSegments - 2]);
            $filename = implode('.', $filenameParts);
        }

        return (string)$filename;
    }

    /**
     * Extract any asset files from all of the entries in the manifest
     *
     * @return array
     */
    public static function extractAssetFiles(): array
    {
        // Used the memoized version if available
        if (self::$assetFiles !== null) {
            return self::$assetFiles;
        }
        $assetFiles = [];
        foreach (self::$manifest as $entry) {
            $assets = $entry['assets'] ?? [];
            foreach ($assets as $asset) {
                $assetKey = self::filenameWithoutHash($asset);
                $assetFiles[$assetKey] = $asset;
            }
        }
        self::$assetFiles = $assetFiles;

        return $assetFiles;
    }
}
