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
     * @var string The static page title
     */
    public $title;

    protected $contentCached = false;

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

        if ($this->page)
            $this->title = $this->page['title'] = $this->page->getViewBag()->property('title');
    }

    public function content()
    {
        // Evaluate the content property only when it's requested in the
        // render time. Calling the page's getProcessedMarkup() method in the
        // onRun() handler is too early as it triggers rendering component-based
        // snippets defined on the static page too early in the page life cycle. -ab
        
        if ($this->contentCached !== false)
            return $this->contentCached;

        if ($this->page)
            return $this->contentCached = $this->page->getProcessedMarkup();

        $this->contentCached = '';
    }
}