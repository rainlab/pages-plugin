/*
 * Handles the Pages main page.
 */
+function ($) { "use strict";
    var PagesPage = function () {
        this.init()
    }

    PagesPage.prototype.init = function() {
        this.$masterTabs = $('#pages-master-tabs')
        this.$sidePanel = $('#pages-side-panel')
        this.$pageTree = $('[data-control=treeview]', this.$sidePanel)
        this.masterTabsObj = this.$masterTabs.data('oc.tab')

        /*
         * Bind event handlers
         */

        // Item is clicked in the sidebar
        $(document).on('open.oc.treeview', 'form.layout[data-content-id=pages]', $.proxy(this.onSidebarItemClick, this))

        $(document).on('open.oc.list', this.$sidePanel, $.proxy(this.onSidebarItemClick, this))

        // A master tab is shown / switched
        this.$masterTabs.on('shown.bs.tab', $.proxy(this.onTabShown, this))

        // All master tabs are closed
        this.$masterTabs.on('afterAllClosed.oc.tab', $.proxy(this.onAllTabsClosed, this))

        // A master tab is closed
        this.$masterTabs.on('closed.oc.tab', $.proxy(this.onTabClosed, this))

        // AJAX errors in the master tabs area
        $(document).on('ajaxError', '#pages-master-tabs form', $.proxy(this.onAjaxError, this))

        // AJAX success in the master tabs area
        $(document).on('ajaxSuccess', '#pages-master-tabs form', $.proxy(this.onAjaxSuccess, this))

        // Create object button click
        $(document).on('click', '#pages-side-panel form [data-control=create-object]', $.proxy(this.onCreateObject, this))

        // Submenu item is clicked in the sidebar
        $(document).on('submenu.oc.treeview', 'form.layout[data-content-id=pages]', $.proxy(this.onSidebarSubmenuItemClick, this))

        // The Delete Object button click
        $(document).on('click', '#pages-side-panel form button[data-control=delete-object]', $.proxy(this.onDeleteObject, this))

        // A new tab is added to the editor
        this.$masterTabs.on('initTab.oc.tab', $.proxy(this.onInitTab, this))
    }

    /*
     * Displays the concurrency resolution form.
     */
    PagesPage.prototype.handleMtimeMismatch = function (form) {
        var $form = $(form)

        $form.popup({ handler: 'onOpenConcurrencyResolveForm' })

        var popup = $form.data('oc.popup'),
            self = this

        $(popup.$target).on('click', 'button[data-action=reload]', function(){
            popup.hide()
            self.reloadForm($form)
        })

        $(popup.$target).on('click', 'button[data-action=save]', function(){
            popup.hide()

            $('input[name=objectForceSave]', $form).val(1)
            $('a[data-request=onSave]', $form).trigger('click')
            $('input[name=objectForceSave]', $form).val(0)
        })
    }

    /*
     * Reloads the Editor form.
     */
    PagesPage.prototype.reloadForm = function($form) {
        var data = {
                type: $('[name=objectType]', $form).val(),
                theme: $('[name=theme]', $form).val(),
                path: $('[name=objectPath]', $form).val(),
            },
            tabId = data.type + '-' + data.theme + '-' + data.path,
            tab = this.masterTabsObj.findByIdentifier(tabId),
            self = this

        /*
         * Update tab
         */

        $.oc.stripeLoadIndicator.show()
        $form.request('onOpen', {
            data: data,
            success: function(data) {
                this.success(data).done(function(){
                    $.oc.stripeLoadIndicator.hide()
                    self.$masterTabs.ocTab('updateTab', tab, data.tabTitle, data.tab)
                    self.$masterTabs.ocTab('unmodifyTab', tab)
                    self.updateModifiedCounter()
                })
            }
        }).always(function(){
            $.oc.stripeLoadIndicator.hide()
        })
    }

    /*
     * Updates the sidebar counter
     */
    PagesPage.prototype.updateModifiedCounter = function() {
        var counters = {
            page: {menu: 'pages', count: 0},
            menu: {menu: 'menus', count: 0}
        }

        $('> div.tab-content > div.tab-pane[data-modified]', this.$masterTabs).each(function(){
            var inputType = $('> form > input[name=objectType]', this).val()
            counters[inputType].count++
        })

        $.each(counters, function(type, data){
            $.oc.sideNav.setCounter('pages/' + data.menu, data.count);
        })
    }

    /*
     * Triggered when a master tab is displayed. Updated the current selection in the sidebar.
     */
    PagesPage.prototype.onTabShown = function(e) {
        if ($(e.target).closest('[data-control=tab]').attr('id') != 'pages-master-tabs')
            return

        var dataId = $(e.target).closest('li').attr('data-tab-id'),
            title = $(e.target).attr('title')

        if (title)
            this.setPageTitle(title)

        this.$pageTree.treeView('markActive', dataId)
    }

    /*
     * Triggered when all master tabs are closed.
     */
    PagesPage.prototype.onAllTabsClosed = function() {
        this.$pageTree.treeView('markActive', null)
        this.setPageTitle('')
    }

    /*
     * Triggered when a master tab is closed.
     */
    PagesPage.prototype.onTabClosed = function() {
        this.updateModifiedCounter()
    }

    /*
     * Handles AJAX errors in the master tab forms. Processes the mtime mismatch condition (concurrency).
     */
    PagesPage.prototype.onAjaxError = function(event, context, data, jqXHR) {
        if (context.handler != 'onSave')
            return

        if (jqXHR.responseText == 'mtime-mismatch') {
            event.preventDefault()
            this.handleMtimeMismatch(event.target)
        }
    }

    /*
     * Handles successful AJAX request in the master tab forms. Updates the UI elements and resets the mtime value.
     */
    PagesPage.prototype.onAjaxSuccess = function(event, context, data) {
        var $form = $(event.currentTarget),
            $tabPane = $form.closest('.tab-pane')

        if (data.objectPath !== undefined) {
            $('input[name=objectPath]', $form).val(data.objectPath)
            $('input[name=objectMtime]', $form).val(data.objectMtime)
            $('[data-control=delete-button]', $form).removeClass('hide')
            $('[data-control=preview-button]', $form).removeClass('hide')

            if (data.pageUrl !== undefined)
                $('[data-control=preview-button]', $form).attr('href', data.pageUrl)
        }

        if (data.tabTitle !== undefined) {
            this.$masterTabs.ocTab('updateTitle', $tabPane, data.tabTitle)
            this.setPageTitle(data.tabTitle)
        }

        var tabId = $('input[name=objectType]', $form).val() + '-'
                    + $('input[name=theme]', $form).val() + '-'
                    + $('input[name=objectPath]', $form).val();

        this.$masterTabs.ocTab('updateIdentifier', $tabPane, tabId)
        this.$pageTree.treeView('markActive', tabId)

        var objectType = $('input[name=objectType]', $form).val()
        if (objectType.length > 0)
           this.updateObjectList(objectType)

        if (context.handler == 'onSave' && (!data['X_OCTOBER_ERROR_FIELDS'] && !data['X_OCTOBER_ERROR_MESSAGE']))
            $form.trigger('unchange.oc.changeMonitor')
    }

    /*
     * Updates the browser title when an object is saved.
     */
    PagesPage.prototype.setPageTitle = function(title) {
        $.oc.layout.setPageTitle(title.length ? (title + ' | ') : title)
    }

    /*
     * Updates the sidebar object list.
     */
    PagesPage.prototype.updateObjectList = function(objectType) {
        var $form = $('form[data-object-type='+objectType+']', this.$sidePanel),
            objectList = objectType + 'List',
            self = this

        $.oc.stripeLoadIndicator.show()
        $form.request(objectList + '::onUpdate', {
            complete: function(data) {
                $('button[data-control=delete-object]', $form).trigger('oc.triggerOn.update')
            }
        }).always(function(){
            $.oc.stripeLoadIndicator.hide()
        })
    }

    /*
     * Closes deleted page tabs in the editor area.
     */
    PagesPage.prototype.closePages = function(data) {
        var self = this

        $.each(data.deletedObjects, function(){
            var tabId = 'page-' + data.theme + '-' + this,
                tab = self.masterTabsObj.findByIdentifier(tabId)

            $(tab).trigger('close.oc.tab', [{force: true}])
        })
    }

    /*
     * Triggered when an item is clicked in the sidebar. Opens the item in the editor.
     * If the item is already opened, activate its tab in the editor.
     */
    PagesPage.prototype.onSidebarItemClick = function(e) {
        var self = this,
            $item = $(e.relatedTarget),
            $form = $item.closest('form'),
            theme = $('input[name=theme]', $form).val(),
            data = {
                type: $form.data('object-type'),
                theme: theme,
                path: $item.data('item-path')
            },
            tabId = data.type + '-' + data.theme + '-' + data.path

        /*
         * Find if the tab is already opened
         */

         if (this.masterTabsObj.goTo(tabId))
            return false

        /*
         * Open a new tab
         */

        $.oc.stripeLoadIndicator.show()
        $form.request('onOpen', {
            data: data,
            success: function(data) {
                this.success(data).done(function(){
                    self.$masterTabs.ocTab('addTab', data.tabTitle, data.tab, tabId, $form.data('type-icon'))
                })
            }
        }).always(function(){
            $.oc.stripeLoadIndicator.hide()
        })

        return false
    }

    /*
     * Triggered when the Add button is clicked on the sidebar
     */
    PagesPage.prototype.onCreateObject = function(e) {
        var self = this,
            $button = $(e.target),
            $form = $button.closest('form'),
            parent = $button.data('parent') !== undefined ? $button.data('parent') : null,
            type = $form.data('object-type'),
            tabId = type + Math.random()

        $.oc.stripeLoadIndicator.show()
        $form.request('onCreateObject', {
            data: {
               type: type,
               parent: parent
            },
            success: function(data) {
                this.success(data).done(function(){
                    self.$masterTabs.ocTab('addTab', data.tabTitle, data.tab, tabId, $form.data('type-icon') + ' new-template')
                    $('#layout-side-panel').trigger('close.oc.sidePanel')
                    self.setPageTitle(data.tabTitle)
                })
           }
        }).always(function(){
            $.oc.stripeLoadIndicator.hide()
        })

        e.stopPropagation()

        return false
    }

    /*
     * Triggered when an item is clicked in the sidebar submenu
     */
    PagesPage.prototype.onSidebarSubmenuItemClick = function(e) {
        if ($(e.clickEvent.target).data('control') == 'create-object')
            this.onCreateObject(e.clickEvent)

        return false
    }

    /*
     * Triggered when the Delete button is clicked on the sidebar
     */
    PagesPage.prototype.onDeleteObject = function(e) {
        var $el = $(e.target),
            $form = $el.closest('form'),
            objectType = $form.data('object-type'),
            self = this

        if (!confirm($el.data('confirmation')))
            return

        $form.request('onDeleteObjects', {
            data: {
                type: objectType
            },
            success: function(data) {
                $.each(data.deleted, function(index, path){
                    var tabId = objectType + '-' + data.theme + '-' + path,
                        tab = self.masterTabsObj.findByIdentifier(tabId)

                    self.$masterTabs.ocTab('closeTab', tab, true)
                })

                if (data.error !== undefined && $.type(data.error) === 'string' && data.error.length)
                    $.oc.flashMsg({text: data.error, 'class': 'error'})
            },
            complete: function() {
                self.updateObjectList(objectType)
            }
        })

        return false
    }

    /*
     * Triggered when a new tab is added to the Editor
     */
    PagesPage.prototype.onInitTab = function(e, data) {
        if ($(e.target).attr('id') != 'pages-master-tabs')
            return

            var $collapseIcon = $('<a href="javascript:;" class="tab-collapse-icon tabless"><i class="icon-chevron-up"></i></a>'),
                $panel = $('.form-tabless-fields', data.pane),
                $secondaryPanel = $('.control-tabs.secondary', data.pane),
                $primaryPanel = $('.control-tabs.primary', data.pane),
                hasSecondaryTabs = $secondaryPanel.length > 0

            $panel.append($collapseIcon);

            if (!hasSecondaryTabs) {
                $primaryPanel.parent().removeClass('min-size')
                $primaryPanel.removeClass('layout-row min-size').addClass('layout')
                $('> div:first-child', $primaryPanel).addClass('layout-row min-size')
                $('.tab-content', $primaryPanel).addClass('layout-row')
                $('.tab-pane', $primaryPanel).addClass('layout-cell full-size')
            }

            $collapseIcon.click(function(){
                $panel.toggleClass('collapsed')

                if (typeof(localStorage) !== 'undefined')
                    localStorage.ocPagesTablessCollapsed = $panel.hasClass('collapsed') ? 1 : 0

                window.setTimeout(function(){
                    $(window).trigger('oc.updateUi')
                }, 500)

                return false
            })

            var $primaryCollapseIcon = $('<a href="javascript:;" class="tab-collapse-icon primary"><i class="icon-chevron-down"></i></a>'),
                $primaryTabContainer = $('.nav-tabs', $primaryPanel)

            $primaryTabContainer.addClass('master-area')

            if ($primaryPanel.length > 0) {
                $secondaryPanel.append($primaryCollapseIcon);

                $primaryCollapseIcon.click(function(){
                    $primaryPanel.toggleClass('collapsed')
                    $secondaryPanel.toggleClass('primary-collapsed')
                    $(window).trigger('oc.updateUi')
                    if (typeof(localStorage) !== 'undefined')
                        localStorage.ocPagesPrimaryCollapsed = $primaryPanel.hasClass('collapsed') ? 1 : 0
                    return false
                })
            }

            if (typeof(localStorage) !== 'undefined') {
                if (!$('a', data.tab).hasClass('new-template') && localStorage.ocPagesTablessCollapsed == 1)
                    $panel.addClass('collapsed')

                if (localStorage.ocPagesPrimaryCollapsed == 1 && hasSecondaryTabs) {
                    $primaryPanel.addClass('collapsed')
                    $secondaryPanel.addClass('primary-collapsed')
                }
            }

        var $form = $('form', data.pane),
            self = this,
            $panel = $('.form-tabless-fields', data.pane)

        $form.on('changed.oc.changeMonitor', function() {
            $panel.trigger('modified.oc.tab')
            self.updateModifiedCounter()
        })

        $form.on('unchanged.oc.changeMonitor', function() {
            $panel.trigger('unmodified.oc.tab')
            self.updateModifiedCounter()
        })
    }

    $(document).ready(function(){
        $.oc.pagesPage = new PagesPage()
    })

}(window.jQuery);