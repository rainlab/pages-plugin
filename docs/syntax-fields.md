# Syntax Fields

There is a special syntax you can use inside your layout to add custom fields to the page editor form, called *Syntax Fields*. For example, if you add the following markup to a Layout that uses Static Pages:

```
{variable name="tagline" label="Tagline" tab="Header" type="text"}{/variable}
{variable name="banner" label="Banner" tab="Header" type="mediafinder" mode="image"}{/variable}
{variable name="color" label="Color" tab="Header" type="dropdown"
    options="blue:Blue | orange:Orange | red:Red"
}{/variable}
```

These act just like regular form field definitions. Accessing the variables inside the markup is just as easy:

```twig
<h1>{{ tagline }}</h1>
<img src="{{ banner|media }}" alt="" />
```

All custom fields are placed in the Secondary tabs container (next to the Content field). If you need to place them in the Primary tabs container, use the `placement="primary"` attribute.

```
{variable name="tagline" label="Tagline" tab="Header" type="text" placement="primary"}{/variable}
```

Alternatively you may use the field type as the tag name. Here we use the `{text}` tag to directly render the `tagline` variable:

```html
<h1>{text name="tagline" label="Tagline"}Our wonderful website{/text}</h1>
```

You may also use the `{repeater}` tag for repeating content:

```html
{repeater name="content_sections" prompt="Add another content section"}
    <h3>
        {text name="content_header" label="Content section" placeholder="Type in a heading and enter some content for it below"}{/text}
    </h3>
    <div>
        {richeditor name="content_body" size="large"}{/richeditor}
    </div>
{/repeater}
```

For more details on syntax fields, see the [Parser section](https://octobercms.com/docs/services/parser#dynamic-syntax-parser) of the October documentation.

## Placeholders

[Placeholders](https://octobercms.com/docs/cms/layouts#placeholders) defined in the layout are automatically detected by the Pages plugin. The Edit Static Page form displays a tab for each placeholder defined in the layout used by the page. Placeholders are defined in the layout in the usual way:

```twig
{% placeholder ordering %}
```

The `placeholder` tag accepts some optional attributes:

- `title`: manages the tab title in the Static Page editor.
- `type`: manages the placeholder type. The following values are supported: **text**, **html** and **hidden**.

The content of text placeholders is escaped before it is displayed. Text placeholders are edited with a regular (non-WYSIWYG) text editor. The title and type attributes should be defined after the placeholder code:

```twig
{% placeholder ordering title="Ordering information" type="text" %}
```

They should also appear after the `default` attribute, if it is present.

```twig
{% placeholder ordering default title="Ordering information" type="text" %}
    There is no ordering information for this product.
{% endplaceholder %}
```

To prevent a placeholder from appearing in the editor, set the `type` attribute to **hidden**.

```twig
{% placeholder systemInfo type="hidden" %}
```
