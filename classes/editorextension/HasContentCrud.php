<?php namespace RainLab\Pages\Classes\EditorExtension;

use File;
use Event;
use SystemException;
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

        return [
            'document' => $this->contentToDocumentArray($content),
            'metadata' => $this->contentMetadata($content)
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

        if (
            strlen($path) &&
            !$forceSave &&
            $content->mtime &&
            array_get($metadata, 'mtime') != $content->mtime
        ) {
            return ['mtimeMismatch' => true];
        }

        $fileName = (string) array_get($documentData, 'fileName');
        if (strlen($fileName)) {
            $content->fileName = $fileName;
        }

        $content->markup = (string) array_get($documentData, 'markup');
        $content->save();

        Event::fire('cms.template.save', [$controller, $content, 'content']);

        return [
            'metadata' => $this->contentMetadata($content)
        ];
    }

    /**
     * deleteContentDocument removes a content block.
     */
    protected function deleteContentDocument($controller)
    {
        $metadata = (array) post('documentMetadata');
        $path = trim((string) array_get($metadata, 'path'));

        $content = Content::load($this->getContentTheme(), $path);
        if ($content) {
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
     *
     * htm/html content blocks open in the WYSIWYG richeditor (parity with the original
     * plugin); md uses the markdown editor; everything else is a plain code editor.
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
