<?php

use System\Classes\SiteManager;
use System\Classes\SiteCollection;
use System\Models\SiteDefinition;
use Backend\VueComponents\TreeView\SectionDefinition;
use RainLab\Pages\Classes\Content;
use RainLab\Pages\Classes\EditorExtension;
use RainLab\Pages\Controllers\Index;

require_once __DIR__.'/PagesPluginTestCase.php';

/**
 * ContentLocaleTest covers translating content blocks by selecting a site in the editor
 */
class ContentLocaleTest extends PagesPluginTestCase
{
    /**
     * @var array createdDirectories are removed after each test, since the snapshot restores files only
     */
    protected $createdDirectories = ['content/fr', 'content/fr-ca', 'content/nl', 'content/about', 'content/notes'];

    /**
     * tearDown the test case
     */
    public function tearDown(): void
    {
        $this->restoreThemeFiles();

        foreach ($this->createdDirectories as $directory) {
            File::deleteDirectory($this->theme->getPath().'/'.$directory);
        }

        $this->setProtectedProperty(SiteManager::instance(), 'sites', null);

        parent::tearDown();
    }

    public function testIsTranslationFileName()
    {
        $keys = ['fr', 'nl'];

        $this->assertTrue(Content::isTranslationFileName('fr/welcome.htm', $keys));
        $this->assertTrue(Content::isTranslationFileName('nl/home/about.htm', $keys));
        $this->assertTrue(Content::isTranslationFileName('welcome.fr.htm', $keys));
        $this->assertTrue(Content::isTranslationFileName('home/about.nl.md', $keys));

        $this->assertFalse(Content::isTranslationFileName('welcome.htm', $keys));
        $this->assertFalse(Content::isTranslationFileName('de/welcome.htm', $keys));
        $this->assertFalse(Content::isTranslationFileName('home/fr.htm', $keys));
        $this->assertFalse(Content::isTranslationFileName('home/fr/about.htm', $keys));
        $this->assertFalse(Content::isTranslationFileName('welcome.fr.htm', []));
    }

    public function testMakeLocaleFileNames()
    {
        $this->assertEquals('fr/home/about.htm', Content::makeLocaleFileName('home/about.htm', 'fr'));
        $this->assertEquals('home/about.fr.htm', Content::makeLegacyLocaleFileName('home/about.htm', 'fr'));
        $this->assertEquals('home/about.v2.fr.md', Content::makeLegacyLocaleFileName('/home/about.v2.md', 'fr'));
    }

    public function testMakeLocaleFileNameFromLegacy()
    {
        $keys = ['fr', 'fr-ca'];

        $this->assertEquals('fr/welcome.htm', Content::makeLocaleFileNameFromLegacy('welcome.fr.htm', $keys));
        $this->assertEquals('fr-ca/blog/intro.md', Content::makeLocaleFileNameFromLegacy('/blog/intro.fr-ca.md', $keys));

        $this->assertNull(Content::makeLocaleFileNameFromLegacy('welcome.htm', $keys));
        $this->assertNull(Content::makeLocaleFileNameFromLegacy('blog/fr.htm', $keys));
        $this->assertNull(Content::makeLocaleFileNameFromLegacy('welcome.de.htm', $keys));
    }

    public function testMigrateCommandMovesLegacyTranslations()
    {
        $this->setUpMultisite(1);
        $this->putContent('welcome.fr.htm', '<p>Bienvenue</p>');
        $this->putContent('notes/intro.fr.htm', '<p>Introduction</p>');
        $this->putContent('notes/skip.fr.htm', '<p>Legacy</p>');
        $this->putContent('fr/notes/skip.htm', '<p>Already migrated</p>');

        $this->assertEquals(0, Artisan::call('pages:migratev2', ['--theme' => 'test']));

        $this->assertEquals('<p>Bienvenue</p>', File::get($this->contentPath('fr/welcome.htm')));
        $this->assertEquals('<p>Introduction</p>', File::get($this->contentPath('fr/notes/intro.htm')));
        $this->assertFalse(File::exists($this->contentPath('welcome.fr.htm')));
        $this->assertFalse(File::exists($this->contentPath('notes/intro.fr.htm')));

        // An existing locale directory file is kept and the legacy file is left for review
        $this->assertEquals('<p>Already migrated</p>', File::get($this->contentPath('fr/notes/skip.htm')));
        $this->assertTrue(File::exists($this->contentPath('notes/skip.fr.htm')));

        // Static page mirrors keep their own storage
        $this->assertTrue(File::exists($this->contentPath('static-pages-fr/about.htm')));
        $this->assertTrue(File::exists($this->contentPath('welcome.htm')));
    }

