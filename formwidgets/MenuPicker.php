<?php namespace RainLab\Pages\FormWidgets;

use Backend\Classes\FormWidgetBase;
use Cms\Classes\Theme;
use RainLab\Pages\Classes\Menu;

/**
 * Static Menu picker widget
 *
 * @package RainLab\Pages\FormWidgets
 */
class MenuPicker extends FormWidgetBase
{
    /**
     * @inheritDoc
     */
    public function render()
    {
        $this->prepareVars();

        return $this->makePartial('~/modules/backend/widgets/form/partials/_field_dropdown.htm');
    }

    /**
     * Prepares the view data
     */
    public function prepareVars()
    {
        $this->vars['field'] = $this->makeFormField();
    }

    protected function makeFormField(): \Backend\Classes\FormField
    {
        $field = clone $this->formField;
        $field->type = 'dropdown';
        $field->options = $this->getOptions();

        return $field;
    }

    protected function getOptions(): array
    {
        return Menu::listInTheme(Theme::getEditTheme(), true)
            ->mapWithKeys(function ($menu) {
                return [
                    $menu->fileName => $menu->name,
                ];
            })->toArray();
    }
}
