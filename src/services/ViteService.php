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

use nystudio107\pluginvite\helpers\FileHelper;
use nystudio107\pluginvite\helpers\ManifestHelper;

use Craft;
use craft\base\Component;
use craft\helpers\Html as HtmlHelper;
use craft\helpers\Json as JsonHelper;
use craft\web\View;

use yii\base\InvalidConfigException;
use yii\caching\TagDependency;

use Throwable;

/**
 * @author    nystudio107
 * @package   Vite
 * @since     1.0.0
 */
class ViteService extends Component
{
    // Constants
    // =========================================================================

    const VITE_CLIENT = '@vite/client.js';
    const LEGACY_POLYFILLS = 'vite/legacy-polyfills';

    // Public Properties
    // =========================================================================

    /**
     * @var bool Should the dev server be used for?
     */
    public $useDevServer;

    /**
     * @var string File system path (or URL) to the Vite-built manifest.json
     */
    public $manifestPath;

    /**
     * @var string The public URL to the dev server (what appears in `<script src="">` tags
     */
    public $devServerPublic;

    /**
     * @var string The public URL to use when not using the dev server
     */
    public $serverPublic;

    /**
     * @var string The JavaScript entry from the manifest.json to inject on Twig error pages
     *              This can be a string or an array of strings
     */
    public $errorEntry = '';

    /**
     * @var string String to be appended to the cache key
     */
    public $cacheKeySuffix = '';

    /**
     * @var string The internal URL to the dev server, when accessed from the environment in which PHP is executing
     *              This can be the same as `$devServerPublic`, but may be different in containerized or VM setups.
     *              ONLY used if $checkDevServer = true
     */
    public $devServerInternal;

    /**
     * @var bool Should we check for the presence of the dev server by pinging $devServerInternal to make sure it's running?
     */
    public $checkDevServer = false;

    // Protected Properties
    // =========================================================================

    /**
     * @var bool Whether the legacy polyfill has been included yet or not
     */
    protected $legacyPolyfillIncluded = false;

    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function init()
    {
        parent::init();
        // Do nothing for console requests
        $request = Craft::$app->getRequest();
        if ($request->getIsConsoleRequest()) {
            return;
        }
        // Our component is lazily loaded, so the View will be instantiated by now
        if (Craft::$app->getConfig()->getGeneral()->devMode) {
            Craft::$app->getView()->on(View::EVENT_END_BODY, [$this, 'injectErrorEntry']);
        }
    }

    /**
     * Return the appropriate tags to load the Vite script, either via the dev server or
     * extracting it from the manifest.json file
     *
     * @param string $path
     * @param bool $asyncCss
     * @param array $scriptTagAttrs
     * @param array $cssTagAttrs
     *
     * @return string
     */
    public function script(string $path, bool $asyncCss = true, array $scriptTagAttrs = [], array $cssTagAttrs = []): string
    {
        if ($this->devServerRunning()) {
            return $this->devServerScript($path, $scriptTagAttrs);
        }

        return $this->manifestScript($path, $asyncCss, $scriptTagAttrs, $cssTagAttrs);
    }

    /**
     * Return the script tag to load the script from the Vite dev server
     *
     * @param string $path
     * @param array $scriptTagAttrs
     *
     * @return string
     */
    public function devServerScript(string $path, array $scriptTagAttrs = []): string
    {
        $lines = [];
        // Include the entry script
        $url = FileHelper::createUrl($this->devServerPublic, $path);
        $lines[] = HtmlHelper::jsFile($url, array_merge([
            'type' => 'module',
        ], $scriptTagAttrs));

        return implode("\r\n", $lines);
    }

    /**
     * Return the script, module link, and CSS link tags for the script from the manifest.json file
     *
     * @param string $path
     * @param bool $asyncCss
     * @param array $scriptTagAttrs
     * @param array $cssTagAttrs
     *
     * @return string
     */
    public function manifestScript(string $path, bool $asyncCss = true, array $scriptTagAttrs = [], array $cssTagAttrs = []): string
    {
        $lines = [];
        ManifestHelper::fetchManifest($this->manifestPath);
        $tags = ManifestHelper::manifestTags($path, $asyncCss, $scriptTagAttrs, $cssTagAttrs);
        $legacyTags = ManifestHelper::legacyManifestTags($path, $asyncCss, $scriptTagAttrs, $cssTagAttrs);
        // Handle any legacy polyfills
        if (!empty($legacyTags) && !$this->legacyPolyfillIncluded) {
            $lines[] = HtmlHelper::script(
                FileHelper::fetchScript('safari-nomodule-fix.min.js', $this->cacheKeySuffix),
                []
            );
            $legacyPolyfillTags = ManifestHelper::extractManifestTags(self::LEGACY_POLYFILLS, $asyncCss, $scriptTagAttrs, $cssTagAttrs, true);
            $tags = array_merge($legacyPolyfillTags, $tags);
            $this->legacyPolyfillIncluded = true;
        }
        foreach(array_merge($tags, $legacyTags) as $tag) {
            if (!empty($tag)) {
                $url = FileHelper::createUrl($this->serverPublic, $tag['url']);
                switch ($tag['type']) {
                    case 'file':
                        $lines[] = HtmlHelper::jsFile($url, $tag['options']);
                        break;
                    case 'css':
                        $lines[] = HtmlHelper::cssFile($url, $tag['options']);
                        break;
                    default:
                        break;
                }
            }
        }

        return implode("\r\n", $lines);
    }

