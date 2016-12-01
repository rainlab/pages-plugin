<?php namespace RainLab\Pages\Behaviors;

use Lang;
use Event;
use ApplicationException;
use Cms\Classes\Theme;
use System\Traits\ConfigMaker;
use Cms\Classes\Page as CmsPage;
use System\Classes\ModelBehavior;


/**
 * MenuItemGenerator model extension
 *
 * Note the notational difference between 'type' and 'generationType'
 * 'generationType' defines the behavior's generation type, e.g. list, tree
 * Like in the event hooks (pages.menuitem.listTypes) 'type' refers to
 * the unique menu item type, e.g. 'all-static-pages'
 *
 * Usage:
 *
 * In the model class definition use
 * public $implement = ['RainLab.Pages.Behaviors.MenuItemGenerator'];
 * public $menuItemConfig = 'menu_items.yaml';
 *
 */
class MenuItemGenerator extends ModelBehavior
{

    use ConfigMaker;

    /**
     * Configuration array as parsed from models menuItemConfig path
     * array[type] stdClass Config with type specific properties from $requiredConfig
     * @var array
     */
    protected $generatorConfig;

    /**
     * @var array Properties that must exist in the model using this behavior.
     */
    protected $requiredProperties = ['menuItemConfig'];

    /**
     * Required configuration fields for the different types of menu items
     * used to validate the different generationTypes
     *
     * array[generationType]              array  Defines the required config fields per type
     * array[generationType][configField] string Name of the required field for type
     * @var array
     */
    protected $requiredConfig = [
        'list' => [
            'generationType',
            'name',
            'titleFrom',
            'slugField',
            'detailComponentName',
            'detailComponentSlugProperty',
        ],
    ];

    /**
     * Constructor
     */
    public function __construct($model)
    {
        parent::__construct($model);

        $this->configPath = $this->guessConfigPathFrom($model);

        $fileConfig = $this->makeConfig($this->model->menuItemConfig, ['types']);

        /** @TODO might be obsolete, ensures associative array YAML structure */
        if (count(array_filter(array_keys($fileConfig->types), 'is_string')) != count($fileConfig->types)) {
                throw new ApplicationException(Lang::get(
                    'system::lang.config.required',
                    ['property' => 'type', 'location' => get_called_class()]
                ));
        }

        // setup config for all menu item types
        $this->generatorConfig = [];
        foreach ($fileConfig->types as $type => $configArray) {

            // generationType exists in behavior / requirements are defined
            if (!isset($this->requiredConfig[$configArray['generationType']])) {
                throw new ApplicationException(Lang::get(
                    'system::lang.config.required',
                    ['property' => 'generationType', 'location' => get_called_class()]
                ));
            }

            // set and validate config per menu item type
            $this->generatorConfig[$type] = $this->makeConfig(
                $configArray,
                $this->requiredConfig[$configArray['generationType']]
            );
        }
    }

    /**
     * Registers the configured menu item types
     * use in plugin's boot method once per model
     * @return void
     */
    public function registerMenuItems()
    {
        Event::listen('pages.menuitem.listTypes', function() {
            return $this->getMenuItemTypes();
        });
        Event::listen('pages.menuitem.getTypeInfo', function($type) {
            return $this->getMenuTypeInfo($type);
        });
        Event::listen('pages.menuitem.resolveItem', function($type, $item, $url, $theme) {
            return $this->resolveMenuItem($type, $item, $url, $theme);
        });
    }

    /**
     * Returns the menu item types
     * array[type] string Frontend name of menu item type
     * @return array
     */
    protected function getMenuItemTypes()
    {
        return array_map(
            function($config) { return $config->name; },
            $this->generatorConfig
        );
    }

    /** Wrapper for different generationTypes */
    protected function getMenuTypeInfo($type)
    {
        if (isset($this->generatorConfig[$type])) {
            $typeConfig = $this->generatorConfig[$type];
            $methodName = 'getMenuTypeInfoFor'.ucfirst($typeConfig->generationType);
            return $this->{$methodName}($typeConfig);
        }
    }

