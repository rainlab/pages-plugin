<backend-document
    :header-collapsed="documentHeaderCollapsed"
    :full-screen="documentFullScreen"
    :loading="initializing"
    :processing="processing"
    :error-loading-document="errorLoadingDocument"
    error-loading-document-header="<?= e(trans('Error loading page')) ?>"
    container-css-class="fill-container"
>
    <template v-slot:header>
        <backend-document-header
            title-property="title"
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
            <!-- Content-region tabs: main content, layout placeholders, and syntax-field
                 groups, all in one strip (styled like the backend form document tabs). -->
            <div v-if="hasTabs" class="flex-shrink-0">
                <backend-tabs
                    :tabs="surfaceTabs"
                    :closeable="false"
                    :no-panes="true"
                    :tooltips-enabled="false"
                    tabs-style="primary"
                    @tabselected="onSurfaceTabSelected"
                ></backend-tabs>
            </div>

            <div class="flex-fill position-relative editor-panel">
                <!-- WYSIWYG richeditor for the active rich surface. Only one connector is
                     mounted at a time so it lays out exactly like the native editor (no
                     absolute-positioned stacking, which broke the resizer's responsiveness). -->
                <template v-for="surface in contentSurfaces" :key="'rich-' + surface.key">
                    <backend-richeditor-document-connector
                        v-if="surface.mode === 'rich' && surface.key === activeSurfaceKey"
                        :allow-resizing="true"
                        :toolbar-container="surfaceToolbars[surface.key]"
                        :use-media-manager="true"
                        :unique-key="'pages-page-' + surface.key"
                        container-css-class="fill-container"
                        :ref="'richEditor_' + surface.key"
                    >
                        <backend-richeditor
                            :model-value="surfaceValue(surface)"
                            @update:model-value="setSurfaceValue(surface, $event)"
                        >
                        </backend-richeditor>
                    </backend-richeditor-document-connector>
                </template>

                <!-- Shared Monaco for text-type placeholder surfaces (code). -->
                <backend-monacoeditor
                    v-show="showCodeEditor"
                    ref="editor"
                    container-css-class="fill-container"
                    :model-definitions="codeEditorModelDefinitions"
                    :glyph-margin="true"
                >
                </backend-monacoeditor>

                <!-- One Form-widget island per syntax-field group; shown when its tab is active. -->
                <template v-for="surface in syntaxSurfaces" :key="'syntax-' + surface.key">
                    <div
                        v-show="surface.key === activeSurfaceKey"
                        class="pages-syntax-fields-panel"
                        style="position:absolute; inset:0; overflow:auto; padding:20px; background:var(--oc-panel-bg, #fff);"
                    >
                        <form :ref="'form_' + surface.containerId" role="form" data-change-monitor>
                            <div :id="surface.containerId"></div>
                        </form>
                    </div>
                </template>
            </div>

            <editor-component-editorconflictresolver
                ref="conflictResolver"
            ></editor-component-editorconflictresolver>
        </div>
    </template>
</backend-document>
