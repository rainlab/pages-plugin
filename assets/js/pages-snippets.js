/*
 * Handles snippet operations on the Pages main page
 */
+function ($) { "use strict";
    if ($.oc.pages === undefined)
        $.oc.pages = {}

    var SnippetManager = function ($masterTabs) {
        this.$masterTabs = $masterTabs
    }

    SnippetManager.prototype.onSidebarSnippetClick = function($sidebarItem) {
        var $pageForm = $('div.tab-content > .tab-pane.active form[data-object-type=page]', this.$masterTabs)

        if (!$pageForm.length) {
            alert('Snippets can only be added to Pages. Please open or create a Page first.')

            return
        }

        // Find the content editor field

        var $textarea = $('[data-field-name="markup"] [data-control="richeditor"] textarea', $pageForm),
            $snippetNode = $('<div data-inspectable data-snippet data-inspector-values />')

        $snippetNode.attr('data-snipet', $sidebarItem.data('snippet'))
        $snippetNode.attr('data-name', $sidebarItem.data('snippet-name'))
        $snippetNode.attr('data-inspector-title', $sidebarItem.data('snippet-name'))
        $snippetNode.attr('data-inspector-description', $sidebarItem.data('description'))
        $snippetNode.attr('data-inspector-config', $sidebarItem.attr('data-properties'))

        var redactor = $textarea.redactor('core.getObject'),
            current = redactor.selection.getCurrent()

        if (current === false)
            redactor.focus.setStart()

        if (current !== false && typeof current !== 'string') {
            var $current = $(current)

            if ($current.attr("data-snippet") !== undefined) {
                // Don't allow inserting snippets into snippets

                alert('Snippets cannot be inserted into other snippets.')
                return
            }
        }

        redactor.insert.node($snippetNode)
    }

    $.oc.pages.snippetManager = SnippetManager
}(window.jQuery);