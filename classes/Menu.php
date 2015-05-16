<?php namespace RainLab\Pages\Classes;

use URL;
use File;
use Lang;
use Yaml;
use Event;
use Config;
use Request;
use Validator;
use RainLab\Pages\Classes\MenuItem;
use RainLab\Pages\Classes\MenuItemReference;
use Cms\Classes\Theme;
use Cms\Classes\CmsObject;
use Cms\Classes\Controller as CmsController;
use SystemException;
use ValidationException;
use ApplicationException;
use October\Rain\Support\Str;
use October\Rain\Router\Helper as RouterHelper;
use Symfony\Component\Yaml\Dumper as YamlDumper;
use DirectoryIterator;
use Exception;

/**
 * Represents a front-end menu.
 *
 * @package rainlab\pages
 * @author Alexey Bobkov, Samuel Georges
 */
class Menu extends CmsObject
{
    /**
     * @var string Specifies the menu name.
     */
    public $name;

    /**
     * @var array The menu items.
     * Items are objects of the \RainLab\Pages\Classes\MenuItem class.
     */
    protected $items;

    /**
     * @var array Raw item data.
     * This property is used by the menu editor.
     */
    protected $itemData = false;

    protected static $allowedExtensions = ['yaml'];

    protected static $defaultExtension = 'yaml';

    protected static $fillable = [
        'code',
        'name',
        'itemData'
    ];

    /**
     * Returns the directory name corresponding to the object type.
     * For pages the directory name is "pages", for layouts - "layouts", etc.
     * @return string
     */
    public static function getObjectTypeDirName()
    {
        return 'meta/menus';
    }

    /**
     * Returns the menu code.
     * @return string
     */
    public function getCode()
    {
        $place = strrpos($this->fileName, '.');
        if ($place !== false) {
            return substr($this->fileName, 0, $place);
        }

        return null;
    }

    /**
     * Sets the menu code.
     * @param string $code Specifies the file code.
     * @return \Cms\Classes\CmsObject Returns the object instance.
     */
    public function setCode($code)
    {
        $code = trim($code);

        if (!strlen($code)) {
            throw new ValidationException(['code' =>
                Lang::get('rainlab.pages::lang.menu.code_required')
            ]);
        }

        if (!preg_match('/^[0-9a-z\-\_]+$/i', $code)) {
            throw new ValidationException(['code' =>
                Lang::get('rainlab.pages::lang.menu.invalid_code')
            ]);
        }

        $this->code = $code;
        $this->fileName = $code.'.yaml';

        return $this;
    }

