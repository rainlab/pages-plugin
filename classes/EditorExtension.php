<?php namespace RainLab\Pages\Classes;

use BackendAuth;
use SystemException;
use ApplicationException;
use Cms\Classes\Theme;
use RainLab\Pages\Classes\Page as StaticPage;
use RainLab\Pages\Classes\Menu;
use RainLab\Pages\Classes\Content;
use Editor\Classes\ExtensionBase;
use Editor\Classes\NewDocumentDescription;
use Backend\VueComponents\TreeView\SectionList;
use Backend\VueComponents\TreeView\NodeDefinition;
use Backend\VueComponents\DropdownMenu\ItemDefinition;

/**
 * EditorExtension adds static pages, menus and content to the October Editor IDE.
 */
class EditorExtension extends ExtensionBase
{
    use \RainLab\Pages\Classes\EditorExtension\HasStaticPageCrud;
    use \RainLab\Pages\Classes\EditorExtension\HasMenuCrud;
    use \RainLab\Pages\Classes\EditorExtension\HasContentCrud;

    const DOCUMENT_TYPE_PAGE = 'static-page';
    const DOCUMENT_TYPE_MENU = 'menu';
    const DOCUMENT_TYPE_CONTENT = 'content';

    const ICON_COLOR_PAGE = '#6a70f2';
    const ICON_COLOR_MENU = '#e15b64';
    const ICON_COLOR_CONTENT = '#4f9d69';

    const DOCUMENT_TYPE_PERMISSIONS = [
        self::DOCUMENT_TYPE_PAGE => ['rainlab.pages.manage_pages'],
        self::DOCUMENT_TYPE_MENU => ['rainlab.pages.manage_menus'],
        self::DOCUMENT_TYPE_CONTENT => ['rainlab.pages.manage_content']
    ];

    /**
     * @var string CONTEXT is the editor context this extension is hosted in - its own
     * dedicated Pages backend page, not the global /admin/editor IDE.
     */
    const CONTEXT = 'pages';

    /**
     * getNamespace returns the unique extension namespace.
     */
    public function getNamespace(): string
    {
        return 'pages';
    }

    /**
     * getEditorContext scopes this extension to its own Pages page only, keeping it out
     * of the global Editor IDE (and keeping the global extensions out of the Pages page).
     */
    public function getEditorContext(): string
    {
        return self::CONTEXT;
    }

    /**
     * getExtensionSortOrder affects the extension position in the Editor Navigator.
     */
    public function getExtensionSortOrder()
    {
        return 30;
    }

    /**
     * hasAccessToDocType returns true if the user can manage a document type.
     */
    public static function hasAccessToDocType($user, $documentType): bool
    {
        if (!array_key_exists($documentType, self::DOCUMENT_TYPE_PERMISSIONS)) {
            throw new SystemException(sprintf('Unknown document type: %s', $documentType));
        }

        return $user && $user->hasAnyAccess(self::DOCUMENT_TYPE_PERMISSIONS[$documentType]);
    }

    /**
     * assertDocumentTypePermissions guards a command against unauthorized access.
     */
    protected function assertDocumentTypePermissions($documentType)
    {
        if (!self::hasAccessToDocType(BackendAuth::getUser(), $documentType)) {
            throw new ApplicationException(__("You don't have permissions to manage :type documents.", ['type' => $documentType]));
        }
    }

    /**
     * resolveRequestDocumentType normalizes a posted document type value.
     */
    protected function resolveRequestDocumentType($value): string
    {
        return in_array($value, [self::DOCUMENT_TYPE_MENU, self::DOCUMENT_TYPE_CONTENT], true)
            ? $value
            : self::DOCUMENT_TYPE_PAGE;
    }

    /**
     * command_onOpenDocument dispatches to the handler for the requested document type.
     */
    protected function command_onOpenDocument($controller)
    {
        $type = $this->resolveRequestDocumentType(array_get((array) post('documentData'), 'type'));
        $this->assertDocumentTypePermissions($type);

        switch ($type) {
            case self::DOCUMENT_TYPE_MENU:
                return $this->openMenuDocument($controller);
            case self::DOCUMENT_TYPE_CONTENT:
                return $this->openContentDocument($controller);
            default:
                return $this->openPageDocument($controller);
        }
    }

    /**
     * command_onSaveDocument dispatches to the handler for the requested document type.
     */
    protected function command_onSaveDocument($controller)
    {
        $type = $this->resolveRequestDocumentType(array_get((array) post('documentMetadata'), 'type'));
        $this->assertDocumentTypePermissions($type);

        switch ($type) {
            case self::DOCUMENT_TYPE_MENU:
                return $this->saveMenuDocument($controller);
            case self::DOCUMENT_TYPE_CONTENT:
                return $this->saveContentDocument($controller);
            default:
                return $this->savePageDocument($controller);
        }
    }

