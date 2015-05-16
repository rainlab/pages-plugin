<?php namespace RainLab\Pages\Classes;

use Cache;
use Config;
use Cms\Classes\Partial;
use System\Classes\PluginManager;
use SystemException;
use RainLab\Pages\Classes\Snippet;

/**
 * Returns information about snippets based on partials and components.
 *
 * @package rainlab\pages
 * @author Alexey Bobkov, Samuel Georges
 */
class SnippetManager
{
    use \October\Rain\Support\Traits\Singleton;

    const CACHE_KEY_PARTIAL_MAP = 'snippet-partial-map';

    protected $snippets = null;

    /**
     * Returns a list of available snippets.
     * @param \Cms\Classes\Theme $theme Specifies a parent theme.
     * @return array Returns an unsorted array of snippet objects.
     */
    public function listSnippets($theme)
    {
        if ($this->snippets !== null) {
            return $this->snippets;
        }

        $themeSnippets = $this->listThemeSnippets($theme);
        $componentSnippets = $this->listComponentSnippets();

        return $this->snippets = array_merge($themeSnippets, $componentSnippets);
    }

    /**
     * Finds a snippet by its code.
     * This method is used internally by the system.
     * @param \Cms\Classes\Theme $theme Specifies a parent theme.
     * @param string $code Specifies the snippet code.
     * @param string $$componentClass Specifies the snippet component class, if available.
     * @param boolean $allowCaching Specifies whether caching is allowed for the call.
     * @return array Returns an array of Snippet objects.
     */
    public function findByCodeOrComponent($theme, $code, $componentClass, $allowCaching = false)
    {
        if (!$allowCaching) {
            // If caching is not allowed, list all available snippets, 
            // find the snippet in the list and return it.
            $snippets = $this->listSnippets($theme);

            foreach ($snippets as $snippet) {
                if ($componentClass && $snippet->getComponentClass() == $componentClass) {
                    return $snippet;
                }

                if ($snippet->code == $code) {
                    return $snippet;
                }
            }

            return null;
        }

        // If caching is allowed, and the requested snippet is a partial snippet,
        // try to load the partial name from the cache and initialize the snippet
        // from the partial.

        if (!strlen($componentClass)) {
            $map = $this->getPartialSnippetMap($theme);
            if (!array_key_exists($code, $map)) {
                return null;
            }

            $partialName = $map[$code];
            $partial = Partial::loadCached($theme, $partialName);
            if (!$partial) {
                return null;
            }

            $snippet = new Snippet();
            $snippet->initFromPartial($partial);

            return $snippet;
        }
        else {
            // If the snippet is a component snippet, initialize it
            // from the component

            if (!class_exists($componentClass)) {
                throw new SystemException(sprintf('The snippet component class %s is not found.', $componentClass));
            }

            $snippet = new Snippet();
            $snippet->initFromComponentInfo($componentClass, $code);

            return $snippet;
        }
    }

    /**
     * Clears front-end run-time cache.
     * @param \Cms\Classes\Theme $theme Specifies a parent theme.
     */
    public static function clearCache($theme)
    {
        $keys = [self::CACHE_KEY_PARTIAL_MAP, Snippet::CACHE_PAGE_SNIPPET_MAP];
        $keyBase = crc32($theme->getPath());

        foreach ($keys as $key) {
            Cache::forget($keyBase.$key);
        }
    }

    /**
     * Returns a list of partial-based snippets and corresponding partial names.
     * @param \Cms\Classes\Theme $theme Specifies a parent theme.
     * @return Returns an associative array with the snippet code in keys and partial file names in values.
     */
    public function getPartialSnippetMap($theme)
    {
        $result = [];

        $key = crc32($theme->getPath()).self::CACHE_KEY_PARTIAL_MAP;
        $cached = Cache::get($key, false);
        if ($cached !== false && ($cached = @unserialize($cached)) !== false) {
            return $cached;
        }

        $partials = Partial::listInTheme($theme);
        foreach ($partials as $partial) {
            $viewBag = $partial->getViewBag();

            $snippetCode = $viewBag->property('staticPageSnippetCode');
            if (!strlen($snippetCode)) {
                continue;
            }

            $result[$snippetCode] = $partial->getFileName();
        }

        Cache::put($key, serialize($result), Config::get('cms.parsedPageCacheTTL', 10));

        return $result;
    }

    /**
     * Returns a list of snippets in the specified theme.
     * @param \Cms\Classes\Theme $theme Specifies a parent theme.
     * @return array Returns an array of Snippet objects.
     */
    protected function listThemeSnippets($theme)
    {
        $result = [];

        $partials = Partial::listInTheme($theme, true);
        foreach ($partials as $partial) {
            $viewBag = $partial->getViewBag();

            if (strlen($viewBag->property('staticPageSnippetCode'))) {
                $snippet = new Snippet();
                $snippet->initFromPartial($partial);
                $result[] = $snippet;
            }
        }

        return $result;
    }

    /**
     * Returns a list of snippets created from components.
     * @return array Returns an array of Snippet objects.
     */
    protected function listComponentSnippets()
    {
        $result = [];

        $pluginManager = PluginManager::instance();
        $plugins = $pluginManager->getPlugins();

        foreach ($plugins as $id => $plugin) {
            if (!method_exists($plugin, 'registerPageSnippets')) {
                continue;
            }

            $snippets = $plugin->registerPageSnippets();
            if (!is_array($snippets)) {
                continue;
            }

            foreach ($snippets as $componentClass=>$componentCode) {
                // TODO: register snippet components later, during 
                // the page life cycle.
                $snippet = new Snippet();
                $snippet->initFromComponentInfo($componentClass, $componentCode);
                $result[] = $snippet;
            }
        }

        return $result;
    }
}