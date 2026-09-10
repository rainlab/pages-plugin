# Introduction

The Pages plugin brings file-based static pages and menus to the CMS, letting you build a website structure without writing code for each page.

The plugin includes four components: Static Page, Static Menu, Static Breadcrumbs and Child Pages.

In the simplest case you can create a [layout](https://octobercms.com/docs/cms/layouts) in the CMS area and include the plugin's components in its body. The next example layout outputs a menu, breadcrumbs and a static page:

```twig
<html>
    <head>
        <title>{{ this.page.title }}</title>
    </head>
    <body>
        {% component 'staticMenu' %}
        {% component 'staticBreadcrumbs' %}
        {% page %}
    </body>
</html>
```

![image](https://raw.githubusercontent.com/rainlab/pages-plugin/master/docs/static-layout.png)  {.img-responsive .frame}
