<?php namespace RainLab\Pages\Classes;

use Site;
use Cms\Classes\Content as ContentBase;

/**
 * Content represents a content template, translated in locale directories (content/fr/welcome.htm) or the legacy suffix format (content/welcome.fr.htm).
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

    /**
     * listLocaleKeys returns the locale keys of non-primary sites, which name translation files.
     */
    public static function listLocaleKeys(): array
    {
        if (!Site::hasMultiSite()) {
            return [];
        }

        $primary = Site::getPrimarySite();
        $primaryKeys = $primary ? Site::getLocaleKeyChain((string) $primary->hard_locale) : [];

        $keys = [];
        foreach (Site::listSites() as $site) {
            foreach (Site::getLocaleKeyChain((string) $site->hard_locale) as $key) {
                $keys[$key] = true;
            }
        }

        return array_values(array_diff(array_keys($keys), $primaryKeys));
    }

    /**
     * isTranslationFileName checks if a file name is a translation in a locale directory or with a legacy locale suffix.
     */
    public static function isTranslationFileName(string $fileName, array $localeKeys): bool
    {
        $fileName = ltrim($fileName, '/');
        $segments = explode('/', $fileName);

        if (count($segments) > 1 && in_array($segments[0], $localeKeys, true)) {
            return true;
        }

        return static::makeLocaleFileNameFromLegacy($fileName, $localeKeys) !== null;
    }

    /**
     * makeLocaleFileNameFromLegacy converts a suffixed file name (blog/intro.fr.htm) to its locale directory (fr/blog/intro.htm), or null.
     */
    public static function makeLocaleFileNameFromLegacy(string $fileName, array $localeKeys): ?string
    {
        $fileName = ltrim($fileName, '/');
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $baseName = strlen($extension) ? substr($fileName, 0, -(strlen($extension) + 1)) : $fileName;

        foreach ($localeKeys as $key) {
            if (str_ends_with(basename($baseName), '.'.$key)) {
                $stripped = substr($baseName, 0, -(strlen($key) + 1));
                return $key.'/'.$stripped.(strlen($extension) ? '.'.$extension : '');
            }
        }

        return null;
    }

    /**
     * makeLocaleFileName returns the file name of a translation in its locale directory.
     */
    public static function makeLocaleFileName(string $fileName, string $locale): string
    {
        return $locale.'/'.ltrim($fileName, '/');
    }

    /**
     * makeLegacyLocaleFileName returns the file name of a translation using the locale suffix format.
     */
    public static function makeLegacyLocaleFileName(string $fileName, string $locale): string
    {
        $fileName = ltrim($fileName, '/');
        $position = strrpos($fileName, '.');

        return $position === false
            ? $fileName.'.'.$locale
            : substr_replace($fileName, '.'.$locale, $position, 0);
    }

    /**
     * findLocalizedTranslation returns the translation stored in the locale directory, or null.
     * @return static|null
     */
    public function findLocalizedTranslation(string $locale)
    {
        return static::load($this->theme, static::makeLocaleFileName($this->fileName, $locale));
    }

    /**
     * findLegacyTranslation returns the translation stored with a locale suffix, or null.
     * @return static|null
     */
    public function findLegacyTranslation(string $locale)
    {
        return static::load($this->theme, static::makeLegacyLocaleFileName($this->fileName, $locale));
    }

    /**
     * findTranslation returns the translation stored for the exact locale in either format, or null.
     * @return static|null
     */
    public function findTranslation(string $locale)
    {
        return $this->findLocalizedTranslation($locale) ?: $this->findLegacyTranslation($locale);
    }

    /**
     * findInheritedTranslation returns the content a locale shows when it has no translation of its own.
     * @return static
     */
    public function findInheritedTranslation(string $locale)
    {
        foreach (Site::getLocaleKeyChain($locale) as $key) {
            if ($key === $locale) {
                continue;
            }

            if ($content = $this->findLocalizedTranslation($key)) {
                return $content;
            }
        }

        return $this;
    }

    /**
     * renameTranslationsFrom moves the translations of a previous file name to follow this content.
     */
    public function renameTranslationsFrom(string $oldFileName)
    {
        if (ltrim($oldFileName, '/') === ltrim($this->fileName, '/')) {
            return;
        }

        foreach (static::listLocaleKeys() as $key) {
            $moves = [
                static::makeLocaleFileName($oldFileName, $key) => static::makeLocaleFileName($this->fileName, $key),
                static::makeLegacyLocaleFileName($oldFileName, $key) => static::makeLegacyLocaleFileName($this->fileName, $key)
            ];

            foreach ($moves as $fromFileName => $toFileName) {
                $translation = static::load($this->theme, $fromFileName);
                if (!$translation || static::load($this->theme, $toFileName)) {
                    continue;
                }

                $translation->fileName = $toFileName;
                $translation->save();
            }
        }
    }

    /**
     * deleteTranslations removes the translations of this content in every site locale.
     */
    public function deleteTranslations()
    {
        foreach (static::listLocaleKeys() as $key) {
            foreach ([$this->findLocalizedTranslation($key), $this->findLegacyTranslation($key)] as $translation) {
                if ($translation) {
                    $translation->delete();
                }
            }
        }
    }
}
