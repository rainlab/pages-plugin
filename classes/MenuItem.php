<?php namespace RainLab\Pages\Classes;

use ApplicationException;
use Validator;
use Lang;
use Event;

/**
 * Represents a front-end menu item.
 *
 * @package rainlab\pages
 * @author Alexey Bobkov, Samuel Georges
 */
class MenuItem
{
    /**
     * @var string Specifies the menu title
     */
    public $title;

    /**
     * @var array Specifies the menu subitems
     */
    public $items = [];

    /**
     * @var string Specifies the parent menu item.
     * An object of the RainLab\Pages\Classes\MenuItem class or null.
     */
    public $parent;

    /**
     * @var boolean Determines whether the auto-generated menu items could have subitems.
     */
    public $allowNested;

    /**
     * @var string Specifies the menu item type - URL, static page, etc.
     */
    public $type;

    /**
     * @var string Specifies the URL for URL-type items.
     */
    public $url;

    /**
     * @var string Specifies the object identifier the item refers to.
     * The identifier could be the database identifier or an object code.
     */
    public $reference;

    /**
     * @var boolean Used by the system internally.
     */
    public $exists = false;

    protected $fillable = [
        'title', 
        'allowNested', 
        'type', 
        'url', 
        'reference'
    ];

    /**
     * Initializes a menu item from a data array. 
     * @param array $items Specifies the menu item data.
     * @return Returns an array of the MenuItem objects.
     */
    public static function initFromArray($items)
    {
        $result = [];

        foreach ($items as $itemData) {
            $obj = new self();

            foreach ($itemData as $name=>$value) {
                if ($name != 'items') {
                    if (property_exists($obj, $name))
                        $obj->$name = $value;
                } else
                    $obj->items = self::initFromArray($value);
            }

            $result[] = $obj;
        }

        return $result;
    }

    /**
     * Returns the item reference description.
     * This method is used by the back-end UI.
     * @return string 
     */
    public function getReferenceDescription()
    {
        return 'Static page';
    }

    /**
     * Returns a list of registered menu item types
     * @return array Returns an array of registered item types
     */
    public function getTypeOptions($keyValue = null)
    {
        $result = ['url' => 'URL'];

        $apiResult = Event::fire('pages.menuitem.listTypes');
        if (is_array($apiResult)) {
            foreach ($apiResult as $typeList) {
                foreach ($typeList as $typeCode=>$typeName)
                    $result[$typeCode] = $typeName;
            }
        }

        return $result;
    }

    /**
     * Converts the menu item data to an array
     * @return array Returns the menu item data as array
     */
    public function toArray()
    {
        $result = [];
        foreach ($this->fillable as $property)
            $result[$property] = $this->$property;

        return $result;
    }
}