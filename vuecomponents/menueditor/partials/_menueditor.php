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
            <ul style="list-style:none; margin:0; padding:0; max-width:720px;">
                <li
                    v-for="entry in flatItems"
                    :key="entry.item._id"
                    class="menu-item-row"
                    :class="{
                        selected: entry.item._selected,
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
                        <span class="menu-item-title">{{ entry.item.title || '<?= e(trans('Untitled')) ?>' }}</span>
                        <span class="menu-item-subtitle">{{ itemSubtitle(entry.item) }}</span>
                    </span>
                    <span class="menu-item-actions" @click.stop>
                        <button type="button" title="<?= e(trans('Move up')) ?>" @click="moveItemUp(entry)" class="menu-item-action"><i class="icon-arrow-up"></i></button>
                        <button type="button" title="<?= e(trans('Move down')) ?>" @click="moveItemDown(entry)" class="menu-item-action"><i class="icon-arrow-down"></i></button>
                        <button type="button" title="<?= e(trans('Indent')) ?>" @click="indentItem(entry)" class="menu-item-action"><i class="icon-arrow-right"></i></button>
                        <button type="button" title="<?= e(trans('Outdent')) ?>" @click="outdentItem(entry)" class="menu-item-action"><i class="icon-arrow-left"></i></button>
                        <button type="button" title="<?= e(trans('Delete')) ?>" @click="deleteItem(entry.item, entry.siblings)" class="menu-item-action menu-item-action-danger"><i class="icon-times"></i></button>
                    </span>
                </li>
            </ul>

            <p v-if="!flatItems.length" style="color:#97a1ab; padding:10px 0;">
                <?= e(trans('No menu items yet. Use "Add item" in the toolbar.')) ?>
            </p>
        </div>

        <!-- Edit Menu Item modal (hosts the legacy Form widget island). -->
        <div
            v-show="modalVisible"
            class="pages-menu-modal-overlay"
            style="position:fixed; inset:0; background:rgba(0,0,0,.4); z-index:1050; display:flex; align-items:flex-start; justify-content:center; padding-top:60px;"
            @click.self="closeModal"
        >
            <div class="pages-menu-modal" style="background:var(--oc-panel-bg,#fff); border-radius:6px; width:640px; max-width:92%; max-height:80vh; display:flex; flex-direction:column; box-shadow:0 10px 40px rgba(0,0,0,.3);">
                <div class="pages-menu-modal-header" style="display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid rgba(0,0,0,.08);">
                    <h4 style="margin:0;"><?= e(trans('Edit Menu Item')) ?></h4>
                    <button type="button" class="btn btn-default btn-sm" @click="closeModal"><i class="icon-times"></i></button>
                </div>
                <div class="pages-menu-modal-body" style="padding:20px; overflow:auto; flex:1 1 auto;">
                    <form ref="menuItemForm" role="form" data-change-monitor>
                        <div id="pagesMenuItemForm"></div>
                    </form>
                </div>
                <div class="pages-menu-modal-footer" style="padding:12px 20px; border-top:1px solid rgba(0,0,0,.08); text-align:right;">
                    <button type="button" class="btn btn-default" @click="closeModal"><?= e(trans('Cancel')) ?></button>
                    <button type="button" class="btn btn-primary" @click="applyAndClose"><?= e(trans('Apply')) ?></button>
                </div>
            </div>
        </div>

        <editor-component-editorconflictresolver
            ref="conflictResolver"
        ></editor-component-editorconflictresolver>
    </template>
</backend-document>
