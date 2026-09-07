<?php namespace RainLab\Pages\Classes;

use Site;
use Cache;
use Event;
use Config;
use Cms\Classes\Theme;
use RainLab\Pages\Classes\Page;
use October\Rain\Support\Str;
use October\Rain\Router\Helper as RouterHelper;

/**
 * Router for static pages.
 */
class Router
{
    /**
     * @var \Cms\Classes\Theme theme reference containing the object
     */
    protected $theme;

    /**
     * @var array urlMap of page file names and corresponding URL patterns
     */
    private static $urlMap = [];

    /**
     * @var array cache is a request-level cache
     */
    private static $cache = [];

    /**
     * __construct creates the router instance
     * @param \Cms\Classes\Theme $theme Specifies the theme being processed.
     */
    public function __construct(Theme $theme)
    {
        $this->theme = $theme;
    }

    /**
     * findByUrl finds a static page by its URL
     * @param string $url The requested URL string.
     * @return \RainLab\Pages\Classes\Page Returns the page object or null if the page cannot be found.
     */
    public function findByUrl($url)
    {
        $url = Str::lower(RouterHelper::normalizeUrl($url));

        // Request-level caches are keyed per theme and locale, matching the
        // persistent cache, so iterating sites in one request stays correct.
        $cacheKey = $this->getCacheKey('static-page-url-map');

        if (isset(self::$cache[$cacheKey]) && array_key_exists($url, self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey][$url];
        }

        $urlMap = $this->getUrlMap($cacheKey);
        $urlMap = array_key_exists('urls', $urlMap) ? $urlMap['urls'] : [];

        if (!array_key_exists($url, $urlMap)) {
            return null;
        }

        $fileName = $urlMap[$url];

        if (($page = Page::loadCached($this->theme, $fileName)) === null) {
            /*
             * If the page was not found on the disk, clear the URL cache
             * and try again.
             */
            $this->clearCache();

            return self::$cache[$cacheKey][$url] = Page::loadCached($this->theme, $fileName);
        }

        return self::$cache[$cacheKey][$url] = $page;
    }

    /**
     * getUrlMap autoloads the URL map only allowing a single execution
     * @return array Returns the URL map.
     */
    protected function getUrlMap($cacheKey)
    {
        if (empty(self::$urlMap[$cacheKey])) {
            $this->loadUrlMap($cacheKey);
        }

        return self::$urlMap[$cacheKey];
    }

    /**
     * loadUrlMap loads the URL map - a list of page file names and corresponding URL patterns
     * @return boolean Returns true if the URL map was loaded from the cache. Otherwise returns false.
     */
    protected function loadUrlMap($cacheKey)
    {
        $cacheable = Config::get('cms.enable_route_cache', false);
        $cached = $cacheable ? Cache::get($cacheKey, false) : false;

        if (!$cached || ($unserialized = @unserialize($cached)) === false) {
            /*
             * The item doesn't exist in the cache, create the map
             */
            $pageList = new PageList($this->theme);

            $pages = $pageList->listPages();
            $map = [
                'urls'   => [],
                'files'  => [],
                'titles' => []
            ];
            foreach ($pages as $page) {
                if (!$page) {
                    continue;
                }

                // Prefer the translated URL for the active site, if any
                $url = $page->getTranslatableUrl() ?: $page->getViewBag()->property('url');
                if (!$url) {
                    continue;
                }

                $url = Str::lower(RouterHelper::normalizeUrl($url));
                $file = $page->getBaseFileName();

                $map['urls'][$url] = $file;
                $map['files'][$file] = $url;
                $map['titles'][$file] = $page->getViewBag()->property('title');
            }

            self::$urlMap[$cacheKey] = $map;

            if ($cacheable) {
                $comboConfig = Config::get('cms.url_cache_ttl', 10);
                $expiresAt = now()->addMinutes($comboConfig);
                Cache::put($cacheKey, serialize($map), $expiresAt);
            }

            return false;
        }

        self::$urlMap[$cacheKey] = $unserialized;

        return true;
    }

    /**
     * getCacheKey returns the caching URL key depending on the theme
     * @param string $keyName Specifies the base key name.
     * @return string Returns the theme-specific key name.
     */
    protected function getCacheKey($keyName, $locale = null)
    {
        $key = crc32($this->theme->getPath()).$keyName;

        // URL maps hold translated URLs, cache them per locale
        if ($locale === null && Site::hasMultiSite()) {
            $locale = Site::getActiveSite()?->hard_locale;
        }

        if ($locale) {
            $key .= '-'.$locale;
        }

        /**
         * @event pages.router.getCacheKey
         * Enables modifying the key used to reference cached RainLab.Pages routes
         *
         * Example usage:
         *
         *     Event::listen('pages.router.getCacheKey', function (&$key) {
         *          $key = $key . '-' . App::getLocale();
         *     });
         *
         */
        Event::fire('pages.router.getCacheKey', [&$key]);
        return $key;
    }

    /**
     * clearCache clears the router cache
     */
    public function clearCache()
    {
        self::$cache = [];
        self::$urlMap = [];
        Cache::forget($this->getCacheKey('static-page-url-map'));

        // Clear every locale's map
        if (Site::hasMultiSite()) {
            foreach (Site::listSites() as $site) {
                if ($site->hard_locale) {
                    Cache::forget($this->getCacheKey('static-page-url-map', $site->hard_locale));
                }
            }
        }
    }
}
