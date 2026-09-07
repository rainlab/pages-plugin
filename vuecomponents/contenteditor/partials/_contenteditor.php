<backend-document
    :header-collapsed="documentHeaderCollapsed"
    :full-screen="documentFullScreen"
    :loading="initializing"
    :processing="processing"
    :error-loading-document="errorLoadingDocument"
    error-loading-document-header="<?= e(trans('Error loading content block')) ?>"
    container-css-class="fill-container"
>
    <template v-slot:header>
        <backend-document-header
            title-property="fileName"
            ref="documentHeader"
            :data="documentData"
            :disabled="processing"
        ></backend-document-header>
    </template>

    <template v-slot:toolbar>
        <backend-document-toolbar
            :elements="toolbarElements"
            @command="onToolbarCommand"
            :disabled="processing"
        ></backend-document-toolbar>
    </template>

    <template v-slot:content>
        <div class="d-flex flex-column fill-container">
            <div class="flex-fill position-relative editor-panel">
                <backend-monacoeditor
                    v-show="modelsReady && isCodeDocument"
                    ref="editor"
                    container-css-class="fill-container"
                    :model-definitions="codeEditorModelDefinitions"
                    :glyph-margin="true"
                >
                </backend-monacoeditor>

                <backend-richeditor-document-connector
                    :allow-resizing="true"
                    :toolbar-container="toolbarExtensionPoint"
                    :use-media-manager="true"
                    unique-key="pages-content-html-editor"
                    container-css-class="fill-container"
                    ref="richEditorDocumentConnector"
                    v-if="isRicheditorDocument"
                >
                    <backend-richeditor
                        v-model="documentData.markup"
                    >
                    </backend-richeditor>
                </backend-richeditor-document-connector>

                <backend-document-markdowneditor
                    v-if="isMarkdownDocument"
                    v-model="documentData.markup"
                    ref="markdownEditor"
                    container-css-class="fill-container"
                    :toolbar-container="toolbarExtensionPoint"
                    :use-media-manager="true"
                >
                </backend-document-markdowneditor>
            </div>

            <editor-component-editorconflictresolver
                ref="conflictResolver"
            ></editor-component-editorconflictresolver>
        </div>
    </template>
</backend-document>
