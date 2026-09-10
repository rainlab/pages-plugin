# Events

This plugin will fire some global events that can be useful for interacting with other plugins.

Events | Description
------ | ---------------
**pages.menu.referencesGenerated** | Fires after a menu's item references have been generated, right before they are injected into the page object. Passes the `$items` collection of `MenuItemReference` objects by reference, so entries can be filtered or modified. See the [Menus](./menus.md) article.
**pages.page.getProcessedMarkup** | Fires after a static page's markup has been processed. Passes the `$markup` string by reference to enable further modification.
**pages.page.getProcessedPlaceholderMarkup** | Fires after a placeholder's markup has been processed. Passes the `$markup` string by reference to enable further modification.
**pages.router.getCacheKey** | Enables modifying the key used to reference cached routes. Passes the `$key` string by reference.
**pages.page.getMenuCacheKey** | Enables modifying the key used to reference cached menu trees. Passes the `$key` string by reference.

Here is an example of hooking an event to hide menu entries for a specific user group:

```php
Event::listen('pages.menu.referencesGenerated', function (&$items) {
    foreach ($items as $item) {
        if (isset($item->viewBag['group']) && $item->viewBag['group'] !== 'registered') {
            $item->viewBag['isHidden'] = '1';
        }
    }
});
```

Cache key events are useful when the same theme serves different content per locale, so cached routes and menus can be separated:

```php
Event::listen('pages.router.getCacheKey', function (&$key) {
    $key = $key . '-' . App::getLocale();
});
```

## Menu Item Type Events

Menu item types are registered and resolved through the shared page lookup API events (`cms.pageLookup.listTypes`, `cms.pageLookup.getTypeInfo` and `cms.pageLookup.resolveItem`). These are covered in detail in the [Menu Item Types](./menu-item-types.md) article.
