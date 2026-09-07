<?php namespace RainLab\Pages\Components;

use Cms\Classes\Theme;
use Cms\Classes\ComponentBase;
use RainLab\Pages\Classes\Router;
use Cms\Models\MaintenanceSetting;

/**
 * StaticPage component outputs a static page in a CMS layout.
 */
class StaticPage extends ComponentBase
{
    /**
     * @var \RainLab\Pages\Classes\Page pageObject reference to the static page object
     */
    public $pageObject;

    /**
     * @var string title of the static page
     */
    public $title;

    /**
     * @var array extraData added by syntax fields
     */
    public $extraData = [];

    /**
     * @var string contentCached
     */
    protected $contentCached = false;

    /**
     * componentDetails
     */
    public function componentDetails()
    {
        return [
            'name'        => 'Static page',
            'description' => 'Outputs a static page in a CMS layout.'
        ];
    }

    /**
     * defineProperties
     */
    public function defineProperties()
    {
        return [
            'useContent' => [
                'title'             => 'Use page content field',
                'description'       => 'If unchecked, the content section will not appear when editing the static page. Page content will be determined solely through placeholders and variables.',
                'default'           => 1,
                'type'              => 'checkbox',
                'showExternalParam' => false
            ],
            'default' => [
                'title'             => 'Default layout',
                'description'       => 'Defines this layout as the default for new pages',
                'default'           => 0,
                'type'              => 'checkbox',
                'showExternalParam' => false
            ],
            'childLayout' => [
                'title'             => 'Subpage layout',
                'description'       => 'The layout to use as the default for any new subpages',
                'type'              => 'string',
                'showExternalParam' => false
            ]
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

        if ($this->isMaintenanceModeEnabled()) {
            return;
        }

        $router = new Router(Theme::getActiveTheme());
        $this->pageObject = $this->page['page'] = $router->findByUrl($url);

        if ($this->pageObject) {
            $this->title = $this->page['title'] = array_get($this->pageObject->viewBag, 'title');
            $this->extraData = $this->page['extraData'] = $this->defineExtraData();
        }
    }

    /**
     * page returns the static page object
     */
    public function page()
    {
        return $this->pageObject;
    }

    /**
     * parent returns the parent static page
     */
    public function parent()
    {
        return $this->pageObject ? $this->pageObject->getParent() : null;
    }

    /**
     * children returns the child static pages
     */
    public function children()
    {
        return $this->pageObject ? $this->pageObject->getChildren() : null;
    }

    /**
     * content returns the processed page markup
     */
    public function content()
    {
        // Evaluate the content property only when it's requested in the
        // render time. Calling the page's getProcessedMarkup() method in the
        // onRun() handler is too early as it triggers rendering component-based
        // snippets defined on the static page too early in the page life cycle. -ab

        if ($this->contentCached !== false) {
            return $this->contentCached;
        }

        if ($this->pageObject) {
            return $this->contentCached = $this->pageObject->getProcessedMarkup();
        }

        $this->contentCached = '';
    }

    /**
     * defineExtraData finds foreign view bag values and adds them to the component and page vars.
     */
    protected function defineExtraData()
    {
        $standardProperties = [
            'title',
            'url',
            'layout',
            'is_hidden',
            'navigation_hidden',
            'meta_title',
            'meta_description'
        ];

        $extraData = array_diff_key(
            $this->pageObject->viewBag,
            array_flip($standardProperties)
        );

        foreach ($extraData as $key => $value) {
            $this->page[$key] = $value;
        }

        return $extraData;
    }

    /**
     * isMaintenanceModeEnabled will check if maintenance mode is currently enabled.
     * Static page logic should be disabled when this occurs.
     */
    protected function isMaintenanceModeEnabled(): bool
    {
        // Logic for October CMS v2.0
        if (method_exists(MaintenanceSetting::class, 'isEnabled')) {
            return MaintenanceSetting::isEnabled();
        }

        // Logic for October CMS v1.0
        return MaintenanceSetting::isConfigured() &&
            MaintenanceSetting::get('is_enabled', false) &&
            !\BackendAuth::getUser();
    }

    /**
     * __get implements the getter functionality for extra data.
     * @param  string  $name
     */
    public function __get($name)
    {
        if (array_key_exists($name, $this->extraData)) {
            return $this->extraData[$name];
        }

        return null;
    }

    /**
     * __isset determines if an extra data attribute exists.
     * @param  string  $key
     */
    public function __isset($key)
    {
        if (array_key_exists($key, $this->extraData)) {
            return true;
        }

        return false;
    }
}
