<?php namespace RainLab\Pages\Classes;

/**
 * Contains a list of page placeholders.
 * Objects of this class are used in the Editor internally.
 *
 * @package rainlab\pages
 * @author Alexey Bobkov, Samuel Georges
 */
class PlaceholderList
{
    protected $placeholders = [];

    public function __get($name)
    {
        if (array_key_exists($name, $this->placeholders)) {
            return $this->placeholders[$name];
        }

        return null;
    }

    public function __isset($name)
    {
        return array_key_exists($name, $this->placeholders);
    }

    public function add($name, $content)
    {
        $this->placeholders[$name] = $content;
    }
}
