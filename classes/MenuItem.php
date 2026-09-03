<?php namespace RainLab\Pages\Classes;

use Event;

/**
 * MenuItem represents a menu item, used in the back-end for managing the menu items.
 * On the front-end items are represented with the
 * \RainLab\Pages\Classes\MenuItemReference objects.
 */
class MenuItem
{
    /**
     * @var string title specifies the menu title.
     */
    public $title;

    /**
     * @var array items specifies the item subitems.
     */
    public $items = [];

    /**
     * @var mixed parent specifies the parent menu item.
     * An object of the \RainLab\Pages\Classes\MenuItem class or null.
     */
    public $parent;

    /**
     * @var bool nesting determines whether the auto-generated menu items could have subitems.
     */
    public $nesting;

    /**
     * @var array|bool sites includes a lookup for other sites.
     */
    public $sites = false;

    /**
     * @var string type specifies the menu item type - URL, static page, etc.
     */
    public $type = 'url';

    /**
     * @var string url specifies the URL for URL-type items.
     */
    public $url;

    /**
     * @var string code specifies the menu item code.
     */
    public $code;

    /**
     * @var string reference specifies the object identifier the item refers to.
     * The identifier could be the database identifier or an object code.
     */
    public $reference;

    /**
     * @var bool replace indicates that generated items should replace this item.
     */
    public $replace;

    /**
     * @var string cmsPage specifies the CMS page path to resolve dynamic menu items to.
     */
    public $cmsPage;

    /**
     * @var bool exists is used by the system internally.
     */
    public $exists = false;

    /**
     * @var array fillable attributes for this menu item.
     */
    public $fillable = [
        'title',
        'nesting',
        'type',
        'url',
        'code',
        'reference',
        'cmsPage',
        'replace',
        'viewBag'
    ];

    /**
     * @var array viewBag contains the view bag properties, used by the menu editor internally.
     */
    public $viewBag = [];

    /**
     * @var array attributes contains additional item properties.
     */
    public $attributes = [];

    /**
     * initFromArray initializes a menu item from a data array
     * @param array $items Specifies the menu item data.
     * @return array Returns an array of the MenuItem objects.
     */
    public static function initFromArray($items)
    {
        $result = [];

        foreach ($items as $itemData) {
            $obj = new self;

            foreach ($itemData as $name => $value) {
                if ($name != 'items') {
                    if (property_exists($obj, $name)) {
                        $obj->$name = $value;
                    }
                }
                else {
                    $obj->items = self::initFromArray($value);
                }
            }

            $result[] = $obj;
        }

        return $result;
    }

    /**
     * getTypeOptions returns a list of registered menu item types
     * @return array Returns an array of registered item types
     */
    public function getTypeOptions($keyValue = null)
    {
        /*
         * Baked in types
         */
        $result = [
            'url' => 'URL',
            'header' => 'Header',
        ];

        $apiResult = Event::fire('pages.menuitem.listTypes');

        if (is_array($apiResult)) {
            foreach ($apiResult as $typeList) {
                if (!is_array($typeList)) {
                    continue;
                }

                foreach ($typeList as $typeCode => $typeName) {
                    $result[$typeCode] = $typeName;
                }
            }
        }

        return $result;
    }

    /**
     * getCmsPageOptions returns options for the CMS page dropdown
     */
    public function getCmsPageOptions($keyValue = null)
    {
        return []; // CMS Pages are loaded client-side
    }

    /**
     * getReferenceOptions returns options for the reference dropdown
     */
    public function getReferenceOptions($keyValue = null)
    {
        return []; // References are loaded client-side
    }

    /**
     * getTypeInfo returns type information resolved from menu item providers
     */
    public static function getTypeInfo($type)
    {
        $result = [];
        $apiResult = Event::fire('pages.menuitem.getTypeInfo', [$type]);

        if (is_array($apiResult)) {
            foreach ($apiResult as $typeInfo) {
                if (!is_array($typeInfo)) {
                    continue;
                }

                foreach ($typeInfo as $name => $value) {
                    if ($name == 'cmsPages') {
                        $cmsPages = [];

                        foreach ($value as $page) {
                            $baseName = $page->getBaseFileName();
                            $pos = strrpos($baseName, '/');

                            $dir = $pos !== false ? substr($baseName, 0, $pos).' / ' : null;
                            $cmsPages[$baseName] = strlen($page->title)
                                ? $dir.$page->title
                                : $baseName;
                        }

                        $value = $cmsPages;
                    }

                    $result[$name] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * toArray converts the menu item data to an array
     * @return array Returns the menu item data as array
     */
    public function toArray()
    {
        $result = [];

        foreach ($this->fillable as $property) {
            $result[$property] = $this->$property;
        }

        return $result;
    }
}
