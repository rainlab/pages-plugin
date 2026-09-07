<?php namespace RainLab\Pages\FormWidgets;

use Cms\Classes\Theme;
use Backend\Classes\FormField;
use Backend\Classes\FormWidgetBase;
use RainLab\Pages\Classes\Page;

/**
 * PagePicker allows the user to pick from available static pages
 */
class PagePicker extends FormWidgetBase
{
    /**
     * @var string indent for nested pages
     */
    protected $indent = '&nbsp;&nbsp;&nbsp;';

    /**
     * @inheritDoc
     */
    public function render()
    {
        $this->prepareVars();

        return $this->makePartial('~/modules/backend/widgets/form/partials/_field_dropdown.php');
    }

    /**
     * prepareVars for display
     */
    public function prepareVars()
    {
        $this->vars['field'] = $this->makeFormField();
    }

    /**
     * makeFormField as a dropdown listing the page hierarchy
     */
    protected function makeFormField(): FormField
    {
        $field = clone $this->formField;
        $field->type = 'dropdown';

        $tree = Page::buildMenuTree(Theme::getEditTheme());
        $indent = $field->getConfig('indent', $this->indent);

        $options = [];
        $iterator = function($items, $depth = 0) use (&$iterator, &$tree, &$options, $indent) {
            foreach ($items as $code) {
                $itemData = $tree[$code];
                $options[$code] = str_repeat($indent, $depth) . $itemData['title'];
                if (!empty($itemData['items'])) {
                    $iterator($itemData['items'], $depth + 1);
                }
            }

            return $options;
        };

        $field->options = $iterator($tree['--root-pages--']);

        return $field;
    }
}
