<?php namespace RainLab\Pages\Classes;

use Cms\Classes\Content as ContentBase;

/**
 * Content represents a content template.
 */
class Content extends ContentBase
{
    /**
     * getNiceTitleAttribute converts the file name into something nicer for humans to read
     */
    public function getNiceTitleAttribute()
    {
        $title = basename($this->getBaseFileName());
        $title = ucwords(str_replace(['-', '_'], ' ', $title));
        return $title;
    }
}
