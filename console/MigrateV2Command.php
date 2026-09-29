<?php namespace RainLab\Pages\Console;

use Cms\Classes\Theme;
use RainLab\Pages\Classes\Content;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * MigrateV2Command moves content translations from the RainLab.Translate suffix format (welcome.fr.htm) into locale directories (fr/welcome.htm).
 */
class MigrateV2Command extends Command
{
    /**
     * @var string name
     */
    protected $name = 'pages:migratev2';

    /**
     * @var string description
     */
    protected $description = 'Moves content translations from Pages plugin v2 into locale directories';

    /**
     * handle
     */
    public function handle()
    {
        if ($themeName = $this->option('theme')) {
            if (!Theme::exists($themeName)) {
                $this->error("Theme [{$themeName}] not found.");
                return 1;
            }

            $theme = Theme::load($themeName);
        }
        else {
            $theme = Theme::getActiveTheme();
        }

        if (!$theme) {
            $this->error("No active theme found.");
            return 1;
        }

        $localeKeys = Content::listLocaleKeys();
        if (!$localeKeys) {
            $this->info("No site locales found, nothing to migrate.");
            return 0;
        }

        $moved = 0;

        foreach (Content::listInTheme($theme, true) as $content) {
            $fileName = ltrim($content->fileName, '/');

            // Static page files are managed by the page document type
            if (preg_match('#^static-pages(-[^/]+)?/#', $fileName)) {
                continue;
            }

            $target = Content::makeLocaleFileNameFromLegacy($fileName, $localeKeys);
            if ($target === null) {
                continue;
            }

            if (Content::load($theme, $target)) {
                $this->warn("Skipped {$fileName}, {$target} already exists");
                continue;
            }

            $content->fileName = $target;
            $content->save();

            $this->line("Moved {$fileName} to {$target}");
            $moved++;
        }

        if (!$moved) {
            $this->info("No legacy content translations found, nothing to migrate.");
            return 0;
        }

        $this->info("Successfully moved {$moved} content translation(s) in theme [{$theme->getDirName()}]");
        return 0;
    }

    /**
     * getArguments
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * getOptions
     */
    protected function getOptions()
    {
        return [
            ['theme', null, InputOption::VALUE_REQUIRED, 'Migrate a specific theme instead of the active theme'],
        ];
    }
}