    /**
     * command_onPageStructureUpdate persists a reordered/re-nested static page tree.
     */
    protected function command_onPageStructureUpdate($controller)
    {
        $this->assertDocumentTypePermissions(self::DOCUMENT_TYPE_PAGE);

        return $this->updatePageStructure($controller);
    }

    /**
     * command_onDeleteDocument dispatches to the handler for the requested document type.
     */
    protected function command_onDeleteDocument($controller)
    {
        $type = $this->resolveRequestDocumentType(array_get((array) post('documentMetadata'), 'type'));
        $this->assertDocumentTypePermissions($type);

        switch ($type) {
            case self::DOCUMENT_TYPE_MENU:
                return $this->deleteMenuDocument($controller);
            case self::DOCUMENT_TYPE_CONTENT:
                return $this->deleteContentDocument($controller);
            default:
                return $this->deletePageDocument($controller);
        }
    }

    /**
     * listJsFiles returns the client-side extension bundle.
     */
    public function listJsFiles()
    {
        return [
            '/plugins/rainlab/pages/assets/js/pages.editor.extension.js'
        ];
    }

    /**
     * listVueComponents returns the Vue components required by the extension.
     */
    public function listVueComponents()
    {
        return [
            \RainLab\Pages\VueComponents\StaticPageEditor::class,
            \RainLab\Pages\VueComponents\MenuEditor::class,
            \RainLab\Pages\VueComponents\ContentEditor::class
        ];
    }

    /**
     * getSettingsForms returns the Inspector settings forms per document type.
     * Menus have no settings form - the name and code are edited in the
     * document header, which hides the Settings toolbar button.
     */
    public function getSettingsForms()
    {
        return [
            self::DOCUMENT_TYPE_PAGE => $this->loadLocalizedSettingsFields(\RainLab\Pages\Classes\StaticPage\Fields::class)
        ];
    }

    /**
     * loadLocalizedSettingsFields loads and localizes an Inspector settings fields class.
     */
    protected function loadLocalizedSettingsFields(string $fieldsClass)
    {
        $fields = $this->loadSettingsFields($fieldsClass);

        array_walk_recursive($fields, function (&$value, $key) {
            if (is_string($value)) {
                $value = trans($value);
            }
        });

        return $fields;
    }

    /**
     * getNewDocumentsData returns the new document descriptions per document type.
     */
    public function getNewDocumentsData()
    {
        $description = new NewDocumentDescription(
            __("New page"),
            [
                'mtime' => null,
                'path' => null,
                'type' => self::DOCUMENT_TYPE_PAGE,
                'isNewDocument' => true
            ]
        );

        $description->setIcon(self::ICON_COLOR_PAGE, 'backend-icon-background entity-small cms-page');
        $description->setInitialDocumentData([
            'fileName' => '',
            'markup' => '',
            'settings' => ['title' => '', 'url' => '/']
        ]);

        $menuDescription = new NewDocumentDescription(
            __("New menu"),
            [
                'mtime' => null,
                'path' => null,
                'type' => self::DOCUMENT_TYPE_MENU,
                'isNewDocument' => true
            ]
        );

        $menuDescription->setIcon(self::ICON_COLOR_MENU, 'backend-icon-background entity-small text');
        // No top-level `code` here: the document header only auto-generates the
        // code from the name while the code is untouched (undefined).
        $menuDescription->setInitialDocumentData([
            'name' => __("New menu"),
            'items' => [],
            'settings' => ['name' => __("New menu")]
        ]);

        $contentDescription = new NewDocumentDescription(
            __("New content block"),
            [
                'mtime' => null,
                'path' => null,
                'type' => self::DOCUMENT_TYPE_CONTENT,
                'isNewDocument' => true
            ]
        );

        $contentDescription->setIcon(self::ICON_COLOR_CONTENT, 'backend-icon-background entity-small cms-content');
        $contentDescription->setInitialDocumentData([
            'fileName' => '',
            'markup' => '',
            'language' => 'html'
        ]);

        return [
            self::DOCUMENT_TYPE_PAGE => $description,
            self::DOCUMENT_TYPE_MENU => $menuDescription,
            self::DOCUMENT_TYPE_CONTENT => $contentDescription
        ];
    }

    /**
     * getCustomData exposes layout options to the client for the layout dropdown.
     */
    public function getCustomData(): array
    {
        return [
            'layouts' => $this->listLayoutOptions()
        ];
    }

