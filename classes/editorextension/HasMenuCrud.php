<?php namespace RainLab\Pages\Classes\EditorExtension;

use Event;
use SystemException;
use Cms\Classes\Theme;
use RainLab\Pages\Classes\Menu;
use RainLab\Pages\Classes\EditorExtension;

/**
 * HasMenuCrud provides the open/save/delete command handlers for menus.
 */
trait HasMenuCrud
{
    /**
     * command_onOpenMenu loads a menu for editing.
     */
    protected function openMenuDocument($controller)
    {
        $documentData = post('documentData');
        $code = $this->getRequestMenuCode($documentData);

        $menu = Menu::load($this->getMenuTheme(), $code . '.yaml');
        if (!$menu) {
            throw new SystemException(sprintf('The menu %s was not found.', $code));
        }

        return [
            'document' => $this->menuToDocumentArray($menu),
            'metadata' => $this->menuMetadata($menu)
        ];
    }

    /**
     * command_onSaveMenu creates or updates a menu.
     */
    protected function saveMenuDocument($controller)
    {
        $documentData = (array) post('documentData');
        $metadata = (array) post('documentMetadata');
        $forceSave = (bool) post('documentForceSave');

        $theme = $this->getMenuTheme();
        $code = trim((string) array_get($metadata, 'path'));
        $code = preg_replace('/\.yaml$/', '', $code);

        $menu = strlen($code)
            ? Menu::load($theme, $code . '.yaml')
            : Menu::inTheme($theme);

        if (!$menu) {
            throw new SystemException(sprintf('The menu %s was not found.', $code));
        }

        if (
            strlen($code) &&
            !$forceSave &&
            $menu->mtime &&
            array_get($metadata, 'mtime') != $menu->mtime
        ) {
            return ['mtimeMismatch' => true];
        }

        $settings = (array) array_get($documentData, 'settings', []);
        $items = array_get($documentData, 'items', []);
        $items = is_array($items) ? $this->normalizeItems($items) : [];

        // With a non-primary-locale site selected, posted title/url values are
        // stored as per-item locale translations and the base values are kept.
        if (strlen($code) && ($locale = $this->getEditLocale())) {
            $originalItems = (array) array_get($menu->attributes, 'items', []);
            $items = $this->localizeItemData($items, $originalItems, $locale);
        }

        $menu->fill([
            'name' => (string) array_get($settings, 'name'),
            // The code is a root document property (edited in the header)
            'code' => (string) (array_get($documentData, 'code') ?: array_get($settings, 'code', $code)),
            'itemData' => $items
        ]);

        $menu->save();

        Event::fire('cms.template.save', [$controller, $menu, 'menu']);

        return [
            'metadata' => $this->menuMetadata($menu)
        ];
    }

    /**
     * command_onDeleteMenu removes a menu.
     */
    protected function deleteMenuDocument($controller)
    {
        $metadata = (array) post('documentMetadata');
        $code = preg_replace('/\.yaml$/', '', trim((string) array_get($metadata, 'path')));

        $menu = Menu::load($this->getMenuTheme(), $code . '.yaml');
        if ($menu) {
            $menu->delete();
            Event::fire('cms.template.delete', [$controller, $menu]);
        }
    }

    /**
     * menuToDocumentArray flattens a menu into the client document shape.
     */
    protected function menuToDocumentArray(Menu $menu): array
    {
        $items = $this->itemsToArray($menu->items);

        // When the backend site picker selects a non-primary locale, show that
        // locale's item translations (viewBag.locale.{locale}.{field}).
        if ($locale = $this->getEditLocale()) {
            $items = $this->applyItemsEditLocale($items, $locale);
        }

        return [
            'name' => $menu->name,
            'code' => $menu->getBaseFileName(),
            'items' => $items,
            'settings' => [
                'name' => $menu->name,
                'code' => $menu->getBaseFileName()
            ]
        ];
    }

    /**
     * applyItemsEditLocale replaces item fields with their translated values for
     * display in the editor, matching the storage format used by earlier versions
     * of this plugin (viewBag.locale.{locale}.{field}).
     */
    protected function applyItemsEditLocale(array $items, string $locale): array
    {
        foreach ($items as &$item) {
            $localeFields = (array) array_get($item, 'viewBag.locale.'.$locale, []);

            foreach (['title', 'url'] as $fieldName) {
                $value = array_get($localeFields, $fieldName);
                if ($value !== null && $value !== '' && array_key_exists($fieldName, $item)) {
                    $item[$fieldName] = $value;
                }
            }

            if (!empty($item['items']) && is_array($item['items'])) {
                $item['items'] = $this->applyItemsEditLocale($item['items'], $locale);
            }
        }

        return $items;
    }

