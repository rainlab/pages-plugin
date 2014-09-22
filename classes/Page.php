<?php namespace RainLab\Pages\Classes;

use Cms\Classes\Content;
use RainLab\Pages\Classes\PageList;
use RainLab\Pages\Classes\Router;
use Cms\Classes\Theme;
use Cms\Classes\Layout;
use ApplicationException;
use October\Rain\Router\Helper as RouterHelper;
use October\Rain\Support\Str;
use Cache;
use Validator;
use File;
use Lang;
use Config;

/**
 * Represents a static page.
 *
 * @package rainlab\pages
 * @author Alexey Bobkov, Samuel Georges
 */
class Page extends Content
{
    protected $viewBagValidationRules = [
        'title' => 'required',
        'url'   => ['required', 'regex:/^\/[a-z0-9\/_\-]*$/i', 'uniqueUrl']
    ];

    protected static $fillable = [
        'markup',
        'settings',
        'code',
        'fileName',
        'parent'
    ];

    /**
     * @var array Contains the view bag properties.
     * This property is used by the page editor internally.
     */
    public $viewBag = [];

    /**
     * @var string Contains the page parent file name.
     * This property is used by the page editor internally.
     */
    public $parent;

    protected static $menuTreeCache = null;

    /**
     * Creates an instance of the object and associates it with a CMS theme.
     * @param \Cms\Classes\Theme $theme Specifies the theme the object belongs to.
     * If the theme is specified as NULL, then a query can be performed on the object directly.
     */
    public function __construct(Theme $theme = null)
    {
        parent::__construct($theme);

        $this->viewBagValidationMessages = [
            'url.regex' => Lang::get('rainlab.pages::lang.page.invalid_url'),
            'url.unique_url' => Lang::get('rainlab.pages::lang.page.url_not_unique')
        ];
    }

    /**
     * Returns the directory name corresponding to the object type.
     * For pages the directory name is "pages", for layouts - "layouts", etc.
     * @return string
     */
    public static function getObjectTypeDirName()
    {
        return 'content/static-pages';
    }

    /**
     * Saves the object to the disk.
     */
    public function save()
    {
        $isNewFile = !strlen($this->fileName);

        /*
         * Generate a file name basing on the URL
         */
        if ($isNewFile) {
            $dir = rtrim(static::getFilePath($this->theme, ''), '/');

            $fileName = trim(str_replace('/', '-', $this->getViewBag()->property('url')), '-');
            if (strlen($fileName) > 200)
                $fileName = substr($fileName, 0, 200);

            $curName = $fileName.'.htm';
            $counter = 2;
            while (File::exists($dir.'/'.$curName)) {
                $curName = $fileName.'-'.$counter.'.htm';
                $counter++;
            }

            $this->fileName = $curName;
        }

        parent::save();

        if ($isNewFile) {
            $pageList = new PageList($this->theme);
            $pageList->appendPage($this);
        }
    }

    protected function parseSettings()
    {
        /*
         * Copy view bag properties to the view bag array.
         * This is required for the back-end editors.
         */
        $viewBag = $this->getViewBag();
        foreach ($viewBag->getProperties() as $name=>$value)
            $this->viewBag[$name] = $value;

        parent::parseSettings();
    }

    /**
     * Sets the object attributes.
     * @param array $attributes A list of attributes to set.
     */
    public function fill(array $attributes)
    {
        parent::fill($attributes);

        /*
         * When the page is saved, copy setting properties to the view bag.
         * This is required for the back-end editors.
         */
        if (array_key_exists('settings', $attributes) && array_key_exists('viewBag', $attributes['settings'])) {
            $this->getViewBag()->setProperties($attributes['settings']['viewBag']);
        }
    }

