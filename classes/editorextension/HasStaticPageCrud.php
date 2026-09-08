<?php namespace RainLab\Pages\Classes\EditorExtension;

use Url;
use Site;
use File;
use Event;
use Config;
use SystemException;
use Cms\Classes\Theme;
use RainLab\Pages\Classes\Page as StaticPage;
use RainLab\Pages\Classes\PageLocale;
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

        // When the backend site picker selects a non-primary locale, overlay the
        // translated mirror (content/static-pages-{locale}/) so the editor shows
        // and saves that locale's content.
        $document = $this->pageToDocumentArray($page);
        $metadata = $this->pageMetadata($page);

        if ($locale = $this->getEditLocale()) {
            $mirror = PageLocale::findLocale($locale, $page);
            $document = $this->overlayLocaleDocument($document, $page, $mirror, $locale);
            $metadata['locale'] = $locale;
            $metadata['mtime'] = $mirror ? $mirror->mtime : null;
        }

        return [
            'document' => $document,
            'metadata' => $metadata,
            'previewUrl' => $this->pagePreviewUrl(array_get($document, 'settings.url')),
            'hasContentField' => $this->pageHasContentField($page)
        ];
    }

    /**
     * pagePreviewUrl returns the frontend URL for a page URL string.
     */
    protected function pagePreviewUrl($url): ?string
    {
        $url = trim((string) $url);

        return strlen($url) ? Url::to($url) : null;
    }

    /**
     * pageHasContentField checks the layout's staticPage component useContent property,
     * which hides the content field when disabled.
     */
    protected function pageHasContentField(StaticPage $page): bool
    {
        $layout = $page->getLayoutObject();
        $component = $layout ? $layout->getComponent('staticPage') : null;

        return $component ? (bool) $component->property('useContent', true) : true;
    }

    /**
     * getEditLocale returns the locale being edited when the backend site picker
     * has a non-primary-locale site selected, otherwise null.
     */
    protected function getEditLocale(): ?string
    {
        if (!Site::hasMultiSite()) {
            return null;
        }

        $site = Site::getSiteFromContext();
        $primary = Site::getPrimarySite();
        if (!$site || !$primary || $site->id === $primary->id) {
            return null;
        }

        $locale = (string) $site->hard_locale;

        return (strlen($locale) && $locale !== (string) $primary->hard_locale) ? $locale : null;
    }

    /**
     * overlayLocaleDocument overrides the base document values with the translated
     * mirror's content. The URL comes from viewBag.localeUrl in the base file.
     */
    protected function overlayLocaleDocument(array $document, StaticPage $page, ?PageLocale $mirror, string $locale): array
    {
        // Translated URL lives in the base page's view bag
        $localeUrl = array_get($page->viewBag, 'localeUrl.'.$locale);
        if ($localeUrl !== null && $localeUrl !== '') {
            $document['url'] = $localeUrl;
            $document['settings']['url'] = $localeUrl;
        }

        if (!$mirror) {
            return $document;
        }

        // Mirror view bag values (title, syntax field data) override the base
        foreach ((array) $mirror->getViewBag()->getProperties() as $name => $value) {
            if (in_array($name, ['url', 'layout']) || $value === null || $value === '') {
                continue;
            }

            $document['settings'][$name] = $value;
            if (array_key_exists($name, $document)) {
                $document[$name] = $value;
            }
        }

        if (strlen(trim((string) $mirror->markup))) {
            $document['markup'] = $mirror->markup;
        }

        // Mirror placeholders override where present
        $mirrorPlaceholders = (array) $mirror->placeholders;
        foreach ($mirrorPlaceholders as $code => $content) {
            if (array_key_exists($code, (array) $document['placeholders']) && strlen(trim((string) $content))) {
                $document['placeholders'][$code] = $content;
            }
        }

        return $document;
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

        // Editing an existing page with a non-primary-locale site selected writes
        // to that locale's mirror file instead. New pages always create the base.
        if (strlen($path) && ($locale = $this->getEditLocale())) {
            return $this->saveLocalizedPageDocument($controller, $locale);
        }

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

        // New pages nest under a parent when created via "Add subpage".
        $parentFileName = trim((string) array_get($metadata, 'parentFileName'));
        if (!strlen($path) && strlen($parentFileName)) {
            $page->parentFileName = $parentFileName;
        }

        $fillData = [
            'settings' => ['viewBag' => $settings],
            'markup' => $this->convertLineEndings((string) array_get($documentData, 'markup')),
        ];

        // Placeholder content is stored as {% put %} blocks, keyed by placeholder code.
        $placeholders = array_get($documentData, 'placeholders');
        if (is_array($placeholders)) {
            $fillData['placeholders'] = array_map([$this, 'convertLineEndings'], $placeholders);
        }

        $page->fill($fillData);

        // New pages without a chosen layout inherit the parent's child layout, or the
        // theme layout marked as default.
        if (!strlen($path) && !strlen((string) array_get($settings, 'layout'))) {
            $parentPage = strlen($parentFileName)
                ? StaticPage::load($theme, $parentFileName)
                : null;

            $page->setDefaultLayout($parentPage);
        }

        $page->validate();
        $page->save();

        Event::fire('cms.template.save', [$controller, $page, 'static-page']);

        return [
            'metadata' => $this->pageMetadata($page),
            'previewUrl' => $this->pagePreviewUrl(array_get($page->viewBag, 'url')),
            'placeholderInfo' => $this->getPlaceholderInfo($page),
            'syntaxFieldGroups' => $this->getSyntaxFieldGroups($page),
            'hasContentField' => $this->pageHasContentField($page)
        ];
    }

    /**
     * convertLineEndings normalizes CRLF/CR to LF when enabled by configuration.
     */
    protected function convertLineEndings($content)
    {
        if (is_string($content) && Config::get('system.convert_line_endings', false) === true) {
            $content = str_replace(["\r\n", "\r"], "\n", $content);
        }

        return $content;
    }

    /**
     * saveLocalizedPageDocument writes the posted document to the locale's mirror
     * file (content/static-pages-{locale}/), leaving the base page untouched except
     * for the translated URL, which is stored in the base view bag as localeUrl.
     */
    protected function saveLocalizedPageDocument($controller, string $locale)
    {
        $documentData = (array) post('documentData');
        $metadata = (array) post('documentMetadata');
        $forceSave = (bool) post('documentForceSave');

        $theme = $this->getEditTheme();
        $path = trim((string) array_get($metadata, 'path'));

        $page = StaticPage::load($theme, $path);
        if (!$page) {
            throw new SystemException(sprintf('The static page %s was not found.', $path));
        }

        $mirror = PageLocale::findLocale($locale, $page);

        // Concurrency guard against the mirror file
        if (
            $mirror &&
            !$forceSave &&
            $mirror->mtime &&
            array_get($metadata, 'mtime') != $mirror->mtime
        ) {
            return ['mtimeMismatch' => true];
        }

        $settings = $this->cleanSyntaxFieldData((array) array_get($documentData, 'settings', []));

        // A URL differing from the base URL is stored as localeUrl.{locale} in the
        // base file, matching the translated URL storage read by HasTranslatableBag.
        $postedUrl = trim((string) array_get($settings, 'url'));
        $baseUrl = (string) array_get($page->viewBag, 'url');
        $localeUrls = (array) array_get($page->viewBag, 'localeUrl', []);
        $newLocaleUrls = $localeUrls;

        if (strlen($postedUrl) && $postedUrl !== $baseUrl) {
            $newLocaleUrls[$locale] = $postedUrl;
        }
        else {
            unset($newLocaleUrls[$locale]);
        }

        if ($newLocaleUrls != $localeUrls) {
            $baseViewBag = (array) $page->getViewBag()->getProperties();
            $baseViewBag['localeUrl'] = $newLocaleUrls;

            $page->fill(['settings' => ['viewBag' => $baseViewBag]]);
            $page->save();
        }

        // Mirrors never store structural fields
        unset($settings['url']);

        // Following the Translatable convention, values matching the base are not
        // stored - the mirror holds only translated values, so base edits keep
        // propagating to locales that never diverged.
        $baseViewBag = (array) $page->getViewBag()->getProperties();
        foreach ($settings as $name => $value) {
            if ($this->localeValueMatchesBase($value, array_get($baseViewBag, $name))) {
                unset($settings[$name]);
            }
        }

        $markup = $this->convertLineEndings((string) array_get($documentData, 'markup'));
        if ($this->localeValueMatchesBase($markup, $page->markup)) {
            $markup = '';
        }

        $placeholders = array_get($documentData, 'placeholders');
        if (is_array($placeholders)) {
            $basePlaceholders = (array) $page->placeholders;
            foreach ($placeholders as $code => $content) {
                $content = $this->convertLineEndings($content);
                if ($this->localeValueMatchesBase($content, array_get($basePlaceholders, $code))) {
                    unset($placeholders[$code]);
                }
                else {
                    $placeholders[$code] = $content;
                }
            }
        }

        // A mirror left without any translated values is removed entirely,
        // restoring full inheritance from the base page.
        if (empty($settings) && !strlen(trim($markup)) && empty($placeholders)) {
            if ($mirror) {
                PageLocale::withLocale($locale, function() use ($mirror) {
                    File::delete($mirror->getFilePath());
                });

                Event::fire('cms.template.delete', [$controller, $mirror]);
            }

            $mirror = null;
        }
        else {
            // The layout is copied from the base so placeholder pruning resolves
            // against the correct layout.
            $settings['layout'] = array_get($page->viewBag, 'layout');

            $fillData = [
                'settings' => ['viewBag' => $settings],
                'markup' => $markup,
            ];

            if (is_array($placeholders)) {
                $fillData['placeholders'] = $placeholders;
            }

            $mirror = PageLocale::withLocale($locale, function() use ($theme, $page, $mirror, $fillData) {
                if (!$mirror) {
                    $mirror = PageLocale::inTheme($theme);
                    $mirror->fileName = $page->fileName;
                }

                // Fill the settings first so the layout is resolvable when the
                // placeholder fill prunes against the layout's placeholder list.
                $mirror->fill(['settings' => $fillData['settings']]);
                $mirror->fill(array_diff_key($fillData, ['settings' => true]));
                $mirror->save();

                return $mirror;
            });

            Event::fire('cms.template.save', [$controller, $mirror, 'static-page']);
        }

        $result = $this->pageMetadata($page);
        $result['locale'] = $locale;
        $result['mtime'] = $mirror ? $mirror->mtime : null;

        $previewUrl = array_get($page->viewBag, 'localeUrl.'.$locale)
            ?: array_get($page->viewBag, 'url');

        return [
            'metadata' => $result,
            'previewUrl' => $this->pagePreviewUrl($previewUrl),
            'placeholderInfo' => $this->getPlaceholderInfo($page),
            'syntaxFieldGroups' => $this->getSyntaxFieldGroups($page),
            'hasContentField' => $this->pageHasContentField($page)
        ];
    }

    /**
     * localeValueMatchesBase determines whether a posted locale value matches the
     * base value. Whitespace is ignored entirely - the rich editor reserializes
     * markup with different spacing between tags, and a value differing from the
     * base only in whitespace is not a translation worth storing.
     */
    protected function localeValueMatchesBase($value, $base): bool
    {
        if (is_array($value) || is_array($base)) {
            return $value == $base;
        }

        $normalize = function($text) {
            return preg_replace('/\s+/', '', (string) $text);
        };

        return $normalize($value) === $normalize($base);
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
     * Each distinct field `tab` becomes one content tab; fields without a tab fall back
     * to a single "Fields" group.
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
