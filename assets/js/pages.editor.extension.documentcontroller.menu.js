import { DocumentControllerBase } from '../../../../../modules/editor/assets/js/editor.extension.documentcontroller.base.js';

export class DocumentControllerMenu extends DocumentControllerBase {
    get documentType() {
        return 'menu';
    }

    get vueEditorComponentName() {
        return 'pages-editor-component-menu-editor';
    }

    beforeDocumentOpen(commandObj, nodeData) {
        // The tree root ("Menus") is not editable; individual menus are.
        if (nodeData && nodeData.userData && nodeData.userData.topLevel) {
            return false;
        }

        return true;
    }
}
