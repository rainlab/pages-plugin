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
use ApplicationException;
use Backend\Traits\InspectableContainer;
use RainLab\Pages\Widgets\PageList;
use RainLab\Pages\Widgets\MenuList;
use RainLab\Pages\Widgets\SnippetList;
use RainLab\Pages\Classes\Snippet;
use RainLab\Pages\Classes\Page as StaticPage;
use RainLab\Pages\Classes\Router;
use RainLab\Pages\Classes\MenuItem;
use Cms\Classes\Content;
use Cms\Widgets\TemplateList;
use RainLab\Pages\Plugin as PagesPlugin;
use RainLab\Pages\Classes\SnippetManager;

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
            new MenuList($this, 'menuList');
            new SnippetList($this, 'snippetList');

            $theme = $this->theme;
            new TemplateList($this, 'contentList', function() use ($theme) {
                return Content::listInTheme($theme, true);
            });
        }
        catch (Exception $ex) {
            $this->handleError($ex);
        }

        $this->addJs('/modules/backend/assets/js/october.treeview.js', 'core');
        $this->addJs('/plugins/rainlab/pages/assets/js/pages-page.js');
        $this->addJs('/plugins/rainlab/pages/assets/js/pages-snippets.js');
        $this->addCss('/plugins/rainlab/pages/assets/css/pages.css');

        // Preload the code editor class as it could be needed
        // before it loads dynamically.
        $this->addJs('/modules/backend/formwidgets/codeeditor/assets/js/codeeditor.js', 'core');

        $this->bodyClass = 'compact-container side-panel-not-fixed';
        $this->pageTitle = 'rainlab.pages::lang.plugin.name';
        $this->pageTitleTemplate = '%s Pages';
    }

    //
    // Pages, menus and text blocks
    //

    public function index()
    {
        // Preload Ace editor modes explicitly, because they could be changed dynamically
        // depending on a content block type
        $this->addJs('/modules/backend/formwidgets/codeeditor/assets/vendor/ace/ace.js', 'core');

        $aceModes = ['markdown', 'plain_text', 'html', 'less', 'css', 'scss', 'sass', 'javascript'];
        foreach ($aceModes as $mode) {
            $this->addJs('/modules/backend/formwidgets/codeeditor/assets/vendor/ace/mode-'.$mode.'.js', 'core');
        }
    }

    public function index_onOpen()
    {
        $this->validateRequestTheme();

        $type = Request::input('type');
        $object = $this->loadObject($type, Request::input('path'));

        return $this->pushObjectForm($type, $object);
    }

    public function onSave()
    {
        $this->validateRequestTheme();
        $type = Request::input('objectType');

        $object = $this->fillObjectFromPost($type);

        $object->save();

        $result = [
            'objectPath'  => $type != 'content' ? $object->getBaseFileName() : $object->fileName,
            'objectMtime' => $object->mtime,
            'tabTitle'    => $this->getTabTitle($type, $object)
        ];

        if ($type == 'page') {
            $result['pageUrl'] = URL::to($object->getViewBag()->property('url'));

            PagesPlugin::clearCache();
        }

        $successMessages = [
            'page' => 'rainlab.pages::lang.page.saved',
            'menu' => 'rainlab.pages::lang.menu.saved'
        ];

        $successMessage = isset($successMessages[$type])
            ? $successMessages[$type]
            : $successMessages['page'];

        Flash::success(Lang::get($successMessage));

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
        if (!$objects)
            $objects = Request::input('template');

        $error = null;
        $deleted = [];

        try {
            foreach ($objects as $path=>$selected) {
                if ($selected) {
                    $object = $this->loadObject($type, $path, true);
                    if ($object) {
                        $deletedObjects = $object->delete();
                        if (is_array($deletedObjects))
                            $deleted = array_merge($deleted, $deletedObjects);
                        else
                            $deleted[] = $path;
                    }
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

    public function onGetMenuItemTypeInfo()
    {
        $type = Request::input('type');

        return [
            'menuItemTypeInfo' => MenuItem::getTypeInfo($type)
        ];
    }

    public function onUpdatePageLayout()
    {
        $this->validateRequestTheme();
        $type = Request::input('objectType');

        $object = $this->fillObjectFromPost($type);

        return $this->pushObjectForm($type, $object);
    }

    public function onGetInspectorConfiguration()
    {
        $configuration = [];

        $snippetCode = Request::input('snippet');
        $componentClass = Request::input('component');

        if (strlen($snippetCode)) {
            $snippet = SnippetManager::instance()->findByCodeOrComponent($this->theme, $snippetCode, $componentClass);
            if (!$snippet)
                throw new ApplicationException(sprintf(trans('rainlab.pages::lang.snippet.not_found'), $snippetCode));

            $configuration = $snippet->getProperties();
        }

        return [
            'configuration' => [
                'properties'=>$configuration,
                'title' => $snippet->getName(),
                'description' => $snippet->getDescription()
            ]
        ];
    }

    public function onGetSnippetNames()
    {
        $codes = array_unique(Request::input('codes'));
        $result = [];
        foreach ($codes as $snippetCode) {
            $parts = explode('|', $snippetCode);
            $componentClass = null;

            if (count($parts) > 1) {
                $snippetCode = $parts[0];
                $componentClass = $parts[1];
            }

            $snippet = SnippetManager::instance()->findByCodeOrComponent($this->theme, $snippetCode, $componentClass);
            if (!$snippet)
                $result[$snippetCode] = sprintf(trans('rainlab.pages::lang.snippet.not_found'), $snippetCode);
            else
                $result[$snippetCode] =$snippet->getName();
        }

        return [
            'names' => $result
        ];
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
            if (!$ignoreNotFound) {
                throw new ApplicationException(trans('rainlab.pages::lang.object.not_found'));
            }

            return null;
        }

        if ($type == 'content') {
            $fileName = $object->getFileName();
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);

            if ($extension == 'htm') {
                $object->markup_html = $object->markup;
            }
        }

        return $object;
    }

    protected function createObject($type)
    {
        $class = $this->resolveTypeClassName($type);

        if (!($object = new $class($this->theme))) {
            throw new ApplicationException(trans('rainlab.pages::lang.object.not_found'));
        }

        return $object;
    }

    protected function resolveTypeClassName($type)
    {
        $types = [
            'page' => 'RainLab\Pages\Classes\Page',
            'menu' => 'RainLab\Pages\Classes\Menu',
            'content' => 'Cms\Classes\Content'
        ];

        if (!array_key_exists($type, $types)) {
            throw new ApplicationException(trans('rainlab.pages::lang.object.invalid_type'));
        }

        return $types[$type];
    }

    protected function makeObjectFormWidget($type, $object)
    {
        $formConfigs = [
            'page' => '~/plugins/rainlab/pages/classes/page/fields.yaml',
            'menu' => '~/plugins/rainlab/pages/classes/menu/fields.yaml',
            'content' => '~/plugins/rainlab/pages/classes/content/fields.yaml'
        ];

        if (!array_key_exists($type, $formConfigs)) {
            throw new ApplicationException(trans('rainlab.pages::lang.object.not_found'));
        }

        $widgetConfig = $this->makeConfig($formConfigs[$type]);
        $widgetConfig->model = $object;
        $widgetConfig->alias = 'form'.studly_case($type).md5($object->getFileName()).uniqid();

        $widget = $this->makeWidget('Backend\Widgets\Form', $widgetConfig);

        if ($type == 'page') {
            $widget->bindEvent('form.extendFieldsBefore', function() use ($widget, $object) {
                $this->addPagePlaceholders($widget, $object);
            });
        }

        return $widget;
    }

    protected function addPagePlaceholders($formWidget, $page)
    {
        $placeholders = $page->listLayoutPlaceholders();

        foreach ($placeholders as $placeholderCode=>$info) {
            $placeholderTitle = $info['title'];
            $fieldConfig = [
                'tab' => $placeholderTitle,
                'stretch' => '1',
                'size' => 'huge'
            ];

            if ($info['type'] != 'text') {
                $fieldConfig['type'] = 'richeditor';
            }
            else {
                $fieldConfig['type'] = 'codeeditor';
                $fieldConfig['language'] = 'text';
                $fieldConfig['theme'] = 'chrome';
                $fieldConfig['showGutter'] = false;
                $fieldConfig['highlightActiveLine'] = false;
                $fieldConfig['cssClass'] = 'pagesTextEditor';
                $fieldConfig['showInvisibles'] = false;
                $fieldConfig['fontSize'] = 13;
                $fieldConfig['wordWrap'] = '80';
            }

            $formWidget->secondaryTabs['fields']['placeholders['.$placeholderCode.']'] = $fieldConfig;
        }

        $placeholderValues = $page->getPlaceholderValues();
        foreach ($placeholderValues as $name => $value) {
            $page->placeholders->add($name, $value);
        }
    }

    protected function getTabTitle($type, $object)
    {
        if ($type == 'page') {
            $viewBag = $object->getViewBag();
            $result = $viewBag ? $viewBag->property('title') : false;
            if (!$result) {
                $result = trans('rainlab.pages::lang.page.new');
            }

            return $result;
        }
        elseif ($type == 'menu') {
            $result = $object->name;
            if (!strlen($result)) {
                $result = trans('rainlab.pages::lang.menu.new');
            }

            return $result;
        }
        elseif ($type == 'content') {
            $result = in_array($type, ['asset', 'content'])
                ? $object->getFileName()
                : $object->getBaseFileName();

            if (!$result) {
                $result = trans('cms::lang.'.$type.'.new');
            }

            return $result;
        }

        return $object->getFileName();
    }

    protected function formatSettings()
    {
        $settings = [];

        if (!array_key_exists('viewBag', $_POST))
            return $settings;

        $settings['viewBag'] = $_POST['viewBag'];

        return $settings;
    }

    protected function setPlaceholders($page)
    {
        $data = post();
        if (!array_key_exists('placeholders', $data))
            return null;

        $placeholderData = $data['placeholders'];
        $placeholders = $page->listLayoutPlaceholders();

        $result = null;
        foreach ($placeholders as $placeholderCode=>$info) {
            if (!array_key_exists($placeholderCode, $placeholderData))
                continue;

            $placeholderValue = trim($placeholderData[$placeholderCode]);

            if (strlen($placeholderValue)) {
                $putCode = "{% put $placeholderCode %}".PHP_EOL;
                $putCode .= $placeholderValue.PHP_EOL;
                $putCode .= "{% endput %}".PHP_EOL.PHP_EOL;

                $result .= $putCode;
            }
        }

        return trim($result);
    }

    protected function fillObjectFromPost($type)
    {
        $objectPath = trim(Request::input('objectPath'));
        $object = $objectPath ? $this->loadObject($type, $objectPath) : $this->createObject($type);

        $settings = $this->formatSettings($this->formatSettings());

        $objectData = [];
        if ($settings)
            $objectData['settings'] = $settings;

        $fields = ['markup', 'code', 'fileName', 'content', 'itemData', 'name'];

        if ($type != 'menu' && $type != 'content') {
            $fields[] = 'parent';
        }

        foreach ($fields as $field) {
            if (array_key_exists($field, $_POST)) {
                $objectData[$field] = Request::input($field);
            }
        }

        if ($type == 'page') {
            $objectData['code'] = $this->setPlaceholders($object);

            if (!empty($objectData['code']) && Config::get('cms.convertLineEndings', false) === true)
                $objectData['code'] = $this->convertLineEndings($objectData['code']);
        }

        if (!empty($objectData['markup']) && Config::get('cms.convertLineEndings', false) === true)
            $objectData['markup'] = $this->convertLineEndings($objectData['markup']);

        if (!Request::input('objectForceSave') && $object->mtime) {
            if (Request::input('objectMtime') != $object->mtime) {
                throw new ApplicationException('mtime-mismatch');
            }
        }

        if ($type == 'content') {
            $fileName = $objectData['fileName'];

            if (dirname($fileName) == 'static-pages') {
                throw new ApplicationException(trans('rainlab.pages::lang.content.cant_save_to_dir'));
            }
        }

        $object->fill($objectData);
        return $object;
    }

    protected function pushObjectForm($type, $object)
    {
        $widget = $this->makeObjectFormWidget($type, $object);

        $this->vars['objectPath'] = Request::input('path');

        if ($type == 'page') {
            $this->vars['pageUrl'] = URL::to($object->getViewBag()->property('url'));
        }

        return [
            'tabTitle' => $this->getTabTitle($type, $object),
            'tab' => $this->makePartial('form_page', [
                'form'        => $widget,
                'objectType'  => $type,
                'objectTheme' => $this->theme->getDirName(),
                'objectMtime' => $object->mtime
            ])
        ];
    }

    /**
     * Replaces Windows style (/r/n) line endings with unix style (/n)
     * line endings.
     * @param string $markup The markup to convert to unix style endings
     * @return string
     */
    protected function convertLineEndings($markup)
    {
        $markup = str_replace("\r\n", "\n", $markup);
        $markup = str_replace("\r", "\n", $markup);
        return $markup;
    }
}
