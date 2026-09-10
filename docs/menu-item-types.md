# Menu Item Types

Plugins can extend the Pages plugin with new menu item types. Please refer to the [Blog plugin](https://octobercms.com/plugin/rainlab-blog) for the integration example. New item types are registered with the core page lookup API events, shared with the CMS `pagefinder` form widget. One registration makes a type available in both the menu editor and the page finder. The event handlers should be defined in the `boot()` method of the [plugin registration file](https://octobercms.com/docs/plugin/registration#registration-file). There are three events that should be handled in the plugin.

- `cms.pageLookup.listTypes` event handler should return a list of new menu item types supported by the plugin.
- `cms.pageLookup.getTypeInfo` event handler returns detailed information about a menu item type.
- `cms.pageLookup.resolveItem` event handler "resolves" a menu item's information and returns the actual item URL, title, an indicator whether the item is currently active, and subitems, if any.

> **Note**: earlier versions of this plugin used `pages.menuitem.*` events for this purpose; these are no longer fired and plugins should migrate to the `cms.pageLookup.*` events above (same handler signatures).

The next example shows an event handler registration for the Blog plugin. The Blog plugin registers two item types. As you can see, the Blog plugin uses the Category class to handle the events. That is a recommended approach.

```php
public function boot()
{
    Event::listen('cms.pageLookup.listTypes', function() {
        return [
            'blog-category'=>'Blog category',
            'all-blog-categories'=>'All blog categories',
        ];
    });

    Event::listen('cms.pageLookup.getTypeInfo', function($type) {
        if ($type == 'blog-category' || $type == 'all-blog-categories') {
            return Category::getMenuTypeInfo($type);
        }
    });

    Event::listen('cms.pageLookup.resolveItem', function($type, $item, $url, $theme) {
        if ($type == 'blog-category' || $type == 'all-blog-categories') {
            return Category::resolveMenuItem($item, $url, $theme);
        }
    });
}
```

## Registering New Menu Item Types

New menu item types are registered with the `cms.pageLookup.listTypes` event handlers. The handler should return an associative array with the type codes in indexes and type names in values. It is highly recommended to use the plugin name in the type codes, to avoid conflicts with other menu item type providers. Example:

```php
[
    'my-plugin-item-type' => 'My plugin menu item type'
]
```

Types that generate nested items can use the extended label format so that single-URL contexts (such as the page finder in single mode) can exclude them:

```php
[
    'all-my-plugin-items' => ['label' => 'All my plugin items', 'nesting' => true]
]
```

## Returning Information About an Item Type

Plugins should provide detailed information about the supported menu item types with the `cms.pageLookup.getTypeInfo` event handlers. The handler gets a single parameter, the menu item type code (one of the codes you registered with the `cms.pageLookup.listTypes` handler). The handler code must check whether the requested item type code belongs to the plugin. The handler should return an associative array in the following format:

```
Array (
    [dynamicItems]  => 0,
    [nesting]       => 0,
    [references]    => Array (
        [11] => News,
        [12] => Tutorials,
        [33] => Philosophy
    )
    [cmsPages]      => Array (
        [0] => Cms\Classes\Page object,
        [1] => Cms\Classes\Page object
    )
)
```

All elements of the array are optional and depend on the menu item type. The default values for `dynamicItems` and `nesting` are `false` and these keys can be omitted.

### dynamicItems element

The `dynamicItems` element is a Boolean value indicating whether the item type could generate new menu items. Optional, false if omitted. Examples of menu item types that generate new menu items: **All blog categories**, **Static page**. Examples of item types that do not generate new menu items: **URL**, **Blog category**.

### nesting element

The `nesting` element is a Boolean value indicating whether the item type supports nested items. Optional, `false` if omitted. Examples of item types that support nesting: **Static page**, **All static pages**. Examples of item types that do not support nesting: **Blog category**, **URL**.

### references element

The `references` element is a list of objects the menu item could refer to. For example, the **Blog category** menu item type returns a list of the blog categories. Some objects support nesting, for example static pages. Other objects do not support nesting, for example the blog categories. The format of the `references` value depends on whether the references have subitems or not. The format for references that do not support subitems is:

```
['item-key' => 'Item title']
```

The format for references with subitems is:

```
['item-key' => ['title'=>'Item title', 'items'=>[...]]]
```

The reference keys should reflect the object identifier they represent. For blog categories keys match the category identifiers. A plugin should be able to load an object by its key in the `cms.pageLookup.resolveItem` event handler. The references element is optional, it is required only if a menu item type supports the Reference drop-down, or, in other words, if the user should be able to select an object the menu item refers to.

### cmsPages element

The `cmsPages` is a list of CMS pages that can display objects supported by the menu item type. For example, for the **Blog category** item type the page list contains pages that host the `blogPosts` component. That component can display a blog category's contents. The `cmsPages` element should be an array of the `Cms\Classes\Page` objects. The next code snippet shows how to return a list of pages hosting a specific component.

```php
use Cms\Classes\Page as CmsPage;
use Cms\Classes\Theme;

// ...

$result = [];
// ...
$theme = Theme::getActiveTheme();
$pages = CmsPage::listInTheme($theme, true);

$cmsPages = [];
foreach ($pages as $page) {
    if (!$page->hasComponent('blogPosts')) {
        continue;
    }

    $cmsPages[] = $page;
}

$result['cmsPages'] = $cmsPages;
// ...
return $result;
```

## Resolving Menu Items

When the Pages plugin generates a menu on the front-end, every menu item should be **resolved** by the plugin that supplies the menu item type. The process of resolving involves generating the real item URL, determining whether the menu item is active, and generating the subitems (if required). Plugins should register the `cms.pageLookup.resolveItem` event handler in order to resolve menu items. The event handler takes four arguments:

* `$type` - the item type name. Plugins must only handle item types they provide and ignore other types.
* `$item` - the item object (`RainLab\Pages\Classes\MenuItem` when resolving a menu, or `Cms\Models\PageLookupItem` when resolving a page finder link). The item object represents the configuration provided by the user. Both objects expose the following properties: `title`, `type`, `reference`, `cmsPage`, `nesting`.
* `$url` - specifies the current absolute URL, in lower case. Always use the `Url::to()` helper to generate menu item links and compare them with the current URL.
* `$theme` - the current theme object (`Cms\Classes\Theme`).

The event handler should return an array. The array keys depend on whether the menu item contains subitems or not. Expected result format:

```
Array (
    [url] => https://example.com/blog/category/another-category
    [isActive] => 1,
    [items] => Array (
        [0] => Array  (
            [title] => Another category
            [url] => https://example.com/blog/category/another-category
            [isActive] => 1
        )

        [1] => Array (
                [title] => News
                [url] => https://example.com/blog/category/news
                [isActive] => 0
        )
    )
)
```

The `url` and `isActive` elements are required for menu items that point to a specific page, but that is not always the case. For example, the **All blog categories** menu item type does not have a specific page to point to. It generates multiple menu items. In this case the items should be listed in the `items` element. The `items` element should only be provided if the menu item's `nesting` property is `true`.

As the resolving process occurs every time the front-end page is rendered, it is a good idea to cache all the information required for resolving menu items, if that is possible.

If your item type requires a CMS page to resolve item URLs, you might need to return the selected page's URL, and sometimes pass parameters to the page through the URL. The next code example shows how to load a blog category CMS page referred to by a menu item and how to generate a URL to this page. The blog category page has the `blogPosts` component that can load the requested category slug from the URL. We assume that the URL parameter is called 'slug', although it can be edited manually. We skip the part that loads the real parameter name for simplicity. Please refer to the [Blog plugin](https://octobercms.com/plugin/rainlab-blog) for the reference.

```php
$page = \Cms\Classes\Page::loadCached($theme, $item->cmsPage);

// Always check if the page can be resolved
if (!$page) {
    return;
}

// Generate the URL
$url = \Cms::pageUrl($page->getBaseFileName(), ['slug' => $category->slug]);
```

To determine whether an item is active just compare it with the `$url` argument of the event handler.

## Overriding Generated References

In order to override generated references you can listen to the `pages.menu.referencesGenerated` event that fires right before injecting into the page object. For example, you can filter out unwanted menu entries.
