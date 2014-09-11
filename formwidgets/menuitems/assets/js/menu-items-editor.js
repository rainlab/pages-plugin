/*
 * The menu item editor. Provides tools for managing the 
 * menu items.
 */
+function ($) { "use strict";
    var MenuItemsEditor = function (el, options) {
        this.$el = $(el)
        this.options = options

        this.init()
    }

    MenuItemsEditor.prototype.init = function() {
        // Menu items is clicked
        this.$el.on('open.oc.treeview', $.proxy(this.onItemClick, this))

        // Submenu item is clicked in the master tabs
        this.$el.on('submenu.oc.treeview', $.proxy(this.onSubmenuItemClick, this))
    }

    /*
     * Triggered when a submenu item is clicked in the menu editor.
     */
    MenuItemsEditor.prototype.onSubmenuItemClick = function(e) {
        if ($(e.relatedTarget).data('control') == 'delete-menu-item')
            this.onDeleteMenuItem(e.relatedTarget)

        return false
    }

    /*
     * Removes a menu item
     */
    MenuItemsEditor.prototype.onDeleteMenuItem = function(link) {
        if (!confirm('Do you really want to delete the menu item? This will also delete the subitems, if any.'))
            return

        $(link).closest('li[data-menu-item]').remove()

        $(window).trigger('oc.updateUi')
    }

    /*
     * Opens the menu item editor
     */
    MenuItemsEditor.prototype.onItemClick = function(e) {
        var $item = $(e.relatedTarget),
            $container = $('> div', $item),
            self = this

        $container.on('show.oc.popover', function(e){
            $(document).trigger('render')

            self.$popupContainer = $(e.relatedTarget);
            self.loadProperties(self.$popupContainer, $container.closest('li').data('menu-item'))

            $('input[name=title]', self.$popupContainer).focus()

            $('select[name=type]', self.$popupContainer).change($.proxy(self.updateReferenceControls, self))
        })

        $container.ocPopover({
            content: $('script[data-editor-template]', this.$el).html(),
            placement: 'center',
            modal: true,
            closeOnPageClick: true,
            highlightModalTarget: true,
            width: 600
        })

        return false
    }

    MenuItemsEditor.prototype.loadProperties = function($popupContainer, properties) {
        $.each(properties, function(property) {
            var $input = $('[name="'+property+'"]', $popupContainer)

            if ($input.prop('type') !== 'checkbox' ) {
                $input.val(this)
                $input.change()
            } else
                $input.prop('checked', this)
        })

        this.updateReferenceControls()
    }

    MenuItemsEditor.prototype.updateReferenceControls = function() {
        var type = $('select[name=type]', this.$popupContainer).val()

        if (type == 'url') {
            $('div[data-field-name="reference"]', this.$popupContainer).hide()
            $('div[data-field-name="url"]', this.$popupContainer).show()
        } else {
            $('div[data-field-name="reference"]', this.$popupContainer).show()
            $('div[data-field-name="url"]', this.$popupContainer).hide()
        }
    }

    MenuItemsEditor.DEFAULTS = {
    }

    // MENUITEMSEDITOR PLUGIN DEFINITION
    // ============================

    var old = $.fn.menuItemsEditor

    $.fn.menuItemsEditor = function (option) {
        var args = Array.prototype.slice.call(arguments, 1)
        return this.each(function () {
            var $this   = $(this)
            var data    = $this.data('oc.menuitemseditor')
            var options = $.extend({}, MenuItemsEditor.DEFAULTS, $this.data(), typeof option == 'object' && option)
            if (!data) $this.data('oc.menuitemseditor', (data = new MenuItemsEditor(this, options)))
            else if (typeof option == 'string') data[option].apply(data, args)
        })
    }

    $.fn.menuItemsEditor.Constructor = MenuItemsEditor

    // MENUITEMSEDITOR NO CONFLICT
    // =================

    $.fn.menuItemsEditor.noConflict = function () {
        $.fn.menuItemsEditor = old
        return this
    }

    // MENUITEMSEDITOR DATA-API
    // ===============

    $(document).on('render', function() {
        $('[data-control="menu-item-editor"]').menuItemsEditor()
    });
}(window.jQuery);