    /** Wrapper for different generationTypes */
    protected function resolveMenuItem($type, $item, $url, $theme)
    {
        if (isset($this->generatorConfig[$type])) {
            $typeConfig = $this->generatorConfig[$type];
            $methodName = 'resolveMenuItemFor'.ucfirst($typeConfig->generationType);
            return $this->{$methodName}($typeConfig, $item, $url, $theme);
        }
    }

    /**
     * Menu item info for generationType list
     * @param  stdClass $config Menu item type config
     * @return array
     */
    protected function getMenuTypeInfoForList($config)
    {
        $result = [
            'dynamicItems' => true,
            'nesting' => false,
        ];

        // get page with detailComponentName component
        $theme = Theme::getActiveTheme();
        $pages = CmsPage::listInTheme($theme, true);
        $itemPages = [];
        foreach ($pages as $page) {
            if ($page->hasComponent($config->detailComponentName)) {
                $itemPages[] = $page;
            }
        }
        $result['cmsPages'] = $itemPages;

        return $result;
    }

    /**
     * Returns the resolved menu item(s)
     * @param  stdClass                        $config Menu item type config
     * @param  \RainLab\Pages\Classes\MenuItem $item   Menu item object
     * @param  string                          $url    Current url
     * @param  \Cms\Classes\Theme              $theme  Current theme
     * @return array                                   Menu item array
     */
    protected function resolveMenuItemForList($config, $item, $url, $theme)
    {
        $query = $this->model->select(
            'id',
            $config->titleFrom,
            $config->slugField
        );

        if (isset($config->orderBy)) {
            if (is_array($config->orderBy)) {
                $sortField = $config->orderBy['field'];
                $sortDir = $config->orderBy['direction'];
            } else {
                list($sortField,$sortDir) = explode(' ', $config->orderBy);
            }
            if ($sortField != $config->titleFrom && $sortField != $config->slugField) {
                $query->addSelect($sortField);
            }
            $query->orderBy($sortField,$sortDir);
        }
        $modelItems = $query->get();

        if (!$modelItems->count()) {
            return;
        }

        $itemUrls = $this->getPageUrlsForList($config, $item->cmsPage, $modelItems, $theme);

        $result = [
            // 'url' => $itemUrls[0],
            // 'isActive' => ($url == $itemUrls[0]),
            'items' => []
        ];

        foreach ($modelItems as $modelItem) {
            $result['items'][] = [
                'title' => $modelItem->{$config->titleFrom},
                'url' => $itemUrls[$modelItem->id],
                'isActive' => ($url == $itemUrls[$modelItem->id]),
                'cssClass' => ''
            ];
        }

        return $result;
    }

    /**
     * Gather model item URLs by model id
     * @param  stdClass           $config     Menu item type config
     * @param  string             $pageCode   Cms page code
     * @param  Model[]            $modelItems Array of model items
     * @param  \Cms\Classes\Theme $theme      Current theme
     * @return array
     */
    protected function getPageUrlsForList($config, $pageCode, $modelItems, $theme)
    {
        $page = CmsPage::loadCached($theme, $pageCode);
        if (!$page) return;
        $properties = $page->getComponentProperties($config->detailComponentName);

        /*
         * Extract the routing parameter name from the category filter
         * eg: {{ :someRouteParam }}
         */
        if (!preg_match('/^\{\{([^\}]+)\}\}$/', $properties[$config->detailComponentSlugProperty], $matches)) {
            return;
        }
        $paramName = substr(trim($matches[1]), 1);
        $itemUrls = [];
        $itemUrls[0] = CmsPage::url($page->getBaseFileName(), [$paramName => null]);
        foreach ($modelItems as $modelItem) {
            $itemUrls[$modelItem->id] = CmsPage::url(
                $page->getBaseFileName(),
                [$paramName => $modelItem->{$config->slugField}]
            );
        }
        return $itemUrls;
    }


}
