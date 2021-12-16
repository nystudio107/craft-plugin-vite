<?php
/**
 * Vite plugin for Craft CMS 3.x
 *
 * Allows the use of the Vite.js next generation frontend tooling with Craft CMS
 *
 * @link      https://nystudio107.com
 * @copyright Copyright (c) 2021 nystudio107
 */

namespace nystudio107\pluginvite\variables;

use yii\base\InvalidConfigException;

use Twig\Markup;

/**
 * @author    nystudio107
 * @package   Vite
 * @since     1.0.4
 */
interface ViteVariableInterface
{
    // Public Methods
    // =========================================================================

    /**
     * Return the appropriate tags to load the Vite script, either via the dev server or
     * extracting it from the manifest.json file
     *
     * @param string $path
     * @param bool $asyncCss
     * @param array $scriptTagAttrs
     * @param array $cssTagAttrs
     *
     * @return Markup
     */
    public function script(string $path, bool $asyncCss = true, array $scriptTagAttrs = [], array $cssTagAttrs = []): Markup;

    /**
     * Register the appropriate tags to the Craft View to load the Vite script, either via the dev server or
     * extracting it from the manifest.json file
     *
     * @param string $path
     * @param bool $asyncCss
     * @param array $scriptTagAttrs
     * @param array $cssTagAttrs
     *
     * @return Markup
     * @throws InvalidConfigException
     */
    public function register(string $path, bool $asyncCss = true, array $scriptTagAttrs = [], array $cssTagAttrs = []): Markup;

    /**
     * Return the URL for the given entry
     *
     * @param string $path
     *
     * @return Markup
     */
    public function entry(string $path): Markup;

    /**
     * Return the URL for the given asset
     *
     * @param string $path
     *
     * @return Markup
     */
    public function asset(string $path): Markup;

    /**
     * Inline the contents of a local file (via path) or remote file (via URL) in your templates.
     * Yii2 aliases and/or environment variables may be used
     *
     * @param string $pathOrUrl
     *
     * @return string|null
     */
    public function inline(string $pathOrUrl): Markup;

    /**
     * Determine whether the Vite dev server is running
     *
     * @return bool
     */
    public function devServerRunning(): bool;
}
