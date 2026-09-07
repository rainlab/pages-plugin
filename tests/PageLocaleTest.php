<?php

use RainLab\Pages\Classes\Page;
use RainLab\Pages\Classes\PageLocale;

require_once __DIR__.'/PagesPluginTestCase.php';

/**
 * PageLocaleTest covers loading translated page mirror files
 */
class PageLocaleTest extends PagesPluginTestCase
{
    public function testFindLocaleReturnsMirror()
    {
        $page = Page::load($this->theme, 'about');
        $mirror = PageLocale::findLocale('fr', $page);

        $this->assertNotNull($mirror);
        $this->assertEquals('À propos', $mirror->getViewBag()->property('title'));
        $this->assertEquals('<p>Contenu à propos</p>', trim($mirror->markup));
    }

    public function testFindLocaleReturnsNullWhenMissing()
    {
        $page = Page::load($this->theme, 'index');

        $this->assertNull(PageLocale::findLocale('fr', $page));
        $this->assertNull(PageLocale::findLocale('de', $page));
    }

    public function testTranslatableUrlFallsBackToBaseUrl()
    {
        $page = Page::load($this->theme, 'about');

        $this->assertEquals('/a-propos', array_get($page->attributes, 'viewBag.localeUrl.fr'));
    }
}
