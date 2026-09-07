<?php namespace RainLab\Pages\Classes;

use RainLab\Pages\Plugin;
use Cms\Classes\Controller as CmsController;

/**
 * ExtendCmsModule wires the frontend routing and rendering of static pages,
 * and registers static page types with the page lookup and rich editor.
 */
class ExtendCmsModule
{
    /**
     * subscribe
     */
    public function subscribe($events)
    {
        // Routing and rendering
        $events->listen('cms.router.beforeRoute', [static::class, 'initCmsPage']);
        $events->listen('cms.page.beforeRenderPage', [static::class, 'beforeRenderPage']);
        $events->listen('cms.block.render', [static::class, 'renderBlockContents']);
        $events->listen('cms.template.processTwigContent', [static::class, 'processTwigContent']);
        $events->listen('cms.template.save', [static::class, 'templateAfterSave']);
        $events->listen('cms.sitePicker.overridePattern', [static::class, 'overrideSitePickerPattern']);

        // Page lookup
        $events->listen('cms.pageLookup.listTypes', [static::class, 'listPageLookupTypes']);
        $events->listen('cms.pageLookup.getTypeInfo', [static::class, 'getPageLookupTypeInfo']);
        $events->listen('cms.pageLookup.resolveItem', [static::class, 'resolvePageLookupItem']);

        // Rich editor page links
        $events->listen('backend.richeditor.listTypes', [static::class, 'listRichEditorTypes']);
        $events->listen('backend.richeditor.getTypeInfo', [static::class, 'getRichEditorTypeInfo']);

        // Theme sync
        $events->listen('system.console.theme.sync.getAvailableModelClasses', [static::class, 'getThemeSyncModelClasses']);
    }

    /**
     * initCmsPage routes the URL to a static page when no CMS page matches.
     */
    public function initCmsPage($url)
    {
        return Controller::instance()->initCmsPage($url);
    }

    /**
     * beforeRenderPage renders the static page contents in place of the CMS page.
     */
    public function beforeRenderPage($controller, $page)
    {
        // Before twig renders
        $twig = $controller->getTwig();
        $loader = $controller->getLoader();
        Controller::instance()->injectPageTwig($page, $loader, $twig);

        // Get rendered content
        $contents = Controller::instance()->getPageContents($page);
        if ($contents && strlen($contents)) {
            return $contents;
        }
    }

    /**
     * renderBlockContents renders placeholder contents defined by a static page.
     */
    public function renderBlockContents($blockName, $blockContents)
    {
        $page = CmsController::getController()->getPage();

        if (!isset($page->apiBag['staticPage'])) {
            return;
        }

        $contents = Controller::instance()->getPlaceholderContents($page, $blockName, $blockContents);
        if ($contents && strlen($contents)) {
            return $contents;
        }
    }

    /**
     * processTwigContent parses syntax fields defined in layout templates.
     */
    public function processTwigContent($template, $dataHolder)
    {
        if ($template instanceof \Cms\Classes\Layout) {
            $dataHolder->content = Controller::instance()->parseSyntaxFields($dataHolder->content);
        }
    }

    /**
     * templateAfterSave clears the static page caches when any template is saved.
     */
    public function templateAfterSave($controller, $template, $type)
    {
        Plugin::clearCache();
    }

    /**
     * overrideSitePickerPattern resolves translated static page URLs when switching
     * sites via the site picker.
     */
    public function overrideSitePickerPattern($page, $pattern, $currentSite, $proposedSite)
    {
        if (isset($page->apiBag['staticPage'])) {
            $staticPage = $page->apiBag['staticPage'];

            return $staticPage->getTranslatableUrl($proposedSite)
                ?: array_get($staticPage->attributes, 'viewBag.url');
        }
    }

    /**
     * listPageLookupTypes
     */
    public function listPageLookupTypes()
    {
        return [
            'static-page'      => 'Static page',
            'all-static-pages' => ['label' => 'All static pages', 'nesting' => true]
        ];
    }

    /**
     * getPageLookupTypeInfo
     */
    public function getPageLookupTypeInfo($type)
    {
        if ($type == 'url') {
            return [];
        }

        if ($type == 'static-page' || $type == 'all-static-pages') {
            return Page::getMenuTypeInfo($type);
        }
    }

    /**
     * resolvePageLookupItem
     */
    public function resolvePageLookupItem($type, $item, $url, $theme)
    {
        if ($type == 'static-page' || $type == 'all-static-pages') {
            return Page::resolveMenuItem($item, $url, $theme);
        }
    }

    /**
     * listRichEditorTypes
     */
    public function listRichEditorTypes()
    {
        return [
            'static-page' => 'Static page',
        ];
    }

    /**
     * getRichEditorTypeInfo
     */
    public function getRichEditorTypeInfo($type)
    {
        if ($type === 'static-page') {
            return Page::getRichEditorTypeInfo($type);
        }
    }

    /**
     * getThemeSyncModelClasses registers static page models with the theme:sync command.
     */
    public function getThemeSyncModelClasses()
    {
        return [
            Menu::class,
            Page::class,
        ];
    }
}
