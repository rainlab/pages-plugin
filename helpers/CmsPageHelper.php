<?php namespace RainLab\Pages\Helpers;

use Cms\Classes\Page;
use Cms\Classes\Theme;
use Cms\Classes\Controller;

/**
 * CmsPageHelper reverse extends the CMS module
 */
class CmsPageHelper
{
    /**
     * getMenuTypeInfo handler for the pages.menuitem.getTypeInfo event.
     * Returns a menu item type information. The type information is returned as array
     * with the following elements:
     * - references - a list of the item type reference options. The options are returned in the
     *   ["key"] => "title" format for options that don't have sub-options, and in the format
     *   ["key"] => ["title"=>"Option title", "items"=>[...]] for options that have sub-options. Optional,
     *   required only if the menu item type requires references.
     * - nesting - Boolean value indicating whether the item type supports nested items. Optional,
     *   false if omitted.
     * - dynamicItems - Boolean value indicating whether the item type could generate new menu items.
     *   Optional, false if omitted.
     * - cmsPages - a list of CMS pages (objects of the Cms\Classes\Page class), if the item type requires
     *   a CMS page reference to resolve the item URL.
     * @param string $type Specifies the menu item type
     * @return array Returns an array
     */
    public static function getMenuTypeInfo()
    {
        $result = [];

        $theme = Theme::getActiveTheme();
        $pages = Page::listInTheme($theme, true);
        $references = [];

        foreach ($pages as $page) {
            $references[$page->getBaseFileName()] = $page->title . ' [' . $page->getBaseFileName() . ']';
        }

        $result = [
            'references' => $references,
            'nesting' => false,
            'dynamicItems' => false
        ];

        return $result;
    }

    /**
     * resolveMenuItem handler for the pages.menuitem.resolveItem event.
     * Returns information about a menu item. The result is an array
     * with the following keys:
     * - url - the menu item URL. Not required for menu item types that return all available records.
     *   The URL should be returned relative to the website root and include the subdirectory, if any.
     *   Use the Url::to() helper to generate the URLs.
     * - isActive - determines whether the menu item is active. Not required for menu item types that
     *   return all available records.
     * - items - an array of arrays with the same keys (url, isActive, items) + the title key.
     *   The items array should be added only if the $item's $nesting property value is TRUE.
     * @param \RainLab\Pages\Classes\MenuItem $item Specifies the menu item.
     * @param string $url Specifies the current page URL, normalized, in lower case
     * @param \Cms\Classes\Theme $theme Specifies the current theme.
     * The URL is specified relative to the website root, it includes the subdirectory name, if any.
     * @return mixed Returns an array. Returns null if the item cannot be resolved.
     */
    public static function resolveMenuItem($item, string $url, Theme $theme)
    {
        $result = null;

        if ($item->type === 'cms-page') {
            if (!$item->reference) {
                return;
            }

            $page = Page::loadCached($theme, $item->reference);
            $controller = Controller::getController() ?: new Controller;
            $pageUrl = $controller->pageUrl($item->reference, [], false);

            $result = [];
            $result['url'] = $pageUrl;
            $result['isActive'] = $pageUrl == $url;
            $result['mtime'] = $page ? $page->mtime : null;
        }

        return $result;
    }
}
