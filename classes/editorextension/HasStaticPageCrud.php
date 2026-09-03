<?php namespace RainLab\Pages\Classes\EditorExtension;

use Event;
use SystemException;
use Cms\Classes\Theme;
use RainLab\Pages\Classes\Page as StaticPage;
use RainLab\Pages\Classes\EditorExtension;

/**
 * HasStaticPageCrud provides the open/save/delete command handlers for static pages.
 */
trait HasStaticPageCrud
{
    /**
     * openPageDocument loads a static page for editing.
     */
    protected function openPageDocument($controller)
    {
        $documentData = post('documentData');
        $path = $this->getRequestPath($documentData);

        $page = StaticPage::load($this->getEditTheme(), $path);
        if (!$page) {
            throw new SystemException(sprintf('The static page %s was not found.', $path));
        }

        return [
            'document' => $this->pageToDocumentArray($page),
            'metadata' => $this->pageMetadata($page)
        ];
    }

    /**
     * savePageDocument creates or updates a static page.
     */
    protected function savePageDocument($controller)
    {
        $documentData = (array) post('documentData');
        $metadata = (array) post('documentMetadata');
        $forceSave = (bool) post('documentForceSave');

        $theme = $this->getEditTheme();
        $path = trim((string) array_get($metadata, 'path'));

        $page = strlen($path)
            ? StaticPage::load($theme, $path)
            : StaticPage::inTheme($theme);

        if (!$page) {
            throw new SystemException(sprintf('The static page %s was not found.', $path));
        }

        // Concurrency guard: refuse to overwrite a file changed on disk.
        if (
            strlen($path) &&
            !$forceSave &&
            $page->mtime &&
            array_get($metadata, 'mtime') != $page->mtime
        ) {
            return ['mtimeMismatch' => true];
        }

        $settings = $this->cleanSyntaxFieldData((array) array_get($documentData, 'settings', []));

        $fillData = [
            'settings' => ['viewBag' => $settings],
            'markup' => (string) array_get($documentData, 'markup'),
        ];

        // Placeholder content is stored as {% put %} blocks, keyed by placeholder code.
        $placeholders = array_get($documentData, 'placeholders');
        if (is_array($placeholders)) {
            $fillData['placeholders'] = $placeholders;
        }

        $page->fill($fillData);

        $page->validate();
        $page->save();

        Event::fire('cms.template.save', [$controller, $page, 'static-page']);

        return [
            'metadata' => $this->pageMetadata($page)
        ];
    }