    public function testMigrateCommandWithoutSiteLocales()
    {
        $this->putContent('welcome.fr.htm', '<p>Bienvenue</p>');

        $this->assertEquals(0, Artisan::call('pages:migratev2', ['--theme' => 'test']));

        $this->assertTrue(File::exists($this->contentPath('welcome.fr.htm')));
        $this->assertFalse(File::exists($this->contentPath('fr/welcome.htm')));
    }

    public function testListLocaleKeysExcludesPrimaryLocale()
    {
        $this->setUpMultisite(1, ['fr', 'fr-ca']);

        $this->assertEqualsCanonicalizing(['fr', 'fr-ca'], Content::listLocaleKeys());
    }

    public function testListLocaleKeysIsEmptyWithoutMultisite()
    {
        $this->assertEquals([], Content::listLocaleKeys());
    }

    public function testNavigatorSortsAndHidesTranslations()
    {
        $this->setUpMultisite(1);

        $this->putContent('zebra.htm', 'Z');
        $this->putContent('Apple.htm', 'A');
        $this->putContent('about/b.htm', 'B');
        $this->putContent('about/a.htm', 'A');
        $this->putContent('nl/other.htm', 'Not a site locale');
        $this->putContent('welcome.fr.htm', 'Legacy');
        $this->putContent('fr/welcome.htm', 'Localized');

        $section = new SectionDefinition('Pages', 'pages');
        $method = new ReflectionMethod(EditorExtension::class, 'addContentNavigatorNodes');
        $method->setAccessible(true);
        $method->invoke(new EditorExtension, $section, $this->theme);

        $rootNode = $section->toArray()['nodes'][0];
        $labels = array_column($rootNode['nodes'], 'label');

        $this->assertEquals(['about', 'nl', 'Apple.htm', 'welcome.htm', 'zebra.htm'], $labels);
        $this->assertEquals(['a.htm', 'b.htm'], array_column($rootNode['nodes'][0]['nodes'], 'label'));
    }

    public function testOpenLoadsLegacyTranslation()
    {
        $this->setUpMultisite(2);
        $this->putContent('welcome.fr.htm', '<p>Bienvenue</p>');

        $result = $this->openDocument('welcome.htm');

        $this->assertEquals('<p>Bienvenue</p>', $result['document']['markup']);
        $this->assertEquals('fr', $result['metadata']['locale']);
        $this->assertEquals('welcome.htm', $result['metadata']['path']);
        $this->assertNotNull($result['metadata']['mtime']);
    }

    public function testOpenPrefersLocaleDirectory()
    {
        $this->setUpMultisite(2);
        $this->putContent('welcome.fr.htm', '<p>Legacy</p>');
        $this->putContent('fr/welcome.htm', '<p>Localized</p>');

        $result = $this->openDocument('welcome.htm');

        $this->assertEquals('<p>Localized</p>', $result['document']['markup']);
    }

    public function testOpenShowsBaseMarkupUntilTranslated()
    {
        $this->setUpMultisite(2);

        $result = $this->openDocument('welcome.htm');

        $this->assertEquals('<p>Welcome content block</p>', trim($result['document']['markup']));
        $this->assertNull($result['metadata']['mtime']);
    }

    public function testOpenOnPrimarySiteShowsBase()
    {
        $this->setUpMultisite(1);
        $this->putContent('fr/welcome.htm', '<p>Localized</p>');

        $result = $this->openDocument('welcome.htm');

        $this->assertEquals('<p>Welcome content block</p>', trim($result['document']['markup']));
        $this->assertArrayNotHasKey('locale', $result['metadata']);
    }

