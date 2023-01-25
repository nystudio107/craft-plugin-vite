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
use craft\base\Component;
use craft\helpers\Html as HtmlHelper;
use craft\helpers\Json as JsonHelper;
use craft\web\View;
use nystudio107\pluginvite\helpers\FileHelper;
use nystudio107\pluginvite\helpers\ManifestHelper;
use Throwable;
use yii\base\InvalidConfigException;
use yii\caching\TagDependency;

/**
 * @author    nystudio107
 * @package   Vite
 * @since     1.0.0
 */
class ViteService extends Component
{
    // Constants
    // =========================================================================

    protected const VITE_CLIENT = '@vite/client';
    protected const VITE_DEVSERVER_PING = '__vite_ping';
    protected const LEGACY_POLYFILLS = 'vite/legacy-polyfills';

    // Public Properties
    // =========================================================================

    /**
     * @var bool Should the dev server be used for?
     */
    public bool $useDevServer = false;

    /**
     * @var string File system path (or URL) to the Vite-built manifest.json
     */
    public string $manifestPath = '';

    /**
     * @var string The public URL to the dev server (what appears in `<script src="">` tags
     */
    public string $devServerPublic = '';

    /**
     * @var string The public URL to use when not using the dev server
     */
    public string $serverPublic = '';

    /**
     * @var array|string The JavaScript entry from the manifest.json to inject on Twig error pages
     *              This can be a string or an array of strings
     */
    public array|string $errorEntry = '';

    /**
     * @var string String to be appended to the cache key
     */
    public string $cacheKeySuffix = '';

    /**
     * @var string The internal URL to the dev server, when accessed from the environment in which PHP is executing
     *              This can be the same as `$devServerPublic`, but may be different in containerized or VM setups.
     *              ONLY used if $checkDevServer = true
     */
    public string $devServerInternal = '';

    /**
     * @var bool Should we check for the presence of the dev server by pinging $devServerInternal to make sure it's running?
     */
    public bool $checkDevServer = false;

    /**
     * @var bool Whether the react-refresh-shim should be included
     */
    public bool $includeReactRefreshShim = false;

    /**
     * @var bool Whether the modulepreload-polyfill shim should be included
     */
    public bool $includeModulePreloadShim = true;

    // Protected Properties
    // =========================================================================

    /**
     * @var bool Whether the manifest shims has been included yet or not
     */
    protected bool $manifestShimsIncluded = false;

    /**
     * @var bool Whether the dev server shims has been included yet or not
     */
    protected bool $devServerShimsIncluded = false;

