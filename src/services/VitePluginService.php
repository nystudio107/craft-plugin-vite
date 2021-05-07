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
use craft\web\AssetBundle;

/**
 * @author    nystudio107
 * @package   Vite
 * @since     1.0.0
 */
class VitePluginService extends ViteService
{
    // Public Properties
    // =========================================================================

    /**
     * @var AssetBundle bundle class name to get the published URLs from
     */
    public $assetClass;

    /**
     * @var string The environment variable to look for in order to enable the devServer; the value doesn't matter,
     *              it just needs to exist
     */
    public $pluginDevServerEnvVar = 'VITE_PLUGIN_DEVSERVER';

    /**
     * @inheritDoc
     */
    public function init()
    {
        parent::init();
        $request = Craft::$app->getRequest();
        if (!$request->getIsConsoleRequest()) {
            $this->invalidateCaches();
            // See if the $pluginDevServerEnvVar env var exists, and if not, don't run off of the dev server
            $useDevServer = getenv($this->pluginDevServerEnvVar);
            if ($useDevServer === false) {
                $this->useDevServer = false;
            }
            // If we're in a plugin, make sure the caches are unique
            if ($this->assetClass) {
                $this->cacheKeySuffix = $this->assetClass;
            }
            // If we have an asset bundle, and the dev server isn't running, then swap in our published asset bundle paths
            if ($this->assetClass && !$this->useDevServer) {
                $bundle = new $this->assetClass;
                $baseAssetsUrl = Craft::$app->assetManager->getPublishedUrl(
                    $bundle->sourcePath,
                    true
                );
                $this->manifestPath = Craft::getAlias($bundle->sourcePath) . '/manifest.json';
                $this->serverPublic = $baseAssetsUrl;
            }
        }
    }
}
