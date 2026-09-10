# Tailor Integration

This plugin includes integration with Tailor by providing content fields for selecting static pages and menus inside a Tailor Blueprint. The field types share the same names as the [form widgets](./form-widgets.md).

## Static Page Picker Field

The `staticpagepicker` field type stores a reference to a static page. The functionality is introduced by the `RainLab\Pages\ContentFields\PagePickerField` PHP class.

```yaml
landing_page:
    label: Landing Page
    type: staticpagepicker
```

The stored value is the static page's file name, which can be used to link to the page using the `|staticPage` filter described in the [Static Pages](./static-pages.md) article.

## Static Menu Picker Field

The `staticmenupicker` field type stores a reference to a static menu. The functionality is introduced by the `RainLab\Pages\ContentFields\MenuPickerField` PHP class.

```yaml
main_menu:
    label: Main Menu
    type: staticmenupicker
```

The stored value is the static menu's code, which can be passed to the [Static Menu component](./component-static-menu.md) to render the menu.
