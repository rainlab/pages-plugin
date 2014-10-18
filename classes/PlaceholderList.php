<?php namespace RainLab\Pages\Classes;

use RainLab\Pages\Classes\Page;
use File;
use DirectoryIterator;
use ApplicationException;
use October\Rain\Support\Yaml;
use System\Classes\SystemException;
use Symfony\Component\Yaml\Dumper as YamlDumper;

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
        if (array_key_exists($name, $this->placeholders))
            return $this->placeholders[$name];

        return null;
    }

    public function add($name, $content)
    {
        $this->placeholders[$name] = $content;
    }
}