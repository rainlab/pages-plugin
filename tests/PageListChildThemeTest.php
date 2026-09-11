<?php

use Cms\Classes\Theme;
use RainLab\Pages\Classes\Page;
use RainLab\Pages\Classes\PageList;

require_once __DIR__.'/PagesPluginTestCase.php';

/**
 * PageListChildThemeTest covers static page structure merging across theme layers, so a
 * child theme inherits the parent's page hierarchy and overlays its own changes.
 */
class PageListChildThemeTest extends PagesPluginTestCase
{
    /**
     * @var \Cms\Classes\Theme childTheme fixture instance
     */
    protected $childTheme;

    /**
     * @var array childSnapshot of child fixture file contents, keyed by absolute path
     */
    protected $childSnapshot = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->childTheme = Theme::load('test-child');
        $this->childTheme->registerHalcyonDatasource();

        $this->childSnapshot = [];
        foreach (File::allFiles($this->childTheme->getPath()) as $file) {
            $this->childSnapshot[$file->getPathname()] = File::get($file->getPathname());
        }
    }

    public function tearDown(): void
    {
        // Restore any child fixture files a structural-edit test wrote.
        foreach (File::allFiles($this->childTheme->getPath()) as $file) {
            if (!array_key_exists($file->getPathname(), $this->childSnapshot)) {
                File::delete($file->getPathname());
            }
        }
        foreach ($this->childSnapshot as $path => $contents) {
            if (!File::exists($path) || File::get($path) !== $contents) {
                File::put($path, $contents);
            }
        }

        parent::tearDown();
    }

    public function testChildInheritsParentPageFiles()
    {
        $pageList = new PageList($this->childTheme);

        $fileNames = $pageList->listPages(true)->map(function($page) {
            return $page->getBaseFileName();
        })->all();

        // The child sees its own pages plus the parent's, with the child winning on overlap.
        $this->assertContains('child-only', $fileNames);
        $this->assertContains('sidebar-page', $fileNames);
        $this->assertContains('about', $fileNames);
    }

    public function testChildOverridesParentPageContent()
    {
        $page = Page::load($this->childTheme, 'about');

        $this->assertEquals('About (child override)', $page->getViewBag()->property('title'));
    }

    public function testMergedTreeResurfacesParentOnlyPages()
    {
        $pageList = new PageList($this->childTheme);
        $tree = $pageList->getPageTree(true);

        $names = [];
        $collect = function($nodes) use (&$collect, &$names) {
            foreach ($nodes as $node) {
                $names[] = $node->page->getBaseFileName();
                $collect($node->subpages);
            }
        };
        $collect($tree);

        // Parent-only pages (absent from the child's own static-pages.yaml) still appear.
        $this->assertContains('sidebar-page', $names);
        $this->assertContains('hidden-page', $names);
        // Child-only page appears too.
        $this->assertContains('child-only', $names);
        // The overridden page keeps its nested child.
        $this->assertContains('about-team', $names);
    }

    public function testMergedGetPageParentSpansLayers()
    {
        $pageList = new PageList($this->childTheme);

        // about-team is nested under about in the child structure.
        $child = Page::load($this->childTheme, 'about-team');
        $this->assertEquals('about', $pageList->getPageParent($child));
    }

    public function testStructuralEditKeepsParentPagesVisible()
    {
        $pageList = new PageList($this->childTheme);

        // A structural change writes only the child's static-pages.yaml.
        $page = Page::load($this->childTheme, 'about');
        $pageList->renameKey('about', 'company');

        // Re-reading merges the parent again, so parent-only pages are not dropped and
        // the rename is reflected.
        $freshList = new PageList($this->childTheme);
        $tree = $freshList->getPageTree(true);

        $names = [];
        $collect = function($nodes) use (&$collect, &$names) {
            foreach ($nodes as $node) {
                $names[] = $node->page->getBaseFileName();
                $collect($node->subpages);
            }
        };
        $collect($tree);

        $this->assertContains('sidebar-page', $names);
        $this->assertContains('hidden-page', $names);
    }
}
