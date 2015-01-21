<?php namespace RainLab\Pages;

use Backend;
use Event;
use System\Classes\PluginBase;
use RainLab\Pages\Classes\Controller;
use RainLab\Pages\Classes\Page as StaticPage;
use RainLab\Pages\Classes\Router;
use RainLab\Pages\Classes\Snippet;
use Cms\Classes\Theme;
use RainLab\Pages\Classes\SnippetManager;

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
                    ],
                    'snippets' => [
                        'label'       => 'rainlab.pages::lang.snippet.menu_label',
                        'icon'        => 'icon-newspaper-o',
                        'url'         => 'javascript:;',
                        'attributes'  => ['data-menu-item'=>'snippet'],
                        'permissions' => ['rainlab.pages.access_snippets'],
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

        Event::listen('cms.page.beforeRenderPage', function($controller, $page) {
            /*
             * Before twig renders
             */
            $twig = $controller->getTwig();
            $loader = $controller->getLoader();
            Controller::instance()->injectPageTwig($page, $loader, $twig);

            /*
             * Get rendered content
             */
            $contents = Controller::instance()->getPageContents($page);
            if (strlen($contents)) {
                return $contents;
            }
        });

        Event::listen('cms.page.initComponents', function($controller, $page) {
            Controller::instance()->initPageComponents($controller, $page);
        });

        Event::listen('pages.menuitem.listTypes', function() {
            return [
                'static-page'      => 'Static page',
                'all-static-pages' => 'All static pages'
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

        Event::listen('backend.form.extendFieldsBefore', function($formWidget) {
            if ($formWidget->model instanceof \Cms\Classes\Partial)
                Snippet::extendPartialForm($formWidget);
        });

        Event::listen('cms.template.save', function($controller, $template, $type) {
            Plugin::clearCache();
        });

        Event::listen('cms.template.processSettingsBeforeSave', function($controller, $dataHolder) {
            $dataHolder->settings = Snippet::processTemplateSettingsArray($dataHolder->settings);
        });

        Event::listen('cms.template.processSettingsAfterLoad', function($controller, $template) {
            Snippet::processTemplateSettings($template);
        });
    }

    public static function clearCache()
    {
        $theme = Theme::getEditTheme();

        $router = new Router($theme);
        $router->clearCache();

        StaticPage::clearMenuCache($theme);
        SnippetManager::clearCache($theme);
    }
}