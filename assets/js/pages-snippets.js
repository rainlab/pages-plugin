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

        $(this.$masterTabs).on('enter.oc.richeditor', '.redactor-box textarea', function(ev, originalEv, $editor, $textarea){
            self.editorKeyDown(ev, originalEv, $editor, $textarea)
        })

        $(document).on('click', '[data-snippet]', function(){
            $(this).inspector()
            return false
        })
    }

    SnippetManager.prototype.onSidebarSnippetClick = function($sidebarItem) {
        var $pageForm = $('div.tab-content > .tab-pane.active form[data-object-type=page]', this.$masterTabs)

        if (!$pageForm.length) {
            alert('Snippets can only be added to Pages. Please open or create a Page first.')

            return
        }

        var $activeEditorTab = $('.control-tabs.secondary .tab-pane.active', $pageForm),
            $textarea = $activeEditorTab.find('[data-control="richeditor"] textarea'),
            $snippetNode = $('<figure contenteditable="false" data-inspector-css-class="hero" />'),
            componentClass = $sidebarItem.attr('data-component-class'),
            snippetCode = $sidebarItem.data('snippet')

        if (!$textarea.length) {
            alert('Snippets can only to page Content or HTML placeholders.')

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
            'tabindex': '0'
        })

        $snippetNode.get(0).contentEditable = false

        var redactor = $textarea.redactor('core.getObject'),
            current = redactor.selection.getCurrent(),
            inserted = false

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

        if (current !== false) {
            // If snippet is inserted into a paragraph, insert it after the paragraph.
            var $paragraph = $(current).closest('p')
            if ($paragraph.length > 0) {
                redactor.caret.setAfter($paragraph.get(0))

                // If the paragraph is empty, remove it.
                if ($.trim($paragraph.text()).length == 0)
                    $paragraph.remove()
            } else {
                // If snippet is inserted into another snippet, insert it after the snippet.
                var $closestSnippet = $(current).closest('[data-snippet]')
                if ($closestSnippet.length > 0) {
                    $snippetNode.insertBefore($closestSnippet.get(0))
                    inserted = true
                }
            }
        }

        if (!inserted)
            redactor.insert.node($snippetNode)

        $snippetNode.focus()

        redactor.code.sync();
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
                'data-inspector-css-class': 'hero'
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

            $snippet.removeAttr('contenteditable data-name tabindex data-inspector-css-class data-inspector-class data-property-inspectorclassname data-property-inspectorproperty')

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

            originalEv.preventDefault()
            return
        }

        switch (originalEv.which) {
            case 38:
                // Up arrow
                var block = redactor.selection.getBlock()
                if (block)
                    this.handleSnippetCaretIn($(block).prev(), redactor)
            break
            case 40:
                // Down arrow
                var block = redactor.selection.getBlock()
                if (block)
                    this.handleSnippetCaretIn($(block).next(), redactor)
            break
        }
    }

    SnippetManager.prototype.handleSnippetCaretIn = function($block, redactor) {
        if ($block.attr('data-snippet') !== undefined) {
            $block.focus()
            redactor.selection.remove()

            return true
        }

        return false
    }

    SnippetManager.prototype.focusSnippetOrText = function(redactor, $block, gotoStart) {
        if ($block.length > 0) {
            if (!this.handleSnippetCaretIn($block, redactor)) {
                if (gotoStart)
                    redactor.caret.setStart($block.get(0))
                else
                    redactor.caret.setEnd($block.get(0))
            }
        }
    }

    SnippetManager.prototype.snippetKeyDown = function(ev, snippet) {
        if (ev.which == 40 || ev.which == 38 || ev.which == 13 || ev.which == 8 || ev.which == 32) {
            var $textarea = $(snippet).closest('.redactor-box').find('textarea'),
                redactor = $textarea.redactor('core.getObject')

            switch (ev.which) {
                case 40:
                    // Down arrow
                    this.focusSnippetOrText(redactor, $(snippet).next(), true)
                break
                case 38:
                    // Up arrow
                    this.focusSnippetOrText(redactor, $(snippet).prev(), false)
                break
                case 13:
                    // Enter key
                    var $paragraph = $('<p><br/></p>')
                    $paragraph.insertAfter(snippet)
                    redactor.caret.setStart($paragraph.get(0))
                break
                case 8:
                    // Backspace key
                    var $nextFocus = $(snippet).next(),
                        gotoStart = true

                    if ($nextFocus.length == 0) {
                        $nextFocus = $(snippet).prev()
                        gotoStart = false
                    }

                    this.focusSnippetOrText(redactor, $nextFocus, gotoStart)

                    $(snippet).remove()
                break
                case 32: 
                    // Space key
                    $(snippet).inspector()
                break
            }
        }
    }

    $.oc.pages.snippetManager = SnippetManager
}(window.jQuery);