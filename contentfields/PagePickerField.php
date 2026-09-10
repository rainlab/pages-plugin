<?php namespace RainLab\Pages\ContentFields;

use Tailor\Classes\ContentFieldBase;
use October\Contracts\Element\FormElement;
use October\Contracts\Element\ListElement;
use October\Contracts\Element\FilterElement;

/**
 * PagePickerField content field stores a static page reference selected
 * using the staticpagepicker form widget.
 *
 * @link https://docs.octobercms.com/3.x/extend/tailor-fields.html
 */
class PagePickerField extends ContentFieldBase
{
    /**
     * defineFormField will define how a field is displayed in a form.
     */
    public function defineFormField(FormElement $form, $context = null)
    {
        $form->addFormField($this->fieldName, $this->label)
            ->useConfig($this->config)
            ->displayAs('staticpagepicker')
        ;
    }

    /**
     * defineListColumn will define how a field is displayed in a list.
     */
    public function defineListColumn(ListElement $list, $context = null)
    {
        $list->defineColumn($this->fieldName, $this->label)
            ->shortLabel($this->shortLabel)
            ->useConfig($this->column ?: [])
        ;
    }

    /**
     * defineFilterScope will define how a field is displayed in a filter.
     */
    public function defineFilterScope(FilterElement $filter, $context = null)
    {
        if (is_array($this->scope)) {
            $filter->defineScope($this->fieldName, $this->label)
                ->shortLabel($this->shortLabel)
                ->useConfig($this->scope)
            ;
        }
    }

    /**
     * extendDatabaseTable adds any required columns to the database.
     */
    public function extendDatabaseTable($table)
    {
        $table->text($this->fieldName)->nullable();
    }
}
