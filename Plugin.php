<?php namespace RainLab\Pages;

use Event;
use Backend;
use RainLab\Pages\Classes\Controller;
use RainLab\Pages\Classes\Page as StaticPage;
use RainLab\Pages\Classes\Router;
use Cms\Classes\Theme;
use Cms\Classes\Controller as CmsController;
use System\Classes\PluginBase;

/**
 * Plugin for the modernized Pages editor, rebuilt on the Vue Editor module.
 *
 * The file-based data model and frontend components are preserved from the original for
 * drop-in compatibility; only the backend editing experience is modernized.
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
     * registerComponents used by the frontend, preserved for theme compatibility.
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
     * registerMarkupTags adds the staticPage filter, preserved for theme compatibility.
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
     * boot wires the frontend routing and rendering of static pages, preserved from the original.
     */
    public function boot()
    {
        Event::listen('cms.router.beforeRoute', function($url) {
            return Controller::instance()->initCmsPage($url);
        });

        Event::listen('cms.page.beforeRenderPage', function($controller, $page) {
            // Before twig renders
            $twig = $controller->getTwig();
            $loader = $controller->getLoader();
            Controller::instance()->injectPageTwig($page, $loader, $twig);

            // Get rendered content
            $contents = Controller::instance()->getPageContents($page);
            if ($contents && strlen($contents)) {
                return $contents;
            }
        });

        Event::listen('cms.block.render', function($blockName, $blockContents) {
            $page = CmsController::getController()->getPage();

            if (!isset($page->apiBag['staticPage'])) {
                return;
            }

            $contents = Controller::instance()->getPlaceholderContents($page, $blockName, $blockContents);
            if ($contents && strlen($contents)) {
                return $contents;
            }
        });

        Event::listen('cms.pageLookup.listTypes', function() {
            return [
                'static-page'      => 'Static page',
                'all-static-pages' => ['All static pages', true]
            ];
        });

        Event::listen('pages.menuitem.listTypes', function() {
            return [
                'static-page'      => 'Static page',
                'all-static-pages' => 'All static pages'
            ];
        });

        Event::listen(['cms.pageLookup.getTypeInfo', 'pages.menuitem.getTypeInfo'], function($type) {
            if ($type == 'url') {
                return [];
            }

            if ($type == 'static-page'|| $type == 'all-static-pages') {
                return StaticPage::getMenuTypeInfo($type);
            }
        });

        Event::listen(['cms.pageLookup.resolveItem', 'pages.menuitem.resolveItem'], function($type, $item, $url, $theme) {
            if ($type == 'static-page' || $type == 'all-static-pages') {
                return StaticPage::resolveMenuItem($item, $url, $theme);
            }
        });

        Event::listen('cms.template.save', function($controller, $template, $type) {
            Plugin::clearCache();
        });

        Event::listen('cms.template.processTwigContent', function($template, $dataHolder) {
            if ($template instanceof \Cms\Classes\Layout) {
                $dataHolder->content = Controller::instance()->parseSyntaxFields($dataHolder->content);
            }
        });

        Event::listen('backend.richeditor.listTypes', function () {
            return [
                'static-page' => 'Static page',
            ];
        });

        Event::listen('backend.richeditor.getTypeInfo', function ($type) {
            if ($type === 'static-page') {
                return StaticPage::getRichEditorTypeInfo($type);
            }
        });

        Event::listen('system.console.theme.sync.getAvailableModelClasses', function () {
            return [
                Classes\Menu::class,
                Classes\Page::class,
            ];
        });
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
