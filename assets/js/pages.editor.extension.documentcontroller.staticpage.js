import { DocumentControllerBase } from '../../../../../modules/editor/assets/js/editor.extension.documentcontroller.base.js';
import { EditorCommand } from '../../../../../modules/editor/assets/js/editor.command.js';
import { DocumentUri } from '../../../../../modules/editor/assets/js/editor.documenturi.js';

export class DocumentControllerStaticPage extends DocumentControllerBase {
    get documentType() {
        return 'static-page';
    }

    get vueEditorComponentName() {
        return 'pages-editor-component-staticpage-editor';
    }

    initListeners() {
        // Persist page order/nesting when a page is dragged in the navigator.
        this.on('pages:navigator-node-moved', this.onPageNodeMoved);
        this.on('pages:navigator-context-menu-display', this.getNavigatorContextMenuItems);
    }

    getNavigatorContextMenuItems(commandObj, payload) {
        const uri = DocumentUri.parse(payload.nodeData.uniqueKey);
        if (!uri || uri.documentType !== this.documentType) {
            return;
        }

        const userData = payload.nodeData.userData || {};
        if (userData.topLevel || !userData.path) {
            return;
        }

        payload.menuItems.push({
            type: 'text',
            icon: 'icon-create',
            command: new EditorCommand('pages:create-document@' + this.documentType, {
                parentFileName: userData.path,
                parentUrl: userData.url
            }),
            label: this.trans('Add subpage') || 'Add subpage'
        });
    }

    onBeforeDocumentCreated(commandObj, payload, documentData) {
        const userData = commandObj.userData || {};
        if (!userData.parentFileName) {
            return;
        }

        // New subpages nest under their parent and preset the URL with the parent's.
        // The editor component keeps appending a slug of the title to the parent URL
        // (via metadata.parentUrl) until the URL is edited by hand.
        documentData.metadata.parentFileName = userData.parentFileName;

        const parentUrl = (userData.parentUrl || '').replace(/\/+$/, '');
        if (parentUrl.length) {
            documentData.metadata.parentUrl = parentUrl;
            documentData.document.url = parentUrl + '/';
            if (documentData.document.settings) {
                documentData.document.settings.url = parentUrl + '/';
            }
        }
    }

    beforeDocumentOpen(commandObj, nodeData) {
        // The tree root ("Static Pages") is not an editable document; page nodes are.
        if (nodeData && nodeData.userData && nodeData.userData.topLevel) {
            return false;
        }

        return true;
    }

    // Persist a page drag. The TreeView applies the move to its own node arrays in
    // completeDrop(), which only runs if we DON'T preventDefault - so let it reorder the
    // tree first, then (on the next tick) serialize the updated tree and post it.
    onPageNodeMoved(cmd) {
        // Do not preventDefault: the tree must run completeDrop to reflect the new order.
        setTimeout(() => this.persistPageStructure(), 0);
    }

    async persistPageStructure() {
        const structure = this.buildPageStructure();

        // Guard: never post an empty structure (would wipe the yaml server-side).
        if (!structure || !Object.keys(structure).length) {
            return;
        }

        $.oc.editor.application.setNavigatorReadonly(true);
        try {
            await $.oc.editor.application.ajaxRequest('onCommand', {
                extension: this.editorNamespace,
                command: 'onPageStructureUpdate',
                // JSON-encode: form encoding drops the empty-object leaves that represent
                // childless pages, which would otherwise post an empty structure.
                documentData: { structure: JSON.stringify(structure) }
            });
            await this.editorStore.refreshExtensionNavigatorNodes(this.editorNamespace, this.documentType);
        }
        catch (error) {
            await this.editorStore.refreshExtensionNavigatorNodes(this.editorNamespace, this.documentType);
            $.oc.editor.page.showAjaxErrorAlert(error, this.trans('editor::lang.common.error'));
        }
        finally {
            $.oc.editor.application.setNavigatorReadonly(false);
        }
    }

    // Walk the Static Pages navigator node into a nested {path: {child: {...}}} map,
    // matching the structure PageList::updateStructure() expects.
    buildPageStructure() {
        const sections = this.parentExtension.state.navigatorSections || [];
        let pagesRoot = null;

        const findRoot = (nodes) => {
            (nodes || []).forEach((node) => {
                if (node.userData && node.userData.topLevel && node.uniqueKey
                    && node.uniqueKey.indexOf('static-page') !== -1) {
                    pagesRoot = node;
                }
                if (!pagesRoot && node.nodes) {
                    findRoot(node.nodes);
                }
            });
        };

        sections.forEach((section) => findRoot(section.nodes));

        const serialize = (nodes) => {
            const result = {};
            (nodes || []).forEach((node) => {
                const path = node.userData && node.userData.path;
                if (!path) {
                    return;
                }
                result[path] = serialize(node.nodes);
            });
            return result;
        };

        return pagesRoot ? serialize(pagesRoot.nodes) : {};
    }

    preprocessSettingsFields(settingsFields) {
        const layouts = this.parentExtension.customData.layouts || {};

        settingsFields.some((field) => {
            if (field.property === 'layout') {
                field.options = layouts;
                return true;
            }
        });

        return settingsFields;
    }
}