    public function testInheritedTranslationFollowsLocaleChain()
    {
        $this->setUpMultisite(3, ['fr', 'fr-ca']);
        $this->putContent('fr/welcome.htm', '<p>Bienvenue</p>');

        $result = $this->openDocument('welcome.htm');
        $this->assertEquals('<p>Bienvenue</p>', $result['document']['markup']);
        $this->assertNull($result['metadata']['mtime']);

        // Saving the inherited markup unchanged does not fork a dedicated translation
        $this->saveDocument('welcome.htm', '<p>Bienvenue</p>');
        $this->assertFalse(File::exists($this->contentPath('fr-ca/welcome.htm')));

        $this->saveDocument('welcome.htm', '<p>Bienvenue au Canada</p>');
        $this->assertEquals('<p>Bienvenue au Canada</p>', File::get($this->contentPath('fr-ca/welcome.htm')));
        $this->assertEquals('<p>Bienvenue</p>', File::get($this->contentPath('fr/welcome.htm')));
    }

    public function testSaveWritesLocaleDirectory()
    {
        $this->setUpMultisite(2);

        $result = $this->saveDocument('welcome.htm', '<p>Bienvenue</p>');

        $this->assertEquals('<p>Bienvenue</p>', File::get($this->contentPath('fr/welcome.htm')));
        $this->assertEquals('<p>Welcome content block</p>', trim(File::get($this->contentPath('welcome.htm'))));
        $this->assertEquals('fr', $result['metadata']['locale']);
        $this->assertNotNull($result['metadata']['mtime']);
    }

    public function testSaveMigratesLegacyTranslation()
    {
        $this->setUpMultisite(2);
        $this->putContent('welcome.fr.htm', '<p>Bienvenue</p>');

        $this->saveDocument('welcome.htm', '<p>Bienvenue encore</p>');

        $this->assertEquals('<p>Bienvenue encore</p>', File::get($this->contentPath('fr/welcome.htm')));
        $this->assertFalse(File::exists($this->contentPath('welcome.fr.htm')));
    }

    public function testSaveMatchingBaseRemovesTranslation()
    {
        $this->setUpMultisite(2);
        $this->putContent('welcome.fr.htm', '<p>Bienvenue</p>');
        $this->putContent('fr/welcome.htm', '<p>Bienvenue</p>');

        $result = $this->saveDocument('welcome.htm', "<p>Welcome content block</p>\n");

        $this->assertFalse(File::exists($this->contentPath('fr/welcome.htm')));
        $this->assertFalse(File::exists($this->contentPath('welcome.fr.htm')));
        $this->assertNull($result['metadata']['mtime']);
    }

    public function testSaveGuardsTranslationChangedOnDisk()
    {
        $this->setUpMultisite(2);
        $this->putContent('fr/welcome.htm', '<p>Bienvenue</p>');

        $result = $this->saveDocument('welcome.htm', '<p>Changed</p>', ['mtime' => 1]);

        $this->assertEquals(['mtimeMismatch' => true], $result);
        $this->assertEquals('<p>Bienvenue</p>', File::get($this->contentPath('fr/welcome.htm')));
    }

    public function testSaveRejectsTranslationFileName()
    {
        $this->setUpMultisite(1);

        $this->expectException(ApplicationException::class);

        $this->swapPost([
            'documentMetadata' => ['type' => 'content', 'path' => ''],
            'documentData' => ['fileName' => 'notes.fr.htm', 'markup' => '<p>Notes</p>'],
        ]);

        $this->invokeCommand('saveContentDocument');
    }

    public function testRenameMovesTranslations()
    {
        $this->setUpMultisite(1);
        $this->putContent('notes/intro.htm', '<p>Intro</p>');
        $this->putContent('fr/notes/intro.htm', '<p>Introduction</p>');
        $this->putContent('notes/intro.fr.htm', '<p>Legacy</p>');

        $this->swapPost([
            'documentMetadata' => ['type' => 'content', 'path' => 'notes/intro.htm'],
            'documentForceSave' => 1,
            'documentData' => ['fileName' => 'notes/start.htm', 'markup' => '<p>Intro</p>'],
        ]);

        $this->invokeCommand('saveContentDocument');

        $this->assertTrue(File::exists($this->contentPath('notes/start.htm')));
        $this->assertEquals('<p>Introduction</p>', File::get($this->contentPath('fr/notes/start.htm')));
        $this->assertEquals('<p>Legacy</p>', File::get($this->contentPath('notes/start.fr.htm')));
        $this->assertFalse(File::exists($this->contentPath('fr/notes/intro.htm')));
        $this->assertFalse(File::exists($this->contentPath('notes/intro.fr.htm')));
    }

