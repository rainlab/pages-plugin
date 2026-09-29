<?php namespace RainLab\Pages\Classes\EditorExtension;

use File;
use Event;
use SystemException;
use ApplicationException;
use Cms\Classes\Theme;
use RainLab\Pages\Classes\Content;
use RainLab\Pages\Classes\EditorExtension;

/**
 * HasContentCrud provides the open/save/delete command handlers for content blocks.
 */
trait HasContentCrud
{
    /**
     * openContentDocument loads a content block for editing.
     */
    protected function openContentDocument($controller)
    {
        $documentData = post('documentData');
        $path = $this->getRequestContentPath($documentData);

        $content = Content::load($this->getContentTheme(), $path);
        if (!$content) {
            throw new SystemException(sprintf('The content block %s was not found.', $path));
        }

        $document = $this->contentToDocumentArray($content);
        $metadata = $this->contentMetadata($content);

        // A non-primary-locale site shows its translation, or the content it inherits until translated.
        if ($locale = $this->getEditLocale()) {
            $translation = $content->findTranslation($locale);
            $document['markup'] = ($translation ?: $content->findInheritedTranslation($locale))->markup;
            $metadata['locale'] = $locale;
            $metadata['mtime'] = $translation ? $translation->mtime : null;
        }

        return [
            'document' => $document,
            'metadata' => $metadata
        ];
    }

    /**
     * saveContentDocument creates or updates a content block.
     */
    protected function saveContentDocument($controller)
    {
        $documentData = (array) post('documentData');
        $metadata = (array) post('documentMetadata');
        $forceSave = (bool) post('documentForceSave');

        $theme = $this->getContentTheme();
        $path = trim((string) array_get($metadata, 'path'));

        $content = strlen($path)
            ? Content::load($theme, $path)
            : Content::inTheme($theme);

        if (!$content) {
            throw new SystemException(sprintf('The content block %s was not found.', $path));
        }

        // Editing an existing block with a non-primary-locale site selected saves its translation.
        if (strlen($path) && ($locale = $this->getEditLocale())) {
            return $this->saveLocalizedContentDocument($controller, $content, $locale);
        }

        if (
            strlen($path) &&
            !$forceSave &&
            $content->mtime &&
            array_get($metadata, 'mtime') != $content->mtime
        ) {
            return ['mtimeMismatch' => true];
        }

        $previousFileName = $content->fileName;

        $fileName = (string) array_get($documentData, 'fileName');
        if (strlen($fileName)) {
            // Static page storage is managed by the page document type.
            if (preg_match('#^/?static-pages(-[^/]+)?(/|$)#', ltrim($fileName, '/'))) {
                throw new ApplicationException(__("Content files cannot be saved in the static pages directory."));
            }

            // Translations are edited by selecting their site, so their file names are reserved.
            if (Content::isTranslationFileName($fileName, Content::listLocaleKeys())) {
                throw new ApplicationException(__("This file name is reserved for translations. Select a site to translate the content block instead."));
            }

            $content->fileName = $fileName;
        }

        $content->markup = $this->convertLineEndings((string) array_get($documentData, 'markup'));
        $content->save();

        if (strlen($path)) {
            $content->renameTranslationsFrom($previousFileName);
        }

        Event::fire('cms.template.save', [$controller, $content, 'content']);

        return [
            'metadata' => $this->contentMetadata($content)
        ];
    }

