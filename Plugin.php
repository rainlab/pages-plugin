<?php namespace RainLab\Pages;

use Backend;
use Event;
use System\Classes\PluginBase;
use RainLab\Pages\Classes\Controller;
use RainLab\Pages\Classes\Page as StaticPage;
use RainLab\Pages\Classes\Router;
use Cms\Classes\Theme;

class Plugin extends PluginBase
{

    public function pluginDetails()
    {
        return [
            'name'        => 'Static Pages',
            'description' => 'Pages & menus features.',
            'author'      => 'Alexey Bobkov, Samuel Georges',
            'icon'        => 'icon-files-o'
        ];
    }

    public function registerComponents()
    {
        return [
            '\RainLab\Pages\Components\StaticPage' => 'staticPage',
            '\RainLab\Pages\Components\StaticMenu' => 'staticMenu',
            '\RainLab\Pages\Components\StaticBreadcrumbs' => 'staticBreadcrumbs'
        ];
    }

    public function registerNavigation()
    {
        return [
            'pages' => [
                'label'       => 'rainlab.pages::lang.plugin_name',
                'url'         => Backend::url('rainlab/pages'),
                'icon'        => 'icon-files-o',
                'permissions' => ['rainlab.pages.*'],
                'order'       => 20,

                'sideMenu' => [
                    'pages' => [
                        'label'       => 'rainlab.pages::lang.page.menu_label',
                        'icon'        => 'icon-files-o',
                        'url'         => 'javascript:;',
                        'attributes'  => ['data-menu-item'=>'pages'],
                        'permissions' => ['rainlab.pages.manage_pages'],
                    ],
                    'menus' => [
                        'label'       => 'rainlab.pages::lang.menu.menu_label',
                        'icon'        => 'icon-sitemap',
                        'url'         => 'javascript:;',
                        'attributes'  => ['data-menu-item'=>'menus'],
                        'permissions' => ['rainlab.pages.manage_menus'],
                    ],
                    'content' => [
                        'label'       => 'rainlab.pages::lang.content.menu_label',
                        'icon'        => 'icon-file-text-o',
                        'url'         => 'javascript:;',
                        'attributes'  => ['data-menu-item'=>'content'],
                        'permissions' => ['rainlab.pages.manage_content'],
                    ]
                ]

            ]
        ];
    }

    public function boot()
    {
        Event::listen('cms.router.beforeRoute', function($url) {
            return Controller::instance()->initCmsPage($url);
        });

        Event::listen('cms.page.beforeTwigRender', function($page, $loader, $twig) {
            return Controller::instance()->injectPageTwig($page, $loader, $twig);
        });

        Event::listen('cms.page.getRenderedContents', function($page) {
            return Controller::instance()->getPageContents($page);
        });

        Event::listen('pages.menuitem.listTypes', function() {
            return [
                'static-page'=>'Static page',
                'all-static-pages'=>'All static pages'
            ];
        });

        Event::listen('pages.menuitem.getTypeInfo', function($type) {
            if ($type == 'url')
                return [];

            if ($type == 'static-page'|| $type == 'all-static-pages')
                return StaticPage::getMenuTypeInfo($type);
        });

        Event::listen('pages.menuitem.resolveItem', function($type, $item, $url, $theme) {
            if ($type == 'static-page' || $type == 'all-static-pages')
                return StaticPage::resolveMenuItem($item, $url, $theme);
        });
    }

    public static function clearCache()
    {
        $activeTheme = Theme::getActiveTheme();

        $router = new Router($activeTheme);
        $router->clearCache();

        StaticPage::clearMenuCache($activeTheme);
    }

    public function registerPermissions()
    {
        return [
            'rainlab.pages.manage_pages'    => ['label' => 'Manage Pages', 'tab' => 'Rainlab.Pages'],
            'rainlab.pages.manage_menus'    => ['label' => 'Manage Menus', 'tab' => 'Rainlab.Pages'],
            'rainlab.pages.manage_content'  => ['label' => 'Manage Contents', 'tab' => 'Rainlab.Pages']
        ];
    }
}