    /**
     * listLayoutOptions returns theme layouts that support static pages.
     */
    protected function listLayoutOptions(): array
    {
        $page = StaticPage::inTheme(Theme::getEditTheme());

        return $page->getLayoutOptions();
    }

    /**
     * getClientSideLangStrings returns language strings required by the client controller.
     */
    public function getClientSideLangStrings()
    {
        return [
            'backend::lang.form.save',
            'backend::lang.form.delete',
            'editor::lang.common.settings',
            'editor::lang.common.toggle_document_header',
            // Plain-English strings the Vue editor components resolve via trans().
            'Add item',
            'Add subpage',
            'Content',
            'Custom Fields',
            'Menu',
            'New menu item',
            'Preview',
            'Static page',
        ];
    }

    /**
     * listNavigatorSections initializes the extension's sidebar Navigator sections.
     */
    public function listNavigatorSections(SectionList $sectionList, $documentType = null)
    {
        $user = BackendAuth::getUser();
        $theme = Theme::getEditTheme();

        $section = $sectionList->addSection(__("Pages"), 'pages');
        $section->setHasApiMenuItems(true);
        $section->setUserDataElement('uniqueKey', 'pages:root');

        $this->addSectionMenuItems($section);

        if (
            self::hasAccessToDocType($user, self::DOCUMENT_TYPE_PAGE) &&
            (!$documentType || $documentType === self::DOCUMENT_TYPE_PAGE)
        ) {
            $this->addPagesNavigatorNodes($section, $theme);
        }

        if (
            self::hasAccessToDocType($user, self::DOCUMENT_TYPE_MENU) &&
            (!$documentType || $documentType === self::DOCUMENT_TYPE_MENU)
        ) {
            $this->addMenusNavigatorNodes($section, $theme);
        }

        if (
            self::hasAccessToDocType($user, self::DOCUMENT_TYPE_CONTENT) &&
            (!$documentType || $documentType === self::DOCUMENT_TYPE_CONTENT)
        ) {
            $this->addContentNavigatorNodes($section, $theme);
        }
    }

    /**
     * addContentNavigatorNodes builds the list of content blocks, excluding static page content.
     */
    protected function addContentNavigatorNodes($section, $theme)
    {
        $rootNode = $section->addNode(__("Content"), self::DOCUMENT_TYPE_CONTENT);
        $rootNode
            ->setDisplayMode(NodeDefinition::DISPLAY_MODE_TREE)
            ->setChildKeyPrefix(self::DOCUMENT_TYPE_CONTENT.':')
            ->setUserData(['topLevel' => true]);

        $this->addNodeCreateMenu($rootNode, self::DOCUMENT_TYPE_CONTENT, __("New Content Block"));

        // Folder nodes are cached by path so files sharing a directory nest under one folder.
        $folderNodes = [];

        foreach (Content::listInTheme($theme, true) as $content) {
            $fileName = ltrim($content->fileName, '/');

            // Static page content is managed by the page document type, not as content
            // blocks. This includes translated mirror directories (static-pages-{locale}).
            if (preg_match('#^static-pages(-[^/]+)?/#', $fileName)) {
                continue;
            }

            $parentNode = $this->resolveContentFolderNode($rootNode, $fileName, $folderNodes);

            $node = $parentNode->addNode(basename($fileName), $fileName);
            $node->setIcon(self::ICON_COLOR_CONTENT, 'backend-icon-background entity-small cms-content');
        }
    }

    /**
     * resolveContentFolderNode returns (creating as needed) the folder node a content file nests under.
     */
    protected function resolveContentFolderNode($rootNode, string $fileName, array &$folderNodes)
    {
        $dir = trim(dirname($fileName), './');
        if ($dir === '') {
            return $rootNode;
        }

        $parentNode = $rootNode;
        $accumulated = '';

        foreach (explode('/', $dir) as $segment) {
            $accumulated = $accumulated === '' ? $segment : $accumulated.'/'.$segment;

            if (!isset($folderNodes[$accumulated])) {
                $folder = $parentNode->addNode($segment, 'folder:'.$accumulated);
                $folder
                    ->setDisplayMode(NodeDefinition::DISPLAY_MODE_TREE)
                    ->setUserData(['isFolder' => true]);
                $folder->setFolderIcon();
                $folderNodes[$accumulated] = $folder;
            }

            $parentNode = $folderNodes[$accumulated];
        }

        return $parentNode;
    }

