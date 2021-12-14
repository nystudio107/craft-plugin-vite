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
            // If the $path isn't in the $manifestKey, skip it
            if (strpos($manifestKey, $path) === false) {
                continue;
            }
            // Include the entry script
            $tagOptions = array_merge($scriptOptions, $scriptTagAttrs);
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
                foreach ($importFiles as $importFile) {
                    $tags[$importFile] = [
                        'crossorigin' => $tagOptions['crossorigin'] ?? true,
                        'type' => 'import',
                        'url' => $importFile,
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
                // Get just the file name
                $assetKeyParts = explode('/', $asset);
                $assetKey = end($assetKeyParts);
                // If there is a version hash, remove it
                $assetKeyParts = explode('.', $assetKey);
                $dotSegments = count($assetKeyParts);
                if ($dotSegments > 2) {
                    unset($assetKeyParts[$dotSegments - 2]);
                    $assetKey = implode('.', $assetKeyParts);
                }
                $assetFiles[$assetKey] = $asset;
            }
        }
        self::$assetFiles = $assetFiles;

        return $assetFiles;
    }

    // Protected Static Methods
    // =========================================================================

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
            $importFiles[] = $manifest[$import]['file'];
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
}
