<?php namespace RainLab\Pages\Controllers;

use URL;
use Lang;
use Flash;
use Event;
use Config;
use Request;
use Response;
use Exception;
use BackendMenu;
use Cms\Classes\Theme;
use Backend\Classes\Controller;
use Backend\Classes\WidgetManager;
use System\Classes\ApplicationException;
use Backend\Traits\InspectableContainer;
use RainLab\Pages\Widgets\PageList;
use RainLab\Pages\Classes\Page as StaticPage;
use RainLab\Pages\Classes\Router;

/**
 * Pages and Menus index
 *
 * @package rainlab\pages
 * @author Alexey Bobkov, Samuel Georges
 */
class Index extends Controller
{
    use InspectableContainer;

    protected $theme;

    public $requiredPermissions = ['rainlab.pages.*'];

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('RainLab.Pages', 'pages', 'pages');

        try {
            if (!($this->theme = Theme::getEditTheme()))
                throw new ApplicationException(Lang::get('cms::lang.theme.edit.not_found'));

            new PageList($this, 'pageList');
        }
        catch (Exception $ex) {
            $this->handleError($ex);
        }

        $this->addJs('/modules/backend/assets/js/october.treeview.js', 'core');
        $this->addJs('/plugins/rainlab/pages/assets/js/pages-page.js');

