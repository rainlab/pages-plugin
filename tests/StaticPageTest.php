<?php

use RainLab\Pages\Classes\Page;
use RainLab\Pages\Classes\PageList;

require_once __DIR__.'/PagesPluginTestCase.php';

/**
 * StaticPageTest covers the static page model
 */
class StaticPageTest extends PagesPluginTestCase
{
    public function testLoadPage()
    {
        $page = Page::load($this->theme, 'about');

        $this->assertNotNull($page);
        $this->assertEquals('About', $page->getViewBag()->property('title'));
        $this->assertEquals('/about', $page->getViewBag()->property('url'));
        $this->assertEquals('<p>About content</p>', trim($page->markup));
    }

    public function testUrlHelper()
    {
        $this->assertEquals(url('/about'), Page::url('about'));
        $this->assertNull(Page::url('missing-page'));
        $this->assertNull(Page::url(''));
    }

    public function testGetParentAndChildren()
    {
        $page = Page::load($this->theme, 'about');
        $children = $page->getChildren();

        $this->assertCount(1, $children);
        $this->assertEquals('about-team', $children[0]->getBaseFileName());

        $child = Page::load($this->theme, 'about-team');
        $parent = $child->getParent();

        $this->assertNotNull($parent);
        $this->assertEquals('about', $parent->getBaseFileName());
    }

    public function testGetLayoutOptions()
    {
        $page = Page::inTheme($this->theme);
        $options = $page->getLayoutOptions();

        $this->assertArrayHasKey('default', $options);
        $this->assertArrayHasKey('sidebar', $options);
        $this->assertArrayNotHasKey('plain', $options);
        $this->assertEquals('Default layout', $options['default']);
    }

    public function testListLayoutPlaceholders()
    {
        $page = Page::load($this->theme, 'sidebar-page');
        $placeholders = $page->listLayoutPlaceholders();

        $this->assertArrayHasKey('sidebar', $placeholders);
        $this->assertEquals('Sidebar', $placeholders['sidebar']['title']);
        $this->assertEquals('html', $placeholders['sidebar']['type']);

        $this->assertArrayHasKey('notes', $placeholders);
        $this->assertEquals('text', $placeholders['notes']['type']);
    }

    public function testGetPlaceholdersAttribute()
    {
        $page = Page::load($this->theme, 'sidebar-page');
        $placeholders = $page->placeholders;

        $this->assertArrayHasKey('sidebar', $placeholders);
        $this->assertEquals('<p>Sidebar placeholder content</p>', trim($placeholders['sidebar']));
    }

    public function testSetPlaceholdersRendersPutBlocks()
    {
        $page = Page::load($this->theme, 'sidebar-page');

        $page->placeholders = [
            'sidebar' => '<p>Updated</p>',
            'unknown' => '<p>Not defined by the layout</p>',
        ];

        $this->assertStringContainsString('{% put sidebar %}', $page->code);
        $this->assertStringContainsString('<p>Updated</p>', $page->code);
        $this->assertStringNotContainsString('unknown', $page->code);
    }

    public function testCreatePageAppendsToMeta()
    {
        $page = Page::inTheme($this->theme);
        $page->fill([
            'settings' => [
                'viewBag' => [
                    'title' => 'New Page',
                    'url' => '/new-page',
                    'layout' => 'default',
                ],
            ],
            'markup' => '<p>New page content</p>',
        ]);
        $page->save();

        $this->assertEquals('new-page.htm', $page->fileName);
        $this->assertFileExists($this->theme->getPath().'/content/static-pages/new-page.htm');

        $pageList = new PageList($this->theme);
        $tree = $pageList->getPageTree(true);
        $names = array_map(function($node) {
            return $node->page->getBaseFileName();
        }, $tree);

        $this->assertContains('new-page', $names);
    }