    public function testDeleteRemovesTranslations()
    {
        $this->setUpMultisite(1);
        $this->putContent('notes/intro.htm', '<p>Intro</p>');
        $this->putContent('fr/notes/intro.htm', '<p>Introduction</p>');
        $this->putContent('notes/intro.fr.htm', '<p>Legacy</p>');

        $this->swapPost([
            'documentMetadata' => ['type' => 'content', 'path' => 'notes/intro.htm'],
        ]);

        $this->invokeCommand('deleteContentDocument');

        $this->assertFalse(File::exists($this->contentPath('notes/intro.htm')));
        $this->assertFalse(File::exists($this->contentPath('fr/notes/intro.htm')));
        $this->assertFalse(File::exists($this->contentPath('notes/intro.fr.htm')));
    }

    /**
     * openDocument opens a content block through the editor command handler
     */
    protected function openDocument(string $path): array
    {
        $this->swapPost([
            'documentData' => ['type' => 'content', 'key' => $path],
        ]);

        return $this->invokeCommand('openContentDocument');
    }

    /**
     * saveDocument saves existing content block markup through the editor command handler
     */
    protected function saveDocument(string $path, string $markup, array $metadata = [])
    {
        $this->swapPost([
            'documentMetadata' => ['type' => 'content', 'path' => $path] + $metadata,
            'documentForceSave' => $metadata ? 0 : 1,
            'documentData' => ['fileName' => $path, 'markup' => $markup],
        ]);

        return $this->invokeCommand('saveContentDocument');
    }

    /**
     * invokeCommand calls a protected editor extension command handler
     */
    protected function invokeCommand(string $name)
    {
        $method = new ReflectionMethod(EditorExtension::class, $name);
        $method->setAccessible(true);

        return $method->invoke(new EditorExtension, new Index);
    }

    /**
     * swapPost installs a POST request so post() reads the payload
     */
    protected function swapPost(array $data): void
    {
        $request = \Illuminate\Http\Request::create('/', 'POST', $data);
        app()->instance('request', $request);
        \Illuminate\Support\Facades\Facade::clearResolvedInstance('request');
    }

    /**
     * putContent writes a content file to the fixture theme
     */
    protected function putContent(string $fileName, string $markup): void
    {
        $path = $this->contentPath($fileName);
        File::makeDirectory(dirname($path), 0777, true, true);
        File::put($path, $markup);
    }

    /**
     * contentPath returns the absolute path to a fixture content file
     */
    protected function contentPath(string $fileName): string
    {
        return $this->theme->getPath().'/content/'.$fileName;
    }

    /**
     * setUpMultisite installs an English primary site plus one site per extra locale, with the given site active
     */
    protected function setUpMultisite(int $activeSiteId, array $locales = ['fr'])
    {
        $sites = Model::unguarded(function() use ($locales) {
            $definitions = [
                new SiteDefinition([
                    'id' => 1,
                    'name' => 'Primary',
                    'code' => 'primary',
                    'locale' => 'en',
                    'is_primary' => true,
                    'is_enabled' => true,
                    'is_enabled_edit' => true
                ])
            ];

            foreach (array_values($locales) as $index => $locale) {
                $definitions[] = new SiteDefinition([
                    'id' => $index + 2,
                    'name' => strtoupper($locale),
                    'code' => $locale,
                    'locale' => $locale,
                    'is_primary' => false,
                    'is_enabled' => true,
                    'is_enabled_edit' => true
                ]);
            }

            return new SiteCollection($definitions);
        });

        $this->setProtectedProperty(SiteManager::instance(), 'sites', $sites);
        Config::set('system.active_site', $activeSiteId);
    }
}
