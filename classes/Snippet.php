<?php namespace RainLab\Pages\Classes;

use Cms\Classes\Partial;
use Config;
use Cache;
use DOMDocument;
use System\Classes\ApplicationException;
use Cms\Classes\Controller as CmsController;

/**
 * Represents a static page snippet.
 *
 * @package rainlab\pages
 * @author Alexey Bobkov, Samuel Georges
 */
class Snippet
{
    const CACHE_KEY_PARTIAL_MAP = 'snippet-partial-map';

    const CACHE_PAGE_SNIPPET_MAP = 'snippet-map';

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
     * This method is used internally by the system and shouldn't be used
     * in front-end request handling calls.
     * @param \Cms\Classes\Theme $theme Specifies a parent theme.
     * @return array Returns an array of Snippet objects.
     */
    public static function listInTheme($theme)
    {
        $partials = Partial::listInTheme($theme, true);
        foreach ($partials as $partial) {
            $viewBag = $partial->getViewBag();
            if (strlen($viewBag->property('staticPageSnippetCode')))
                $result[] = new self($partial);
        }

        return $result;
    }

    /**
     * Finds a snippet by its code.
     * This method is used internally by the system.
     * @param \Cms\Classes\Theme $theme Specifies a parent theme.
     * @param string $code Specifies the snippet code.
     * @param boolean $getPartialSnippetMap Specifies whether caching is allowed for the call.
     * @return array Returns an array of Snippet objects.
     */
    public static function findByCode($theme, $code, $allowCaching = false)
    {
        if (!$allowCaching) {
            // If caching is not allowed, list snippets in the theme, 
            // initialize the snippet object and return it.
            $snippets = self::listInTheme($theme);
            foreach ($snippets as $snippet) {
                if ($snippet->code == $code)
                    return $snippet;
            }

            return null;
        }

        // If caching is allowed, try to load the partial name from the
        // cache and initialize the snippet from the partial.

        $map = self::getPartialSnippetMap($theme);
        if (!array_key_exists($code, $map))
            return null;

        $partialName = $map[$code];
        $partial = Partial::loadCached($theme, $partialName);
        if ($partial)
            return null;

        return new self($partial);
    }

    /**
     * Clears front-end run-time cache.
     * @param \Cms\Classes\Theme $theme Specifies a parent theme.
     */
    public static function clearCache($theme)
    {
        $keys = [self::CACHE_KEY_PARTIAL_MAP, self::CACHE_PAGE_SNIPPET_MAP];
        $keyBase = crc32($theme->getPath());

        foreach ($keys as $key)
            Cache::forget($keyBase.$key);
    }

    /**
     * Returns the component property list as array, in format compatible with Inspector.
     */
    public function getProperties()
    {
        return self::parseIniProperties($this->properties);
    }

