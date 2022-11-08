<?php
/**
 * Vite plugin for Craft CMS 3.x
 *
 * Allows the use of the Vite.js next generation frontend tooling with Craft CMS
 *
 * @link      https://nystudio107.com
 * @copyright Copyright (c) 2021 nystudio107
 */

namespace nystudio107\pluginvite\services;

use Craft;
use craft\helpers\App;
use nystudio107\pluginvite\helpers\FileHelper;

/**
 * @author    nystudio107
 * @package   Vite
 * @since     1.0.0
 */
class VitePluginService extends ViteService
{
    // Constants
    // =========================================================================

    protected const MANIFEST_FILE_NAME = 'manifest.json';

    // Public Properties
    // =========================================================================

    /**
     * @var string AssetBundle class name to get the published URLs from
     */
    public string $assetClass = '';

    /**
     * @var string The environment variable to look for in order to enable the devServer; the value doesn't matter,
     *              it just needs to exist
     */
    public string $pluginDevServerEnvVar = 'VITE_PLUGIN_DEVSERVER';

    /**
     * @inheritDoc
     */
    public function init(): void
    {
        // See if the $pluginDevServerEnvVar env var exists, and if not, don't run off of the dev server
        $useDevServer = (bool)App::env($this->pluginDevServerEnvVar);
        if ($useDevServer === false) {
            $this->useDevServer = false;
        }
        parent::init();
        // If we're in a plugin, make sure the caches are unique
        if ($this->assetClass) {
            $this->cacheKeySuffix = $this->assetClass;
        }
        if ($this->devServerRunning()) {
            $this->invalidateCaches();
        }
        // If we have no asset bundle class, or the dev server is running, don't swap in our `/cpresources/` paths
        if (!$this->assetClass || $this->devServerRunning()) {
            return;
        }
        // Map the $manifestPath and $serverPublic to the hashed `/cpresources/` path & URL for our AssetBundle
        $bundle = new $this->assetClass();
        $baseAssetsUrl = Craft::$app->assetManager->getPublishedUrl(
            $bundle->sourcePath,
            true
        );
        $this->manifestPath = FileHelper::createUrl($bundle->sourcePath, self::MANIFEST_FILE_NAME);
        if ($baseAssetsUrl !== false) {
            $this->serverPublic = $baseAssetsUrl;
        }
    }
}
