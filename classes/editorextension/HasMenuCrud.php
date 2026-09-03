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

        $menu->fill([
            'name' => (string) array_get($settings, 'name'),
            'code' => (string) array_get($settings, 'code', $code),
            'itemData' => is_array($items) ? $this->normalizeItems($items) : []
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
        return [
            'code' => $menu->getBaseFileName(),
            'items' => $this->itemsToArray($menu->items),
            'settings' => [
                'name' => $menu->name,
                'code' => $menu->getBaseFileName()
            ]
        ];
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