    /**
     * Register the appropriate tags to the Craft View to load the Vite script, either via the dev server or
     * extracting it from the manifest.json file
     *
     * @param string $path
     * @param bool $asyncCss
     * @param array $scriptTagAttrs
     * @param array $cssTagAttrs
     *
     * @return void
     * @throws InvalidConfigException
     */
    public function register(string $path, bool $asyncCss = true, array $scriptTagAttrs = [], array $cssTagAttrs = [])
    {
        if ($this->devServerRunning()) {
            $this->devServerRegister($path, $scriptTagAttrs);

            return;
        }

        $this->manifestRegister($path, $asyncCss, $scriptTagAttrs, $cssTagAttrs);
    }

    /**
     * Register the script tag to the Craft View to load the script from the Vite dev server
     *
     * @param string $path
     * @param array $scriptTagAttrs
     *
     * @return void
     * @throws InvalidConfigException
     */
    public function devServerRegister(string $path, array $scriptTagAttrs = [])
    {
        $view = Craft::$app->getView();
        // Include the entry script
        $url = FileHelper::createUrl($this->devServerPublic, $path);
        $view->registerJsFile(
            $url,
            array_merge(['type' => 'module'], $scriptTagAttrs),
            md5($url . JsonHelper::encode($scriptTagAttrs))
        );
    }

    /**
     * Register the script, module link, and CSS link tags to the Craft View for the script from the manifest.json file
     *
     * @param string $path
     * @param bool $asyncCss
     * @param array $scriptTagAttrs
     * @param array $cssTagAttrs
     *
     * @return void
     * @throws InvalidConfigException
     */
    public function manifestRegister(string $path, bool $asyncCss = true, array $scriptTagAttrs = [], array $cssTagAttrs = [])
    {
        $view = Craft::$app->getView();
        ManifestHelper::fetchManifest($this->manifestPath);
        $tags = ManifestHelper::manifestTags($path, $asyncCss, $scriptTagAttrs, $cssTagAttrs);
        $legacyTags = ManifestHelper::legacyManifestTags($path, $asyncCss, $scriptTagAttrs, $cssTagAttrs);
        // Handle any legacy polyfills
        if (!empty($legacyTags) && !$this->legacyPolyfillIncluded) {
            $view->registerScript(
                FileHelper::fetchScript('safari-nomodule-fix.min.js', $this->cacheKeySuffix),
                $view::POS_HEAD,
                [],
                'SAFARI_NOMODULE_FIX'
            );
            $legacyPolyfillTags = ManifestHelper::extractManifestTags(self::LEGACY_POLYFILLS, $asyncCss, $scriptTagAttrs, $cssTagAttrs, true);
            $tags = array_merge($legacyPolyfillTags, $tags);
            $this->legacyPolyfillIncluded = true;
        }
        foreach(array_merge($tags, $legacyTags) as $tag) {
            if (!empty($tag)) {
                $url = FileHelper::createUrl($this->serverPublic, $tag['url']);
                switch ($tag['type']) {
                    case 'file':
                        $view->registerJsFile(
                            $url,
                            $tag['options'],
                            md5($url . JsonHelper::encode($tag['options']))
                        );
                        break;
                    case 'css':
                        $view->registerCssFile(
                            $url,
                            $tag['options']
                        );
                        break;
                    default:
                        break;
                }
            }
        }
    }

    /**
     * Return the contents of a local file (via path) or remote file (via URL),
     * or null if the file doesn't exist or couldn't be fetched
     * Yii2 aliases and/or environment variables may be used
     *
     * @param string $pathOrUrl
     * @param callable|null $callback
     *
     * @return string|array|null
     */
    public function fetch(string $pathOrUrl, callable $callback = null)
    {
        return FileHelper::fetch($pathOrUrl, $callback, $this->cacheKeySuffix);
    }

    /**
     * Determine whether the Vite dev server is running
     *
     * @return bool
     */
    public function devServerRunning(): bool
    {
        // If the dev server is turned off via config, say it's not running
        if (!$this->useDevServer) {
            return false;
        }
        // If we're not supposed to check that the dev server is actually running, just assume it is
        if (!$this->checkDevServer) {
            return true;
        }
        // Check to see if the dev server is actually running by pinging it
        $url = FileHelper::createUrl($this->devServerInternal, self::VITE_CLIENT);

        return !($this->fetch($url) === null);
    }

    /**
     * Invalidate all of the Vite caches
     */
    public function invalidateCaches()
    {
        $cache = Craft::$app->getCache();
        TagDependency::invalidate($cache, FileHelper::CACHE_TAG . $this->cacheKeySuffix);
        Craft::info('All Vite caches cleared', __METHOD__);
    }

    // Protected Methods
    // =========================================================================

    /**
     * Inject the error entry point JavaScript for auto-reloading of Twig error
     * pages
     */
    protected function injectErrorEntry()
    {
        // If there's no error entry provided, return
        if (empty($this->errorEntry)) {
            return;
        }
        // If it's not a server error or a client error, return
        $response = Craft::$app->getResponse();
        if (!($response->isServerError || $response->isClientError)) {
            return;
        }
        // If the dev server isn't running, return
        if (!$this->devServerRunning()) {
            return;
        }
        // Inject the errorEntry script tags to enable HMR on this page
        try {
            $errorEntry = $this->errorEntry;
            if (is_string($errorEntry)) {
                $errorEntry = [$errorEntry];
            }
            foreach ($errorEntry as $entry) {
                $tag = $this->script($entry);
                if ($tag !== null) {
                    echo $tag;
                }
            }
        } catch (Throwable $e) {
            // That's okay, Vite will have already logged the error
        }
    }
}
