<?php namespace RainLab\Pages\Classes;

use Cms\Classes\Theme;
use October\Rain\Support\Yaml;
use System\Classes\SystemException;
use Cms\Classes\CmsObject;
use RainLab\Pages\Classes\MenuItem;
use ApplicationException;
use DirectoryIterator;
use Validator;
use Exception;
use File;
use Lang;
use Config;
use Cache;

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

    protected static $allowedExtensions = ['yaml'];

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
        if ($place !== false)
            return substr($this->fileName, 0, $place);

        return null;
    }

    /**
     * Returns the menu items
     * @return array Returns an array of the MenuItem objects.
     */
    public function getItems()
    {
        return $this->items;
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
}