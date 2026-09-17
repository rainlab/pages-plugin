<?php

use Backend\Widgets\Form;
use October\Rain\Database\Model;

/**
 * SyntaxTagListModel is a plain jsonable-backed model standing in for a page's
 * viewBag, so the syntax repeater's save cycle can be exercised in isolation.
 */
class SyntaxTagListModel extends Model
{
    public $table = 'syntax_taglist_model';

    protected $jsonable = ['data'];

    protected $fillable = ['data'];

    public $timestamps = false;
}

/**
 * SyntaxTagListStoreTest covers a taglist nested in a static-pages "Blocks" repeater
 * (a grouped syntax-field repeater). A taglist in string mode must join its multiple
 * selections into a separator-delimited string on save, rather than losing all but
 * one value. This regressed when the Vue editor rewrite stopped routing syntax-field
 * values through the Form widget's getSaveData().
 */
class SyntaxTagListStoreTest extends PluginTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('syntax_taglist_model')) {
            Schema::create('syntax_taglist_model', function ($table) {
                $table->increments('id');
                $table->mediumText('data')->nullable();
            });
        }
    }

    public function tearDown(): void
    {
        Schema::dropIfExists('syntax_taglist_model');

        parent::tearDown();
    }

    /**
     * repeaterFields returns the field config for a grouped repeater whose "documents"
     * group holds a string-mode taglist, mirroring a Blocks definition.
     */
    protected function repeaterFields(): array
    {
        return [
            'data' => [
                'type' => 'repeater',
                'groups' => [
                    'documents' => [
                        'name' => 'Documents',
                        'fields' => [
                            'categories' => [
                                'type' => 'taglist',
                                'mode' => 'string',
                                'customTags' => false,
                                'options' => [
                                    'cad-a-bim-objekty' => 'CAD a BIM objekty',
                                    'technicke-vykresy' => 'Technicke vykresy',
                                    'fotografie' => 'Fotografie',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * swapPost replaces the request instance so post() reads the given data.
     */
    protected function swapPost(array $data): void
    {
        $request = \Illuminate\Http\Request::create('/', 'POST', $data);
        app()->instance('request', $request);
        \Illuminate\Support\Facades\Facade::clearResolvedInstance('request');
    }

    /**
     * repeaterAlias builds a throwaway form to discover the generated repeater alias,
     * needed to name the _loaded postback marker before the real form is built.
     */
    protected function repeaterAlias(): string
    {
        $probe = new Form(new \Backend\Classes\Controller, [
            'model' => new SyntaxTagListModel,
            'arrayName' => 'SyntaxTagListModel',
            'fields' => $this->repeaterFields(),
        ]);
        $probe->bindToController();

        return $probe->getFormWidget('data')->alias;
    }

    /**
     * saveGroupedTaglist posts a repeater item and returns the saved repeater array.
     *
     * The postback is installed before the form is built, matching the real request
     * lifecycle: the repeater seeds its item widgets from the postback at init time,
     * which is what lets the nested taglist apply its string-mode save processing.
     */
    protected function saveGroupedTaglist(array $categories): array
    {
        $data = [0 => ['_group' => 'documents', 'categories' => $categories]];

        $this->swapPost([
            'SyntaxTagListModel' => ['data' => $data],
            $this->repeaterAlias() . '_loaded' => '1',
        ]);

        $form = new Form(new \Backend\Classes\Controller, [
            'model' => new SyntaxTagListModel,
            'arrayName' => 'SyntaxTagListModel',
            'fields' => $this->repeaterFields(),
        ]);
        $form->bindToController();

        return (array) $form->getFormWidget('data')->getSaveValue($data);
    }

    /**
     * testMultipleTagsSaveAsJoinedString posts a taglist holding two selections; the
     * saved value must be the comma-joined string, not a single-element array.
     */
    public function testMultipleTagsSaveAsJoinedString()
    {
        $saved = $this->saveGroupedTaglist(['technicke-vykresy', 'cad-a-bim-objekty']);

        $this->assertArrayHasKey(0, $saved);
        $this->assertEquals(
            'technicke-vykresy,cad-a-bim-objekty',
            $saved[0]['categories'],
            'A string-mode taglist must join its selections rather than storing an array.'
        );
    }

    /**
     * testSingleTagSaveAsString confirms a lone selection is still stored as a plain
     * string, matching string-mode taglist behavior.
     */
    public function testSingleTagSaveAsString()
    {
        $saved = $this->saveGroupedTaglist(['fotografie']);

        $this->assertEquals('fotografie', $saved[0]['categories']);
    }
}
