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

## Blocks

A repeater can offer the editor a choice of item types by supplying a `groups` attribute. Each item then picks a group, and only that group's fields are shown. This turns the repeater into a block builder, where every group is a reusable block definition. The `groups` attribute points to a YAML file, conventionally stored in the theme's `meta` directory:

```html
{repeater name="sections" groups="~/themes/website/meta/blocks.yaml" prompt="Add a block"}{/repeater}
```

The groups file defines each block by a code, with its `name`, an optional `icon`, and its `fields`:

```yaml
documents:
    name: Documents
    icon: icon-file-text-o
    fields:
        heading:
            label: Heading
            type: text
        categories:
            label: Categories
            type: taglist
            mode: string
            optionsMethod: getDocumentCategoryOptions
quote:
    name: Pull quote
    icon: icon-quote-left
    fields:
        body:
            label: Quote
            type: textarea
```

The `~` prefix resolves to the application directory, so `~/themes/website/meta/blocks.yaml` refers to the file inside the active theme. The `$` prefix may be used as an alternative absolute path syntax.

Each saved item stores its chosen block code alongside its field values, so you can branch on it in the markup. Loop over the items and render each block by its code:

```twig
{% for section in sections %}
    {% if section._group == 'documents' %}
        <h2>{{ section.heading }}</h2>
    {% elseif section._group == 'quote' %}
        <blockquote>{{ section.body }}</blockquote>
    {% endif %}
{% endfor %}
```

Nested widgets inside a block behave exactly as they do on a regular form. A `taglist` in `string` mode, for example, joins its selections into a separator-delimited string, and a `mediafinder` returns its attachment, so no special handling is needed when reading block values in the markup.

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
