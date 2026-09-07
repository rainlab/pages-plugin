<?php namespace RainLab\Pages\VueComponents;

/**
 * StaticPageEditor is the Vue editing surface for a static page document.
 */
class StaticPageEditor extends \System\Classes\VueComponentBase
{
    /**
     * @var string componentName is the Vue tag matched by the document controller.
     */
    protected $componentName = 'pages-editor-component-staticpage-editor';

    /**
     * @var array require lists peer Vue components loaded before this one.
     */
    protected $require = [
        \Backend\VueComponents\MonacoEditor::class,
        \Backend\VueComponents\RichEditorDocumentConnector::class
    ];
}
