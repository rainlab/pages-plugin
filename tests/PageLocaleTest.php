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

    public function testApplyLocaleMirrorMergesPlaceholdersPerCode()
    {
        // A partial mirror translating only the "notes" placeholder
        File::put(
            $this->theme->getPath().'/content/static-pages-fr/sidebar-page.htm',
            "##\n[viewBag]\n==\n{% put notes %}\nNotes en français\n{% endput %}\n==\n"
        );

        $page = Page::load($this->theme, 'sidebar-page');
        $mirror = PageLocale::findLocale('fr', $page);
        $this->assertNotNull($mirror);

        $method = new ReflectionMethod($page, 'applyLocaleMirror');
        $method->setAccessible(true);
        $method->invoke($page, $mirror);

        $placeholders = $page->placeholders;

        $this->assertEquals('Notes en français', trim(array_get($placeholders, 'notes')));

        // The untranslated placeholder and view bag values inherit the base
        $this->assertEquals('<p>Sidebar placeholder content</p>', trim(array_get($placeholders, 'sidebar')));
        $this->assertEquals('Sidebar Page', $page->getViewBag()->property('title'));
        $this->assertEquals('<p>Sidebar page content</p>', trim($page->markup));
    }

    public function testApplyLocaleMirrorOverridesPlaceholder()
    {
        File::put(
            $this->theme->getPath().'/content/static-pages-fr/sidebar-page.htm',
            "##\n[viewBag]\n==\n{% put sidebar %}\nBarre latérale\n{% endput %}\n==\n"
        );

        $page = Page::load($this->theme, 'sidebar-page');
        $mirror = PageLocale::findLocale('fr', $page);

        $method = new ReflectionMethod($page, 'applyLocaleMirror');
        $method->setAccessible(true);
        $method->invoke($page, $mirror);

        $this->assertEquals('Barre latérale', trim(array_get($page->placeholders, 'sidebar')));
    }

    public function testLocaleValueMatchesBase()
    {
        $extension = new \RainLab\Pages\Classes\EditorExtension;
        $method = new ReflectionMethod($extension, 'localeValueMatchesBase');
        $method->setAccessible(true);

        // Whitespace differences are ignored, including richeditor reserialization
        $this->assertTrue($method->invoke($extension, "Line\r\nTwo\n", "Line\nTwo"));
        $this->assertTrue($method->invoke($extension, "<h3>Hi</h3>\n<p>There</p>", '<h3>Hi</h3><p>There</p>'));
        $this->assertTrue($method->invoke($extension, '', null));
        $this->assertTrue($method->invoke($extension, ['a' => '1'], ['a' => '1']));

        $this->assertFalse($method->invoke($extension, 'Bonjour', 'Hello'));
        $this->assertFalse($method->invoke($extension, ['a' => '1'], ['a' => '2']));
    }
}
