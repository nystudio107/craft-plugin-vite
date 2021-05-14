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
use craft\helpers\UrlHelper;
use craft\web\View;

use yii\base\InvalidConfigException;
use yii\caching\ChainedDependency;
use yii\caching\FileDependency;
use yii\caching\TagDependency;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

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
    const LEGACY_EXTENSION = '-legacy.';
    const LEGACY_POLYFILLS = 'vite/legacy-polyfills';

    const CACHE_KEY = 'vite';
    const CACHE_TAG = 'vite';

    const DEVMODE_CACHE_DURATION = 30;

    const USER_AGENT_STRING = 'User-Agent:Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13';

    const SAFARI_NOMODULE_FIX = '!function(){var e=document,t=e.createElement("script");if(!("noModule"in t)&&"onbeforeload"in t){var n=!1;e.addEventListener("beforeload",function(e){if(e.target===t)n=!0;else if(!e.target.hasAttribute("nomodule")||!n)return;e.preventDefault()},!0),t.type="module",t.src=".",e.head.appendChild(t),t.remove()}}();';

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
     * @var bool Whether any legacy tags were found in this request
     */
    protected $hasLegacyTags = false;

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
        $url = $this->createUrl($this->devServerPublic, $path);
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
        $tags = $this->manifestTags($path, $asyncCss, $scriptTagAttrs, $cssTagAttrs);
        // Handle any legacy polyfills
        if ($this->hasLegacyTags && !$this->legacyPolyfillIncluded) {
            $lines[] = HtmlHelper::script(self::SAFARI_NOMODULE_FIX, []);
            $legacyPolyfillTags = $this->extractManifestTags(self::LEGACY_POLYFILLS, $asyncCss, $scriptTagAttrs, $cssTagAttrs, true);
            $tags = array_merge($legacyPolyfillTags, $tags);
            $this->legacyPolyfillIncluded = true;
        }
        foreach($tags as $tag) {
            if (!empty($tag)) {
                switch ($tag['type']) {
                    case 'file':
                        $lines[] = HtmlHelper::jsFile($tag['url'], $tag['options']);
                        break;
                    case 'css':
                        $lines[] = HtmlHelper::cssFile($tag['url'], $tag['options']);
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
        $url = $this->createUrl($this->devServerPublic, $path);
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
        $tags = $this->manifestTags($path, $asyncCss, $scriptTagAttrs, $cssTagAttrs);
        // Handle any legacy polyfills
        if ($this->hasLegacyTags && !$this->legacyPolyfillIncluded) {
            $view->registerScript(self::SAFARI_NOMODULE_FIX, $view::POS_HEAD, [], 'SAFARI_NOMODULE_FIX');
            $legacyPolyfillTags = $this->extractManifestTags(self::LEGACY_POLYFILLS, $asyncCss, $scriptTagAttrs, $cssTagAttrs, true);
            $tags = array_merge($legacyPolyfillTags, $tags);
            $this->legacyPolyfillIncluded = true;
        }
        foreach($tags as $tag) {
            if (!empty($tag)) {
                switch ($tag['type']) {
                    case 'file':
                        $view->registerJsFile(
                            $tag['url'],
                            $tag['options'],
                            md5($tag['url'] . JsonHelper::encode($tag['options']))
                        );
                        break;
                    case 'css':
                        $view->registerCssFile(
                            $tag['url'],
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
     *
     * @param string $pathOrUrl
     * @param callable|null $callback
     * @return string|null
     */
    public function fetch(string $pathOrUrl, callable $callback = null)
    {
        // Create the dependency tags
        $dependency = new TagDependency([
            'tags' => [
                self::CACHE_TAG . $this->cacheKeySuffix,
                self::CACHE_TAG . $this->cacheKeySuffix . $pathOrUrl,
            ],
        ]);
        // If this is a file path such as for the `manifest.json`, add a FileDependency so it's cache bust if the file changes
        if (!UrlHelper::isAbsoluteUrl($pathOrUrl)) {
            $dependency = new ChainedDependency([
                'dependencies' => [
                    new FileDependency([
                        'fileName' => $pathOrUrl
                    ]),
                    $dependency
                ]
            ]);
        }
        // Set the cache duration based on devMode
        $cacheDuration = Craft::$app->getConfig()->getGeneral()->devMode
            ? self::DEVMODE_CACHE_DURATION
            : null;
        // Get the result from the cache, or parse the file
        $cache = Craft::$app->getCache();
        return $cache->getOrSet(
            self::CACHE_KEY . $this->cacheKeySuffix . $pathOrUrl,
            function () use ($pathOrUrl, $callback) {
                $contents = null;
                $result = null;
                if (UrlHelper::isAbsoluteUrl($pathOrUrl)) {
                    // See if we can connect to the server
                    $clientOptions = [
                        RequestOptions::HTTP_ERRORS => false,
                        RequestOptions::CONNECT_TIMEOUT => 3,
                        RequestOptions::VERIFY => false,
                        RequestOptions::TIMEOUT => 5,
                    ];
                    $client = new Client($clientOptions);
                    try {
                        $response = $client->request('GET', $pathOrUrl, [
                            RequestOptions::HEADERS => [
                                'User-Agent' => self::USER_AGENT_STRING,
                                'Accept' => '*/*',
                            ],
                        ]);
                        if ($response->getStatusCode() === 200) {
                            $contents = $response->getBody()->getContents();
                        }
                    } catch (Throwable $e) {
                        Craft::error($e, __METHOD__);
                    }
                } else {
                    $contents = @file_get_contents($pathOrUrl);
                }
                if ($contents) {
                    $result = $contents;
                    if ($callback) {
                        $result = $callback($result);
                    }
                }

                return $result;
            },
            $cacheDuration,
            $dependency
        );
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
        $url = $this->createUrl($this->devServerInternal, self::VITE_CLIENT);

        return !($this->fetch($url) === null);
    }

    /**
     * Invalidate all of the Vite caches
     */
    public function invalidateCaches()
    {
        $cache = Craft::$app->getCache();
        TagDependency::invalidate($cache, self::CACHE_TAG . $this->cacheKeySuffix);
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
        $response = Craft::$app->getResponse();
        if ($response->isServerError || $response->isClientError) {
            if (!empty($this->errorEntry) && $this->devServerRunning()) {
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
    protected function manifestTags(string $path, bool $asyncCss = true, array $scriptTagAttrs = [], array $cssTagAttrs = []): array
    {
        // Get the modern tags for this $path
        $tags = $this->extractManifestTags($path, $asyncCss, $scriptTagAttrs, $cssTagAttrs);
        // Look for a legacy version of this $path too
        $parts = pathinfo($path);
        $legacyPath = $parts['dirname']
            . '/'
            . $parts['filename']
            . self::LEGACY_EXTENSION
            . $parts['extension'];
        $legacyTags = $this->extractManifestTags($legacyPath, $asyncCss, $scriptTagAttrs, $cssTagAttrs, true);
        // Set a flag to indicate the some legacy gets were found
        $legacyPolyfillTags = [];
        if (!empty($legacyTags)) {
            $this->hasLegacyTags = true;
        }
        return array_merge(
            $legacyPolyfillTags,
            $tags,
            $legacyTags
        );
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
    protected function extractManifestTags(string $path, bool $asyncCss = true, array $scriptTagAttrs = [], array $cssTagAttrs = [], bool $legacy = false): array
    {
        $tags = [];
        // Grab the manifest
        $pathOrUrl = (string)Craft::parseEnv($this->manifestPath);
        $manifest = $this->fetch($pathOrUrl, [JsonHelper::class, 'decodeIfJson']);
        // If no manifest file is found, bail
        if ($manifest === null) {
            Craft::error('Manifest not found at ' . $this->manifestPath, __METHOD__);

            return [];
        }
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
                'type' => 'nomodule',
            ];
        }
        // Iterate through the manifest
        /* @var array $manifest */
        foreach ($manifest as $manifestKey => $entry) {
            // If it's not an entry, skip it
            if (!isset($entry['isEntry']) || !$entry['isEntry']) {
                continue;
            }
            // If there's no file, skip it
            if (!isset($entry['file'])) {
                continue;
            }
            // If the $path isn't in the $manifestKey, skip it
            if (strpos($path, $manifestKey) === false) {
                continue;
            }
            // Include the entry script
            $tags[] = [
                'type' => 'file',
                'url' => $this->createUrl($this->serverPublic, $entry['file']),
                'options' => array_merge($scriptOptions, $scriptTagAttrs)
            ];
            // Include any CSS tags
            $cssFiles = [];
            $this->extractCssFiles($manifest, $manifestKey, $cssFiles);
            foreach ($cssFiles as $cssFile) {
                $tags[] = [
                    'type' => 'css',
                    'url' => $this->createUrl($this->serverPublic, $cssFile),
                    'options' => array_merge([
                        'rel' => 'stylesheet',
                    ], $asyncCssOptions, $cssTagAttrs)
                ];
            }
        }

        return $tags;
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
    protected function extractCssFiles(array $manifest, string $manifestKey, array &$cssFiles): array
    {
        $entry = $manifest[$manifestKey] ?? null;
        if (!$entry) {
            return [];
        }
        $cssFiles = array_merge($cssFiles, $entry['css'] ?? []);
        $imports = array_merge($entry['imports'] ?? [], $entry['dynamicImport'] ?? []);
        foreach ($imports as $import) {
            $this->extractCssFiles($manifest, $import, $cssFiles);
        }

        return $cssFiles;
    }

    /**
     * Combine a path with a URL to create a URL
     *
     * @param string $url
     * @param string $path
     *
     * @return string
     */
    protected function createUrl(string $url, string $path): string
    {
        $url = (string)Craft::parseEnv($url);
        return rtrim($url, '/') . '/' . trim($path, '/');
    }
}
