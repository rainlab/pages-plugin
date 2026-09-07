<?php

use Cms\Classes\Page as CmsPage;
use RainLab\Pages\Classes\Menu;

require_once __DIR__.'/PagesPluginTestCase.php';

/**
 * MenuTest covers menu storage and front-end reference generation
 */
class MenuTest extends PagesPluginTestCase
{
    public function testLoadMenu()
    {
        $menu = Menu::load($this->theme, 'main-menu.yaml');

        $this->assertNotNull($menu);
        $this->assertEquals('Main Menu', $menu->name);
        $this->assertEquals('main-menu', $menu->code);
        $this->assertCount(3, $menu->items);
        $this->assertEquals('Home', $menu->items[0]->title);
        $this->assertEquals('static-page', $menu->items[0]->type);
    }

    public function testGenerateReferences()
    {
        $menu = Menu::load($this->theme, 'main-menu.yaml');
        $references = $menu->generateReferences($this->makeCmsPage());

        $this->assertCount(3, $references);

        [$home, $about, $external] = $references;

        $this->assertEquals('Home', $home->title);
        $this->assertEquals(url('/'), $home->url);

        $this->assertEquals('About', $about->title);
        $this->assertEquals(url('/about'), $about->url);
        $this->assertCount(1, $about->items, 'Nested item should include the subpage');
        $this->assertEquals('Team', $about->items[0]->title);

        $this->assertEquals('External', $external->title);
        $this->assertEquals('https://example.com', $external->url);
    }

    public function testGenerateReferencesWithReplaceExpandsGeneratedItems()
    {
        $menu = Menu::load($this->theme, 'all-pages.yaml');
        $references = $menu->generateReferences($this->makeCmsPage());

        $titles = array_map(function($reference) {
            return $reference->title;
        }, $references);

        $this->assertContains('Home', $titles);
        $this->assertContains('About', $titles);
        $this->assertNotContains('All pages', $titles, 'Replaced item should not appear itself');
        $this->assertNotContains('Hidden', $titles, 'Pages hidden from navigation should be excluded');
    }

    public function testSetCodeRenamesFile()
    {
        $menu = new Menu;
        $menu->code = 'footer';

        $this->assertEquals('footer.yaml', $menu->fileName);
    }

    public function testValidationRejectsInvalidCode()
    {
        $menu = Menu::inTheme($this->theme);
        $menu->fill([
            'name' => 'Bad Menu',
            'code' => 'bad code!',
        ]);

        $this->expectException(\October\Rain\Halcyon\Exception\ModelException::class);
        $menu->save();
    }

    /**
     * makeCmsPage returns a host page for reference generation
     */
    protected function makeCmsPage()
    {
        return CmsPage::inTheme($this->theme);
    }
}