    public function testRenameFileNameMovesFileAndMetaKey()
    {
        $page = Page::load($this->theme, 'about');
        $page->renameFileName('company');
        $page->save();

        $this->assertEquals('company.htm', $page->fileName);
        $this->assertFileExists($this->theme->getPath().'/content/static-pages/company.htm');
        $this->assertFileDoesNotExist($this->theme->getPath().'/content/static-pages/about.htm');

        // The meta index key is renamed in place and the child stays nested under it.
        $pageList = new PageList($this->theme);
        $config = $pageList->getPageTree(true);

        $company = null;
        foreach ($config as $node) {
            if ($node->page->getBaseFileName() === 'company') {
                $company = $node;
            }
        }

        $this->assertNotNull($company);
        $this->assertCount(1, $company->subpages);
        $this->assertEquals('about-team', $company->subpages[0]->page->getBaseFileName());
    }

    public function testRenameFileNameMovesLocaleMirror()
    {
        $page = Page::load($this->theme, 'about');
        $page->renameFileName('company.htm');
        $page->save();

        $this->assertFileExists($this->theme->getPath().'/content/static-pages-fr/company.htm');
        $this->assertFileDoesNotExist($this->theme->getPath().'/content/static-pages-fr/about.htm');
    }

    public function testRenameFileNameNoOpWhenUnchanged()
    {
        $page = Page::load($this->theme, 'about');
        $page->renameFileName('about');

        $this->assertFalse($page->isDirty('fileName'));
    }

    public function testDeletePageRemovesChildrenAndMeta()
    {
        $page = Page::load($this->theme, 'about');
        $deleted = $page->delete();

        sort($deleted);
        $this->assertEquals(['about', 'about-team'], $deleted);
        $this->assertFileDoesNotExist($this->theme->getPath().'/content/static-pages/about.htm');
        $this->assertFileDoesNotExist($this->theme->getPath().'/content/static-pages/about-team.htm');
        $this->assertFileDoesNotExist($this->theme->getPath().'/content/static-pages-fr/about.htm');
    }

    public function testValidationRequiresTitleAndUrl()
    {
        $page = Page::inTheme($this->theme);
        $page->fill([
            'settings' => [
                'viewBag' => [
                    'title' => '',
                    'url' => '/valid-url',
                ],
            ],
        ]);

        $this->expectException(\October\Rain\Halcyon\Exception\ModelException::class);
        $page->save();
    }

    public function testValidationRejectsMalformedUrl()
    {
        $page = Page::inTheme($this->theme);
        $page->fill([
            'settings' => [
                'viewBag' => [
                    'title' => 'Bad URL',
                    'url' => 'no-leading-slash',
                ],
            ],
        ]);

        $this->expectException(\October\Rain\Halcyon\Exception\ModelException::class);
        $page->save();
    }

    public function testValidationRejectsDuplicateUrl()
    {
        $page = Page::inTheme($this->theme);
        $page->fill([
            'settings' => [
                'viewBag' => [
                    'title' => 'Duplicate',
                    'url' => '/about',
                ],
            ],
        ]);

        $this->expectException(\October\Rain\Halcyon\Exception\ModelException::class);
        $page->save();
    }

    public function testResolveMenuItemForStaticPage()
    {
        $item = new \RainLab\Pages\Classes\MenuItem;
        $item->type = 'static-page';
        $item->reference = 'about';
        $item->nesting = true;

        $result = Page::resolveMenuItem($item, url('/'), $this->theme);

        $this->assertEquals(url('/about'), $result['url']);
        $this->assertFalse($result['isActive']);
        $this->assertCount(1, $result['items']);
        $this->assertEquals('Team', $result['items'][0]['title']);
    }

    public function testResolveMenuItemForAllPagesSkipsHiddenNavigation()
    {
        $item = new \RainLab\Pages\Classes\MenuItem;
        $item->type = 'all-static-pages';

        $result = Page::resolveMenuItem($item, url('/'), $this->theme);
        $titles = array_column($result['items'], 'title');

        $this->assertContains('Home', $titles);
        $this->assertContains('About', $titles);
        $this->assertNotContains('Hidden', $titles);
    }

    public function testGetMenuTypeInfo()
    {
        $info = Page::getMenuTypeInfo('static-page');

        $this->assertTrue($info['nesting']);
        $this->assertTrue($info['dynamicItems']);
        $this->assertArrayHasKey('about', $info['references']);

        $info = Page::getMenuTypeInfo('all-static-pages');
        $this->assertTrue($info['dynamicItems']);
    }
}
