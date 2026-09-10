# Static Pages

Static pages are managed in the backend under the Pages area and stored as content files in the active theme.

## Displaying a Static Page

Include the [Static Page component](./component-static-page.md) in a layout. The layout can then be selected when editing a static page. It is recommended to render the page contents using `{% page %}` rather than the component's default partial, to match how CMS pages are rendered.

## Linking to Static Pages

When a static page is first created it is assigned a file name based on the URL. For example, a page with the URL **/chairs** creates a content file called **static-pages/chairs.htm** in the theme. This file does not change even if the URL is changed at a later time.

To create a link to a static page, use the `|staticPage` filter:

```twig
<a href="{{ 'chairs'|staticPage }}">Go to Chairs</a>
```

This filter translates to PHP code as:

```php
echo \RainLab\Pages\Classes\Page::url('chairs');
```

If you want to link to the static page by its URL, simply use the `|app` filter:

```twig
<a href="{{ '/chairs'|app }}">Go to Chairs</a>
```

Linking to the current page, if the component name is `staticPage`:

```twig
{{ staticPage.page.baseFileName|staticPage }}
```
