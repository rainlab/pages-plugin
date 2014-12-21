<?php namespace RainLab\Pages\Classes;

use Cms\Classes\Partial;

/**
 * Represents a static page snippet.
 *
 * @package rainlab\pages
 * @author Alexey Bobkov, Samuel Georges
 */
class Snippet
{
    /**
     * @var string Specifies the snippet code.
     */
    public $code;

    /**
     * @var string Specifies the snippet description.
     */
    public $description;

    /**
     * @var string Specifies the snippet name.
     */
    public $name;

    /**
     * @var string Snippet properties
     */
    protected $properties;

    /**
     * Creates a snippet object and loads its configuration from a partial.
     * @param \Cms\Classes\Partial $parital A partial to load the configuration from.
     */
    public function __construct($partial)
    {
        $viewBag = $partial->getViewBag();

        $this->code = $viewBag->property('staticPageSnippetCode');
        $this->description = $partial->description;
        $this->name = $viewBag->property('staticPageSnippetName');
        $this->properties = $viewBag->property('staticPageSnippetProperties', []);
    }

    /**
     * Returns the list of snippets in the specified theme.
     * This method is used internally by the system.
     * @param \Cms\Classes\Theme $theme Specifies a parent theme.
     * @return array Returns an array of Snippet objects.
     */
    public static function listInTheme($theme)
    {
        $result = [];

        $partials = Partial::listInTheme($theme, true);
        foreach ($partials as $partial) {
            $viewBag = $partial->getViewBag();
            if (strlen($viewBag->property('staticPageSnippetCode')))
                $result[] = new self($partial);
        }

        return $result;
    }

    /**
     * Returns the component property list as array, in format compatible with Inspector.
     */
    public function getProperties()
    {
        $result = [];

        foreach ($this->properties as $propertyInfo=>$value) {
            $qualifiers = explode('|', $propertyInfo);

            if (($cnt = count($qualifiers)) < 2) {
                // Ignore lines with invalid format
                continue;
            }

            $propertyCode = trim($qualifiers[0]);
            if (!array_key_exists($propertyCode, $result))
                $result[$propertyCode] = [];

            $paramName = trim($qualifiers[1]);

            // Handling the "[viewMode|options|list] => Display as a list" case
            if ($qualifiers[1] == 'options') {
                if ($cnt > 2) {
                    if (!array_key_exists('options', $result[$propertyCode])) 
                        $result[$propertyCode]['options'] = [];

                    $result[$propertyCode]['options'][$qualifiers[2]] = $value;
                }
            } else {
                $result[$propertyCode][$paramName] = $value;
            }
        }

        return $result;
    }

    /**
     * Extends the parital form with Snippet fields.
     */
    public static function extendPartialForm($formWidget)
    {
        /*
         * Snippet code field
         */

        $fieldConfig = [
            'tab' => 'rainlab.pages::lang.snippet.partialtab',
            'type' => 'text',
            'label' => 'rainlab.pages::lang.snippet.code',
            'comment' => 'rainlab.pages::lang.snippet.code_comment',
            'span' => 'left'
        ];

        $formWidget->config->tabs['fields']['viewBag[staticPageSnippetCode]'] = $fieldConfig;

        /*
         * Snippet description field
         */

        $fieldConfig = [
            'tab' => 'rainlab.pages::lang.snippet.partialtab',
            'type' => 'text',
            'label' => 'rainlab.pages::lang.snippet.name',
            'comment' => 'rainlab.pages::lang.snippet.name_comment',
            'span' => 'right'
        ];

        $formWidget->config->tabs['fields']['viewBag[staticPageSnippetName]'] = $fieldConfig;

        /*
         * Snippet properties field
         */

        // $fieldConfig = [
        //     'tab' => 'rainlab.pages::lang.snippet.partialtab',
        //     'type' => 'datagrid',
        //     'label' => 'rainlab.pages::lang.snippet.properties',
        //     'columns' => [
        //         'type' => [
        //             'title' => 'rainlab.pages::lang.snippet.column_title',
        //         ]
        //     ]
        // ];

        // $formWidget->config->tabs['fields']['viewBag[staticPagesProperties]'] = $fieldConfig;
    }
}