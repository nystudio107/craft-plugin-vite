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

/**
 * @author    nystudio107
 * @package   Vite
 * @since     1.0.5
 */
class UrlHelper
{
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
}
