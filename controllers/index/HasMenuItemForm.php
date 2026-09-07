<?php namespace RainLab\Pages\Controllers\Index;

use RainLab\Pages\Classes\MenuItem;
use RainLab\Pages\FormWidgets\MenuItemSearch;

/**
 * HasMenuItemForm hosts the legacy per-menu-item Form widget island and its helpers.
 *
 * The per-item form is a real Backend\Widgets\Form bound to a MenuItem model so that
 * backend.form.extendFields keeps firing for third-party field extensions.
 */
trait HasMenuItemForm
{
    /**
     * @var \Backend\Widgets\Form menuItemFormWidget cache for the current request.
     */
    protected $menuItemFormWidget;

    /**
     * makeMenuItemFormWidget builds the per-item Form widget bound to a MenuItem model.
     */
    protected function makeMenuItemFormWidget()
    {
        if ($this->menuItemFormWidget !== null) {
            return $this->menuItemFormWidget;
        }

        $menuItem = new MenuItem;

        $config = $this->makeConfig('~/plugins/rainlab/pages/classes/menuitem/fields.yaml');
        $config->model = $menuItem;
        $config->alias = $this->getMenuItemFormAlias();
        $config->arrayName = 'menuItem';

        $widget = $this->makeWidget(\Backend\Widgets\Form::class, $config);
        $widget->bindToController();

        return $this->menuItemFormWidget = $widget;
    }

    /**
     * getMenuItemFormAlias returns the client-supplied form alias. Each open menu
     * document uses its own alias so the generated field ids are unique, keeping
     * checkbox labels bound to their own document's inputs.
     */
    protected function getMenuItemFormAlias(): string
    {
        $alias = preg_replace('/[^a-zA-Z0-9]/', '', (string) post('formAlias'));

        return strlen($alias) ? $alias : 'menuItemForm';
    }

    /**
     * onLoadMenuItemForm renders the per-item Form widget over AJAX.
     */
    public function onLoadMenuItemForm()
    {
        $widget = $this->makeMenuItemFormWidget();

        $containerId = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) post('containerId'));
        if (!strlen($containerId)) {
            $containerId = 'pagesMenuItemForm';
        }

        return [
            '#'.$containerId => $widget->render(['useContainer' => false])
        ];
    }

    /**
     * onGetMenuItemTypeInfo returns type info (references, cmsPages, nesting) for a menu item type.
     */
    public function onGetMenuItemTypeInfo()
    {
        $type = trim((string) post('type'));

        return [
            'menuItemTypeInfo' => MenuItem::getTypeInfo($type)
        ];
    }

    /**
     * onMenuItemReferenceSearch returns matching references for the reference search field.
     */
    public function onMenuItemReferenceSearch()
    {
        $alias = trim((string) post('alias'));

        $formField = new \Backend\Classes\FormField([
            'fieldName' => 'referenceSearch',
            'arrayName' => 'menuItem'
        ]);

        $widget = new MenuItemSearch($this, $formField, ['alias' => $alias]);

        return $widget->onSearch();
    }
}