    /**
     * Returns the menu items.
     * This function is used in the back-end.
     * @return array Returns an array of the \RainLab\Pages\Classes\MenuItem objects.
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * Returns the menu item references.
     * This function is used on the front-end.
     * @param Cms\Classes\Page $page The current page object.
     * @return array Returns an array of the \RainLab\Pages\Classes\MenuItemReference objects.
     */
    public function generateReferences($page)
    {
        $currentUrl = Request::path();

        if (!strlen($currentUrl)) {
            $currentUrl = '/';
        }

        $currentUrl = Str::lower(URL::to($currentUrl));

        $activeMenuItem = $page->activeMenuItem ?: false;
        $iterator = function($items) use ($currentUrl, &$iterator, $activeMenuItem) {
            $result = [];

            foreach ($items as $item) {
                $parentReference = new MenuItemReference();
                $parentReference->title = $item->title;

                /*
                 * If the item type is URL, assign the reference the item's URL and compare the current URL with the item URL
                 * to determine whether the item is active.
                 */
                if ($item->type == 'url') {
                    $parentReference->url = $item->url;
                    $parentReference->isActive = $currentUrl == Str::lower($item->url) || $activeMenuItem === $item->code;
                }
                else {
                    /*
                     * If the item type is not URL, use the API to request the item type's provider to
                     * return the item URL, subitems and determine whether the item is active.
                     */
                    $apiResult = Event::fire('pages.menuitem.resolveItem', [$item->type, $item, $currentUrl, $this->theme]);
                    if (is_array($apiResult)) {
                        foreach ($apiResult as $itemInfo) {
                            if (!is_array($itemInfo)) {
                                continue;
                            }

                            if (!$item->replace && isset($itemInfo['url'])) {
                                $parentReference->url = $itemInfo['url'];
                                $parentReference->isActive = $itemInfo['isActive'] || $activeMenuItem === $item->code;
                            }

                            if (isset($itemInfo['items'])) {
                                $itemIterator = function($items) use (&$itemIterator, $parentReference) {
                                    $result = [];

                                    foreach ($items as $item) {
                                        $reference = new MenuItemReference();
                                        $reference->title = isset($item['title']) ? $item['title'] : '--no title--';
                                        $reference->url = isset($item['url']) ? $item['url'] : '#';
                                        $reference->isActive = isset($item['isActive']) ? $item['isActive'] : false;

                                        if (!strlen($parentReference->url)) {
                                            $parentReference->url = $reference->url;
                                            $parentReference->isActive = $reference->isActive;
                                        }

                                        if (isset($item['items'])) {
                                            $reference->items = $itemIterator($item['items']);
                                        }

                                        $result[] = $reference;
                                    }

                                    return $result;
                                };

                                $parentReference->items = $itemIterator($itemInfo['items']);
                            }
                        }
                    }
                }

                if ($item->items) {
                    $parentReference->items = $iterator($item->items);
                }

                if (!$item->replace) {
                    $result[] = $parentReference;
                }
                else {
                    foreach ($parentReference->items as $subItem) {
                        $result[] = $subItem;
                    }
                }
            }

            return $result;
        };

        $items = $iterator($this->items);

        /*
         * Populate the isChildActive property
         */
        $hasActiveChild = function($items) use (&$hasActiveChild) {
            foreach ($items as $item) {
                if ($item->isActive) {
                    return true;
                }

                $result = $hasActiveChild($item->items);
                if ($result) {
                    return $result;
                }
            }
        };

        $iterator = function($items) use (&$iterator, &$hasActiveChild) {
            foreach ($items as $item) {
                $item->isChildActive = $hasActiveChild($item->items);

                $iterator($item->items);
            }
        };

        $iterator($items);

        return $items;
    }

    /**
     * Loads the object from a file.
     * This method is used in the CMS back-end. It doesn't use any caching.
     * @param \Cms\Classes\Theme $theme Specifies the theme the object belongs to.
     * @param string $fileName Specifies the file name, with the extension.
     * The file name can contain only alphanumeric symbols, dashes and dots.
     * @return mixed Returns a CMS object instance or null if the object wasn't found.
     */
    public static function load($theme, $fileName)
    {
        if (!strlen(File::extension($fileName)))
            $fileName .= '.yaml';

        if (($obj = parent::load($theme, $fileName)) === null)
            return null;

        $parsedData = Yaml::parse($obj->content);
        if (!array_key_exists('name', $parsedData))
            throw new SystemException(sprintf('The content of the %s file is invalid: the name element is not found.', $fileName));

        $obj->name = $parsedData['name'];

        if (isset($parsedData['items']))
            $obj->items = MenuItem::initFromArray($parsedData['items']);

        return $obj;
    }

    /**
     * Saves the object to the disk.
     */
    public function save()
    {
        if ($this->itemData !== false)
            $this->items = MenuItem::initFromArray($this->itemData);

        $contentData = [
            'name' => $this->name,
            'items' => $this->itemData ? $this->itemData : []
        ];

        $dumper = new YamlDumper();
        $this->content = $dumper->dump($contentData, 20, 0, false, true);

        return parent::save();
   }

    /**
     * Initializes a cache item.
     * @param array &$item The cached item array.
     */
    protected function initCacheItem(&$item)
    {
        $item['name'] = $this->name;
        $item['items'] = serialize($this->items);
    }

    /**
     * Initializes the object properties from the cached data.
     * @param array $cached The cached data array.
     */
    protected function initFromCache($cached)
    {
        $this->items = unserialize($cached['items']);
        $this->name = $cached['name'];
    }
}