    /**
     * Parses properties stored in a template in the INI format and converts them to an array.
     */
    protected static function parseIniProperties($properties)
    {
        $result = [];

        foreach ($properties as $propertyInfo=>$value) {
            $qualifiers = explode('|', $propertyInfo);

            if (($cnt = count($qualifiers)) < 2) {
                // Ignore lines with invalid format
                continue;
            }

            $propertyCode = trim($qualifiers[0]);
            if (!array_key_exists($propertyCode, $result))
                $result[$propertyCode] = [
                    'property' => $propertyCode
                ];

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

        return array_values($result);
    }

    /**
     * Parses the static page markup and renders snippets from the page.
     * @param string $pageName Specifies the static page file name (the name of the corresponding content block file).
     * @param \Cms\Classes\Theme $theme Specifies a parent theme.
     * @param string $markup Specifies the markup string to process.
     * @return string Returns the processed string.
     */
    public static function processPageMarkup($pageName, $theme, $markup)
    {
        // Load or initialize the snippet map for the given markup.
        //
        $key = crc32($theme->getPath()).self::CACHE_PAGE_SNIPPET_MAP;

        $map = [];
        $cached = Cache::get($key, false);
        if ($cached !== false && ($cached = @unserialize($cached)) !== false) {
            if (array_key_exists($pageName, $cached))
                $map = $cached[$pageName];
        }

        if (!$map) {
            $map = self::extractSnippetsFromMarkup($markup);

            if (!is_array($cached))
                $cached = [];

            $cached[$pageName] = $map;
            Cache::put($key, serialize($cached), Config::get('cms.parsedPageCacheTTL', 10));
        }

        $partialSnippetMap = self::getPartialSnippetMap($theme);
        $controller = CmsController::getController();
        foreach ($map as $snippetDeclaration => $snippetInfo) {
            $snippetCode = $snippetInfo['code'];

            if (!array_key_exists($snippetCode, $partialSnippetMap))
                throw new ApplicationException(sprintf('Partial for the snippet %s is not found', $snippetCode));

            $partialName = $partialSnippetMap[$snippetCode];

            $partialCode = $controller->renderPartial($partialName, $snippetInfo['properties']);

            $pattern = preg_quote($snippetDeclaration);
            $markup = mb_ereg_replace($pattern, $partialCode, $markup);
        }

        return $markup;
    }

    public static function processTemplateSettingsArray($settingsArray)
    {
        if (isset($settingsArray['viewBag']['staticPageSnippetProperties']['TableData'])) {
            $rows = $settingsArray['viewBag']['staticPageSnippetProperties']['TableData'];

            $columns = ['title', 'property', 'type', 'default', 'options'];

            $properties = [];
            foreach ($rows as $row) {
                $rowHasData = false;

                foreach ($columns as $column) {
                    if (isset($row[$column]) && strlen(trim($row[$column]))) {
                        $rowHasData = true;
                        $row[$column] = trim($row[$column]);
                    }
                }

                if (!$rowHasData)
                    continue;

                $properties['staticPageSnippetProperties['.$row['property'].'|type]'] = $row['type'];
                $properties['staticPageSnippetProperties['.$row['property'].'|title]'] = $row['title'];

                if (isset($row['default']) && strlen($row['default']))
                    $properties['staticPageSnippetProperties['.$row['property'].'|default]'] = $row['default'];

                if (isset($row['options']) && strlen($row['options'])) {
                    $options = explode('|', $row['options']);
                    foreach ($options as $index=>$option)
                        $properties['staticPageSnippetProperties['.$row['property'].'|options|'.$index.']'] = trim($option);
                }
            }

            unset($settingsArray['viewBag']['staticPageSnippetProperties']);

            foreach ($properties as $name=>$value)
                $settingsArray['viewBag'][$name] = $value;
        }

        return $settingsArray;
    }

    public static function processTemplateSettings($template)
    {
        if (!isset($template->viewBag['staticPageSnippetProperties']))
            return;

        $parsedProperties = self::parseIniProperties($template->viewBag['staticPageSnippetProperties']);

        foreach ($parsedProperties as $index=>&$property) {
            $property['id'] = $index;

            if (isset($property['options']))
                $property['options'] = implode('|', $property['options']);
        }

        $template->viewBag['staticPageSnippetProperties'] = $parsedProperties;
    }

    /**
     * Extends the partial form with Snippet fields.
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

        $fieldConfig = [
            'tab' => 'rainlab.pages::lang.snippet.partialtab',
            'type' => 'datatable',
            'columns' => [
                'title' => [
                    'title' => 'rainlab.pages::lang.snippet.column_property',
                ],
                'property' => [
                    'title' => 'rainlab.pages::lang.snippet.column_code',
                ],
                'type' => [
                    'title' => 'rainlab.pages::lang.snippet.column_type',
                    'type' => 'dropdown',
                    'options' => [
                        'string' => 'rainlab.pages::lang.snippet.column_type_string',
                        'checkbox' => 'rainlab.pages::lang.snippet.column_type_checkbox',
                        'dropdown' => 'rainlab.pages::lang.snippet.column_type_dropdown'
                    ]
                ],
                'default' => [
                    'title' => 'rainlab.pages::lang.snippet.column_default',
                ],
                'options' => [
                    'title' => 'rainlab.pages::lang.snippet.column_options',
                ]
            ]
        ];

       $formWidget->config->tabs['fields']['viewBag[staticPageSnippetProperties]'] = $fieldConfig;
    }

    protected static function getPartialSnippetMap($theme)
    {
        $result = [];

        $key = crc32($theme->getPath()).self::CACHE_KEY_PARTIAL_MAP;
        
        $cached = Cache::get($key, false);
        if ($cached !== false && ($cached = @unserialize($cached)) !== false)
            return $cached;

        $partials = Partial::listInTheme($theme);
        foreach ($partials as $partial) {
            $viewBag = $partial->getViewBag();

            $snippetCode = $viewBag->property('staticPageSnippetCode');
            if (!strlen($snippetCode))
                continue;

            $result[$snippetCode] = $partial->getFileName();
        }

        Cache::put($key, serialize($result), Config::get('cms.parsedPageCacheTTL', 10));

        return $result;
    }

    protected static function extractSnippetsFromMarkup($markup)
    {
        $map = [];
        $matches = [];
        if (preg_match_all('/\<figure\s+[^\>]+\>[^\<]*\<\/figure\>/i', $markup, $matches)) {
            foreach ($matches[0] as $snippetDeclaration) {
                $nameMatch = [];
                if (!preg_match('/data\-snippet\s*=\s*"([^"]+)"/', $snippetDeclaration, $nameMatch))
                    continue;

                $snippetCode = $nameMatch[1];

                $propertyMatches = [];
                if (!preg_match_all('/data\-property-(?<property>[^=]+)\s*=\s*\"(?<value>[^\"]+)\"/i', $snippetDeclaration, $propertyMatches))
                    continue;

                $properties = [];

                foreach ($propertyMatches['property'] as $index=>$propertyNames)
                    $properties[$propertyNames] = $propertyMatches['value'][$index];

                $map[$snippetDeclaration] = [
                    'code' => $snippetCode,
                    'properties' => $properties
                ];
            }
        }

        return $map;
    }
}