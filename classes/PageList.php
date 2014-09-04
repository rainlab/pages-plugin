<?php namespace RainLab\Pages\Classes;

use RainLab\Pages\Classes\Page;
use File;
use DirectoryIterator;
use ApplicationException;
use October\Rain\Support\Yaml;
use System\Classes\SystemException;
use Symfony\Component\Yaml\Dumper as YamlDumper;

/**
 * The page list class reads and manages the static page hierarchy.
 *
 * @package rainlab\pages
 * @author Alexey Bobkov, Samuel Georges
 */
class PageList
{
    protected $theme;

    /**
     * Creates the page list object.
     * @param \Cms\Classes\Theme $theme Specifies a parent theme.
     */
    public function __construct($theme)
    {
        $this->theme = $theme;
    }

    /**
     * Returns a list of static pages in the specified theme.
     * This method is used internally by the system.
     * @param boolean $skipCache Indicates if objects should be reloaded from the disk bypassing the cache.
     * @return array Returns an array of static pages.
     */
    public function listPages($skipCache = false)
    {
        return Page::listInTheme($this->theme, $skipCache);
    }

    /**
     * Finds a page by its URL. Returns the page object and sets the $parameters property.
     * @param string $url The requested URL string.
     * @return \Cms\Classes\Page Returns \Cms\Classes\Page object or null if the page cannot be found.
     */
    public function findByUrl($url)
    {
        $url = RouterHelper::normalizeUrl($url);

        for ($pass = 1; $pass <= 2; $pass++) {
            $fileName = null;
            $urlList = [];

            $cacheable = Config::get('cms.enableRoutesCache') && in_array(Config::get('cache.driver'), ['apc', 'memcached', 'redis', 'array']);
            if ($cacheable)
                $fileName = $this->getCachedUrlFileName($url, $urlList);

            /*
             * Find the page by URL and cache the route
             */

            if (!$fileName) {
                $router = $this->getRouterObject();

                if ($router->match($url)) {
                    $this->parameters = $router->getParameters();

                    $fileName = $router->matchedRoute();

                    if ($cacheable) {
                        if (!$urlList || !is_array($urlList))
                            $urlList = [];

                        $urlList[$url] = $fileName;

                        $key = $this->getUrlListCacheKey();
                        Cache::put($key, serialize($urlList), Config::get('cms.urlCacheTtl', 1));
                    }
                }
            }

            /*
             * Return the page 
             */

            if ($fileName) {
                if (($page = Page::loadCached($this->theme, $fileName)) === null) {
                    /*
                     * If the page was not found on the disk, clear the URL cache
                     * and repeat the routing process.
                     */
                    if ($pass == 1) {
                        $this->clearCache();
                        continue;
                    }

                    return null;
                }

                return $page;
            }

            return null;
        }
    }

    /**
     * Returns a list of top-level pages with subpages.
     * The method uses the theme's meta/static-pages.yaml file to build the hierarchy. The pages are returned
     * in the order defined in the YAML file. The result of the method is used for building the back-end UI
     * and for generating the menus.
     * @param boolean $skipCache Indicates if objects should be reloaded from the disk bypassing the cache.
     * @return array Returns a nested array of objects: object('page': $pageObj, 'subpages'=>[...])
     */
    public function getPageTree($skipCache = false)
    {
        $pages = $this->listPages($skipCache);
        $config = $this->getPagesConfig();

        $iterator = function($configPages) use (&$iterator, &$pages) {
            $result = [];

            foreach ($configPages as $fileName=>$subpages) {
                $pageObject = null;
                foreach ($pages as $page) {
                    if ($page->getBaseFileName() == $fileName) {
                        $pageObject = $page;
                        break;
                    }
                }

                if ($pageObject === null)
                    continue;

                $result[] = (object)[
                    'page' => $pageObject,
                    'subpages' => $iterator($subpages)
                ];
            }

            return $result;
        };

        return $iterator($config['static-pages']);
    }