    /**
     * cleanSyntaxFieldData strips repeater bookkeeping keys from posted viewBag data.
     */
    protected function cleanSyntaxFieldData(array $data): array
    {
        $internalKeys = ['_index', '_group'];

        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                foreach ($internalKeys as $internalKey) {
                    unset($value[$internalKey]);
                }
                $value = $this->cleanSyntaxFieldData($value);
            }
        }

        return $data;
    }

    /**
     * deletePageDocument removes a static page and its subpages.
     */
    protected function deletePageDocument($controller)
    {
        $metadata = (array) post('documentMetadata');
        $path = trim((string) array_get($metadata, 'path'));

        $page = StaticPage::load($this->getEditTheme(), $path);
        if ($page) {
            $page->delete();
            Event::fire('cms.template.delete', [$controller, $page]);
        }
    }

    /**
     * updatePageStructure persists a reordered/re-nested page tree to meta/static-pages.yaml.
     */
    protected function updatePageStructure($controller)
    {
        $documentData = (array) post('documentData');
        $structure = array_get($documentData, 'structure', []);

        // The client JSON-encodes the structure (form encoding drops empty-object leaves).
        if (is_string($structure)) {
            $structure = json_decode($structure, true) ?: [];
        }

        $theme = $this->getEditTheme();
        $pageList = new \RainLab\Pages\Classes\PageList($theme);

        // Only persist filenames that are real pages, preserving the posted hierarchy.
        $valid = [];
        foreach ($pageList->getPageTree(true) as $pageInfo) {
            $this->collectValidPageNames($pageInfo, $valid);
        }

        $clean = $this->sanitizePageStructure(is_array($structure) ? $structure : [], $valid);

        // Safety: never wipe the structure. If sanitization produced nothing while real
        // pages exist, the payload was malformed — refuse rather than clear the yaml.
        if (empty($clean) && !empty($valid)) {
            throw new SystemException('Refusing to write an empty page structure.');
        }

        $pageList->updateStructure($clean);

        return ['success' => true];
    }

    /**
     * collectValidPageNames gathers every page base filename from the page tree.
     */
    protected function collectValidPageNames($pageInfo, array &$valid): void
    {
        $valid[$pageInfo->page->getBaseFileName()] = true;

        if (!empty($pageInfo->subpages)) {
            foreach ($pageInfo->subpages as $subpage) {
                $this->collectValidPageNames($subpage, $valid);
            }
        }
    }

    /**
     * sanitizePageStructure keeps only known page filenames from the posted structure.
     */
    protected function sanitizePageStructure(array $structure, array $valid): array
    {
        $result = [];

        foreach ($structure as $fileName => $children) {
            if (!is_string($fileName) || !isset($valid[$fileName])) {
                continue;
            }

            $result[$fileName] = is_array($children)
                ? $this->sanitizePageStructure($children, $valid)
                : [];
        }

        return $result;
    }

    /**
     * pageToDocumentArray flattens a page into the client document shape.
     */
    protected function pageToDocumentArray(StaticPage $page): array
    {
        $viewBag = (array) $page->getViewBag()->getProperties();

        return [
            'fileName' => ltrim($page->getBaseFileName(), '/'),
            'markup' => $page->markup,
            'placeholders' => $this->getPlaceholderData($page),
            'placeholderInfo' => $this->getPlaceholderInfo($page),
            'syntaxFieldGroups' => $this->getSyntaxFieldGroups($page),
            'settings' => $viewBag
        ] + $viewBag;
    }

    /**
     * getSyntaxFieldGroups returns the layout syntax fields grouped into editor tabs.
     *
     * Each distinct field `tab` becomes one content tab (matching the original plugin);
     * fields without a tab fall back to a single "Fields" group. Returns an ordered list
     * of ['key' => <slug>, 'title' => <tab label>].
     */
    protected function getSyntaxFieldGroups(StaticPage $page): array
    {
        $groups = [];

        foreach ($page->listLayoutSyntaxFields() as $fieldCode => $fieldConfig) {
            if (($fieldConfig['type'] ?? null) === 'fileupload') {
                continue;
            }

            $tab = trim((string) ($fieldConfig['tab'] ?? '')) ?: __("Fields");
            $key = 'syntax:'.md5($tab);

            if (!isset($groups[$key])) {
                $groups[$key] = ['key' => $key, 'title' => $tab];
            }
        }

        return array_values($groups);
    }

    /**
     * getPlaceholderData returns the current placeholder content keyed by code.
     */
    protected function getPlaceholderData(StaticPage $page): array
    {
        $result = [];
        $content = (array) $page->placeholders;

        foreach ($this->getPlaceholderInfo($page) as $code => $info) {
            $result[$code] = (string) array_get($content, $code, '');
        }

        return $result;
    }

    /**
     * getPlaceholderInfo returns the editable placeholders defined by the page layout.
     */
    protected function getPlaceholderInfo(StaticPage $page): array
    {
        $result = [];

        foreach ($page->listLayoutPlaceholders() as $code => $info) {
            if (!empty($info['ignore'])) {
                continue;
            }

            $result[$code] = [
                'title' => $info['title'],
                'type' => $info['type'] === 'text' ? 'text' : 'html'
            ];
        }

        return $result;
    }

    /**
     * pageMetadata builds the navigator/tab metadata for a page.
     */
    protected function pageMetadata(StaticPage $page): array
    {
        $fileName = $page->getBaseFileName();
        $navigatorPath = dirname($fileName);
        if ($navigatorPath === '.') {
            $navigatorPath = '';
        }

        return [
            'mtime' => $page->mtime,
            'path' => $fileName,
            'fileName' => basename($fileName),
            'navigatorPath' => $navigatorPath,
            'uniqueKey' => $fileName,
            'type' => EditorExtension::DOCUMENT_TYPE_PAGE
        ];
    }

    /**
     * getRequestPath extracts the requested document path from posted data.
     */
    protected function getRequestPath($documentData): string
    {
        // On open, the client posts documentData as { type, key }.
        $path = is_array($documentData)
            ? array_get($documentData, 'key', array_get($documentData, 'path'))
            : $documentData;

        $path = trim((string) $path);

        if (!strlen($path)) {
            throw new SystemException('Missing document path.');
        }

        return $path;
    }

    /**
     * getEditTheme returns the theme being edited.
     */
    protected function getEditTheme(): Theme
    {
        $theme = Theme::getEditTheme();
        if (!$theme) {
            throw new SystemException('The edit theme is not set.');
        }

        return $theme;
    }
}