    /**
     * Deletes the object from the disk.
     * Recursively deletes subpages. Returns a list of file names of deleted pages.
     * @return array
     */
    public function delete()
    {
        $result = [];

        /*
         * Delete subpages
         */

        $pageList = new PageList($this->theme);

        $subtree = $pageList->getPageSubTree($this);

        foreach ($subtree as $fileName=>$subPages) {
            $subPage = static::load($this->theme, $fileName);
            if ($subPage)
                $result = array_merge($result, $subPage->delete());
        }

        $pageList->removeSubtree($this);

        /*
         * Delete the object
         */

        $result = array_merge($result, [$this->getBaseFileName()]);

        parent::delete();

        return $result;
    }

    /**
     * Returns a list of layouts available in the theme. 
     * This method is used by the form widget.
     * @return array Returns an array of strings.
     */
    public function getLayoutOptions()
    {
        if (!($theme = Theme::getEditTheme()))
            throw new ApplicationException(Lang::get('cms::lang.theme.edit.not_found'));

        $result = [];

        $layouts = Layout::listInTheme($theme, true);
        foreach ($layouts as $layout) {
            if (!$layout->hasComponent('staticPage'))
                continue;

            $baseName = $layout->getBaseFileName();
            $result[$baseName] = strlen($layout->name) ? $layout->name : $baseName;
        }

        if (!$result)
            $result[null] = Lang::get('rainlab.pages::lang.page.layouts_not_found');

        return $result;
    }

    /**
     * Validates the object properties.
     * Throws a ValidationException in case of an error.
     */
    protected function validate()
    {
        $pages = Page::listInTheme($this->theme, true);

        Validator::extend('uniqueUrl', function($attribute, $value, $parameters) use ($pages) {
            $value = trim(strtolower($value));

            foreach ($pages as $existingPage) {
                if (
                    $existingPage->getBaseFileName() !== $this->getBaseFileName() &&
                    strtolower($existingPage->getViewBag()->property('url')) == $value
                )
                    return false;
            }

            return true;
        });

        parent::validate();
    }

    /**
     * Returns a list of options for the Reference drop-down menu in the
     * menu item configuration form, when the Static Page item type is selected.
     * @return array Returns an array
     */
    protected static function listStaticPageMenuOptions()
    {
        $theme = Theme::getEditTheme();

        $pageList = new PageList($theme);
        $pageTree = $pageList->getPageTree(true);

        $iterator = function($pages) use (&$iterator) {
            $result = [];

            foreach ($pages as $pageInfo) {
                $pageName = $pageInfo->page->getViewBag()->property('title');
                $fileName = $pageInfo->page->getBaseFileName();

                if (!$pageInfo->subpages)
                    $result[$fileName] = $pageName;
                else
                    $result[$fileName] = [
                        'title' => $pageName,
                        'items' => $iterator($pageInfo->subpages)
                    ];
            }

            return $result;
        };

        return $iterator($pageTree);
    }

    /**
     * Handler for the pages.menuitem.getTypeInfo event.
     * Returns a menu item type information. The type information is returned as array
     * with the following elements:
     * - references - a list of the item type reference options. The options are returned in the
     *   ["key"] => "title" format for options that don't have sub-options, and in the format
     *   ["key"] => ["title"=>"Option title", "items"=>[...]] for options that have sub-options. Optional,
     *   required only if the menu item type requires references.
     * - nesting - Boolean value indicating whether the item type supports nested items. Optional,
     *   false if omitted.
     * - dynamicItems - Boolean value indicating whether the item type could generate new menu items.
     *   Optional, false if omitted.
     * - cmsPages - a list of CMS pages, if the item type requires a CMS page reference in order to resolve
     *   item URLs. Should return an array in the following format:
     *   ["page-path"] => "Page title"
     *   This element is not only if the item type requires a CMS page reference.
     * @param string $type Specifies the menu item type
     * @return array Returns an array
     */
    public static function getMenuTypeInfo($type)
    {
        if ($type == 'all-static-pages')
            return [
                'dynamicItems' => true
            ];

        if ($type == 'static-page')
            return [
                'references' => self::listStaticPageMenuOptions(),
                'nesting' => true,
                'dynamicItems' => true
            ];
    }

