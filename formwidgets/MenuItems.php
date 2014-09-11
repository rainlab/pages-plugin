<?php namespace RainLab\Pages\FormWidgets;

use Backend\Classes\FormWidgetBase;
use RainLab\Pages\Classes\MenuItem;

/**
 * Menu items widget.
 *
 * @package october\backend
 * @author Alexey Bobkov, Samuel Georges
 */
class MenuItems extends FormWidgetBase
{
    /**
     * {@inheritDoc}
     */
    public $defaultAlias = 'menuitems';

    public $addSubitemLabel = 'rainlab.pages::lang.menu.add_subitem';

    public $noRecordsMessage = 'rainlab.pages::lang.menu.no_records';

    /**
     * {@inheritDoc}
     */
    public function init()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function render()
    {
        $this->prepareVars();
        return $this->makePartial('menuitems');
    }

    /**
     * Prepares the list data
     */
    public function prepareVars()
    {
        $this->vars['items'] = $this->model->items;

        $formConfigs = [
            'page' => '@/plugins/rainlab/pages/classes/page/fields.yaml',
            'menu' => '@/plugins/rainlab/pages/classes/menu/fields.yaml',
        ];

        $widgetConfig = $this->makeConfig('@/plugins/rainlab/pages/classes/menuitem/fields.yaml');
        $widgetConfig->model = new MenuItem();
        $widgetConfig->alias = uniqid();

        $this->vars['itemFormWidget'] = $this->makeWidget('Backend\Widgets\Form', $widgetConfig);
    }

    /**
     * {@inheritDoc}
     */
    public function loadAssets()
    {
        $this->addJs('js/menu-items-editor.js', 'core');
    }

    /**
     * {@inheritDoc}
     */
    public function getSaveData($value)
    {
        return strlen($value) ? $value : null;
    }
}