<?php namespace RainLab\Pages\Classes;

use Cms\Classes\Content;
use RainLab\Pages\Classes\PageList;
use Cms\Classes\Theme;
use Cms\Classes\Layout;
use ApplicationException;
use Validator;
use File;
use Lang;

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
            if (!isset($layout->settings['components']))
                continue;

            if (!array_key_exists('staticPage', $layout->settings['components']))
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
}