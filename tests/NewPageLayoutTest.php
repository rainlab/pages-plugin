<?php

use RainLab\Pages\Classes\Page;
use RainLab\Pages\Classes\EditorExtension;
use RainLab\Pages\Controllers\Index;

require_once __DIR__.'/PagesPluginTestCase.php';

/**
 * NewPageLayoutTest covers the layout of a page created in the editor without choosing
 * a layout. The default layout must be written to the page file, otherwise the page
 * falls back to the first layout found on disk, which depends on the filesystem order.
 */
class NewPageLayoutTest extends PagesPluginTestCase
{
    /**
     * swapPost installs a POST request so post() reads the payload.
     */
    protected function swapPost(array $data): void
    {
        $request = \Illuminate\Http\Request::create('/', 'POST', $data);
        app()->instance('request', $request);
        \Illuminate\Support\Facades\Facade::clearResolvedInstance('request');
    }

    /**
     * testNewPageStoresDefaultLayout saves a new page without a layout and expects the
     * theme layout marked as default to be stored in the saved view bag.
     */
    public function testNewPageStoresDefaultLayout()
    {
        $this->swapPost([
            'documentMetadata' => ['type' => 'static-page', 'path' => ''],
            'documentData' => [
                'settings' => ['title' => 'Layout Page', 'url' => '/layoutpage'],
                'markup' => '<p>hi</p>',
            ],
        ]);

        $extension = new EditorExtension;
        $method = new ReflectionMethod($extension, 'savePageDocument');
        $method->setAccessible(true);
        $result = $method->invoke($extension, new Index);

        $saved = Page::load($this->theme, array_get($result, 'metadata.path'));

        $this->assertNotNull($saved);
        $this->assertEquals('default', array_get($saved->getViewBag()->getProperties(), 'layout'));
    }
}
