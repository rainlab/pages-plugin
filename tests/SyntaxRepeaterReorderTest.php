<?php

use RainLab\Pages\Classes\Page;
use RainLab\Pages\Classes\EditorExtension;
use RainLab\Pages\Controllers\Index;

require_once __DIR__.'/PagesPluginTestCase.php';

/**
 * SyntaxRepeaterReorderTest covers drag-reordering blocks in a grouped repeater syntax
 * field. The Vue editor collects repeater items into a JavaScript object keyed by their
 * original indexes, which re-sorts ascending and loses the drag order. The client sends a
 * larajax request envelope (__ajax.orders) so the server restores the intended order before
 * the save handler runs.
 */
class SyntaxRepeaterReorderTest extends PagesPluginTestCase
{
    /**
     * makeBlocksPage creates a page on the blocks layout with three items in base order.
     */
    protected function makeBlocksPage(): Page
    {
        $page = Page::inTheme($this->theme);
        $page->fill([
            'settings' => [
                'viewBag' => [
                    'title' => 'Reorder Page',
                    'url' => '/reorderpage',
                    'layout' => 'blocks',
                    'sections' => [
                        ['_group' => 'documents', 'label' => 'First'],
                        ['_group' => 'quote', 'body' => 'Second'],
                        ['_group' => 'documents', 'label' => 'Third'],
                    ],
                ],
            ],
            'markup' => '<p>hi</p>',
        ]);
        $page->save();

        return $page;
    }

    /**
     * swapPost installs a POST request so post() and the larajax request read the payload.
     */
    protected function swapPost(array $data): void
    {
        $request = \Illuminate\Http\Request::create('/', 'POST', $data);
        app()->instance('request', $request);
        \Illuminate\Support\Facades\Facade::clearResolvedInstance('request');
    }

    /**
     * testReorderManifestPersistsDragOrder drags the third block to the top. The posted
     * item object is in ascending index order (as the JS serializer leaves it), and the
     * __ajax manifest carries the true DOM order [2, 0, 1]; the saved file must follow it.
     */
    public function testReorderManifestPersistsDragOrder()
    {
        $this->makeBlocksPage();
        $mtime = Page::load($this->theme, 'reorderpage')->mtime;

        $repeaterAlias = 'pagesSyntaxForm1ViewBagSections';

        $syntaxFormData = [
            'syntaxFields' => ['viewBag' => ['sections' => [
                0 => ['_group' => 'documents', 'label' => 'First'],
                1 => ['_group' => 'quote', 'body' => 'Second'],
                2 => ['_group' => 'documents', 'label' => 'Third'],
            ]]],
            $repeaterAlias . '_loaded' => '1',
        ];

        $this->swapPost([
            'documentMetadata' => ['type' => 'static-page', 'path' => 'reorderpage.htm', 'mtime' => $mtime],
            'documentForceSave' => 1,
            'documentData' => [
                'settings' => ['title' => 'Reorder Page', 'url' => '/reorderpage', 'layout' => 'blocks'],
                'markup' => '<p>hi</p>',
                'syntaxFormAlias' => 'pagesSyntaxForm1',
                'syntaxFormData' => $syntaxFormData,
            ],
            '__ajax' => ['orders' => [[
                'path' => ['documentData', 'syntaxFormData', 'syntaxFields', 'viewBag', 'sections'],
                'keys' => ['2', '0', '1'],
            ]]],
        ]);

        // The larajax dispatch applies the envelope before the handler runs.
        ajax()->request()->applyEnvelope();

        $extension = new EditorExtension;
        $method = new ReflectionMethod($extension, 'savePageDocument');
        $method->setAccessible(true);
        $method->invoke($extension, new Index);

        $saved = Page::load($this->theme, 'reorderpage');
        $sections = array_get($saved->getViewBag()->getProperties(), 'sections');

        $labels = array_map(function ($item) {
            return $item['label'] ?? $item['body'] ?? null;
        }, $sections);

        $this->assertEquals(['Third', 'First', 'Second'], $labels);
    }

    /**
     * testAscendingOrderSavesUnchanged confirms a save with no reordering (no manifest)
     * keeps the base order, so the envelope is only corrective.
     */
    public function testAscendingOrderSavesUnchanged()
    {
        $this->makeBlocksPage();
        $mtime = Page::load($this->theme, 'reorderpage')->mtime;

        $repeaterAlias = 'pagesSyntaxForm1ViewBagSections';

        $this->swapPost([
            'documentMetadata' => ['type' => 'static-page', 'path' => 'reorderpage.htm', 'mtime' => $mtime],
            'documentForceSave' => 1,
            'documentData' => [
                'settings' => ['title' => 'Reorder Page', 'url' => '/reorderpage', 'layout' => 'blocks'],
                'markup' => '<p>hi</p>',
                'syntaxFormAlias' => 'pagesSyntaxForm1',
                'syntaxFormData' => [
                    'syntaxFields' => ['viewBag' => ['sections' => [
                        0 => ['_group' => 'documents', 'label' => 'First'],
                        1 => ['_group' => 'quote', 'body' => 'Second'],
                        2 => ['_group' => 'documents', 'label' => 'Third'],
                    ]]],
                    $repeaterAlias . '_loaded' => '1',
                ],
            ],
        ]);

        ajax()->request()->applyEnvelope();

        $extension = new EditorExtension;
        $method = new ReflectionMethod($extension, 'savePageDocument');
        $method->setAccessible(true);
        $method->invoke($extension, new Index);

        $saved = Page::load($this->theme, 'reorderpage');
        $sections = array_get($saved->getViewBag()->getProperties(), 'sections');

        $labels = array_map(function ($item) {
            return $item['label'] ?? $item['body'] ?? null;
        }, $sections);

        $this->assertEquals(['First', 'Second', 'Third'], $labels);
    }
}