    /**
     * Handler for the pages.menuitem.resolveItem event.
     * Returns information about a menu item. The result is an array
     * with the following keys:
     * - url - the menu item URL. Not required for menu item types that return all available records.
     * - isActive - determines whether the menu item is active. Not required for menu item types that 
     *   return all available records.
     * - items - an array of arrays with the same keys (url, isActive, items) + the title key. 
     *   The items array should be added only if the $item's $nesting property value is TRUE.
     * @param \RainLab\Pages\Classes\MenuItem $item Specifies the menu item.
     * @param \Cms\Classes\Theme $theme Specifies the current theme.
     * @param string $url Specifies the current page URL, normalized, in lower case
     * @return mixed Returns an array. Returns null if the item cannot be resolved.
     */
    public static function resolveMenuItem($item, $url, $theme)
    {
        $tree = self::buildMenuTree($theme);

        if ($item->type == 'static-page' && !isset($tree[$item->reference]))
            return;

        $result = [];

        if ($item->type == 'static-page') {
            $pageInfo = $tree[$item->reference];
            $result['url'] = $pageInfo['url'];
            $result['isActive'] = $result['url'] == $url;
        }

        if ($item->nesting || $item->type == 'all-static-pages') {
            $iterator = function($items) use (&$iterator, &$tree, $url) {
                $branch = [];

                foreach ($items as $itemName) {
                    if (!isset($tree[$itemName]))
                        continue;

                    $itemInfo = $tree[$itemName];

                    $branchItem = [];
                    $branchItem['url'] = $itemInfo['url'];
                    $branchItem['isActive'] = $branchItem['url'] == $url;
                    $branchItem['title'] = $itemInfo['title'];

                    if ($itemInfo['items'])
                        $branchItem['items'] = $iterator($itemInfo['items']);

                    $branch[] = $branchItem;
                }

                return $branch;
            };

            $result['items'] = $iterator($item->type == 'static-page' ? $pageInfo['items'] : $tree['--root-pages--']);
        }

        return $result;
    }

    /**
     * Builds and caches a menu item tree.
     * This method is used internally.
     * @param \Cms\Classes\Theme $theme Specifies the current theme.
     * @return array Returns an array containing the page information
     */
    public static function buildMenuTree($theme)
    {
        if (self::$menuTreeCache !== null)
            return self::$menuTreeCache;

        $key = crc32($theme->getPath()).'static-page-menu-tree';

        $cached = Cache::get($key, false);
        $unserialized = $cached ? @unserialize($cached) : false;

        if ($unserialized !== false)
            return self::$menuTreeCache = $unserialized;

        $menuTree = [
            '--root-pages--' => []
        ];

        $iterator = function($items, $parent, $level) use (&$menuTree, &$iterator) {
            $result = [];

            foreach ($items as $item) {
                $viewBag = $item->page->getViewBag();
                $pageCode = $item->page->getBaseFileName();

                $itemData = [
                    'url' => Str::lower(RouterHelper::normalizeUrl($viewBag->property('url'))),
                    'title' => $viewBag->property('title'),
                    'items' => $iterator($item->subpages, $pageCode, $level+1),
                    'parent' => $parent
                ];

                if ($level == 0)
                    $menuTree['--root-pages--'][] = $pageCode;

                $result[] = $pageCode;
                $menuTree[$pageCode] = $itemData;
            }

            return $result;
        };

        $pageList = new PageList($theme);
        $iterator($pageList->getPageTree(), null, 0);

        self::$menuTreeCache = $menuTree;
        Cache::put($key, serialize($menuTree), Config::get('cms.parsedPageCacheTTL', 10));

        return self::$menuTreeCache;
    }

    /**
     * Clears the menu item cache
     * @param \Cms\Classes\Theme $theme Specifies the current theme.
     */
    public static function clearMenuCache($theme)
    {
        $key = crc32($theme->getPath()).'static-page-menu-tree';
        Cache::forget($key);
    }
}