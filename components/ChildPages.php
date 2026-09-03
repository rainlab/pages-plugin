<?php namespace RainLab\Pages\Components;

use Cms\Classes\ComponentBase;

/**
 * ChildPages component displays a list of child pages for the current page.
 */
class ChildPages extends ComponentBase
{
    /**
     * @var \RainLab\Pages\Components\StaticPage staticPageComponent reference
     */
    protected $staticPageComponent;

    /**
     * @var array childPages references to the child static page objects for the current page
     */
    protected $childPages;

    /**
     * @var array pages data for each child page
     */
    public $pages = [];

    /**
     * componentDetails
     */
    public function componentDetails()
    {
        return [
            'name'        => 'Child pages',
            'description' => 'Displays a list of child pages for the current page'
        ];
    }

    /**
     * onRun
     */
    public function onRun()
    {
        // Check if the staticPage component is attached to the rendering template
        $this->staticPageComponent = $this->findComponentByName('staticPage');
        if ($this->staticPageComponent->pageObject) {
            $this->childPages = $this->staticPageComponent->pageObject->getChildren();

            if ($this->childPages) {
                foreach ($this->childPages as $childPage) {
                    $viewBag = $childPage->viewBag;
                    $this->pages = array_merge($this->pages, [[
                        'url'                => @$viewBag['url'],
                        'title'              => @$viewBag['title'],
                        'page'               => $childPage,
                        'viewBag'            => $viewBag,
                        'is_hidden'          => @$viewBag['is_hidden'],
                        'navigation_hidden'  => @$viewBag['navigation_hidden'],
                    ]]);
                }
            }
        }
    }
}
