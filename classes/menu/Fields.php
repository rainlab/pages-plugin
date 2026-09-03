<?php namespace RainLab\Pages\Classes\Menu;

/**
 * Fields defines the menu settings form shown in the Editor Inspector.
 */
class Fields
{
    /**
     * defineSettingsFields returns the Inspector field definitions for a menu.
     */
    public function defineSettingsFields(): array
    {
        return [
            'name' => [
                'title' => "Name",
                'placeholder' => "New menu",
                'type' => 'string',
                'validation' => [
                    'required' => ['message' => "The Name is required."]
                ]
            ],
            'code' => [
                'title' => "Code",
                'placeholder' => "new-menu",
                'type' => 'string',
                'preset' => ['property' => "name", 'type' => 'file'],
                'validation' => [
                    'required' => ['message' => "The Code is required."],
                    'regex' => [
                        'message' => "Invalid Code format. The Code can contain digits, Latin letters and the following symbols: _-",
                        'pattern' => '^[0-9a-z\\-\\_]+$'
                    ]
                ]
            ]
        ];
    }
}
