<?php

use Cms\Classes\Theme;
use RainLab\Pages\Classes\Page;
use RainLab\Pages\Classes\Router;
use RainLab\Pages\Classes\PageList;

/**
 * PagesPluginTestCase is the base class for the plugin test suite. It activates
 * the fixture theme and restores any theme files modified during a test.
 */
abstract class PagesPluginTestCase extends PluginTestCase
{
    /**
     * @var \Cms\Classes\Theme theme fixture instance
     */
    protected $theme;

    /**
     * @var array themeSnapshot of fixture file contents, keyed by absolute path
     */
    protected $themeSnapshot = [];

    /**
     * setUp the test case
     */
    public function setUp(): void
    {
        parent::setUp();

        Config::set('cms.active_theme', 'test');
        Event::forget('cms.theme.getActiveTheme');
        Theme::resetCache();
        Page::unsetModelValidator();
        \RainLab\Pages\Classes\Menu::unsetModelValidator();

        $this->theme = Theme::load('test');
        $this->resetPluginCaches();
        $this->snapshotThemeFiles();
    }

    /**
     * tearDown the test case
     */
    public function tearDown(): void
    {
        $this->restoreThemeFiles();
        $this->resetPluginCaches();

        parent::tearDown();
    }

    /**
     * resetPluginCaches clears static caches held between requests
     */
    protected function resetPluginCaches()
    {
        (new Router($this->theme))->clearCache();
        Page::clearMenuCache($this->theme);
        \RainLab\Pages\Classes\Controller::forgetInstance();

        $this->resetStaticProperty(Page::class, 'menuTreeCache', null);
        $this->resetStaticProperty(PageList::class, 'configCache', false);
    }

    /**
     * resetStaticProperty forces a static property back to its initial value
     */
    protected function resetStaticProperty(string $class, string $property, $value)
    {
        $reflection = new ReflectionProperty($class, $property);
        $reflection->setAccessible(true);
        $reflection->setValue(null, $value);
    }

    /**
     * snapshotThemeFiles records the fixture theme contents
     */
    protected function snapshotThemeFiles()
    {
        $this->themeSnapshot = [];

        foreach (File::allFiles($this->theme->getPath()) as $file) {
            $this->themeSnapshot[$file->getPathname()] = file_get_contents($file->getPathname());
        }
    }

    /**
     * restoreThemeFiles reverts the fixture theme to its recorded state
     */
    protected function restoreThemeFiles()
    {
        if (!$this->themeSnapshot) {
            return;
        }

        foreach (File::allFiles($this->theme->getPath()) as $file) {
            if (!array_key_exists($file->getPathname(), $this->themeSnapshot)) {
                $this->deleteWithRetry($file->getPathname());
            }
        }

        foreach ($this->themeSnapshot as $path => $contents) {
            if (!File::exists($path) || file_get_contents($path) !== $contents) {
                File::put($path, $contents);
            }
        }
    }

    /**
     * deleteWithRetry deletes a file, retrying while external processes hold a lock
     */
    protected function deleteWithRetry(string $path)
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            File::delete($path);
            clearstatcache(true, $path);

            if (!File::exists($path)) {
                return;
            }

            usleep(100000);
        }
    }
}
