<?php namespace RainLab\Pages\Classes;

/**
 * MenuItemReference represents a front-end menu item, used on the front-end.
 * In the back-end items are represented with the
 * \RainLab\Pages\Classes\MenuItem objects.
 */
class MenuItemReference
{
    /**
     * @var string type specifies the menu item type.
     */
    public $type;

    /**
     * @var string title specifies the item title.
     */
    public $title;

    /**
     * @var string url specifies the item URL.
     */
    public $url;

    /**
     * @var string code specifies the menu item code.
     */
    public $code;

    /**
     * @var bool isActive indicates whether the item corresponds to the currently viewed page.
     */
    public $isActive = false;

    /**
     * @var bool isChildActive indicates whether a subitem corresponds to the currently viewed page.
     */
    public $isChildActive = false;

    /**
     * @var array items specifies the item subitems.
     */
    public $items = [];

    /**
     * @var array viewBag specifies the item custom view bag properties.
     */
    public $viewBag = [];

    /**
     * @var array attributes contains additional item properties.
     */
    public $attributes = [];
}
