<?php

use RainLab\Pages\Classes\MenuItem;

require_once __DIR__.'/PagesPluginTestCase.php';

/**
 * MenuItemTest covers menu item hydration and the page lookup integration
 */
class MenuItemTest extends PagesPluginTestCase
{
    public function testInitFromArray()
    {
        $items = MenuItem::initFromArray([
            [
                'title' => 'Parent',
                'type' => 'static-page',
                'reference' => 'about',
                'viewBag' => ['cssClass' => 'top'],
                'items' => [
                    ['title' => 'Child', 'type' => 'url', 'url' => '/child'],
                ],
            ],
        ]);

        $this->assertCount(1, $items);
        $this->assertEquals('Parent', $items[0]->title);
        $this->assertEquals(['cssClass' => 'top'], $items[0]->viewBag);
        $this->assertCount(1, $items[0]->items);
        $this->assertEquals('Child', $items[0]->items[0]->title);
        $this->assertEquals('/child', $items[0]->items[0]->url);
    }

    public function testToArrayIncludesFillableProperties()
    {
        $item = new MenuItem;
        $item->title = 'Example';
        $item->type = 'url';
        $item->url = '/example';

        $result = $item->toArray();

        $this->assertEquals('Example', $result['title']);
        $this->assertEquals('url', $result['type']);
        $this->assertEquals('/example', $result['url']);
        $this->assertArrayHasKey('viewBag', $result);
        $this->assertArrayNotHasKey('items', $result);
    }

    public function testGetTypeOptionsIncludesRegisteredTypes()
    {
        $options = (new MenuItem)->getTypeOptions();

        $this->assertArrayHasKey('url', $options);
        $this->assertArrayHasKey('header', $options);
        $this->assertArrayHasKey('static-page', $options);
        $this->assertArrayHasKey('all-static-pages', $options);
    }

    public function testGetTypeInfoForStaticPage()
    {
        $info = MenuItem::getTypeInfo('static-page');

        $this->assertTrue($info['nesting']);
        $this->assertTrue($info['dynamicItems']);
        $this->assertArrayHasKey('index', $info['references']);
        $this->assertArrayHasKey('about', $info['references']);
    }
}
