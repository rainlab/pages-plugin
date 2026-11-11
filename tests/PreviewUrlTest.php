<?php

use System\Classes\SiteManager;
use System\Classes\SiteCollection;
use System\Models\SiteDefinition;
use RainLab\Pages\Classes\EditorExtension;

require_once __DIR__.'/PagesPluginTestCase.php';

/**
 * PreviewUrlTest covers the editor preview URL. It must use the base URL of the site
 * selected in the backend, not the host of the current request.
 */
class PreviewUrlTest extends PagesPluginTestCase
{
    public function tearDown(): void
    {
        $this->setProtectedProperty(SiteManager::instance(), 'sites', null);

        parent::tearDown();
    }

    /**
     * testPreviewUrlUsesSelectedSiteBaseUrl selects a site with its own domain and
     * expects the preview URL on that domain.
     */
    public function testPreviewUrlUsesSelectedSiteBaseUrl()
    {
        $this->setUpMultisite();

        $previewUrl = Site::withContext(2, function () {
            $method = new ReflectionMethod(EditorExtension::class, 'pagePreviewUrl');
            $method->setAccessible(true);

            return $method->invoke(new EditorExtension, '/about');
        });

        $this->assertEquals('https://other.example.com/about', $previewUrl);
    }

    /**
     * setUpMultisite installs a two-site fixture where the second site has a custom domain
     */
    protected function setUpMultisite()
    {
        $sites = Model::unguarded(function() {
            return new SiteCollection([
                new SiteDefinition([
                    'id' => 1,
                    'name' => 'Primary',
                    'code' => 'primary',
                    'is_primary' => true,
                    'is_enabled' => true,
                    'is_enabled_edit' => true
                ]),
                new SiteDefinition([
                    'id' => 2,
                    'name' => 'Other',
                    'code' => 'other',
                    'is_primary' => false,
                    'is_enabled' => true,
                    'is_enabled_edit' => true,
                    'is_custom_url' => true,
                    'app_url' => 'https://other.example.com'
                ])
            ]);
        });

        $this->setProtectedProperty(SiteManager::instance(), 'sites', $sites);
    }
}
