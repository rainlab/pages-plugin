<?php namespace RainLab\Pages\Components;

use Cms\Classes\ComponentBase;
use RainLab\Pages\Classes\Router;
use RainLab\Pages\Classes\MenuItemReference;
use RainLab\Pages\Classes\Page as StaticPageClass;
use Cms\Classes\Theme;

/**
 * StaticBreadcrumbs component outputs breadcrumbs for a static page.
 */
class StaticBreadcrumbs extends ComponentBase
{
    /**
     * @var array breadcrumbs of RainLab\Pages\Classes\MenuItemReference objects
     */
    public $breadcrumbs = [];

    /**
     * componentDetails
     */
    public function componentDetails()
    {
        return [
            'name'        => 'Static breadcrumbs',
            'description' => 'Outputs breadcrumbs for a static page.'
        ];
    }

    /**
     * onRun
     */
    public function onRun()
    {
        $url = $this->getRouter()->getUrl();

        if (!strlen($url)) {
            $url = '/';
        }

        $theme = Theme::getActiveTheme();
        $router = new Router($theme);
        $page = $router->findByUrl($url);

        if ($page) {
            $tree = StaticPageClass::buildMenuTree($theme);

            $code = $startCode = $page->getBaseFileName();
            $breadcrumbs = [];

            while ($code) {
                if (!isset($tree[$code])) {
                    break;
                }

                $pageInfo = $tree[$code];

                if ($pageInfo['navigation_hidden']) {
                    $code = $pageInfo['parent'];
                    continue;
                }

                $reference = new MenuItemReference();
                $reference->title = $pageInfo['title'];
                $reference->url = StaticPageClass::url($code);
                $reference->isActive = $code == $startCode;

                $breadcrumbs[] = $reference;

                $code = $pageInfo['parent'];
            }

            $breadcrumbs = array_reverse($breadcrumbs);

            $this->breadcrumbs = $this->page['breadcrumbs'] = $breadcrumbs;
        }
    }
}
