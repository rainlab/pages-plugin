<?php

use RainLab\Pages\Classes\EditorExtension;

require_once __DIR__.'/PagesPluginTestCase.php';

/**
 * PreviewUrlTest covers the editor preview URL. It must use the base URL of the site
 * selected in the backend, not the host of the current request.
 */
class PreviewUrlTest extends PagesPluginTestCase
{
    /**
     * testPreviewUrlUsesSelectedSiteBaseUrl selects a site with its own domain and
     * expects the preview URL on that domain.
     */
    public function testPreviewUrlUsesSelectedSiteBaseUrl(): void
    {
        $this->setUpMultisite();

        $this->assertEquals('https://other.example.com/about', $this->previewUrl('/about'));
        $this->assertEquals('https://other.example.com', $this->previewUrl('/'));
    }

    /**
     * testPreviewUrlKeepsSiteRoutePrefix expects a prefixed site to keep its prefix
     */
    public function testPreviewUrlKeepsSiteRoutePrefix(): void
    {
        $this->setUpMultisite(['is_prefixed' => true, 'route_prefix' => '/fr']);

        $this->assertEquals('https://other.example.com/fr/about', $this->previewUrl('/about'));
    }

    /**
     * testPreviewUrlIsNullWithoutUrl expects no preview link for a page without a URL
     */
    public function testPreviewUrlIsNullWithoutUrl(): void
    {
        $this->setUpMultisite();

        $this->assertNull($this->previewUrl(''));
    }

    /**
     * previewUrl returns the editor preview URL with the second site selected
     */
    protected function previewUrl(string $url): ?string
    {
        return Site::withContext(2, function() use ($url) {
            return self::callProtectedMethod(new EditorExtension, 'pagePreviewUrl', [$url]);
        });
    }

    /**
     * setUpMultisite installs a two-site fixture where the second site has a custom domain
     */
    protected function setUpMultisite(array $otherSite = []): void
    {
        $this->setUpSites([
            [
                'id' => 1,
                'name' => 'Primary',
                'code' => 'primary',
                'is_primary' => true,
                'is_enabled' => true,
                'is_enabled_edit' => true
            ],
            $otherSite + [
                'id' => 2,
                'name' => 'Other',
                'code' => 'other',
                'is_primary' => false,
                'is_enabled' => true,
                'is_enabled_edit' => true,
                'is_custom_url' => true,
                'app_url' => 'https://other.example.com'
            ]
        ]);
    }
}
