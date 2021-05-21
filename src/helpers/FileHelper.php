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
use craft\helpers\UrlHelper;

use yii\caching\ChainedDependency;
use yii\caching\FileDependency;
use yii\caching\TagDependency;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

use Throwable;

/**
 * @author    nystudio107
 * @package   Vite
 * @since     1.0.5
 */
class FileHelper
{
    // Constants
    // =========================================================================

    const CACHE_KEY = 'vite';
    const CACHE_TAG = 'vite';

    const DEVMODE_CACHE_DURATION = 30;

    const USER_AGENT_STRING = 'User-Agent:Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13';

    const SCRIPTS_DIR = '@vendor/nystudio107/craft-plugin-vite/src/web/assets/dist/';

    /**
     * Return the contents of a local file (via path) or remote file (via URL),
     * or null if the file doesn't exist or couldn't be fetched
     * Yii2 aliases and/or environment variables may be used
     *
     * @param string $pathOrUrl
     * @param callable|null $callback
     * @param string $cacheKeySuffix
     *
     * @return string|array|null
     */
    public static function fetch(string $pathOrUrl, callable $callback = null, string $cacheKeySuffix = '')
    {
        $pathOrUrl = (string)Craft::parseEnv($pathOrUrl);
        // Create the dependency tags
        $dependency = new TagDependency([
            'tags' => [
                self::CACHE_TAG . $cacheKeySuffix,
                self::CACHE_TAG . $cacheKeySuffix . $pathOrUrl,
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
            self::CACHE_KEY . $cacheKeySuffix . $pathOrUrl,
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
     * Combine a path with a URL to create a URL
     *
     * @param string $url
     * @param string $path
     *
     * @return string
     */
    public static function createUrl(string $url, string $path): string
    {
        $url = (string)Craft::parseEnv($url);
        return rtrim($url, '/') . '/' . trim($path, '/');
    }

    /**
     * Fetch a script file
     *
     * @param string $name
     * @param string $cacheKeySuffix
     * @return string
     */
    public static function fetchScript(string $name, string $cacheKeySuffix = ''): string
    {
        $path = self::createUrl(self::SCRIPTS_DIR, $name);

        return self::fetch($path, null, $cacheKeySuffix) ?? '';
    }
}
