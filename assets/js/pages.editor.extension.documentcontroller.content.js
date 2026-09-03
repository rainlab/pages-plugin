import { DocumentControllerBase } from '../../../../../modules/editor/assets/js/editor.extension.documentcontroller.base.js';

export class DocumentControllerContent extends DocumentControllerBase {
    get documentType() {
        return 'content';
    }

    get vueEditorComponentName() {
        return 'pages-editor-component-content-editor';
    }

    beforeDocumentOpen(commandObj, nodeData) {
        // The tree root ("Content") and folder nodes are not editable; content blocks are.
        if (nodeData && nodeData.userData && (nodeData.userData.topLevel || nodeData.userData.isFolder)) {
            return false;
        }

        return true;
    }
}
