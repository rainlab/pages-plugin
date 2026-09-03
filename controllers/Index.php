<?php namespace RainLab\Pages\Controllers;

use BackendMenu;
use SystemException;
use Backend\Classes\Controller;
use Backend\Models\BrandSetting;
use Editor\Classes\ExtensionManager;
use RainLab\Pages\Classes\EditorExtension;

/**
 * Index Backend Controller hosts the Vue Editor shell scoped to the Pages extension.
 */
class Index extends Controller
{
    use \Backend\Traits\InspectableContainer;
    use \RainLab\Pages\Controllers\Index\HasSyntaxFields;
    use \RainLab\Pages\Controllers\Index\HasMenuItemForm;

    /**
     * @var array requiredPermissions to view this page.
     */
    public $requiredPermissions = ['rainlab.pages.*'];

    /**
     * @var array implement behaviors, using a scoped Editor state manager.
     */
    public $implement = [
        \RainLab\Pages\Behaviors\EditorState::class
    ];

    /**
     * @var string turboRouter forces a full reload, Turbo cannot patch a Vue-mounted DOM.
     */
    public $turboRouter = 'reload';

    /**
     * __construct the controller.
     */
    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('RainLab.Pages', 'pages');

        $this->bodyClass = 'compact-container editor-page backend-document-layout';
        $this->pageTitle = 'Pages';

        // Re-bind the syntax fields form on every request so its nested widgets
        // (repeater, mediafinder) can resolve their own AJAX handlers.
        $this->bindSyntaxFieldsWidget();

        // Re-bind the menu item form so its own widgets resolve their AJAX handlers.
        if (post('bindMenuItemForm')) {
            $this->makeMenuItemFormWidget();
        }
    }

    /**
     * index hosts the Editor Application Vue app.
     */
    public function index()
    {
        $this->addCss('/modules/editor/assets/css/editor.css');
        $this->addCss('/plugins/rainlab/pages/assets/css/editor.css');
        $this->addJs('/modules/editor/assets/js/editor.page.js', ['type' => 'module']);

        $this->registerVueComponent(\Backend\VueComponents\Document::class);
        $this->registerVueComponent(\Backend\VueComponents\Tabs::class);
        $this->registerVueComponent(\Backend\VueComponents\TreeView::class);
        $this->registerVueComponent(\Backend\VueComponents\Splitter::class);
        $this->registerVueComponent(\Backend\VueComponents\Modal::class);
        $this->registerVueComponent(\Backend\VueComponents\Inspector::class);
        $this->registerVueComponent(\Backend\VueComponents\Uploader::class);

        $this->registerVueComponent(\Editor\VueComponents\EditorConflictResolver::class);
        $this->registerVueComponent(\Editor\VueComponents\Application::class);

        // Register only the assets of extensions in the Pages context, keeping the page
        // scoped to it.
        $manager = ExtensionManager::instance();
        foreach ($manager->listJsFiles(EditorExtension::CONTEXT) as $jsFile) {
            $this->addJs($jsFile, ['type' => 'module']);
        }
        foreach ($manager->listVueComponents(EditorExtension::CONTEXT) as $componentClass) {
            $this->registerVueComponent($componentClass);
        }

        $this->vars['customLogo'] = BrandSetting::getLogo();
        $this->vars['initialState'] = $this->makeInitialState([]);
    }

    /**
     * index_onCommand routes a client command to an extension in the Pages context.
     */
    public function index_onCommand()
    {
        $namespace = post('extension');
        if (!is_scalar($namespace) || !strlen($namespace)) {
            throw new SystemException('Missing extension name');
        }

        // Only run commands for extensions belonging to the Pages context, keeping this
        // page isolated from the global editor extensions.
        $extension = ExtensionManager::instance()->getExtensionByNamespace($namespace);
        if (!$extension->hasEditorContext(EditorExtension::CONTEXT)) {
            throw new SystemException('Unsupported extension: '.$namespace);
        }

        $command = post('command');
        if (!is_scalar($command) || !strlen($command)) {
            throw new SystemException('Missing command');
        }

        return ExtensionManager::instance()->runCommand($namespace, $command, $this);
    }

    /**
     * onListExtensionNavigatorSections refreshes the navigator sections.
     */
    public function onListExtensionNavigatorSections()
    {
        $namespace = post('extension');
        if (!is_scalar($namespace) || !strlen($namespace)) {
            throw new SystemException('Missing extension namespace');
        }

        $documentType = post('documentType');
        if ($documentType && !is_scalar($documentType)) {
            throw new SystemException('Invalid document type');
        }

        $extension = ExtensionManager::instance()->getExtensionByNamespace($namespace);
        if (!$extension->hasEditorContext(EditorExtension::CONTEXT)) {
            throw new SystemException('Unsupported extension namespace');
        }

        $namespace = $extension->getNamespaceNormalized();

        return [
            'sections' => $this->listExtensionNavigatorSections($extension, $namespace, $documentType)
        ];
    }
}
