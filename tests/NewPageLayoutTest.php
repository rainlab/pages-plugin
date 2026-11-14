<?php

use RainLab\Pages\Classes\Page;
use RainLab\Pages\Classes\EditorExtension;
use Backend\VueComponents\TreeView\SectionDefinition;

require_once __DIR__.'/PagesPluginTestCase.php';

/**
 * NewPageLayoutTest covers the layout preselected in the editor. A new page starts with
 * the parent's child layout or the default layout, so the layout is always posted and
 * written to the page file.
 */
class NewPageLayoutTest extends PagesPluginTestCase
{
    /**
     * testNewPageStoresDefaultLayout saves a new page with the editor's initial document
     * data and expects the theme layout marked as default to be stored.
     */
    public function testNewPageStoresDefaultLayout(): void
    {
        $settings = array_get($this->newPageDocument(), 'settings');

        $result = $this->saveNewPage($settings);

        $this->assertEquals('default', $this->savedLayout($result));
    }

    /**
     * testChosenLayoutIsStored expects a posted layout to be stored as chosen.
     */
    public function testChosenLayoutIsStored(): void
    {
        $result = $this->saveNewPage(['layout' => 'sidebar']);

        $this->assertEquals('sidebar', $this->savedLayout($result));
    }

    /**
     * testNewDocumentPreselectsDefaultLayout expects the new page document to start with
     * the theme layout marked as default.
     */
    public function testNewDocumentPreselectsDefaultLayout(): void
    {
        $document = $this->newPageDocument();

        $this->assertEquals('default', array_get($document, 'layout'));
        $this->assertEquals('default', array_get($document, 'settings.layout'));
    }

    /**
     * testNewDocumentPreselectsFirstLayoutWithoutDefault expects a theme without a default
     * layout to preselect the first layout in the dropdown.
     */
    public function testNewDocumentPreselectsFirstLayoutWithoutDefault(): void
    {
        $this->putLayout('default', "[staticPage]");

        $firstLayout = array_key_first(Page::inTheme($this->theme)->getLayoutOptions());

        $this->assertEquals($firstLayout, array_get($this->newPageDocument(), 'layout'));
    }

    /**
     * testPageNodesCarrySubpageLayout expects each page node to carry the layout a new
     * subpage preselects: the child layout of the page's layout, or the default layout.
     */
    public function testPageNodesCarrySubpageLayout(): void
    {
        $this->putLayout('default', "[staticPage]\ndefault = 1\nchildLayout = \"sidebar\"");

        $section = new SectionDefinition('Pages', 'pages');
        $method = new ReflectionMethod(EditorExtension::class, 'addPagesNavigatorNodes');
        $method->invoke(new EditorExtension, $section, $this->theme);

        $nodes = $this->flattenNodes($section->toArray()['nodes'][0]['nodes']);

        $this->assertEquals('sidebar', array_get($nodes, 'about.subpageLayout'));
        $this->assertEquals('default', array_get($nodes, 'sidebar-page.subpageLayout'));
    }

    /**
     * testSaveKeepsPlaceholdersOfPostedLayout changes the layout of a page and expects the
     * placeholders of the new layout to be kept.
     */
    public function testSaveKeepsPlaceholdersOfPostedLayout(): void
    {
        $this->swapPost([
            'documentMetadata' => ['type' => 'static-page', 'path' => 'index.htm'],
            'documentForceSave' => 1,
            'documentData' => [
                'settings' => ['title' => 'Home', 'url' => '/', 'layout' => 'sidebar'],
                'markup' => '<p>Home content</p>',
                'placeholders' => ['sidebar' => 'Side content'],
            ],
        ]);

        $this->invokeCommand('savePageDocument');

        $saved = Page::load($this->theme, 'index');

        $this->assertStringContainsString('{% put sidebar %}', File::get($saved->getFilePath()));
    }

    /**
     * newPageDocument returns the initial document data of a new page
     */
    protected function newPageDocument(): array
    {
        $descriptions = (new EditorExtension)->getNewDocumentsData();

        return (array) array_get($descriptions[EditorExtension::DOCUMENT_TYPE_PAGE]->toArray(), 'document');
    }

    /**
     * saveNewPage saves a new page through the editor with the given settings
     */
    protected function saveNewPage(array $settings): array
    {
        $this->swapPost([
            'documentMetadata' => ['type' => 'static-page', 'path' => ''],
            'documentData' => [
                'settings' => ['title' => 'Layout Page', 'url' => '/layoutpage'] + $settings,
                'markup' => '<p>hi</p>',
            ],
        ]);

        return $this->invokeCommand('savePageDocument');
    }

    /**
     * savedLayout reads the layout stored in the page file saved by the editor
     */
    protected function savedLayout(array $result): ?string
    {
        $saved = Page::load($this->theme, array_get($result, 'metadata.path'));

        $this->assertNotNull($saved);

        return array_get($saved->getViewBag()->getProperties(), 'layout');
    }

    /**
     * putLayout rewrites a fixture layout with the given settings section
     */
    protected function putLayout(string $name, string $settings): void
    {
        File::put(
            $this->theme->getPath().'/layouts/'.$name.'.htm',
            $settings."\n==\n<html>\n    <body>\n        {% page %}\n    </body>\n</html>"
        );
    }

    /**
     * flattenNodes returns the user data of every page node, keyed by page path
     */
    protected function flattenNodes(array $nodes): array
    {
        $result = [];

        foreach ($nodes as $node) {
            $userData = (array) array_get($node, 'userData');
            $result[array_get($userData, 'path')] = $userData;
            $result += $this->flattenNodes((array) array_get($node, 'nodes'));
        }

        return $result;
    }
}
