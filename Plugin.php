<?php namespace RainLab\Pages;

use Event;
use Backend;
use RainLab\Pages\Classes\Page as StaticPage;
use RainLab\Pages\Classes\Router;
use Cms\Classes\Theme;
use System\Classes\PluginBase;

/**
 * Plugin Information File
 */
class Plugin extends PluginBase
{
    /**
     * register the Editor extension for the backend Pages editor.
     */
    public function register()
    {
        Event::listen('editor.extension.register', function () {
            return \RainLab\Pages\Classes\EditorExtension::class;
        });
    }

    /**
     * pluginDetails returns information about this plugin.
     */
    public function pluginDetails()
    {
        return [
            'name' => 'Pages',
            'description' => 'Pages & menus features.',
            'author' => 'Alexey Bobkov, Samuel Georges',
            'icon' => 'icon-files-o',
            'homepage' => 'https://github.com/rainlab/pages-plugin'
        ];
    }

    /**
     * registerComponents used by the frontend.
     */
    public function registerComponents()
    {
        return [
            \RainLab\Pages\Components\ChildPages::class => 'childPages',
            \RainLab\Pages\Components\StaticPage::class => 'staticPage',
            \RainLab\Pages\Components\StaticMenu::class => 'staticMenu',
            \RainLab\Pages\Components\StaticBreadcrumbs::class => 'staticBreadcrumbs'
        ];
    }

    /**
     * registerFormWidgets available for backend forms.
     */
    public function registerFormWidgets()
    {
        return [
            \RainLab\Pages\FormWidgets\PagePicker::class => 'staticpagepicker',
            \RainLab\Pages\FormWidgets\MenuPicker::class => 'staticmenupicker',
        ];
    }

    /**
     * registerContentFields available for Tailor blueprints, matching the
     * form widget aliases so 'type: staticpagepicker' works in a blueprint.
     */
    public function registerContentFields()
    {
        return [
            \RainLab\Pages\ContentFields\PagePickerField::class => 'staticpagepicker',
            \RainLab\Pages\ContentFields\MenuPickerField::class => 'staticmenupicker',
        ];
    }

    /**
     * registerPermissions available for backend users.
     */
    public function registerPermissions()
    {
        return [
            'rainlab.pages.manage_pages' => [
                'tab'   => 'Pages',
                'order' => 200,
                'label' => 'Manage static pages'
            ],
            'rainlab.pages.manage_menus' => [
                'tab'   => 'Pages',
                'order' => 200,
                'label' => 'Manage static menus'
            ],
            'rainlab.pages.manage_content' => [
                'tab'   => 'Pages',
                'order' => 200,
                'label' => 'Manage static content'
            ]
        ];
    }

    /**
     * registerNavigation for the backend, a single item hosting the Vue Editor shell.
     */
    public function registerNavigation()
    {
        return [
            'pages' => [
                'label'       => 'Pages',
                'url'         => Backend::url('rainlab/pages/index'),
                'icon'        => 'icon-files-o',
                'iconSvg'     => 'plugins/rainlab/pages/assets/images/pages-icon.svg',
                'permissions' => ['rainlab.pages.*'],
                'order'       => 200,
                'useDropdown' => false
            ]
        ];
    }

    /**
     * registerMarkupTags adds the staticPage filter.
     */
    public function registerMarkupTags()
    {
        return [
            'filters' => [
                'staticPage' => [\RainLab\Pages\Classes\Page::class, 'url', false]
            ]
        ];
    }

    /**
     * boot the plugin events.
     */
    public function boot()
    {
        Event::subscribe(\RainLab\Pages\Classes\ExtendCmsModule::class);
    }

    /**
     * clearCache flushes the router and menu caches for the edit theme.
     */
    public static function clearCache()
    {
        $theme = Theme::getEditTheme();

        $router = new Router($theme);
        $router->clearCache();

        StaticPage::clearMenuCache($theme);
    }
}
