<backend-document
    :header-collapsed="documentHeaderCollapsed"
    :full-screen="documentFullScreen"
    :loading="initializing"
    :processing="processing"
    :error-loading-document="errorLoadingDocument"
    error-loading-document-header="<?= e(trans('Error loading menu')) ?>"
    container-css-class="fill-container"
>
    <template v-slot:header>
        <backend-document-header
            title-property="name"
            subtitle-property="code"
            subtitle-label="<?= e(trans('Code')) ?>"
            subtitle-preset-type="file"
            :subtitle-preset-remove-words="true"
            :is-new-document="isNewDocument"
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
        <div class="pages-menu-editor" style="height:100%; overflow:auto; padding:16px 20px;">
            <ul style="list-style:none; margin:0; padding:0; max-width:960px;">
                <li
                    v-for="entry in flatItems"
                    :key="entry.item._id"
                    class="menu-item-row"
                    :class="{
                        selected: entry.item._selected,
                        'item-hidden': isItemHidden(entry.item),
                        'drop-before': dropTargetId === entry.item._id && dropMode === 'before',
                        'drop-after': dropTargetId === entry.item._id && dropMode === 'after',
                        'drop-inside': dropTargetId === entry.item._id && dropMode === 'inside'
                    }"
                    :style="{ marginLeft: (entry.depth * 24) + 'px' }"
                    draggable="true"
                    @dragstart="onDragStart(entry, $event)"
                    @dragover="onDragOver(entry, $event)"
                    @drop="onDrop(entry)"
                    @dragend="onDragEnd"
                    @click="editItem(entry.item)"
                >
                    <i class="menu-item-drag icon-bars"></i>
                    <i class="menu-item-icon icon-file-o"></i>
                    <span class="menu-item-label">
                        <span class="menu-item-title">{{ entry.item.title || '<?= e(trans('Untitled')) ?>' }}<i
                            v-if="isItemHidden(entry.item)"
                            class="menu-item-hidden-icon icon-eye-slash"
                            title="<?= e(trans('Hidden')) ?>"
                        ></i></span>
                        <span class="menu-item-subtitle">{{ itemSubtitle(entry.item) }}</span>
                    </span>
                    <span class="menu-item-actions" @click.stop>
                        <button type="button" title="<?= e(trans('Add subitem')) ?>" @click="addSubItem(entry)" class="menu-item-action"><i class="icon-plus"></i></button>
                        <button type="button" title="<?= e(trans('Move up')) ?>" @click="moveItemUp(entry)" class="menu-item-action"><i class="icon-arrow-up"></i></button>
                        <button type="button" title="<?= e(trans('Move down')) ?>" @click="moveItemDown(entry)" class="menu-item-action"><i class="icon-arrow-down"></i></button>
                        <button type="button" title="<?= e(trans('Indent')) ?>" @click="indentItem(entry)" class="menu-item-action"><i class="icon-arrow-right"></i></button>
                        <button type="button" title="<?= e(trans('Outdent')) ?>" @click="outdentItem(entry)" class="menu-item-action"><i class="icon-arrow-left"></i></button>
                        <button type="button" title="<?= e(trans('Delete')) ?>" @click="deleteItem(entry.item, entry.siblings)" class="menu-item-action menu-item-action-danger"><i class="icon-times"></i></button>
                    </span>
                </li>
            </ul>

            <p v-if="!flatItems.length" style="color:var(--bs-secondary-color); padding:10px 0;">
                <?= e(trans('No menu items yet. Use "Add item" in the toolbar.')) ?>
            </p>
        </div>

        <!-- Edit Menu Item modal (hosts the Form widget island). -->
        <div
            v-show="modalVisible"
            class="pages-menu-modal-overlay modal fade in show"
            style="display:block;"
            @click.self="closeModal"
        >
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title"><?= e(trans('Edit Menu Item')) ?></h4>
                        <button type="button" class="btn-close" @click="closeModal"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Loading indicator shown until the item form is rendered, populated
                             and the type field visibility applied, like the document loader. -->
                        <div v-if="modalLoading" class="pages-menu-modal-loading d-flex align-items-center justify-content-center">
                            <backend-loading-indicator size="small"></backend-loading-indicator>
                        </div>
                        <form v-show="!modalLoading" ref="menuItemForm" role="form" data-change-monitor>
                            <div :id="menuItemFormContainerId"></div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" :disabled="modalLoading" @click="applyAndClose"><?= e(trans('Apply')) ?></button>
                        <button type="button" class="btn btn-link" @click="closeModal"><?= e(trans('Cancel')) ?></button>
                        <span class="pages-menu-modal-footer-spacer"></span>
                        <button type="button" class="btn btn-link pages-menu-delete-btn" :disabled="modalLoading" @click="deleteSelectedItem"><?= e(trans('Delete')) ?></button>
                    </div>
                </div>
            </div>
        </div>

        <editor-component-editorconflictresolver
            ref="conflictResolver"
        ></editor-component-editorconflictresolver>
    </template>
</backend-document>
