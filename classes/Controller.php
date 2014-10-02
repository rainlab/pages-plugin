<?php namespace RainLab\Pages\Classes;

use Cms\Classes\Page;
use Cms\Classes\Theme;
use Cms\Classes\Layout;
use RainLab\Pages\Classes\Router;

/**
 * Represents a static page controller.
 *
 * @package rainlab\pages
 * @author Alexey Bobkov, Samuel Georges
 */
class Controller
{
    protected $theme;

    /**
     * Creates the controller.
     * @param \Cms\Classes\Theme $theme Specifies the CMS theme.
     * If the theme is not specified, the current active theme used.
     */
    public function __construct($theme = null)
    {
        $this->theme = $theme ? $theme : Theme::getActiveTheme();
        if (!$this->theme)
            throw new CmsException(Lang::get('cms::lang.theme.active.not_found'));
    }

    /**
     * Creates a CMS page from a static page and configures it.
     * @param string $url Specifies the static page URL.
     * @return \Cms\Classes\Page Returns the CMS page object or NULL of the requested page was not found.
     */
    public function initCmsPage($url)
    {
        $router = new Router($this->theme);

        $page = $router->findByUrl($url);
        if (!$page)
            return null;

        $viewBag = $page->getViewBag();

        $cmsPage = new Page($this->theme);
        $cmsPage->title = $viewBag->property('title');
        $cmsPage->settings['url'] = $url;
        $cmsPage->settings['components'] = [];
        $cmsPage->settings['hidden'] = $viewBag->property('hidden');
        $cmsPage->settings['layout'] = $viewBag->property('layout');

        return $cmsPage;
    }
}