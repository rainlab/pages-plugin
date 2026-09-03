import { DocumentComponentBase } from '../../../../../../../modules/editor/assets/js/editor.extension.documentcomponent.base.js';

export default {
    extends: DocumentComponentBase,
    data: function() {
        return {
            documentSettingsPopupTitle: this.trans('Menu') || 'Menu',
            items: [],
            selectedItem: null,
            itemFormLoaded: false,
            modalVisible: false,
            newItemTitle: this.trans('New menu item'),
            nextItemId: 1,
            dragItemId: null,
            dropTargetId: null
        };
    },
    computed: {
        flatItems: function() {
            // Flatten the nested tree into a list with depth for indented rendering.
            const result = [];
            const walk = (list, depth, parent) => {
                list.forEach((item) => {
                    result.push({ item: item, depth: depth, parent: parent, siblings: list });
                    if (item._children && item._children.length) {
                        walk(item._children, depth + 1, item);
                    }
                });
            };
            walk(this.items, 0, null);
            return result;
        },

        toolbarElements: function() {
            return [
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
                    icon: 'icon-settings',
                    label: this.trans('editor::lang.common.settings'),
                    command: 'settings',
                    hidden: !this.hasSettingsForm
                },
                {
                    type: 'button',
                    icon: 'icon-plus',
                    label: this.trans('Add item'),
                    command: 'add-item'
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
                {
                    type: 'button',
                    icon: this.documentHeaderCollapsed ? 'icon-angle-down' : 'icon-angle-up',
                    command: 'document:toggleToolbar',
                    fixedRight: true,
                    tooltip: this.trans('editor::lang.common.toggle_document_header')
                }
            ];
        }
    },
    methods: {
        getRootProperties: function() {
            return ['code', 'items'];
        },

        getMainUiDocumentProperties: function() {
            return ['name', 'code', 'items'];
        },

        getSaveDocumentData: function(inspectorDocumentData) {
            const rootProperties = this.getRootProperties();
            const documentData = inspectorDocumentData ? inspectorDocumentData : this.documentData;
            const data = $.oc.vueUtils.getCleanObject(documentData);
            const result = { settings: {} };
            const ignoredProperties = ['items'];

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

            result.items = this.serializeItems(this.items);

            return result;
        },

        syncItemsToDocument: function() {
            if (this.documentData) {
                this.documentData.items = this.serializeItems(this.items);
            }
        },

        serializeItems: function(items) {
            return items.map((item) => {
                const copy = Object.assign({}, item);
                delete copy._children;
                delete copy._selected;
                delete copy._id;
                delete copy.typeLabel;
                if (item._children && item._children.length) {
                    copy.items = this.serializeItems(item._children);
                }
                else {
                    delete copy.items;
                }
                return copy;
            });
        },

        inflateItems: function(rawItems) {
            return (rawItems || []).map((raw) => {
                const item = Object.assign({}, raw);
                item._id = this.nextItemId++;
                item._children = this.inflateItems(raw.items || []);
                item._selected = false;
                delete item.items;
                return item;
            });
        },

        // Subtitle shown under a menu item row (e.g. "Static page").
        itemSubtitle: function(item) {
            return item.typeLabel || item.type || '';
        },

        newBlankItem: function() {
            return {
                _id: this.nextItemId++,
                title: this.newItemTitle,
                type: 'url',
                typeLabel: 'URL',
                url: '/',
                code: '',
                reference: '',
                cmsPage: '',
                nesting: false,
                replace: false,
                viewBag: {},
                _children: [],
                _selected: false
            };
        },

        addItem: function() {
            const item = this.newBlankItem();
            this.items.push(item);
            this.syncItemsToDocument();
            this.editItem(item);
        },

        deleteItem: function(item, list) {
            const arr = list || this.items;
            const idx = arr.indexOf(item);
            if (idx !== -1) {
                arr.splice(idx, 1);
                if (this.selectedItem === item) {
                    this.selectedItem = null;
                }
                this.syncItemsToDocument();
            }
        },

        moveItemUp: function(entry) {
            const arr = entry.siblings;
            const idx = arr.indexOf(entry.item);
            if (idx > 0) {
                arr.splice(idx, 1);
                arr.splice(idx - 1, 0, entry.item);
                this.syncItemsToDocument();
            }
        },

        moveItemDown: function(entry) {
            const arr = entry.siblings;
            const idx = arr.indexOf(entry.item);
            if (idx !== -1 && idx < arr.length - 1) {
                arr.splice(idx, 1);
                arr.splice(idx + 1, 0, entry.item);
                this.syncItemsToDocument();
            }
        },

        indentItem: function(entry) {
            const arr = entry.siblings;
            const idx = arr.indexOf(entry.item);
            if (idx > 0) {
                arr.splice(idx, 1);
                arr[idx - 1]._children.push(entry.item);
                this.syncItemsToDocument();
            }
        },

        outdentItem: function(entry) {
            if (!entry.parent) {
                return;
            }
            const grand = this.findParentContext(entry.parent);
            const arr = entry.siblings;
            const idx = arr.indexOf(entry.item);
            if (idx !== -1) {
                arr.splice(idx, 1);
                const parentIdx = grand.list.indexOf(entry.parent);
                grand.list.splice(parentIdx + 1, 0, entry.item);
                this.syncItemsToDocument();
            }
        },

        findParentContext: function(target) {
            let found = { list: this.items };
            const walk = (list) => {
                list.forEach((item) => {
                    if (item === target) {
                        return;
                    }
                    if (item._children && item._children.indexOf(target) !== -1) {
                        found = { list: item._children, parent: item };
                    }
                    if (item._children) {
                        walk(item._children);
                    }
                });
            };
            walk(this.items);
            return found;
        },

        // --- Drag and drop reordering -------------------------------------

        onDragStart: function(entry, ev) {
            this.dragItemId = entry.item._id;
            if (ev.dataTransfer) {
                ev.dataTransfer.effectAllowed = 'move';
                ev.dataTransfer.setData('text/plain', String(entry.item._id));
            }
        },

        onDragOver: function(entry, ev) {
            if (this.dragItemId === null || this.dragItemId === entry.item._id) {
                return;
            }
            ev.preventDefault();
            this.dropTargetId = entry.item._id;
        },

        onDrop: function(entry) {
            const draggedId = this.dragItemId;
            this.dropTargetId = null;
            this.dragItemId = null;
            if (draggedId === null || draggedId === entry.item._id) {
                return;
            }

            const dragged = this.findEntryById(draggedId);
            if (!dragged) {
                return;
            }

            // Do not drop a node into its own descendant.
            if (this.isDescendant(dragged.item, entry.item)) {
                return;
            }

            // Remove from old position.
            const fromArr = dragged.siblings;
            fromArr.splice(fromArr.indexOf(dragged.item), 1);

            // Insert before the drop target within its sibling list.
            const toArr = entry.siblings;
            const targetIdx = toArr.indexOf(entry.item);
            toArr.splice(targetIdx, 0, dragged.item);

            this.syncItemsToDocument();
        },

        onDragEnd: function() {
            this.dragItemId = null;
            this.dropTargetId = null;
        },

        findEntryById: function(id) {
            return this.flatItems.find((e) => e.item._id === id) || null;
        },

        isDescendant: function(ancestor, node) {
            let found = false;
            const walk = (list) => {
                list.forEach((child) => {
                    if (child === node) {
                        found = true;
                    }
                    if (child._children) {
                        walk(child._children);
                    }
                });
            };
            walk(ancestor._children || []);
            return found;
        },

        // --- Edit Menu Item modal -----------------------------------------

        editItem: function(item) {
            if (this.selectedItem) {
                this.selectedItem._selected = false;
            }
            this.selectedItem = item;
            item._selected = true;
            this.modalVisible = true;

            this.$nextTick(() => {
                this.loadItemForm(item);
            });
        },

        closeModal: function() {
            this.modalVisible = false;
        },

        applyAndClose: function() {
            this.applyItemForm();
            this.modalVisible = false;
        },

        loadItemForm: function(item) {
            const container = this.$refs.menuItemForm;
            if (!container) {
                return;
            }

            oc.request(container, 'onLoadMenuItemForm', {
                data: { bindMenuItemForm: 1 }
            }).then(() => {
                this.itemFormLoaded = true;
                this.populateItemForm(item);
                this.bindTypeChange();
                this.refreshReferenceOptions(item.reference, item.cmsPage);
            });
        },

        // Wire the Type dropdown so switching type reloads the reference/cmsPage options,
        // matching the original plugin's cascade.
        bindTypeChange: function() {
            const form = this.$refs.menuItemForm;
            if (!form) {
                return;
            }
            const typeInput = form.querySelector('[name="menuItem[type]"]');
            if (typeInput && !typeInput._pagesTypeBound) {
                typeInput._pagesTypeBound = true;
                typeInput.addEventListener('change', () => {
                    this.refreshReferenceOptions('', '');
                });
            }
        },

        // Fetch type info for the current type and (re)populate the reference + cmsPage
        // dropdowns, selecting the given values when present.
        refreshReferenceOptions: function(selectedReference, selectedCmsPage) {
            const form = this.$refs.menuItemForm;
            if (!form) {
                return;
            }
            const typeInput = form.querySelector('[name="menuItem[type]"]');
            const type = typeInput ? typeInput.value : '';
            if (!type) {
                return;
            }

            oc.request(form, 'onGetMenuItemTypeInfo', {
                data: { type: type }
            }).then((data) => {
                const info = (data && data.menuItemTypeInfo) || {};
                this.fillSelect(
                    form.querySelector('[name="menuItem[reference]"]'),
                    this.flattenReferences(info.references || {}),
                    selectedReference
                );
                this.fillSelect(
                    form.querySelector('[name="menuItem[cmsPage]"]'),
                    info.cmsPages || {},
                    selectedCmsPage
                );
            });
        },

        // Flatten the (possibly nested) references structure into a flat {key: label} map.
        flattenReferences: function(references) {
            const flat = {};
            const walk = (map, prefix) => {
                Object.keys(map).forEach((key) => {
                    const entry = map[key];
                    const title = (entry && typeof entry === 'object') ? entry.title : entry;
                    flat[key] = (prefix ? prefix + ' / ' : '') + (title || key);
                    if (entry && typeof entry === 'object' && entry.items) {
                        walk(entry.items, flat[key]);
                    }
                });
            };
            walk(references || {}, '');
            return flat;
        },

        fillSelect: function(select, options, selectedValue) {
            if (!select) {
                return;
            }
            select.innerHTML = '';
            Object.keys(options).forEach((value) => {
                const opt = document.createElement('option');
                opt.value = value;
                opt.textContent = options[value];
                if (value === selectedValue) {
                    opt.selected = true;
                }
                select.appendChild(opt);
            });
            select.dispatchEvent(new Event('change', { bubbles: true }));
        },

        populateItemForm: function(item) {
            const form = this.$refs.menuItemForm;
            if (!form) {
                return;
            }

            Object.keys(item).forEach((key) => {
                if (key.charAt(0) === '_' || key === 'typeLabel') {
                    return;
                }
                const input = form.querySelector('[name="menuItem[' + key + ']"]');
                if (input) {
                    if (input.type === 'checkbox') {
                        input.checked = !!item[key] && item[key] !== '0';
                    }
                    else {
                        input.value = item[key];
                    }
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        },

        applyItemForm: function() {
            const form = this.$refs.menuItemForm;
            if (!form || !this.selectedItem) {
                return;
            }

            const formData = new FormData(form);
            const viewBag = {};
            for (const [name, value] of formData.entries()) {
                const vbMatch = name.match(/^menuItem\[viewBag\]\[([^\]]+)\]$/);
                if (vbMatch) {
                    viewBag[vbMatch[1]] = value;
                    continue;
                }
                const m = name.match(/^menuItem\[([^\]]+)\]$/);
                if (m) {
                    this.selectedItem[m[1]] = value;
                }
            }
            this.selectedItem.viewBag = viewBag;

            // Refresh the row subtitle from the (possibly changed) type.
            const typeInput = form.querySelector('[name="menuItem[type]"]');
            if (typeInput) {
                const opt = typeInput.options ? typeInput.options[typeInput.selectedIndex] : null;
                this.selectedItem.typeLabel = opt ? opt.text : this.selectedItem.type;
            }

            this.syncItemsToDocument();
        },

        documentCreatedOrLoaded: function() {
            this.items = this.inflateItems(this.documentData.items || []);
        },

        onToolbarCommand: function(command, isHotkey) {
            if (command === 'add-item') {
                this.addItem();
                return;
            }
            this.handleBasicDocumentCommands(command, isHotkey);
        }
    }
};
