<?php namespace RainLab\Pages\Classes;

/**
 * PageLocale represents a translated mirror of a static page, stored in a
 * locale-suffixed content directory, e.g. content/static-pages-fr/about.htm.
 *
 * Mirrors hold only the translated values (view bag properties, markup and
 * placeholder blocks) and are never indexed in the meta/static-pages.yaml
 * structure. The base page in content/static-pages remains the single source
 * of truth for the page hierarchy, layout and default URL.
 */
class PageLocale extends Page
{
    /**
     * @var string|null contextLocale determines the directory suffix while
     * loading or saving mirrors. Managed by the withLocale() wrapper.
     */
    protected static $contextLocale = null;

    /**
     * @var array rules are relaxed - mirrors store partial (translated) data only.
     */
    public $rules = [];

    /**
     * withLocale runs a callback with the locale directory context applied.
     */
    public static function withLocale(string $locale, callable $callback)
    {
        $previous = static::$contextLocale;
        static::$contextLocale = $locale;

        try {
            return $callback();
        }
        finally {
            static::$contextLocale = $previous;
        }
    }

    /**
     * findLocale returns the mirror for a page in the given locale, or null.
     * @return static|null
     */
    public static function findLocale(string $locale, Page $page)
    {
        return static::withLocale($locale, function() use ($page) {
            return static::load($page->theme, $page->fileName);
        });
    }

    /**
     * getObjectTypeDirName
     */
    public function getObjectTypeDirName()
    {
        return 'content/static-pages-'.static::$contextLocale;
    }

    /**
     * beforeCreate keeps the file name assigned by the caller - mirrors always
     * share their base page's file name.
     */
    public function beforeCreate()
    {
    }

    /**
     * afterCreate does not touch the meta index.
     */
    public function afterCreate()
    {
    }

    /**
     * beforeValidate has no unique URL constraints for mirrors.
     */
    public function beforeValidate()
    {
    }

    /**
     * appendToMeta is disabled for mirrors.
     */
    protected function appendToMeta()
    {
    }

    /**
     * removeFromMeta is disabled for mirrors.
     */
    protected function removeFromMeta()
    {
    }
}
