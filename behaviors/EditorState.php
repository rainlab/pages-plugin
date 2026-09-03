<?php namespace RainLab\Pages\Behaviors;

use Editor\Behaviors\StateManager;
use RainLab\Pages\Classes\EditorExtension;

/**
 * EditorState builds the Editor initial state scoped to the Pages editor context.
 *
 * Extends the core StateManager and only overrides the editor context, so the standalone
 * Pages backend page shows only extensions registered in the 'pages' context - not every
 * registered extension - while reusing all of the core state-building logic.
 */
class EditorState extends StateManager
{
    /**
     * getEditorContext scopes this host to the Pages editor context.
     */
    protected function getEditorContext(): string
    {
        return EditorExtension::CONTEXT;
    }
}
