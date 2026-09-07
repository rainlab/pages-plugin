<?php

use RainLab\Pages\Classes\Page;
use RainLab\Pages\Classes\PageList;

require_once __DIR__.'/PagesPluginTestCase.php';

/**
 * PageListTest covers the static page hierarchy manager
 */
class PageListTest extends PagesPluginTestCase
{
    public function testListPages()
    {
        $pageList = new PageList($this->theme);
        $pages = $pageList->listPages();

        $fileNames = $pages->map(function($page) {
            return $page->getBaseFileName();
        })->all();

        sort($fileNames);
        $this->assertEquals(['about', 'about-team', 'hidden-page', 'index', 'sidebar-page'], $fileNames);
    }

    public function testGetPageTree()
    {
        $pageList = new PageList($this->theme);
        $tree = $pageList->getPageTree();

        $this->assertCount(4, $tree);
        $this->assertEquals('index', $tree[0]->page->getBaseFileName());
        $this->assertEquals('about', $tree[1]->page->getBaseFileName());
        $this->assertCount(1, $tree[1]->subpages);
        $this->assertEquals('about-team', $tree[1]->subpages[0]->page->getBaseFileName());
    }

    public function testGetPageParent()
    {
        $pageList = new PageList($this->theme);

        $child = Page::load($this->theme, 'about-team');
        $this->assertEquals('about', $pageList->getPageParent($child));

        $root = Page::load($this->theme, 'about');
        $this->assertNull($pageList->getPageParent($root));
    }

    public function testGetPageSubTree()
    {
        $pageList = new PageList($this->theme);

        $page = Page::load($this->theme, 'about');
        $subTree = $pageList->getPageSubTree($page);

        $this->assertArrayHasKey('about-team', $subTree);
    }

    public function testUpdateStructure()
    {
        $pageList = new PageList($this->theme);

        $pageList->updateStructure([
            'index' => [],
            'about' => [
                'about-team' => [],
                'sidebar-page' => [],
            ],
            'hidden-page' => [],
        ]);

        $page = Page::load($this->theme, 'sidebar-page');
        $this->assertEquals('about', $pageList->getPageParent($page));
    }
}
