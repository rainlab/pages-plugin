import { DocumentComponentBase } from '../../../../../../../modules/editor/assets/js/editor.extension.documentcomponent.base.js';
import EditorModelDefinition from '../../../../../../../modules/backend/vuecomponents/monacoeditor/assets/js/modeldefinition.js';

export default {
    extends: DocumentComponentBase,
    data: function() {
        return {
            documentTitleProperty: 'fileName',
            codeEditorModelDefinitions: [],
            defMarkup: null,
            modelsReady: false,
            toolbarExtensionPoint: []
        };
    },
    computed: {
        toolbarElements: function() {
            return [].concat([
                {
                    type: 'button',
                    icon: 'icon-save-cloud',
                    label: this.trans('backend::lang.form.save'),
                    hotkey: 'ctrl+s, cmd+s',
                    tooltip: this.trans('backend::lang.form.save'),
                    command: 'save'
                },
                {
                    type: 'separator'
                },
                {
                    type: 'button',
                    icon: 'icon-delete',
                    disabled: this.isNewDocument,
                    command: 'delete',
                    hotkey: 'shift+option+d',
                    tooltip: this.trans('backend::lang.form.delete')
                },
                this.toolbarExtensionPoint,
                {
                    type: 'button',
                    icon: this.documentHeaderCollapsed ? 'icon-angle-down' : 'icon-angle-up',
                    command: 'document:toggleToolbar',
                    fixedRight: true,
                    tooltip: this.trans('editor::lang.common.toggle_document_header')
                }
            ]);
        },

        // The server tells us which surface to use via documentData.language.
        // htm/html content blocks edit as WYSIWYG (richeditor), matching the original plugin.
        isRicheditorDocument: function() {
            return this.documentData && this.documentData.language === 'richeditor';
        },

        isMarkdownDocument: function() {
            return this.documentData && this.documentData.language === 'markdown';
        },

        isCodeDocument: function() {
            return !this.isRicheditorDocument && !this.isMarkdownDocument;
        }
    },
    methods: {
        getRootProperties: function() {
            return ['fileName', 'markup'];
        },

        getMainUiDocumentProperties: function() {
            return ['fileName', 'markup'];
        },

        getSaveDocumentData: function(inspectorDocumentData) {
            const documentData = inspectorDocumentData ? inspectorDocumentData : this.documentData;
            const data = $.oc.vueUtils.getCleanObject(documentData);

            return {
                fileName: data.fileName,
                markup: data.markup
            };
        },

        buildMarkupModel: function() {
            // Monaco backs the code path only (plaintext / raw html source). The
            // richeditor and markdown surfaces bind straight to documentData.markup.
            const language = this.isCodeDocument
                ? (this.documentData.language === 'plaintext' ? 'plaintext' : 'html')
                : 'html';

            this.defMarkup = new EditorModelDefinition(
                language,
                this.trans('Content'),
                this.documentData,
                'markup',
                'backend-icon-background monaco-document html'
            );

            this.codeEditorModelDefinitions = [this.defMarkup];
            this.modelsReady = true;
        },

        onToolbarCommand: function(command, isHotkey, ev) {
            this.handleBasicDocumentCommands(command, isHotkey);

            // Forward toolbar commands to the richeditor/markdown connector event bus
            // so their in-toolbar buttons (fullscreen, code view, etc.) work.
            const connector = this.$refs.richEditorDocumentConnector || this.$refs.markdownEditor;
            if (connector && connector.internalEventBus) {
                connector.internalEventBus.emit('toolbarcmd', { command: command, ev: ev });
            }
        },

        documentLoaded: function(data) {
            // Only the Monaco model needs a manual value push; richeditor/markdown use v-model.
            if (this.isCodeDocument && this.$refs.editor && this.defMarkup) {
                this.$refs.editor.updateValue(this.defMarkup, this.documentData.markup);
            }
        },

        onParentTabSelected: function() {
            if (this.$refs.editor) {
                this.$nextTick(() => this.$refs.editor.layout());
            }
            if (this.$refs.markdownEditor) {
                this.$nextTick(() => this.$refs.markdownEditor.refresh());
            }
        },

        documentCreatedOrLoaded: function() {
            this.buildMarkupModel();
        }
    },
    watch: {
        isRicheditorDocument: function(value) {
            if (!value) {
                this.toolbarExtensionPoint = [];
            }
        },
        isMarkdownDocument: function(value) {
            if (!value) {
                this.toolbarExtensionPoint = [];
            }
        }
    }
};
