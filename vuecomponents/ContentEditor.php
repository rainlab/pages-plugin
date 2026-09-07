<?php namespace RainLab\Pages\VueComponents;

/**
 * ContentEditor is the Vue editing surface for a content block document.
 */
class ContentEditor extends \System\Classes\VueComponentBase
{
    /**
     * @var string componentName is the Vue tag matched by the document controller.
     */
    protected $componentName = 'pages-editor-component-content-editor';

    /**
     * @var array require lists peer Vue components loaded before this one.
     */
    protected $require = [
        \Backend\VueComponents\MonacoEditor::class,
        \Backend\VueComponents\RichEditorDocumentConnector::class,
        \Backend\VueComponents\DocumentMarkdownEditor::class
    ];
}