        $this->bodyClass = 'compact-container side-panel-not-fixed';
        $this->pageTitle = Lang::get('rainlab.pages::lang.plugin_name');
        $this->pageTitleTemplate = '%s Pages | October';
    }

    //
    // Pages, menus and text blocks
    //

    public function index()
    {
    }

    public function index_onOpen()
    {
        $this->validateRequestTheme();

        $type = Request::input('type');
        $object = $this->loadObject($type, Request::input('path'));
        $widget = $this->makeObjectFormWidget($type, $object);

        $this->vars['objectPath'] = Request::input('path');

        if ($type == 'page')
            $this->vars['pageUrl'] = $object->getViewBag()->property('url');

        return [
            'tabTitle' => $this->getTabTitle($type, $object),
            'tab' => $this->makePartial('form_page', [
                'form'          => $widget,
                'objectType'  => $type,
                'objectTheme' => $this->theme->getDirName(),
                'objectMtime' => $object->mtime
            ])
        ];
    }

    public function onSave()
    {
        $this->validateRequestTheme();
        $type = Request::input('objectType');

        $objectPath = trim(Request::input('objectPath'));
        $object = $objectPath ? $this->loadObject($type, $objectPath) : $this->createObject($type);

        $settings = $this->formatSettings($this->formatSettings());

        $objectData = [];
        if ($settings)
            $objectData['settings'] = $settings;

        $fields = ['markup', 'code', 'fileName', 'content', 'parent'];
        foreach ($fields as $field) {
            if (array_key_exists($field, $_POST))
                $objectData[$field] = Request::input($field);
        }

        if (!empty($objectData['markup']) && Config::get('cms.convertLineEndings', false) === true)
            $objectData['markup'] = $this->convertLineEndings($objectData['markup']);

        if (!Request::input('objectForceSave') && $object->mtime) {
            if (Request::input('objectMtime') != $object->mtime)
                throw new ApplicationException('mtime-mismatch');
        }

        $object->fill($objectData);
        $object->save();

        $result = [
            'objectPath' => $object->getBaseFileName(),
            'objectMtime' => $object->mtime,
            'tabTitle'        => $this->getTabTitle($type, $object)
        ];

        if ($type == 'page') {
            $result['pageUrl'] = $object->getViewBag()->property('url');
            $router = new Router($this->theme);
            $router->clearCache();
        }

        Flash::success(Lang::get('rainlab.pages::lang.page.saved'));

        return $result;
    }

    public function onCreateObject()
    {
        $this->validateRequestTheme();

        $type = Request::input('type');
        $object = $this->createObject($type);
        $parent = Request::input('parent');
        $parentPage = null;

        if ($type == 'page' && strlen($parent))
            $parentPage = StaticPage::load($this->theme, $parent);

        $widget = $this->makeObjectFormWidget($type, $object);
        $this->vars['objectPath'] = '';

        $result = [
            'tabTitle' => $this->getTabTitle($type, $object),
            'tab' => $this->makePartial('form_page', [
                'form'         => $widget,
                'objectType'   => $type,
                'objectTheme'  => $this->theme->getDirName(),
                'objectMtime'  => null,
                'objectParent' => $parent,
                'parentPage'   => $parentPage
            ])
        ];

        return $result;
    }

    public function onDelete()
    {
        $this->validateRequestTheme();

        $type = Request::input('objectType');

        $deletedObjects = $this->loadObject($type, trim(Request::input('objectPath')))->delete();

        $result = [
            'deletedObjects' => $deletedObjects,
            'theme' => $this->theme->getDirName()
        ];

        return $result;
    }

    public function onDeleteObjects()
    {
        $this->validateRequestTheme();

        $type = Request::input('type');
        $objects = Request::input('object');
        $error = null;
        $deleted = [];

        try {
            foreach ($objects as $path=>$selected) {
                if ($selected) {
                    $object = $this->loadObject($type, $path, true);
                    if ($object)
                        $deleted = array_merge($deleted, $object->delete());
                }
            }
        }
        catch (Exception $ex) {
            $error = $ex->getMessage();
        }

        return [
            'deleted' => $deleted,
            'error'   => $error,
            'theme'   => Request::input('theme')
        ];
    }

    public function onOpenConcurrencyResolveForm()
    {
        return $this->makePartial('concurrency_resolve_form');
    }

    //
    // Methods for the internal use
    //

    protected function validateRequestTheme()
    {
        if ($this->theme->getDirName() != Request::input('theme'))
            throw new ApplicationException(trans('cms::lang.theme.edit.not_match'));
    }

    protected function loadObject($type, $path, $ignoreNotFound = false)
    {
        $class = $this->resolveTypeClassName($type);

        if (!($object = call_user_func(array($class, 'load'), $this->theme, $path))) {
            if (!$ignoreNotFound)
                throw new ApplicationException(trans('rainlab.pages::lang.object.not_found'));

            return null;
        }

        return $object;
    }

    protected function createObject($type)
    {
        $class = $this->resolveTypeClassName($type);

        if (!($object = new $class($this->theme)))
            throw new ApplicationException(trans('rainlab.pages::lang.object.not_found'));

        return $object;
    }

    protected function resolveTypeClassName($type)
    {
        $types = [
            'page'    => 'RainLab\Pages\Classes\Page'
        ];

        if (!array_key_exists($type, $types))
            throw new ApplicationException(trans('rainlab.pages::lang.object.invalid_type'));

        return $types[$type];
    }

    protected function makeObjectFormWidget($type, $object)
    {
        $formConfigs = [
            'page'    => '@/plugins/rainlab/pages/classes/page/fields.yaml'
        ];

        if (!array_key_exists($type, $formConfigs))
            throw new ApplicationException(trans('rainlab.pages::lang.object.not_found'));

        $widgetConfig = $this->makeConfig($formConfigs[$type]);
        $widgetConfig->model = $object;
        $widgetConfig->alias = 'form'.studly_case($type).md5($object->getFileName()).uniqid();

        $widget = $this->makeWidget('Backend\Widgets\Form', $widgetConfig);

        return $widget;
    }

    protected function getTabTitle($type, $object)
    {
        if ($type == 'page') {
            $viewBag = $object->getViewBag();
            $result = $viewBag ? $viewBag->property('title') : false;
            if (!$result)
                $result = trans('rainlab.pages::lang.page.new');

            return $result;
        }

        return $template->getFileName();
    }

    protected function formatSettings()
    {
        $settings = [];

        if (!array_key_exists('viewBag', $_POST))
            return $settings;

        $settings['viewBag'] = $_POST['viewBag'];

        return $settings;
    }
}