    /**
     * addMenusNavigatorNodes builds the flat list of menus.
     */
    protected function addMenusNavigatorNodes($section, $theme)
    {
        $rootNode = $section->addNode(__("Menus"), self::DOCUMENT_TYPE_MENU);
        $rootNode
            ->setChildKeyPrefix(self::DOCUMENT_TYPE_MENU.':')
            ->setUserData(['topLevel' => true]);

        $this->addNodeCreateMenu($rootNode, self::DOCUMENT_TYPE_MENU, __("New Menu"));

        foreach (Menu::listInTheme($theme, true) as $menu) {
            $node = $rootNode->addNode($menu->name ?: $menu->getBaseFileName(), $menu->getBaseFileName());
            $node->setIcon(self::ICON_COLOR_MENU, 'backend-icon-background entity-small text');
        }
    }

    /**
     * addPagesNavigatorNodes builds the hierarchical static page tree.
     */
    protected function addPagesNavigatorNodes($section, $theme)
    {
        $rootNode = $section->addNode(__("Static Pages"), self::DOCUMENT_TYPE_PAGE);
        $rootNode
            ->setDisplayMode(NodeDefinition::DISPLAY_MODE_TREE)
            ->setChildKeyPrefix(self::DOCUMENT_TYPE_PAGE.':')
            // Drag to reorder (sort) and re-nest (move), persisted via command_onPageStructureUpdate.
            ->setDragAndDropMode([NodeDefinition::DND_SORT, NodeDefinition::DND_MOVE])
            ->setUserData(['topLevel' => true]);

        $this->addNodeCreateMenu($rootNode, self::DOCUMENT_TYPE_PAGE, __("New Page"));

        $pageList = new PageList($theme);
        $this->addPageTreeNodes($pageList->getPageTree(true), $rootNode);
    }

    /**
     * addPageTreeNodes recursively adds page nodes and their subpages.
     */
    protected function addPageTreeNodes($pages, $parentNode)
    {
        foreach ($pages as $pageInfo) {
            $page = $pageInfo->page;
            $baseName = $page->getBaseFileName();
            $title = $page->getViewBag()->property('title') ?: $baseName;

            $node = $parentNode->addNode($title, $baseName);
            $node->setIcon(self::ICON_COLOR_PAGE, 'backend-icon-background entity-small cms-page');
            // path drives the drag-move handler; url presets new subpage URLs.
            $node->setUserData([
                'path' => $baseName,
                'url' => (string) $page->getViewBag()->property('url')
            ]);
            $node->setHasApiMenuItems(true);

            if ($pageInfo->subpages) {
                $this->addPageTreeNodes($pageInfo->subpages, $node);
            }
        }
    }

    /**
     * addNodeCreateMenu adds a "create document" action to a top-level navigator node, so
     * each type (Static Pages / Menus / Content) offers creating a new object in context,
     * mirroring the main Editor IDE.
     */
    protected function addNodeCreateMenu($node, string $documentType, string $label)
    {
        $node->addRootMenuItem(
            ItemDefinition::TYPE_TEXT,
            $label,
            'pages:create-document@'.$documentType
        )->setIcon('icon-create');
    }

    /**
     * addSectionMenuItems adds the refresh and create menu items to a section.
     */
    protected function addSectionMenuItems($section)
    {
        $user = BackendAuth::getUser();

        $section->addMenuItem(ItemDefinition::TYPE_TEXT, __("Refresh"), 'pages:refresh-navigator')
            ->setIcon('icon-refresh');

        $createMenuItem = new ItemDefinition(ItemDefinition::TYPE_TEXT, __("Add"), 'pages:create');
        $createMenuItem->setIcon('icon-create');

        if (self::hasAccessToDocType($user, self::DOCUMENT_TYPE_PAGE)) {
            $createMenuItem->addItemObject(
                $section->addCreateMenuItem(
                    ItemDefinition::TYPE_TEXT,
                    __("Page"),
                    'pages:create-document@'.self::DOCUMENT_TYPE_PAGE
                )
            );
        }

        if (self::hasAccessToDocType($user, self::DOCUMENT_TYPE_MENU)) {
            $createMenuItem->addItemObject(
                $section->addCreateMenuItem(
                    ItemDefinition::TYPE_TEXT,
                    __("Menu"),
                    'pages:create-document@'.self::DOCUMENT_TYPE_MENU
                )
            );
        }

        if (self::hasAccessToDocType($user, self::DOCUMENT_TYPE_CONTENT)) {
            $createMenuItem->addItemObject(
                $section->addCreateMenuItem(
                    ItemDefinition::TYPE_TEXT,
                    __("Content block"),
                    'pages:create-document@'.self::DOCUMENT_TYPE_CONTENT
                )
            );
        }

        if ($createMenuItem->hasItems()) {
            $section->addMenuItemObject($createMenuItem);
        }
    }
}
