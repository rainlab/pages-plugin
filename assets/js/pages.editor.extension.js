import { ExtensionBase } from '../../../../../modules/editor/assets/js/editor.extension.base.js';
import { DocumentControllerStaticPage } from './pages.editor.extension.documentcontroller.staticpage.js';
import { DocumentControllerMenu } from './pages.editor.extension.documentcontroller.menu.js';
import { DocumentControllerContent } from './pages.editor.extension.documentcontroller.content.js';

class PagesEditorExtension extends ExtensionBase {
    constructor(namespace) {
        super(namespace);
    }

    listDocumentControllerClasses() {
        return [
            DocumentControllerStaticPage,
            DocumentControllerMenu,
            DocumentControllerContent
        ];
    }

    onCommand(commandString, payload) {
        super.onCommand(commandString, payload);

        if (commandString === 'pages:refresh-navigator') {
            this.editorStore.refreshExtensionNavigatorNodes(this.editorNamespace).then(() => {});
        }
    }
}

// Register with the editor extension registry
oc.editorExtensions = oc.editorExtensions || {};
oc.editorExtensions['pages'] = PagesEditorExtension;

export { PagesEditorExtension };
