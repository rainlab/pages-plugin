<?php namespace RainLab\Pages;

use Backend;
use Event;
use System\Classes\PluginBase;
use RainLab\Pages\Classes\Controller;

class Plugin extends PluginBase
{

    public function pluginDetails()
    {
        return [
            'name'        => 'Pages',
            'description' => 'Pages & menus features.',
            'author'      => 'Alexey Bobkov, Samuel Georges',
            'icon'        => 'icon-files-o'
        ];
    }

    public function registerComponents()
    {
        return [
            '\RainLab\Pages\Components\StaticPage' => 'staticPage'
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
                        'label'       => 'rainlab.pages::lang.menus.menu_label',
                        'icon'        => 'icon-sitemap',
                        'url'         => 'javascript:;',
                        'permissions' => ['rainlab.pages.manage_menus'],
                    ],
                    'textblocks' => [
                        'label'       => 'rainlab.pages::lang.textblocks.menu_label',
                        'icon'        => 'icon-file-text-o',
                        'url'         => 'javascript:;',
                        'permissions' => ['rainlab.pages.manage_textblocks'],
                    ],
                ]

            ]
        ];
    }

    public function boot()
    {
        Event::listen('cms.router.beforeRoute', function($url){
            $controller = new Controller();

            return $controller->initCmsPage($url);
        });
    }
}