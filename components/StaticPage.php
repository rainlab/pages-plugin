<?php namespace RainLab\Pages\Components;

use Cms\Classes\ComponentBase;
use RainLab\Pages\Classes\Router;
use Cms\Classes\Theme;
use Request;

/**
 * The static page component.
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
     * @var string The static page meta title
     */
    public $title;

    /**
     * @var string The static page meta description
     */
    public $description;
    
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
            if ($this->page->getViewBag()->property('meta_title') == '')
            {
                $title = $this->page->getViewBag()->property('title');
            } else {
                $title = $this->page->getViewBag()->property('meta_title');
            }
            $this->title = $this->page['title'] = $title;
            $this->description = $this->page['description'] = $this->page->getViewBag()->property('meta_description');
            $this->content = $this->page['content'] = $this->page->markup;
        }
    }
}