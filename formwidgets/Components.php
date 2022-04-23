<?php namespace RainLab\Pages\FormWidgets;

use Backend\Classes\FormWidgetBase;
use Cms\Classes\ComponentManager;
use Cms\Classes\ComponentHelpers;
use Cms\Components\UnknownComponent;
use Exception;

/**
 * Components builds a collection of Cms components and configures them
 *
 * @package october\cms
 * @author Alexey Bobkov, Samuel Georges
 */
class Components extends FormWidgetBase
{
    /**
     * @inheritDoc
     */
    public function render()
    {
        $components = $this->listComponents();

        return $this->makePartial('formcomponents', ['components' => $components]);
    }

    /**
     * listComponents
     */
    protected function listComponents()
    {
        $result = [];

        if (!isset($this->model->settings['components'])) {
            return $result;
        }

        $manager = ComponentManager::instance();
        $manager->listComponents();

        foreach ($this->model->settings['components'] as $name => $properties) {
            [$name, $alias] = strpos($name, ' ') ? explode(' ', $name) : [$name, $name];

            try {
                $componentObj = $manager->makeComponent($name, null, $properties);
                $componentObj->alias = $alias;
                $componentObj->pluginIcon = $manager->findComponentOwnerDetails($componentObj)['icon'] ?? 'icon-puzzle-piece';
            }
            catch (Exception $ex) {
                $componentObj = new UnknownComponent(null, $properties, $ex->getMessage());
                $componentObj->alias = $alias;
                $componentObj->pluginIcon = 'icon-bug';
            }

            $result[] = $componentObj;
        }

        return $result;
    }

    /**
     * getComponentName
     */
    protected function getComponentName($component)
    {
        return ComponentHelpers::getComponentName($component);
    }

    /**
     * getComponentDescription
     */
    protected function getComponentDescription($component)
    {
        return ComponentHelpers::getComponentDescription($component);
    }

    /**
     * getComponentsPropertyConfig
     */
    protected function getComponentsPropertyConfig($component)
    {
        return ComponentHelpers::getComponentsPropertyConfig($component);
    }

    /**
     * getComponentPropertyValues
     */
    protected function getComponentPropertyValues($component)
    {
        return ComponentHelpers::getComponentPropertyValues($component);
    }
}