    /**
     * saveLocalizedContentDocument writes the posted markup to the locale directory, migrating any legacy suffixed translation.
     */
    protected function saveLocalizedContentDocument($controller, Content $content, string $locale)
    {
        $documentData = (array) post('documentData');
        $metadata = (array) post('documentMetadata');
        $forceSave = (bool) post('documentForceSave');

        $localized = $content->findLocalizedTranslation($locale);
        $legacy = $content->findLegacyTranslation($locale);
        $translation = $localized ?: $legacy;

        // Concurrency guard against the translation file
        if (
            $translation &&
            !$forceSave &&
            $translation->mtime &&
            array_get($metadata, 'mtime') != $translation->mtime
        ) {
            return ['mtimeMismatch' => true];
        }

        $markup = $this->convertLineEndings((string) array_get($documentData, 'markup'));

        // Markup matching the inherited content is not stored, so the locale keeps following the base.
        if ($this->localeValueMatchesBase($markup, $content->findInheritedTranslation($locale)->markup)) {
            $staleFiles = [$localized, $legacy];
            $translation = null;
        }
        else {
            if (!$localized) {
                $localized = Content::inTheme($content->theme);
                $localized->fileName = Content::makeLocaleFileName($content->fileName, $locale);
            }

            $localized->markup = $markup;
            $localized->save();

            Event::fire('cms.template.save', [$controller, $localized, 'content']);

            // RainLab.Translate renders suffixed files first, so the legacy file is removed once migrated.
            $staleFiles = [$legacy];
            $translation = $localized;
        }

        foreach (array_filter($staleFiles) as $staleFile) {
            $staleFile->delete();
            Event::fire('cms.template.delete', [$controller, $staleFile]);
        }

        $result = $this->contentMetadata($content);
        $result['locale'] = $locale;
        $result['mtime'] = $translation ? $translation->mtime : null;

        return [
            'metadata' => $result
        ];
    }

    /**
     * deleteContentDocument removes a content block and its translations.
     */
    protected function deleteContentDocument($controller)
    {
        $metadata = (array) post('documentMetadata');
        $path = trim((string) array_get($metadata, 'path'));

        $content = Content::load($this->getContentTheme(), $path);
        if ($content) {
            $content->deleteTranslations();
            $content->delete();
            Event::fire('cms.template.delete', [$controller, $content]);
        }
    }

    /**
     * contentToDocumentArray flattens a content block into the client document shape.
     */
    protected function contentToDocumentArray(Content $content): array
    {
        $extension = strtolower(File::extension($content->fileName));

        return [
            'fileName' => ltrim($content->fileName, '/'),
            'markup' => $content->markup,
            'language' => $this->contentLanguage($extension)
        ];
    }

    /**
     * contentLanguage maps a content file extension to an editor surface id.
     * htm/html opens in the richeditor, md in the markdown editor, everything
     * else in a plain code editor.
     */
    protected function contentLanguage(string $extension): string
    {
        switch ($extension) {
            case 'htm':
            case 'html':
                return 'richeditor';
            case 'md':
                return 'markdown';
            case 'txt':
                return 'plaintext';
            default:
                return 'html';
        }
    }

    /**
     * contentMetadata builds the navigator/tab metadata for a content block.
     */
    protected function contentMetadata(Content $content): array
    {
        $fileName = ltrim($content->fileName, '/');
        $navigatorPath = dirname($fileName);
        if ($navigatorPath === '.') {
            $navigatorPath = '';
        }

        return [
            'mtime' => $content->mtime,
            'path' => $fileName,
            'fileName' => basename($fileName),
            'navigatorPath' => $navigatorPath,
            'uniqueKey' => $fileName,
            'type' => EditorExtension::DOCUMENT_TYPE_CONTENT
        ];
    }

    /**
     * getRequestContentPath extracts the requested content path from posted data.
     */
    protected function getRequestContentPath($documentData): string
    {
        $path = is_array($documentData)
            ? array_get($documentData, 'key', array_get($documentData, 'path'))
            : $documentData;

        $path = trim((string) $path);

        if (!strlen($path)) {
            throw new SystemException('Missing content path.');
        }

        return $path;
    }

    /**
     * getContentTheme returns the theme being edited.
     */
    protected function getContentTheme(): Theme
    {
        $theme = Theme::getEditTheme();
        if (!$theme) {
            throw new SystemException('The edit theme is not set.');
        }

        return $theme;
    }
}
