<?php namespace RainLab\Pages\Components;

use Cms\Classes\ComponentBase;
use RainLab\Pages\Classes\Router;
use RainLab\Pages\Classes\MenuItemReference;
use RainLab\Pages\Classes\Page as StaticPageClass;
use Cms\Classes\Theme;
use Request;
use URL;

/**
 * The static breadcrumbs component.
 *
 * @package rainlab\pages
 * @author Alexey Bobkov, Samuel Georges
 */
class StaticBreadcrumbs extends ComponentBase
{
    /**
     * @var array An array of the RainLab\Pages\Classes\MenuItemReference class.
     */
    public $breadcrumbs = [];

    public function componentDetails()
    {
        return [
            'name'        => 'Static breadcrumbs',
            'description' => 'Outputs breadcrumbs for a static page.'
        ];
    }

    public function onRun()
    {
        $url = Request::path();
        
        if (!strlen($url))
            $url = '/';

        $theme =Theme::getActiveTheme();
        $router = new Router($theme);
        $page = $router->findByUrl($url);

        if ($page) {
            $tree = StaticPageClass::buildMenuTree($theme);

            $code = $startCode = $page->getBaseFileName();
            $breadcrumbs = [];

            while ($code) {
                if (!isset($tree[$code]))
                    continue;

                $pageInfo = $tree[$code];
                $reference = new MenuItemReference();
                $reference->title = $pageInfo['title'];
                $reference->url = URL::to($pageInfo['url']);
                $reference->isActive = $code == $startCode;

                $breadcrumbs[] = $reference;

                $code = $pageInfo['parent'];
            }

            $breadcrumbs = array_reverse($breadcrumbs);

            $this->breadcrumbs = $this->page['breadcrumbs'] = $breadcrumbs;
        }
    }
}