<?php namespace RainLab\Pages\Classes\StaticPage;

/**
 * Fields defines the static page settings form shown in the Editor Inspector.
 */
class Fields
{
    /**
     * defineSettingsFields returns the Inspector field definitions for a static page.
     */
    public function defineSettingsFields(): array
    {
        return [
            'title' => [
                'title' => "Title",
                'placeholder' => "New page title",
                'type' => 'string',
                'validation' => [
                    'required' => ['message' => "The Title is required."]
                ]
            ],
            'url' => [
                'title' => "URL",
                'placeholder' => "/",
                'type' => 'string',
                'preset' => ['property' => "title", 'type' => 'url'],
                'validation' => [
                    'required' => ['message' => "The URL is required."],
                    'regex' => [
                        'message' => "Invalid URL format. The URL should start with the forward slash symbol and can contain digits, Latin letters and the following symbols: _-/.",
                        'pattern' => '^/[a-z0-9/_\\-\\.]*$'
                    ]
                ]
            ],
            'fileName' => [
                'title' => "File Name",
                'type' => 'string',
                'preset' => ['property' => "title", 'type' => 'file'],
                'validation' => [
                    'required' => ['message' => "The File Name is required."]
                ]
            ],
            'layout' => [
                'title' => "Layout",
                'type' => 'dropdown',
                'placeholder' => "Layouts not found"
            ],
            'is_hidden' => [
                'title' => "Hidden",
                'type' => 'checkbox',
                'description' => "Hidden pages are accessible only by logged-in back-end users."
            ],
            'navigation_hidden' => [
                'title' => "Hide in navigation",
                'type' => 'checkbox',
                'description' => "Check this box to hide this page from automatically generated menus and breadcrumbs."
            ],
            'meta_title' => [
                'title' => "Title",
                'type' => 'string',
                'tab' => "Meta"
            ],
            'meta_description' => [
                'title' => "Description",
                'type' => 'text',
                'size' => 'medium',
                'tab' => "Meta"
            ]
        ];
    }
}
