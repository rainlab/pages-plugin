# Form Widgets

The plugin provides two form widgets for selecting static pages and menus in your own backend forms. The same names are also available as [Tailor content fields](./tailor.md).

## Static Page Picker

If you need to select from a list of static pages in your own backend forms, you can use the `staticpagepicker` widget:

```yaml
fields:
    field_name:
        label: Static Page
        type: staticpagepicker
```

The field's assigned value will be the static page's file name, which can be used to link to the page as described in the [Static Pages](./static-pages.md) article.

## Static Menu Picker

If you need to select from a list of static menus in your own backend forms, you can use the `staticmenupicker` widget:

```yaml
fields:
    field_name:
        label: Static Menu
        type: staticmenupicker
```

The field's assigned value will be the static menu's code, which can be used to link to the menu as described in the [Menus](./menus.md) article.
