/*
 * Handles snippet operations on the Pages main page
 */
+function ($) { "use strict";
    if ($.oc.pages === undefined)
        $.oc.pages = {}

    var SnippetManager = function ($masterTabs) {
        this.$masterTabs = $masterTabs

        var self = this

        $(document).on('hidden.oc.inspector', '.redactor-box [data-snippet]', function(){
            self.syncEditorCode(this)
        })

        $(document).on('init.oc.richeditor', '.redactor-box textarea', function(ev, $editor){
            self.initSnippets($editor)
        })

        $(document).on('syncBefore.oc.richeditor', '.redactor-box textarea', function(ev, container){
            self.syncPageMarkup(ev, container)
        })

        $(document).on('keydown.oc.richeditor', '.redactor-box textarea', function(ev, originalEv, $editor, $textarea){
            self.editorKeyDown(ev, originalEv, $editor, $textarea)
        })

        $(document).on('click', '[data-snippet]', function(){
            $.oc.inspector.manager.createInspector(this)
            return false
        })
    }

    SnippetManager.prototype.onSidebarSnippetClick = function($sidebarItem) {
        var $pageForm = $('div.tab-content > .tab-pane.active form[data-object-type=page]', this.$masterTabs)

        if (!$pageForm.length) {
            alert('Snippets can only be added to Pages. Please open or create a Page first.')

            return
        }

        var $activeEditorTab = $('.control-tabs.secondary-tabs .tab-pane.active', $pageForm),
            $textarea = $activeEditorTab.find('[data-control="richeditor"] textarea'),
            $richeditorNode = $textarea.closest('[data-control="richeditor"]'),
            $snippetNode = $('<figure contenteditable="false" data-inspector-css-class="hero" />'),
            componentClass = $sidebarItem.attr('data-component-class'),
            snippetCode = $sidebarItem.data('snippet')

        if (!$textarea.length) {
            alert('Snippets can only be added to page Content or HTML placeholders.')

            return
        }

        if (componentClass) {
            $snippetNode.attr({
                'data-component': componentClass,
                'data-inspector-class': componentClass
            })

            // If a component-based snippet was added, make sure that
            // its code is unique, as it will be used as a component 
            // alias.

            snippetCode = this.generateUniqueComponentSnippetCode(componentClass, snippetCode, $pageForm)
        }

        $snippetNode.attr({
            'data-snippet': snippetCode,
            'data-name': $sidebarItem.data('snippet-name'),
            'tabindex': '0',
            'data-ui-block': 'true'
        })

        $snippetNode.get(0).contentEditable = false

        var redactor = $textarea.redactor('core.getObject'),
            current = redactor.selection.getCurrent()

        if (current === false)
            redactor.focus.setStart()

        current = redactor.selection.getCurrent()

        if (current !== false) {
            // If the current element doesn't belog to the redactor on the active tab,
            // exit. A better solution would be inserting the snippet to the top of the
            // text, but it looks like there's a bug in Redactor - although the redactor 
            // object ponts to a correct editor on the page, calling redactor.insert.node 
            // inserts the snippet to an editor on another tab.
            var $currentParent = $(current).parent()
            if ($currentParent.length && !$.contains($activeEditorTab.get(0), $currentParent.get(0))) 
                return
        }

        $richeditorNode.richEditor('insertUiBlock', $snippetNode)
    }

    SnippetManager.prototype.generateUniqueComponentSnippetCode = function(componentClass, originalCode, $pageForm) {
        var updatedCode = originalCode,
            counter = 1,
            snippetFound = false


        do {
            snippetFound = false

            $('[data-control="richeditor"] textarea', $pageForm).each(function(){
                var $textarea = $(this),
                    $codeDom = $('<div>' + $textarea.redactor('code.get') + '</div>')

                if ($codeDom.find('[data-snippet="'+updatedCode+'"][data-component]').length > 0) {
                    snippetFound = true
                    updatedCode = originalCode + counter
                    counter++

                    return false
                }
            })

        } while (snippetFound)

        return updatedCode
    }

    SnippetManager.prototype.syncEditorCode = function(inspectable) {
        var $textarea = $(inspectable).closest('[data-control=richeditor]').find('textarea')

        $textarea.redactor('code.sync')
        inspectable.focus()
    }

    SnippetManager.prototype.initSnippets = function($editor) {
        var snippetCodes = []

        $('.redactor-editor [data-snippet]', $editor).each(function(){
            var $snippet = $(this),
                snippetCode = $snippet.attr('data-snippet'),
                componentClass = $snippet.attr('data-component')

            if (componentClass)
                snippetCode += '|' + componentClass

            snippetCodes.push(snippetCode)

            $snippet.addClass('loading')
            $snippet.attr({
                'data-name': 'Loading...',
                'tabindex': '0',
                'data-inspector-css-class': 'hero',
                'data-ui-block': true
            })

            if (componentClass)
                $snippet.attr('data-inspector-class', componentClass)

            this.contentEditable = false
        })

        if (snippetCodes.length > 0) {
            var request = $editor.request('onGetSnippetNames', {
                data: {
                    codes: snippetCodes
                }
            }).done(function(data) {
                if (data.names !== undefined) {
                    $.each(data.names, function(code){
                        $('[data-snippet="'+code+'"]', $editor)
                            .attr('data-name', this)
                            .removeClass('loading')
                    })
                }
            })
        }
    }

    SnippetManager.prototype.syncPageMarkup = function(ev, container) {
        var $domTree = $('<div>'+container.html+'</div>')

        $('[data-snippet]', $domTree).each(function(){
            var $snippet = $(this)

            $snippet.removeAttr('contenteditable data-name tabindex data-inspector-css-class data-inspector-class data-property-inspectorclassname data-property-inspectorproperty data-ui-block')

            if (!$snippet.attr('class'))
                $snippet.removeAttr('class')
        })

        container.html = $domTree.html()
    }

    SnippetManager.prototype.editorKeyDown = function(ev, originalEv, $editor, $textarea) {
        if ($textarea === undefined)
            return

        var redactor = $textarea.redactor('core.getObject')

        if (originalEv.target && $(originalEv.target).attr('data-snippet') !== undefined) {
            this.snippetKeyDown(originalEv, originalEv.target)

            return
        }
    }

    SnippetManager.prototype.snippetKeyDown = function(ev, snippet) {
        if (ev.which == 32) {
            var $textarea = $(snippet).closest('.redactor-box').find('textarea'),
                redactor = $textarea.redactor('core.getObject')

            switch (ev.which) {
                case 32: 
                    // Space key
                    $.oc.inspector.manager.createInspector(snippet)
                break
            }
        }
    }

    $.oc.pages.snippetManager = SnippetManager
}(window.jQuery);