    /**
     * localizeItemData stores the posted title/url values as locale translations
     * and restores the base values from the menu on disk, mirroring the original
     * RainLab.Translate save behavior. The url is only translated for url-type items.
     */
    protected function localizeItemData(array $postedItems, array $originalItems, string $locale): array
    {
        foreach ($postedItems as $index => &$item) {
            $original = (array) ($originalItems[$index] ?? []);
            $localeData = (array) array_get($original, 'viewBag.locale', []);

            foreach (['title', 'url'] as $fieldName) {
                $value = array_get($item, $fieldName);
                if ($value === null) {
                    continue;
                }

                // Restore the base value; for items new to this locale session the
                // posted value becomes the base value too.
                $originalValue = array_get($original, $fieldName, $value);
                array_set($item, $fieldName, $originalValue);

                $localeData[$locale][$fieldName] = $value;
            }

            // Only url-type items carry a translated URL
            if (array_get($item, 'type', 'url') !== 'url') {
                foreach ($localeData as &$targetData) {
                    unset($targetData['url']);
                }
                unset($targetData);
            }

            if ($localeData) {
                array_set($item, 'viewBag.locale', $localeData);
            }

            if (!empty($item['items']) && is_array($item['items'])) {
                $item['items'] = $this->localizeItemData(
                    $item['items'],
                    (array) array_get($original, 'items', []),
                    $locale
                );
            }
        }

        return $postedItems;
    }

    /**
     * normalizeItems recursively coerces boolean flags to the string format menus store.
     */
    protected function normalizeItems(array $items): array
    {
        foreach ($items as &$item) {
            foreach (['nesting', 'replace'] as $flag) {
                if (array_key_exists($flag, $item)) {
                    $item[$flag] = (!$item[$flag] || $item[$flag] === '0') ? '0' : '1';
                }
            }

            if (!empty($item['items']) && is_array($item['items'])) {
                $item['items'] = $this->normalizeItems($item['items']);
            }
        }

        return $items;
    }

    /**
     * itemsToArray recursively serializes menu items into plain arrays for the client.
     */
    protected function itemsToArray($items): array
    {
        $result = [];
        $typeOptions = $this->menuItemTypeOptions();

        foreach ($items as $item) {
            $data = $item->toArray();

            // typeLabel drives the tree-row subtitle (e.g. "Static page"), matching
            // the original plugin's item list.
            $data['typeLabel'] = array_get($typeOptions, $item->type, $item->type);

            if ($item->items) {
                $data['items'] = $this->itemsToArray($item->items);
            }
            $result[] = $data;
        }

        return $result;
    }

    /**
     * menuItemTypeOptions returns the map of menu item type => human label.
     */
    protected function menuItemTypeOptions(): array
    {
        if ($this->menuItemTypeOptionsCache === null) {
            $this->menuItemTypeOptionsCache = (new \RainLab\Pages\Classes\MenuItem)->getTypeOptions();
        }

        return $this->menuItemTypeOptionsCache;
    }

    /**
     * @var array|null menuItemTypeOptionsCache caches the type option map per request.
     */
    protected $menuItemTypeOptionsCache = null;

    /**
     * menuMetadata builds the navigator/tab metadata for a menu.
     */
    protected function menuMetadata(Menu $menu): array
    {
        $code = $menu->getBaseFileName();

        return [
            'mtime' => $menu->mtime,
            'path' => $code,
            'fileName' => $code,
            'navigatorPath' => '',
            'uniqueKey' => $code,
            'type' => EditorExtension::DOCUMENT_TYPE_MENU
        ];
    }

    /**
     * getRequestMenuCode extracts the requested menu code from posted data.
     */
    protected function getRequestMenuCode($documentData): string
    {
        $code = is_array($documentData)
            ? array_get($documentData, 'key', array_get($documentData, 'path'))
            : $documentData;

        $code = preg_replace('/\.yaml$/', '', trim((string) $code));

        if (!strlen($code)) {
            throw new SystemException('Missing menu code.');
        }

        return $code;
    }

    /**
     * getMenuTheme returns the theme being edited.
     */
    protected function getMenuTheme(): Theme
    {
        $theme = Theme::getEditTheme();
        if (!$theme) {
            throw new SystemException('The edit theme is not set.');
        }

        return $theme;
    }
}
