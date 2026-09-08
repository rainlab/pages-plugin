<?php

use Cms\Classes\Page as CmsPage;
use System\Classes\SiteManager;
use System\Classes\SiteCollection;
use System\Models\SiteDefinition;
use RainLab\Pages\Classes\Menu;
use RainLab\Pages\Classes\EditorExtension;

require_once __DIR__.'/PagesPluginTestCase.php';

/**
 * MenuLocaleTest covers per-locale menu item overrides (title, url, hidden flag)
 */
class MenuLocaleTest extends PagesPluginTestCase
{
    public function tearDown(): void
    {
        $this->setProtectedProperty(SiteManager::instance(), 'sites', null);

        parent::tearDown();
    }

    public function testGenerateReferencesUsesBaseValuesOnPrimarySite()
    {
        $menu = Menu::load($this->theme, 'main-menu.yaml');
        $references = $menu->generateReferences($this->makeCmsPage());

        [$home, $about, $external] = $references;

        $this->assertEquals('Home', $home->title);
        $this->assertEquals('1', array_get($about->viewBag, 'isHidden'));
        $this->assertEquals('0', array_get($external->viewBag, 'isHidden'));
    }

    public function testGenerateReferencesAppliesLocaleOverrides()
    {
        $this->setUpMultisite();

        $menu = Menu::load($this->theme, 'main-menu.yaml');
        $references = $menu->generateReferences($this->makeCmsPage());

        [$home, $about, $external] = $references;

        $this->assertEquals('Accueil', $home->title);

        // The hidden flag is toggleable in both directions
        $this->assertEquals('0', array_get($about->viewBag, 'isHidden'));
        $this->assertEquals('1', array_get($external->viewBag, 'isHidden'));
    }

    public function testLocalizeItemDataStoresHiddenFlagPerLocale()
    {
        $originalItems = [
            ['title' => 'Base', 'type' => 'url', 'url' => '/base', 'viewBag' => ['isHidden' => '0']],
        ];

        $postedItems = [
            ['title' => 'Base FR', 'type' => 'url', 'url' => '/base-fr', 'viewBag' => ['isHidden' => '1']],
        ];

        $result = $this->callLocalizeItemData($postedItems, $originalItems, 'fr');

        // Base values are restored, posted values become locale overrides
        $this->assertEquals('Base', $result[0]['title']);
        $this->assertEquals('0', array_get($result[0], 'viewBag.isHidden'));
        $this->assertEquals('Base FR', array_get($result[0], 'viewBag.locale.fr.title'));
        $this->assertEquals('1', array_get($result[0], 'viewBag.locale.fr.isHidden'));
    }

    public function testLocalizeItemDataDropsHiddenOverrideMatchingBase()
    {
        $originalItems = [
            [
                'title' => 'Base',
                'type' => 'url',
                'url' => '/base',
                'viewBag' => ['isHidden' => '1', 'locale' => ['fr' => ['isHidden' => '0']]]
            ],
        ];

        // The locale editor re-checks the box, matching the base state again
        $postedItems = [
            ['title' => 'Base', 'type' => 'url', 'url' => '/base', 'viewBag' => ['isHidden' => '1']],
        ];

        $result = $this->callLocalizeItemData($postedItems, $originalItems, 'fr');

        $this->assertEquals('1', array_get($result[0], 'viewBag.isHidden'));

        // The emptied locale group is removed entirely
        $this->assertNull(array_get($result[0], 'viewBag.locale'));
    }

    public function testLocalizeItemDataSkipsValuesMatchingBase()
    {
        $originalItems = [
            ['title' => 'Base', 'type' => 'url', 'url' => '/base', 'viewBag' => []],
        ];

        // Only the title differs from the base values
        $postedItems = [
            ['title' => 'Base FR', 'type' => 'url', 'url' => '/base', 'viewBag' => ['isHidden' => '0']],
        ];

        $result = $this->callLocalizeItemData($postedItems, $originalItems, 'fr');

        $this->assertEquals('Base FR', array_get($result[0], 'viewBag.locale.fr.title'));
        $this->assertNull(array_get($result[0], 'viewBag.locale.fr.url'));
        $this->assertNull(array_get($result[0], 'viewBag.locale.fr.isHidden'));
    }

    public function testLocalizeItemDataDropsTitleOverrideMatchingBase()
    {
        $originalItems = [
            [
                'title' => 'Base',
                'type' => 'url',
                'url' => '/base',
                'viewBag' => ['locale' => ['fr' => ['title' => 'Ancien']]]
            ],
        ];

        // The locale editor retypes the base title, re-inheriting it
        $postedItems = [
            ['title' => 'Base', 'type' => 'url', 'url' => '/base', 'viewBag' => []],
        ];

        $result = $this->callLocalizeItemData($postedItems, $originalItems, 'fr');

        $this->assertNull(array_get($result[0], 'viewBag.locale'));
    }

    public function testApplyItemsEditLocaleOverlaysHiddenFlag()
    {
        $items = [
            [
                'title' => 'Base',
                'type' => 'url',
                'url' => '/base',
                'viewBag' => ['isHidden' => '0', 'locale' => ['fr' => ['title' => 'Base FR', 'isHidden' => '1']]]
            ],
        ];

        $method = new ReflectionMethod(EditorExtension::class, 'applyItemsEditLocale');
        $method->setAccessible(true);
        $result = $method->invoke(new EditorExtension, $items, 'fr');

        $this->assertEquals('Base FR', $result[0]['title']);
        $this->assertEquals('1', array_get($result[0], 'viewBag.isHidden'));
    }

    /**
     * callLocalizeItemData invokes the protected save-path helper
     */
    protected function callLocalizeItemData(array $postedItems, array $originalItems, string $locale): array
    {
        $method = new ReflectionMethod(EditorExtension::class, 'localizeItemData');
        $method->setAccessible(true);

        return $method->invoke(new EditorExtension, $postedItems, $originalItems, $locale);
    }

    /**
     * setUpMultisite installs a two-site fixture with a French secondary site active
     */
    protected function setUpMultisite()
    {
        $sites = Model::unguarded(function() {
            return new SiteCollection([
                new SiteDefinition([
                    'id' => 1,
                    'name' => 'Primary',
                    'code' => 'primary',
                    'locale' => 'en',
                    'is_primary' => true,
                    'is_enabled' => true,
                    'is_enabled_edit' => true
                ]),
                new SiteDefinition([
                    'id' => 2,
                    'name' => 'French',
                    'code' => 'french',
                    'locale' => 'fr',
                    'is_primary' => false,
                    'is_enabled' => true,
                    'is_enabled_edit' => true
                ])
            ]);
        });

        $this->setProtectedProperty(SiteManager::instance(), 'sites', $sites);
        Config::set('system.active_site', 2);
    }

    /**
     * makeCmsPage returns a host page for reference generation
     */
    protected function makeCmsPage()
    {
        return CmsPage::inTheme($this->theme);
    }
}
