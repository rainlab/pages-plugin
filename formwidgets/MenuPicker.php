<?php namespace RainLab\Pages\FormWidgets;

use Cms\Classes\Theme;
use Backend\Classes\FormField;
use Backend\Classes\FormWidgetBase;
use RainLab\Pages\Classes\Menu;

/**
 * MenuPicker allows the user to pick from available menus
 */
class MenuPicker extends FormWidgetBase
{
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
     * makeFormField as a dropdown listing the theme menus
     */
    protected function makeFormField(): FormField
    {
        $field = clone $this->formField;
        $field->type = 'dropdown';
        $field->options = $this->getOptions();

        return $field;
    }

    /**
     * getOptions for the dropdown
     */
    protected function getOptions(): array
    {
        return Menu::listInTheme(Theme::getEditTheme(), true)
            ->mapWithKeys(function ($menu) {
                return [
                    $menu->code => $menu->name,
                ];
            })->toArray();
    }
}
