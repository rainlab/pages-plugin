# Menus

Menus are managed in the backend under the Pages area and stored as meta files in the active theme.

## Displaying a Menu

Add the [Static Menu component](./component-static-menu.md) to a layout to output a menu. The `code` property refers to the code of the static menu the component should display.

## Manually Displaying a Static Menu

When a static menu is first created it is assigned a file name based on the menu name (the menu code can also be manually defined). For example, a menu with the name **Primary Nav** creates a meta file called **menus/primary-nav.yaml** in the theme. This file does not change even if the menu name is changed at a later time.

To render a static menu based on a menu code from the `staticmenupicker` dropdown form widget, you can either define the `code` property on the staticMenu component:

```twig
{% component 'staticMenu' code=this.theme.primary_menu %}
```

Or use the `resetMenu` method on the staticMenu component, so you can manually control the menu output without having to create a staticMenu partial override:

```twig
{% set menuItems = staticMenu.resetMenu(this.theme.primary_menu) %}

<ul>
{% for item in menuItems %}
    <li><a href="{{ item.url }}">{{ item.name }}</a></li>
{% endfor %}
</ul>
```

## Setting the Active Menu Item Explicitly

In some cases you might want to mark a specific menu item as active explicitly. You can do that in the page's [`onInit()`](https://octobercms.com/docs/cms/pages#dynamic-pages) function by assigning the `activeMenuItem` page variable a value matching the menu item code you want to make active. Menu item codes are managed in the Edit Menu Item popup.

```php
function onInit()
{
    $this['activeMenuItem'] = 'blog';
}
```

## Custom Menu Item Form Fields

Just like CMS objects have the view bag component to store arbitrary values, you may use the `viewBag` property of the `MenuItem` class to store custom data values and add corresponding form fields.

```php
Event::listen('backend.form.extendFields', function ($widget) {
    if (
        !$widget->getController() instanceof \RainLab\Pages\Controllers\Index ||
        !$widget->model instanceof \RainLab\Pages\Classes\MenuItem
    ) {
        return;
    }

    $widget->addTabFields([
        'viewBag[featured]' => [
            'tab' => 'Display',
            'label' => 'Featured',
            'comment' => 'Mark this menu item as featured',
            'type' => 'checkbox'
        ]
    ]);
});
```

This value can then be accessed in Twig using the `{{ item.viewBag }}` property on the menu item. For example:

```twig
{% for item in items %}
    <li class="{{ item.viewBag.featured ? 'featured' }}">
        <a href="{{ item.url }}">
            {{ item.title }}
        </a>
    </li>
{% endfor %}
```
