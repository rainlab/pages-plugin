import { DocumentComponentBase } from '../../../../../../../modules/editor/assets/js/editor.extension.documentcomponent.base.js';
import EditorModelDefinition from '../../../../../../../modules/backend/vuecomponents/monacoeditor/assets/js/modeldefinition.js';

// Each open page document renders its own syntax-field islands; a per-instance
// counter keeps the container ids and form widget alias (which prefixes every
// field id) unique so labels always target their own tab's inputs.
let syntaxIslandUid = 0;

export default {
    extends: DocumentComponentBase,
    data: function() {
        const uid = ++syntaxIslandUid;

        return {
            syntaxFormUid: uid,
            documentSettingsPopupTitle: this.trans('Static page') || 'Static Page',
            previewUrl: null,
            hasContentField: true,
            // Layout-driven UI state lives outside documentData: the settings popup
            // deep-clones documentData back over itself on apply, which would wipe
            // any values refreshed by the save that runs before the apply.
            placeholderInfo: {},
            syntaxFieldGroups: [],
            // Last auto-generated URL for a new subpage. While the URL still matches
            // this value, typing the title keeps rebuilding it as parentUrl + slug;
            // a manual URL edit (or saving) stops the preset. Null when inactive.
            autoUrlValue: null,
            // Monaco backs only the "code" surfaces (text-type placeholders).
            codeEditorModelDefinitions: [],
            codeModels: {},
            modelsReady: false,
            // Tabs: the main content surface, one per layout placeholder, and one per
            // layout syntax-field group.
            activeSurfaceKey: 'markup',
            loadedSyntaxGroups: {},
            loadingSyntaxGroups: {},
            // Each rich surface's richeditor connector owns its own toolbar-button array,
            // keyed by surface key. The document toolbar renders the active surface's array
            // (activeToolbarExtension) so switching tabs just swaps which array is shown -
            // the connector never has to re-emit its buttons.
            surfaceToolbars: {}
        };
    },
    computed: {
        // Every editable content region as a tab.
        //  - mode 'rich'   = WYSIWYG richeditor (page content, html placeholders)
        //  - mode 'code'   = Monaco (text-type placeholders)
        //  - mode 'syntax' = server-rendered Form-widget island (layout syntax fields)
        contentSurfaces: function() {
            const surfaces = [];

            // The layout's staticPage component can disable the content field (useContent).
            if (this.hasContentField) {
                surfaces.push({ key: 'markup', title: this.trans('Content') || 'Content', mode: 'rich', holder: 'root' });
            }

            const info = this.placeholderInfo || {};
            Object.keys(info).forEach((code) => {
                const meta = info[code] || {};
                surfaces.push({
                    key: code,
                    title: meta.title || code,
                    mode: meta.type === 'text' ? 'code' : 'rich',
                    holder: 'placeholder'
                });
            });

            const groups = this.syntaxFieldGroups || [];
            groups.forEach((group) => {
                surfaces.push({
                    key: group.key,
                    title: group.title,
                    mode: 'syntax',
                    tab: group.title,
                    containerId: 'pagesSyntax' + this.syntaxFormUid + '_' + group.key.replace(/[^a-z0-9]/gi, '')
                });
            });

            return surfaces;
        },

        hasTabs: function() {
            return this.contentSurfaces.length > 1;
        },

        surfaceTabs: function() {
            return this.contentSurfaces.map((surface) => ({
                key: surface.key,
                label: surface.title
            }));
        },

        syntaxSurfaces: function() {
            return this.contentSurfaces.filter((s) => s.mode === 'syntax');
        },

        activeSurface: function() {
            return this.contentSurfaces.find((s) => s.key === this.activeSurfaceKey)
                || this.contentSurfaces[0];
        },

        showCodeEditor: function() {
            return this.modelsReady
                && this.activeSurface
                && this.activeSurface.mode === 'code';
        },

        // The richeditor toolbar buttons for the active surface (empty for non-rich).
        activeToolbarExtension: function() {
            const surface = this.activeSurface;
            if (surface && surface.mode === 'rich') {
                return this.surfaceToolbars[surface.key] || [];
            }
            return [];
        },

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
                    type: 'button',
                    target: '_blank',
                    href: this.previewUrl,
                    disabled: this.previewUrl === null || this.isNewDocument,
                    icon: 'icon-location-target',
                    label: this.trans('Preview') || 'Preview',
                    tooltip: this.trans('Preview') || 'Preview',
                    command: 'preview'
                },
                {
                    type: 'button',
                    icon: 'icon-settings',
                    label: this.trans('editor::lang.common.settings'),
                    command: 'settings',
                    hidden: !this.hasSettingsForm
                },
                this.activeToolbarExtension,
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
                {
                    type: 'button',
                    icon: this.documentHeaderCollapsed ? 'icon-angle-down' : 'icon-angle-up',
                    command: 'document:toggleToolbar',
                    fixedRight: true,
                    tooltip: this.trans('editor::lang.common.toggle_document_header')
                }
            ]);
        }
    },
    watch: {
        // The header subtitle edits the top-level url; saving and the settings
        // popup read settings.url - keep the two in sync both ways.
        'documentData.url': function(value) {
            if (this.documentData && this.documentData.settings && this.documentData.settings.url !== value) {
                this.documentData.settings.url = value;
            }
        },
        'documentData.settings.url': function(value) {
            if (this.documentData && this.documentData.url !== value) {
                this.documentData.url = value;
            }
        },

        // For new subpages, typing the title keeps building the URL as
        // parentUrl + slug until the URL is edited by hand.
        'documentData.title': function(value) {
            if (this.autoUrlValue === null || !this.isNewDocument) {
                return;
            }

            // A URL differing from the last generated value means it was edited by hand
            if (this.documentData.url !== this.autoUrlValue) {
                this.autoUrlValue = null;
                return;
            }

            const prefix = String(this.documentMetadata.parentUrl || '').replace(/\/+$/, '');
            const slug = oc.InputPresetEngine.formatValue(
                { inputPresetType: 'url', inputPresetRemoveWords: true },
                String(value || '')
            );

            this.autoUrlValue = (slug === '/' || slug === '') ? prefix + '/' : prefix + slug;
            this.documentData.url = this.autoUrlValue;
        },

        // The settings popup deep-clones its snapshot back over documentData on
        // apply, replacing the placeholders object - rebind the code models so
        // Monaco keeps writing into the live object.
        'documentData.placeholders': function(newValue, oldValue) {
            if (newValue === oldValue || !this.modelsReady) {
                return;
            }

            this.modelsReady = false;
            this.ensurePlaceholderKeys();
            this.buildCodeModels();
            this.$nextTick(() => {
                this.modelsReady = true;
            });
        }
    },
    methods: {
        getRootProperties: function() {
            return ['fileName', 'markup', 'placeholders'];
        },

        getMainUiDocumentProperties: function() {
            return ['title', 'url', 'markup', 'placeholders'];
        },

        surfaceValue: function(surface) {
            if (surface.holder === 'root') {
                return this.documentData.markup;
            }
            return this.documentData.placeholders[surface.key];
        },

        setSurfaceValue: function(surface, value) {
            if (surface.holder === 'root') {
                this.documentData.markup = value;
            }
            else {
                this.documentData.placeholders[surface.key] = value;
            }
        },

        onSurfaceTabSelected: function(key) {
            this.selectSurface(key);
        },

        selectSurface: function(key) {
            this.activeSurfaceKey = key;

            this.$nextTick(() => {
                const surface = this.activeSurface;
                if (!surface) {
                    return;
                }

                if (surface.mode === 'code' && this.$refs.editor) {
                    const def = this.codeModels[surface.key];
                    if (def) {
                        this.$refs.editor.updateValue(def, this.surfaceValue(surface));
                        this.$refs.editor.layout();
                    }
                }
                else if (surface.mode === 'rich') {
                    this.refreshRichSurface(surface.key);
                }
                else if (surface.mode === 'syntax' && !this.loadedSyntaxGroups[surface.key]) {
                    this.loadSyntaxGroup(surface);
                }
            });
        },

        // Refresh a rich surface's connector once it becomes active. The connector runs
        // updateSize()/extendToolbar() on mount, but on first load Froala's wrapper may not
        // be ready yet (so the resizer stays non-responsive until the tab is toggled). Retry
        // until the Froala wrapper exists, then run both:
        //  - updateSize():    recomputes the resizable width/centering (responsive resize)
        //  - extendToolbar(): repopulates the toolbar buttons
        refreshRichSurface: function(key, attempt) {
            attempt = attempt || 0;

            if (this.activeSurfaceKey !== key || attempt > 20) {
                return;
            }

            const connector = this.$refs['richEditor_' + key];
            const inst = Array.isArray(connector) ? connector[0] : connector;

            // Wait for the connector and its Froala wrapper to exist before measuring.
            const wrapperReady = inst && inst.$el && inst.$el.querySelector('.fr-wrapper');
            if (!wrapperReady) {
                setTimeout(() => this.refreshRichSurface(key, attempt + 1), 50);
                return;
            }

            if (typeof inst.updateSize === 'function') {
                inst.updateSize();
            }
            if (typeof inst.extendToolbar === 'function') {
                inst.extendToolbar();
            }
        },

        buildCodeModels: function() {
            if (!this.documentData.placeholders || typeof this.documentData.placeholders !== 'object') {
                this.documentData.placeholders = {};
            }

            const defs = [];
            const models = {};

            this.contentSurfaces.forEach((surface) => {
                if (surface.mode !== 'code') {
                    return;
                }

                if (surface.holder === 'placeholder' && this.documentData.placeholders[surface.key] === undefined) {
                    this.documentData.placeholders[surface.key] = '';
                }

                const holderObject = surface.holder === 'root'
                    ? this.documentData
                    : this.documentData.placeholders;
                const holderProperty = surface.holder === 'root' ? 'markup' : surface.key;

                const def = new EditorModelDefinition(
                    'plaintext',
                    surface.title,
                    holderObject,
                    holderProperty,
                    'backend-icon-background monaco-document html'
                );

                defs.push(def);
                models[surface.key] = def;
            });

            this.codeEditorModelDefinitions = defs;
            this.codeModels = models;
        },

        ensurePlaceholderKeys: function() {
            const info = this.placeholderInfo || {};
            if (!this.documentData.placeholders || typeof this.documentData.placeholders !== 'object') {
                this.documentData.placeholders = {};
            }
            Object.keys(info).forEach((code) => {
                if (this.documentData.placeholders[code] === undefined) {
                    this.documentData.placeholders[code] = '';
                }
            });
        },

        getSaveDocumentData: function(inspectorDocumentData) {
            const rootProperties = this.getRootProperties();
            const documentData = inspectorDocumentData ? inspectorDocumentData : this.documentData;

            const data = $.oc.vueUtils.getCleanObject(documentData);
            const result = { settings: {} };

            const ignoredProperties = ['placeholderInfo', 'syntaxFieldGroups'];

            Object.keys(data).forEach((property) => {
                if (property === 'settings' || ignoredProperties.indexOf(property) !== -1) {
                    return;
                }

                if (rootProperties.indexOf(property) !== -1) {
                    result[property] = data[property];
                }
                else {
                    result.settings[property] = data[property];
                }
            });

            if (typeof data.settings === 'object' && data.settings !== null) {
                Object.keys(data.settings).forEach((property) => {
                    if (rootProperties.indexOf(property) === -1 && result.settings[property] === undefined) {
                        result.settings[property] = data.settings[property];
                    }
                });
            }

            // Merge values from every loaded syntax-field island into settings (viewBag).
            const syntaxData = this.collectSyntaxFieldData();
            Object.keys(syntaxData).forEach((key) => {
                result.settings[key] = syntaxData[key];
            });

            return result;
        },

        collectSyntaxFieldData: function() {
            const result = {};

            this.syntaxSurfaces.forEach((surface) => {
                const form = this.$refs['form_' + surface.containerId];
                const el = Array.isArray(form) ? form[0] : form;
                if (!this.loadedSyntaxGroups[surface.key] || !el) {
                    return;
                }

                const formData = new FormData(el);
                for (const [name, value] of formData.entries()) {
                    const match = name.match(/^syntaxFields\[viewBag\]\[([^\]]+)\](.*)$/);
                    if (!match) {
                        continue;
                    }

                    const path = [match[1]];
                    const bracketRe = /\[([^\]]*)\]/g;
                    let m;
                    while ((m = bracketRe.exec(match[2])) !== null) {
                        path.push(m[1]);
                    }

                    this.assignNested(result, path, value);
                }
            });

            return result;
        },

        assignNested: function(target, path, value) {
            let node = target;
            for (let i = 0; i < path.length - 1; i++) {
                const key = path[i];
                if (node[key] === undefined || typeof node[key] !== 'object') {
                    node[key] = {};
                }
                node = node[key];
            }
            node[path[path.length - 1]] = value;
        },

        loadSyntaxGroup: function(surface) {
            const form = this.$refs['form_' + surface.containerId];
            const el = Array.isArray(form) ? form[0] : form;
            if (!el || this.loadingSyntaxGroups[surface.key]) {
                return;
            }

            this.loadingSyntaxGroups[surface.key] = true;

            // Load the group's Form-widget island. Core's MutationObserver auto-initializes
            // the injected controls (repeater, mediafinder, richeditor, ...).
            oc.request(el, 'onLoadSyntaxFields', {
                data: {
                    path: this.documentMetadata.path,
                    tab: surface.tab,
                    containerId: surface.containerId,
                    formAlias: 'pagesSyntaxForm' + this.syntaxFormUid
                }
            }).then(
                () => {
                    this.loadedSyntaxGroups[surface.key] = true;
                    this.loadingSyntaxGroups[surface.key] = false;
                },
                () => {
                    this.loadingSyntaxGroups[surface.key] = false;
                }
            );
        },

        onToolbarCommand: function(command, isHotkey, ev) {
            this.handleBasicDocumentCommands(command, isHotkey);

            const surface = this.activeSurface;
            if (surface && surface.mode === 'rich') {
                const connector = this.$refs['richEditor_' + this.activeSurfaceKey];
                const inst = Array.isArray(connector) ? connector[0] : connector;
                if (inst && inst.internalEventBus) {
                    inst.internalEventBus.emit('toolbarcmd', { command: command, ev: ev });
                }
            }
        },

        documentLoaded: function(data) {
            this.previewUrl = (data && data.previewUrl) || null;
            if (data && data.hasContentField !== undefined) {
                this.hasContentField = data.hasContentField !== false;
            }
            this.ensureActiveSurfaceExists();

            this.$nextTick(() => {
                if (this.$refs.editor) {
                    Object.keys(this.codeModels).forEach((key) => {
                        const def = this.codeModels[key];
                        const surface = this.contentSurfaces.find((s) => s.key === key);
                        if (surface) {
                            this.$refs.editor.updateValue(def, this.surfaceValue(surface));
                        }
                    });
                }

                // Ensure the initially-active rich surface's toolbar is populated.
                if (this.activeSurface && this.activeSurface.mode === 'rich') {
                    this.refreshRichSurface(this.activeSurfaceKey);
                }
            });
        },

        documentCreatedOrLoaded: function() {
            this.placeholderInfo = (this.documentData && this.documentData.placeholderInfo) || {};
            this.syntaxFieldGroups = (this.documentData && this.documentData.syntaxFieldGroups) || [];

            this.autoUrlValue = (
                this.documentMetadata &&
                this.documentMetadata.isNewDocument &&
                this.documentMetadata.parentUrl
            ) ? this.documentData.url : null;

            this.ensurePlaceholderKeys();
            this.buildCodeModels();
            this.loadedSyntaxGroups = {};
            this.loadingSyntaxGroups = {};

            // Pre-create a stable toolbar array for every rich surface before the editor
            // panel renders, so each connector binds to (and mutates) its own live array.
            const toolbars = {};
            this.contentSurfaces.forEach((surface) => {
                if (surface.mode === 'rich') {
                    toolbars[surface.key] = [];
                }
            });
            this.surfaceToolbars = toolbars;

            this.activeSurfaceKey = 'markup';
            this.ensureActiveSurfaceExists();
            this.modelsReady = true;
        },

        ensureActiveSurfaceExists: function() {
            if (!this.contentSurfaces.find((s) => s.key === this.activeSurfaceKey)) {
                this.activeSurfaceKey = this.contentSurfaces.length ? this.contentSurfaces[0].key : 'markup';
            }
        },

        documentSaved: function(data) {
            if (!data) {
                return;
            }

            if (data.previewUrl !== undefined) {
                this.previewUrl = data.previewUrl;
            }

            // A layout change alters the placeholder tabs, syntax-field groups and the
            // content field visibility - rebuild the surfaces when they changed.
            const infoChanged =
                (data.placeholderInfo !== undefined &&
                    JSON.stringify(data.placeholderInfo) !== JSON.stringify(this.placeholderInfo || {})) ||
                (data.syntaxFieldGroups !== undefined &&
                    JSON.stringify(data.syntaxFieldGroups) !== JSON.stringify(this.syntaxFieldGroups || [])) ||
                (data.hasContentField !== undefined && (data.hasContentField !== false) !== this.hasContentField);

            if (!infoChanged) {
                return;
            }

            if (data.placeholderInfo !== undefined) {
                this.placeholderInfo = data.placeholderInfo;
            }
            if (data.syntaxFieldGroups !== undefined) {
                this.syntaxFieldGroups = data.syntaxFieldGroups;
            }
            if (data.hasContentField !== undefined) {
                this.hasContentField = data.hasContentField !== false;
            }

            this.rebuildContentSurfaces();
        },

        // Rebuilds the tabs, code models and toolbars after the layout-driven
        // surface set changed.
        rebuildContentSurfaces: function() {
            this.modelsReady = false;
            this.ensurePlaceholderKeys();
            this.buildCodeModels();
            this.loadedSyntaxGroups = {};
            this.loadingSyntaxGroups = {};

            const toolbars = {};
            this.contentSurfaces.forEach((surface) => {
                if (surface.mode === 'rich') {
                    toolbars[surface.key] = this.surfaceToolbars[surface.key] || [];
                }
            });
            this.surfaceToolbars = toolbars;

            this.ensureActiveSurfaceExists();

            this.$nextTick(() => {
                this.modelsReady = true;

                this.$nextTick(() => {
                    const surface = this.activeSurface;
                    if (surface && surface.mode === 'syntax' && !this.loadedSyntaxGroups[surface.key]) {
                        this.loadSyntaxGroup(surface);
                    }
                    else if (surface && surface.mode === 'rich') {
                        this.refreshRichSurface(surface.key);
                    }
                });
            });
        },

        // Keep the active rich surface's resizable width in sync on window resize. The
        // connector's own resize handler is unreliable here (its listener loses the
        // component `this`), so drive updateSize ourselves. Debounced.
        onWindowResize: function() {
            if (this.resizeDebounce) {
                clearTimeout(this.resizeDebounce);
            }
            this.resizeDebounce = setTimeout(() => {
                if (this.activeSurface && this.activeSurface.mode === 'rich') {
                    this.refreshRichSurface(this.activeSurfaceKey);
                }
            }, 10);
        }
    },
    mounted: function() {
        this.boundWindowResize = this.onWindowResize.bind(this);
        window.addEventListener('resize', this.boundWindowResize);
    },
    beforeUnmount: function() {
        if (this.boundWindowResize) {
            window.removeEventListener('resize', this.boundWindowResize);
        }
        if (this.resizeDebounce) {
            clearTimeout(this.resizeDebounce);
        }
    }
};