    /**
     * @var bool Cached status of whether the devServer is running or not
     */
    protected ?bool $devServerRunningCached = null;

    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function init(): void
    {
        parent::init();
        // Do nothing for console requests
        $request = Craft::$app->getRequest();
        if ($request->getIsConsoleRequest()) {
            return;
        }
        // Our component is lazily loaded, so the View will be instantiated by now
        if ($this->devServerRunning() && Craft::$app->getConfig()->getGeneral()->devMode) {
            Craft::$app->getView()->on(View::EVENT_END_BODY, [$this, 'injectErrorEntry']);
        }
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
    public function register(string $path, bool $asyncCss = true, array $scriptTagAttrs = [], array $cssTagAttrs = []): void
    {
        if ($this->devServerRunning()) {
            $this->devServerRegister($path, $scriptTagAttrs);

            return;
        }

        $this->manifestRegister($path, $asyncCss, $scriptTagAttrs, $cssTagAttrs);
    }

    /**
     * Determine whether the Vite dev server is running
     *
     * @return bool
     */
    public function devServerRunning(): bool
    {
        if ($this->devServerRunningCached !== null) {
            return $this->devServerRunningCached;
        }
        // If the dev server is turned off via config, say it's not running
        if (!$this->useDevServer) {
            return false;
        }
        // If we're not supposed to check that the dev server is actually running, just assume it is
        if (!$this->checkDevServer) {
            return true;
        }
        // Check to see if the dev server is actually running by pinging it
        $url = FileHelper::createUrl($this->devServerInternal, self::VITE_DEVSERVER_PING);
        $response = FileHelper::fetchResponse($url);
        $this->devServerRunningCached = false;
        // Status code of 200 or 404 means the dev server is running
        if ($response) {
            $statusCode = $response->getStatusCode();
            $this->devServerRunningCached = $statusCode === 200 || $statusCode === 404;
        }

        return $this->devServerRunningCached;
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
    public function fetch(string $pathOrUrl, callable $callback = null): string|array|null
    {
        return FileHelper::fetch($pathOrUrl, $callback, $this->cacheKeySuffix);
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
    public function devServerRegister(string $path, array $scriptTagAttrs = []): void
    {
        $view = Craft::$app->getView();
        // Include any dev server shims
        if (!$this->devServerShimsIncluded) {
            // Include the react-refresh-shim
            if ($this->includeReactRefreshShim) {
                $script = FileHelper::fetchScript('react-refresh-shim.min.js', $this->cacheKeySuffix);
                // Replace the hard-coded dev server URL with whatever they have theirs set to
                $script = str_replace(
                    'http://localhost:3000/',
                    rtrim($this->devServerPublic, '/') . '/',
                    $script
                );
                $view->registerScript(
                    $script,
                    $view::POS_HEAD,
                    [
                        'type' => 'module',
                    ],
                    'REACT_REFRESH_SHIM'
                );
            }
            $this->devServerShimsIncluded = true;
        }
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
    public function manifestRegister(string $path, bool $asyncCss = true, array $scriptTagAttrs = [], array $cssTagAttrs = []): void
    {
        $view = Craft::$app->getView();
        ManifestHelper::fetchManifest($this->manifestPath);
        $tags = ManifestHelper::manifestTags($path, $asyncCss, $scriptTagAttrs, $cssTagAttrs);
        $legacyTags = ManifestHelper::legacyManifestTags($path, $asyncCss, $scriptTagAttrs, $cssTagAttrs);
        // Include any manifest shims
        if (!$this->manifestShimsIncluded) {
            // Handle the modulepreload-polyfill shim
            if ($this->includeModulePreloadShim) {
                $view->registerScript(
                    FileHelper::fetchScript('modulepreload-polyfill.min.js', $this->cacheKeySuffix),
                    $view::POS_HEAD,
                    ['type' => 'module'],
                    'MODULEPRELOAD_POLYFILL'
                );
            }
            // Handle any legacy polyfills
            if (!empty($legacyTags)) {
                $view->registerScript(
                    FileHelper::fetchScript('safari-nomodule-fix.min.js', $this->cacheKeySuffix),
                    $view::POS_HEAD,
                    [],
                    'SAFARI_NOMODULE_FIX'
                );
                $legacyPolyfillTags = ManifestHelper::extractManifestTags(self::LEGACY_POLYFILLS, $asyncCss, $scriptTagAttrs, $cssTagAttrs, true);
                $tags = array_merge($legacyPolyfillTags, $tags);
            }
            $this->manifestShimsIncluded = true;
        }
        $this->manifestRegisterTags($tags, $legacyTags);
    }

    /**
     * Return the URL for the given entry
     *
     * @param string $path
     *
     * @return string
     */
    public function entry(string $path): string
    {
        ManifestHelper::fetchManifest($this->manifestPath);
        $entry = ManifestHelper::extractEntry($path);

        return FileHelper::createUrl($this->serverPublic, $entry);
    }

    /**
     * Return the URL for the given asset
     *
     * @param string $path
     *
     * @return string
     */
    public function asset(string $path, bool $public = false): string
    {
        if ($this->devServerRunning()) {
            return $this->devServerAsset($path);
        }

        if ($public) {
            return $this->publicAsset($path);
        }

        return $this->manifestAsset($path);
    }

    /**
     * Return the URL for the asset from the Vite dev server
     *
     * @param string $path
     *
     * @return string
     */
    public function devServerAsset(string $path): string
    {
        // Return a URL to the given asset
        return FileHelper::createUrl($this->devServerPublic, $path);
    }

    /**
     * Return the URL for the asset from the public Vite folder
     *
     * @param string $path
     *
     * @return string
     */
    public function publicAsset(string $path): string
    {
        // Return a URL to the given asset
        return FileHelper::createUrl($this->serverPublic, $path);
    }

    /**
     * Return the URL for the asset from the manifest.json file
     *
     * @param string $path
     *
     * @return string
     */
    public function manifestAsset(string $path): string
    {
        ManifestHelper::fetchManifest($this->manifestPath);
        $assets = ManifestHelper::extractAssetFiles();
        // Get just the file name
        $assetKeyParts = explode('/', $path);
        $assetKey = end($assetKeyParts);
        foreach ($assets as $key => $value) {
            if ($key === $assetKey) {
                return FileHelper::createUrl($this->serverPublic, $value);
            }
        }

        // With Vite 3.x or later, the assets are also included as top-level entries in the
        // manifest, so check there, too
        $entry = ManifestHelper::extractEntry($path);

        return $entry === '' ? '' : FileHelper::createUrl($this->serverPublic, $entry);
    }

    /**
     * Invalidate all of the Vite caches
     */
    public function invalidateCaches(): void
    {
        $cache = Craft::$app->getCache();
        TagDependency::invalidate($cache, FileHelper::CACHE_TAG . $this->cacheKeySuffix);
        Craft::info('All Vite caches cleared', __METHOD__);
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
        // Include any dev server shims
        if (!$this->devServerShimsIncluded) {
            // Include the react-refresh-shim
            if ($this->includeReactRefreshShim) {
                $script = FileHelper::fetchScript('react-refresh-shim.min.js', $this->cacheKeySuffix);
                // Replace the hard-coded dev server URL with whatever they have theirs set to
                $script = str_replace(
                    'http://localhost:3000/',
                    rtrim($this->devServerPublic, '/') . '/',
                    $script
                );
                $lines[] = HtmlHelper::script(
                    $script,
                    [
                        'type' => 'module',
                    ]
                );
            }
            $this->devServerShimsIncluded = true;
        }
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
        // Include any manifest shims
        if (!$this->manifestShimsIncluded) {
            // Handle the modulepreload-polyfill shim
            if ($this->includeModulePreloadShim) {
                $lines[] = HtmlHelper::script(
                    FileHelper::fetchScript('modulepreload-polyfill.min.js', $this->cacheKeySuffix),
                    ['type' => 'module']
                );
            }
            // Handle any legacy polyfills
            if (!empty($legacyTags)) {
                $lines[] = HtmlHelper::script(
                    FileHelper::fetchScript('safari-nomodule-fix.min.js', $this->cacheKeySuffix),
                    []
                );
                $legacyPolyfillTags = ManifestHelper::extractManifestTags(self::LEGACY_POLYFILLS, $asyncCss, $scriptTagAttrs, $cssTagAttrs, true);
                $tags = array_merge($legacyPolyfillTags, $tags);
            }
            $this->manifestShimsIncluded = true;
        }
        $lines = array_merge($lines, $this->manifestScriptTags($tags, $legacyTags));

        return implode("\r\n", $lines);
    }

    // Protected Methods
    // =========================================================================

    /**
     * Iterate through all the tags, and register them
     *
     * @param array $tags
     * @param array $legacyTags
     * @throws InvalidConfigException
     */
    protected function manifestRegisterTags(array $tags, array $legacyTags): void
    {
        $view = Craft::$app->getView();
        foreach (array_merge($tags, $legacyTags) as $tag) {
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
                    case 'import':
                        $view->registerLinkTag(
                            array_filter([
                                'crossorigin' => $tag['crossorigin'],
                                'href' => $url,
                                'rel' => 'modulepreload',
                                'integrity' => $tag['integrity'] ?? '',
                            ]),
                            md5($url)
                        );
                        break;
                    default:
                        break;
                }
            }
        }
    }

    /**
     * Inject the error entry point JavaScript for auto-reloading of Twig error
     * pages
     */
    protected function injectErrorEntry(): void
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

    /**
     * Iterate through all the tags, and return them
     *
     * @param array $tags
     * @param array $legacyTags
     * @return array
     */
    protected function manifestScriptTags(array $tags, array $legacyTags): array
    {
        $lines = [];
        foreach (array_merge($tags, $legacyTags) as $tag) {
            if (!empty($tag)) {
                $url = FileHelper::createUrl($this->serverPublic, $tag['url']);
                switch ($tag['type']) {
                    case 'file':
                        $lines[] = HtmlHelper::jsFile($url, $tag['options']);
                        break;
                    case 'css':
                        $lines[] = HtmlHelper::cssFile($url, $tag['options']);
                        break;
                    case 'import':
                        $lines[] = HtmlHelper::tag('link', '', array_filter([
                            'crossorigin' => $tag['crossorigin'],
                            'href' => $url,
                            'rel' => 'modulepreload',
                            'integrity' => $tag['integrity'] ?? '',
                        ]));
                        break;
                    default:
                        break;
                }
            }
        }

        return $lines;
    }
}
