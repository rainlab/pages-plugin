# Static Menu Component

The `staticMenu` component outputs a single menu. The default component partial outputs a simple nested unordered list for menus:

```html
<ul>
    <li>
        <a href="https://example.com">Home</a>
    </li>
    <li class="child-active">
        <a href="https://example.com/about">About</a>
        <ul>
            <li class="active">
                <a href="https://example.com/about/directions">Directions</a>
            </li>
        </ul>
    </li>
</ul>
```

The following properties are available:

Property | Inspector Name | Description
-------- | -------------- | -----------
`code` | Menu | The code (identifier) for the menu that should be displayed by the component

The following page variables are available:

Variable | Type | Description
-------- | ---- | -----------
`menuItems` | `array` | Array of `RainLab\Pages\Classes\MenuItemReference` objects representing the defined menu

You might want to render the menus with your own code. The `menuItems` variable is an array of the `RainLab\Pages\Classes\MenuItemReference` objects. Each object has the following properties:

Property | Type | Description
-------- | ---- | -----------
`title` | `string` | Menu item title
`url` | `string` | Absolute menu item URL
`isActive` | `bool` | Indicates whether the item corresponds to a page currently being viewed
`isChildActive` | `bool` | Indicates whether the item contains an active subitem
`items` | `array` | The menu item subitems, if any. If there are no subitems, the array is empty

## Example of Custom Markup for Component

```html
{% for item in staticMenu.menuItems %}
    <li><a href="{{ item.url }}">{{ item.title }}</a></li>
{% endfor %}
```

## Setting the Active Menu Item Explicitly

In some cases you might want to mark a specific menu item as active explicitly. You can do that in the page's [`onInit()`](https://octobercms.com/docs/cms/pages#dynamic-pages) function by assigning the `activeMenuItem` page variable a value matching the menu item code you want to make active. Menu item codes are managed in the Edit Menu Item popup.

```php
function onInit()
{
    $this['activeMenuItem'] = 'blog';
}
```
