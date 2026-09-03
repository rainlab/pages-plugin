<?php namespace RainLab\Pages\Controllers\Index;

use Cms\Classes\Theme;
use RainLab\Pages\Classes\Page as StaticPage;

/**
 * HasSyntaxFields hosts a legacy Form widget island for a page's layout syntax fields.
 *
 * Syntax fields (repeater, mediafinder, custom widgets) have no Vue equivalent, so they are
 * rendered by a real Backend\Widgets\Form embedded in the Vue editor via AJAX.
 */
trait HasSyntaxFields
{
    /**
     * @var \Backend\Widgets\Form syntaxFieldsWidget cache for the current request.
     */
    protected $syntaxFieldsWidget;

    /**
     * makeSyntaxFieldsWidget builds the Form widget for a page's layout syntax fields.
     *
     * When $tab is given only the fields assigned to that tab are included, so each editor
     * tab hosts its own island (matching the original plugin's per-tab syntax fields).
     */
    protected function makeSyntaxFieldsWidget($path, $tab = null)
    {
        if ($this->syntaxFieldsWidget !== null) {
            return $this->syntaxFieldsWidget;
        }

        $theme = Theme::getEditTheme();
        $page = StaticPage::load($theme, $path);
        if (!$page) {
            return null;
        }

        $config = $this->makeConfig(['fields' => []]);
        $config->model = $page;
        $config->alias = 'pagesSyntaxForm';
        $config->arrayName = 'syntaxFields';
        $config->context = $page->exists ? 'update' : 'create';

        $widget = $this->makeWidget(\Backend\Widgets\Form::class, $config);

        $widget->bindEvent('form.extendFieldsBefore', function () use ($widget, $page, $tab) {
            $this->addPageSyntaxFields($widget, $page, $tab);
        });

        $widget->bindToController();

        return $this->syntaxFieldsWidget = $widget;
    }

    /**
     * addPageSyntaxFields injects the layout syntax fields into the form widget.
     *
     * When $tab is provided, only fields whose config tab matches are added (fields without
     * a tab belong to the "Fields" group).
     */
    protected function addPageSyntaxFields($formWidget, $page, $tab = null)
    {
        $fields = $page->listLayoutSyntaxFields();

        foreach ($fields as $fieldCode => $fieldConfig) {
            if ($fieldConfig['type'] === 'fileupload') {
                continue;
            }

            if ($tab !== null) {
                $fieldTab = trim((string) ($fieldConfig['tab'] ?? '')) ?: __("Fields");
                if ($fieldTab !== $tab) {
                    continue;
                }
            }

            if (in_array($fieldConfig['type'], ['repeater', 'nestedform'])) {
                if (empty($fieldConfig['form']) || !is_string($fieldConfig['form'])) {
                    $repeaterFields = array_get($fieldConfig, 'fields', []);
                    $fieldConfig['form']['fields'] = $repeaterFields;
                    unset($fieldConfig['fields']);
                }
            }

            // Drop the tab hint so the island doesn't render its own tab strip; the editor
            // provides the tab.
            unset($fieldConfig['tab']);

            $formWidget->addFields(['viewBag[' . $fieldCode . ']' => $fieldConfig]);
        }
    }

    /**
     * bindSyntaxFieldsWidget rebuilds the widget on any request carrying a page path.
     *
     * Nested widgets (repeater, mediafinder) fire their own AJAX handlers, which require the
     * parent form to be re-bound to the controller on every request, not only on initial load.
     */
    public function bindSyntaxFieldsWidget()
    {
        $path = trim((string) post('pagesSyntaxPath'));
        if (strlen($path)) {
            $tab = post('pagesSyntaxTab');
            $this->makeSyntaxFieldsWidget($path, is_string($tab) && strlen($tab) ? $tab : null);
        }
    }

    /**
     * onLoadSyntaxFields renders one tab group of syntax fields for a page over AJAX.
     */
    public function onLoadSyntaxFields()
    {
        $path = trim((string) post('path'));
        $tab = post('tab');
        $tab = is_string($tab) && strlen($tab) ? $tab : null;

        $widget = $this->makeSyntaxFieldsWidget($path, $tab);

        $containerId = trim((string) post('containerId')) ?: 'pagesSyntaxFieldsForm';

        if (!$widget) {
            return ['#'.$containerId => ''];
        }

        // Hidden fields let the per-request rebind (bindSyntaxFieldsWidget) rebuild the exact
        // same widget so nested repeater/mediafinder AJAX handlers resolve.
        $hidden = '<input type="hidden" name="pagesSyntaxPath" value="'.e($path).'" />'
            .'<input type="hidden" name="pagesSyntaxTab" value="'.e((string) $tab).'" />';

        return [
            '#'.$containerId => $hidden.$widget->render(['useContainer' => false])
        ];
    }
}
