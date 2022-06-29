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

use craft\helpers\Template;
use nystudio107\pluginvite\services\ViteService;
use Twig\Markup;
use yii\base\InvalidConfigException;

/**
 * @author    nystudio107
 * @package   Vite
 * @since     1.0.4
 */
trait ViteVariableTrait
{
    // Public Properties
    // =========================================================================

    /**
     * @var null|ViteService the Vite service
     */
    public ?ViteService $viteService = null;

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
    public function script(string $path, bool $asyncCss = true, array $scriptTagAttrs = [], array $cssTagAttrs = []): Markup
    {
        return Template::raw(
            $this->viteService->script($path, $asyncCss, $scriptTagAttrs, $cssTagAttrs)
        );
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
     * @return Markup
     * @throws InvalidConfigException
     */
    public function register(string $path, bool $asyncCss = true, array $scriptTagAttrs = [], array $cssTagAttrs = []): Markup
    {
        $this->viteService->register($path, $asyncCss, $scriptTagAttrs, $cssTagAttrs);

        return Template::raw('');
    }

    /**
     * Return the URL for the given entry
     *
     * @param string $path
     *
     * @return Markup
     */
    public function entry(string $path): Markup
    {
        return Template::raw(
            $this->viteService->entry($path)
        );
    }

    /**
     * Return the URL for the given asset
     *
     * @param string $path
     *
     * @return Markup
     */
    public function asset(string $path, bool $public=false): Markup
    {
        return Template::raw(
            $this->viteService->asset($path, $public)
        );
    }

    /**
     * Inline the contents of a local file (via path) or remote file (via URL) in your templates.
     * Yii2 aliases and/or environment variables may be used
     *
     * @param string $pathOrUrl
     *
     * @return Markup
     */
    public function inline(string $pathOrUrl): Markup
    {
        $file = $this->viteService->fetch($pathOrUrl);
        if ($file === null) {
            $file = '';
        }

        return Template::raw($file);
    }

    /**
     * Determine whether the Vite dev server is running
     *
     * @return bool
     */
    public function devServerRunning(): bool
    {
        return $this->viteService->devServerRunning();
    }
}
