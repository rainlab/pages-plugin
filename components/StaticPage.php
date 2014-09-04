<?php namespace RainLab\Pages\Components;

use Cms\Classes\ComponentBase;
use RainLab\Pages\Classes\Router;
use Cms\Classes\Theme;
use Request;

/**
 * The A static page component.
 *
 * @package rainlab\pages
 * @author Alexey Bobkov, Samuel Georges
 */
class StaticPage extends ComponentBase
{
    /**
     * @var \RainLab\Pages\Classes\Page A reference to the static page object
     */
    public $page;

    /**
     * @var string The static page title
     */
    public $title;

    /**
     * @var string The static page content
     */
    public $content;

    public function componentDetails()
    {
        return [
            'name'        => 'Static page',
            'description' => 'Outputs a static page in a CMS layout.'
        ];
    }

    public function onRun()
    {
        $url = Request::path();
        
        if (!strlen($url))
            $url = '/';

        $router = new Router(Theme::getActiveTheme());
        $this->page = $this->page['page'] = $router->findByUrl($url);

        if ($this->page) {
            $this->title = $this->page['title'] = $this->page->getViewBag()->property('title');
            $this->content = $this->page['content'] = $this->page->markup;
        }
    }
}