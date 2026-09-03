<?php namespace RainLab\Pages\Classes;

use Cms\Classes\Meta;
use RainLab\Pages\Classes\Page;

/**
 * PageList reads and manages the static page hierarchy.
 */
class PageList
{
    /**
     * @var \Cms\Classes\Theme theme parent theme
     */
    protected $theme;

    /**
     * @var mixed configCache
     */
    protected static $configCache = false;

    /**
     * __construct creates the page list object
     * @param \Cms\Classes\Theme $theme Specifies a parent theme.
     */
    public function __construct($theme)
    {
        $this->theme = $theme;
    }

    /**
     * listPages returns a list of static pages in the specified theme
     * @param boolean $skipCache Indicates if objects should be reloaded from the disk bypassing the cache.
     * @return object Returns an array of static pages.
     */
    public function listPages($skipCache = false)
    {
        return Page::listInTheme($this->theme, $skipCache);
    }

    /**
     * getPageTree returns a list of top-level pages with subpages
     * @param boolean $skipCache Indicates if objects should be reloaded from the disk bypassing the cache.
     * @return array Returns a nested array of objects: object('page': $pageObj, 'subpages'=>[...])
     */
    public function getPageTree($skipCache = false)
    {
        $pages = $this->listPages($skipCache);
        $config = $this->getPagesConfig();

        // Make the $pages collection an associative array for performance
        $pagesArray = $pages->keyBy(function ($page) {
            return $page->getBaseFileName();
        })->all();

        $iterator = function($configPages) use (&$iterator, $pagesArray) {
            $result = [];

            foreach ($configPages as $fileName => $subpages) {
                if (isset($pagesArray[$fileName])) {
                    $result[] = (object) [
                        'page'     => $pagesArray[$fileName],
                        'subpages' => $iterator($subpages),
                    ];
                }
            }

            return $result;
        };

        return $iterator($config['static-pages']);
    }

    /**
     * getPageParent returns the parent name of the specified page
     * @param \Cms\Classes\Page $page Specifies a page object.
     * @param string Returns the parent page name.
     */
    public function getPageParent($page)
    {
        $pagesConfig = $this->getPagesConfig();
        $requestedFileName = $page->getBaseFileName();

        $parent = null;

        $iterator = function($configPages) use (&$iterator, &$parent, $requestedFileName) {
            foreach ($configPages as $fileName => $subpages) {
                if ($fileName == $requestedFileName) {
                    return true;
                }

                if ($iterator($subpages) == true && is_null($parent)) {

                    $parent = $fileName;

                    return true;
                }
            }
        };

        $iterator($pagesConfig['static-pages']);

        return $parent;
    }

    /**
     * getPageSubTree returns a part of the page hierarchy starting from the specified page
     * @param \Cms\Classes\Page $page Specifies a page object.
     * @param array Returns a nested array of page names.
     */
    public function getPageSubTree($page)
    {
        $pagesConfig = $this->getPagesConfig();
        $requestedFileName = $page->getBaseFileName();

        $subTree = [];

        $iterator = function($configPages) use (&$iterator, &$subTree, $requestedFileName) {
            if (is_array($configPages)) {
                foreach ($configPages as $fileName => $subpages) {
                    if ($fileName == $requestedFileName) {
                        $subTree = $subpages;

                        return true;
                    }

                    if ($iterator($subpages) === true) {
                        return true;
                    }
                }
            }
        };

        $iterator($pagesConfig['static-pages']);

        return $subTree;
    }

    /**
     * appendPage appends a page to the page hierarchy
     */
    public function appendPage($page)
    {
        $parent = $page->parentFileName;

        $originalData = $this->getPagesConfig();
        $structure = $originalData['static-pages'];

        if (!strlen($parent)) {
            $structure[$page->getBaseFileName()] = [];
        }
        else {
            $iterator = function(&$configPages) use (&$iterator, $parent, $page) {
                foreach ($configPages as $fileName => &$subpages) {
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
     * removeSubtree removes a part of the page hierarchy starting from the specified page
     * @param \Cms\Classes\Page $page Specifies a page object.
     */
    public function removeSubtree($page)
    {
        $pagesConfig = $this->getPagesConfig();
        $requestedFileName = $page->getBaseFileName();

        $tree = [];

        $iterator = function($configPages) use (&$iterator, &$pages, $requestedFileName) {
            $result = [];

            foreach ($configPages as $fileName => $subpages) {
                if ($requestedFileName != $fileName) {
                    $result[$fileName] = $iterator($subpages);
                }
            }

            return $result;
        };

        $updatedStructure = $iterator($pagesConfig['static-pages']);
        $this->updateStructure($updatedStructure);
    }

    /**
     * getPagesConfig returns the parsed meta/static-pages.yaml file contents
     * @return mixed
     */
    protected function getPagesConfig()
    {
        if (self::$configCache !== false) {
            return self::$configCache;
        }

        $config = Meta::loadCached($this->theme, 'static-pages.yaml');

        if (!$config) {
            $config = new Meta();
            $config->fileName = 'static-pages.yaml';
            $config['static-pages'] = [];
            $config->save();
        }

        if (!isset($config->attributes['static-pages'])) {
            $config['static-pages'] = [];
        }

        return self::$configCache = $config;
    }

    /**
     * updateStructure updates the page hierarchy structure in the theme's meta/static-pages.yaml file
     * @param array $structure A nested associative array representing the page structure
     */
    public function updateStructure($structure)
    {
        $config = $this->getPagesConfig();
        $config['static-pages'] = $structure;
        $config->save();
    }
}