    /**
     * Updates the page hierarchy structure in the theme's meta/static-pages.yaml file.
     * @param array $structure A nested associative array representing the page structure
     */
    public function updateStructure($structure)
    {
        $originalData = $this->getPagesConfig();
        $originalData['static-pages'] = $structure;

        $dumper = new YamlDumper();
        $yamlData = $dumper->dump($originalData, 20, 0, false, true);

        $filePath = $this->getConfigFilePath();
        $dirPath = dirname($filePath);
        if (!file_exists($dirPath) || !is_dir($dirPath)) {
            if (!File::makeDirectory($dirPath, 0777, true, true))
                throw new ApplicationException(Lang::get('cms::lang.cms_object.error_creating_directory', ['name'=>$dirPath]));
        }

        if (@File::put($filePath, $yamlData) === false)
            throw new ApplicationException(Lang::get('cms::lang.cms_object.error_saving', ['name'=>$filePath]));
    }

    /**
     * Appends page to the page hierarchy.
     * The page can be added to the end of the hierarchy or as a subpage to any existing page.
     */
    public function appendPage($page)
    {
        $parent = $page->parent;

        $originalData = $this->getPagesConfig();
        $structure = $originalData['static-pages'];

        if (!strlen($parent))
            $structure[$page->getBaseFileName()] = [];
        else {
            $iterator = function(&$configPages) use (&$iterator, $parent, $page) {
                foreach ($configPages as $fileName=>&$subpages) {
                    if ($fileName == $parent) {
                        $subpages[$page->getBaseFileName()] = [];
                        return true;
                    }

                    if ($iterator($subpages) == true)
                        return true;
                }
            };

            $iterator($structure);
        }

        $this->updateStructure($structure);
    }

    /**
     * Returns a part of the page hierarchy starting from the specified page.
     * @param \Cms\Classes\Page $page Specifies a page object.
     * @param array Returns a nested array of page names.
     */
    public function getPageSubTree($page)
    {
        $pagesConfig = $this->getPagesConfig();
        $requestedFileName = $page->getBaseFileName();

        $subTree = [];

        $iterator = function($configPages) use (&$iterator, &$pages, &$subTree, $requestedFileName) {
            foreach ($configPages as $fileName=>$subpages) {
                if ($fileName == $requestedFileName) {
                    $subTree = $subpages;
                    return true;
                }

                if ($iterator($subpages) == true)
                    return true;
            }
        };

        $iterator($pagesConfig['static-pages']);

        return $subTree;
    }

    /**
     * Removes a part of the page hierarchy starting from the specified page.
     * @param \Cms\Classes\Page $page Specifies a page object.
     */
    public function removeSubtree($page)
    {
        $pagesConfig = $this->getPagesConfig();
        $requestedFileName = $page->getBaseFileName();

        $tree = [];

        $iterator = function($configPages) use (&$iterator, &$pages, $requestedFileName) {
            $result = [];

            foreach ($configPages as $fileName=>$subpages) {
                if ($requestedFileName != $fileName)
                    $result[$fileName] = $iterator($subpages);
            }

            return $result;
        };

        $updatedStructure = $iterator($pagesConfig['static-pages']);
        $this->updateStructure($updatedStructure);
    }

    /**
     * Returns the parsed meta/static-pages.yaml file contents.
     * @return mixed
     */
    protected function getPagesConfig()
    {
        $filePath = $this->getConfigFilePath();

        if (!file_exists($filePath))
            return ['static-pages'=>[]];

        $config = Yaml::parse(File::get($filePath));
        if (!array_key_exists('static-pages', $config))
            throw new SystemException('The content of the theme meta/static-pages.yaml file is invalid: the "static-pages" root element is not found.');

        return $config;
    }

    /**
     * Returns an absolute path to the meta/static-pages.yaml file.
     * @return string
     */
    protected function getConfigFilePath()
    {
        return $this->theme->getPath().'/meta/static-pages.yaml';
    }
}