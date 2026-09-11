<?php namespace RainLab\Pages\Classes;

use Yaml;
use File;
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
     * @var mixed structureCache holds the merged cross-layer structure
     */
    protected static $structureCache = false;

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
        $structure = $this->getMergedStructure();

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

        return $iterator($structure);
    }

    /**
     * getPageParent returns the parent name of the specified page
     * @param \Cms\Classes\Page $page Specifies a page object.
     * @param string Returns the parent page name.
     */
    public function getPageParent($page)
    {
        $structure = $this->getMergedStructure();
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

        $iterator($structure);

        return $parent;
    }

    /**
     * getPageSubTree returns a part of the page hierarchy starting from the specified page
     * @param \Cms\Classes\Page $page Specifies a page object.
     * @param array Returns a nested array of page names.
     */
    public function getPageSubTree($page)
    {
        $structure = $this->getMergedStructure();
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

        $iterator($structure);

        return $subTree;
    }

    /**
     * appendPage appends a page to the page hierarchy
     */
    public function appendPage($page)
    {
        $parent = $page->parentFileName;

        // Start from the merged structure so a child theme persists the inherited pages
        // alongside the new one, rather than a child-only snapshot that hides the parent.
        $structure = $this->getMergedStructure();

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
     * renameKey renames a page's base filename key in place, preserving its position and its subtree
     * @param string $oldBaseFileName
     * @param string $newBaseFileName
     */
    public function renameKey($oldBaseFileName, $newBaseFileName)
    {
        $structure = $this->getMergedStructure();

        $iterator = function($configPages) use (&$iterator, $oldBaseFileName, $newBaseFileName) {
            $result = [];

            foreach ($configPages as $fileName => $subpages) {
                $key = ($fileName === $oldBaseFileName) ? $newBaseFileName : $fileName;
                $result[$key] = $iterator($subpages);
            }

            return $result;
        };

        $updatedStructure = $iterator($structure);
        $this->updateStructure($updatedStructure);
    }

    /**
     * removeSubtree removes a part of the page hierarchy starting from the specified page
     * @param \Cms\Classes\Page $page Specifies a page object.
     */
    public function removeSubtree($page)
    {
        $structure = $this->getMergedStructure();
        $requestedFileName = $page->getBaseFileName();

        $iterator = function($configPages) use (&$iterator, $requestedFileName) {
            $result = [];

            foreach ($configPages as $fileName => $subpages) {
                if ($requestedFileName != $fileName) {
                    $result[$fileName] = $iterator($subpages);
                }
            }

            return $result;
        };

        $updatedStructure = $iterator($structure);
        $this->updateStructure($updatedStructure);
    }

    /**
     * getMergedStructure returns the page hierarchy merged across the theme layers, so a
     * child theme inherits the parent's structure and overlays its own changes on top.
     *
     * Child wins: a page the child renames, re-nests or reorders is honored; pages defined
     * only in the parent are appended at their original parent position; child-only pages
     * are appended after. This keeps the tree consistent with the layered page files, where
     * a child theme already sees (and can override) the parent's pages.
     * @return array
     */
    protected function getMergedStructure()
    {
        if (self::$structureCache !== false) {
            return self::$structureCache;
        }

        $structure = [];

        // Walk the parent chain from the top down so each layer overlays the one below.
        $themes = [];
        for ($theme = $this->theme; $theme !== null; $theme = $theme->getParentTheme()) {
            $themes[] = $theme;
        }

        foreach (array_reverse($themes) as $theme) {
            $structure = $this->mergeStructures($structure, $this->readThemeStructure($theme));
        }

        return self::$structureCache = $structure;
    }

    /**
     * mergeStructures overlays a child structure onto a base (parent) structure.
     *
     * Keys present in the child replace the base at the same position (recursively), base-only
     * keys are kept in place, and child-only keys are appended.
     * @param array $base
     * @param array $child
     * @return array
     */
    protected function mergeStructures(array $base, array $child)
    {
        $result = [];

        foreach ($base as $fileName => $subpages) {
            $result[$fileName] = array_key_exists($fileName, $child)
                ? $this->mergeStructures(is_array($subpages) ? $subpages : [], is_array($child[$fileName]) ? $child[$fileName] : [])
                : $subpages;
        }

        foreach ($child as $fileName => $subpages) {
            if (!array_key_exists($fileName, $result)) {
                $result[$fileName] = $subpages;
            }
        }

        return $result;
    }

    /**
     * readThemeStructure reads a single theme's own meta/static-pages.yaml, bypassing the
     * layered datasource so each layer's structure can be merged explicitly.
     * @param \Cms\Classes\Theme $theme
     * @return array
     */
    protected function readThemeStructure($theme)
    {
        $path = $theme->getPath().'/meta/static-pages.yaml';

        if (!File::exists($path)) {
            return [];
        }

        $config = Yaml::parse(File::get($path));

        return (array) ($config['static-pages'] ?? []);
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

        // The merged structure is derived from the persisted file, so drop its cache.
        self::$structureCache = false;
    }
}
