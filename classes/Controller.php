<?php namespace RainLab\Pages\Classes;

use Lang;
use Cms\Classes\Page as CmsPage;
use Cms\Classes\Theme;
use Cms\Classes\CmsException;
use October\Rain\Parse\Syntax\Parser as SyntaxParser;
use Exception;

/**
 * Controller represents a static page controller.
 */
class Controller
{
    use \October\Rain\Support\Traits\Singleton;

    /**
     * @var \Cms\Classes\Theme theme active theme instance
     */
    protected $theme;

    /**
     * init this singleton
     */
    protected function init()
    {
        $this->theme = Theme::getActiveTheme();
        if (!$this->theme) {
            throw new CmsException(Lang::get('cms::lang.theme.active.not_found'));
        }
    }

    /**
     * initCmsPage creates a CMS page from a static page and configures it
     * @param string $url Specifies the static page URL.
     * @return \Cms\Classes\Page Returns the CMS page object or NULL of the requested page was not found.
     */
    public function initCmsPage($url)
    {
        $router = new Router($this->theme);
        $page = $router->findByUrl($url);

        if (!$page) {
            return null;
        }

        $viewBag = $page->viewBag;

        $cmsPage = CmsPage::inTheme($this->theme);
        $cmsPage->url = $url;
        $cmsPage->apiBag['staticPage'] = $page;

        /*
         * Transfer specific values from the content view bag to the page settings object.
         */
        $viewBagToSettings = ['title', 'layout', 'meta_title', 'meta_description', 'is_hidden'];

        foreach ($viewBagToSettings as $property) {
            $cmsPage->settings[$property] = array_get($viewBag, $property);
        }

        // Transer page ID to CMS page
        $cmsPage->settings['id'] = $page->getId();

        return $cmsPage;
    }

    /**
     * injectPageTwig renders the static page template into the CMS page
     */
    public function injectPageTwig($page, $loader, $twig)
    {
        if (!isset($page->apiBag['staticPage'])) {
            return;
        }

        $staticPage = $page->apiBag['staticPage'];

        CmsException::mask($staticPage, 400);
        $loader->setObject($staticPage);
        $template = $twig->load($staticPage->getFilePath());
        $template->render([]);
        CmsException::unmask();
    }

    /**
     * getPageContents returns the processed markup for the static page
     */
    public function getPageContents($page)
    {
        if (!isset($page->apiBag['staticPage'])) {
            return;
        }

        return $page->apiBag['staticPage']->getProcessedMarkup();
    }

    /**
     * getPlaceholderContents returns the processed markup for a named placeholder
     */
    public function getPlaceholderContents($page, $placeholderName, $placeholderContents)
    {
        if (!isset($page->apiBag['staticPage'])) {
            return;
        }

        return $page->apiBag['staticPage']->getProcessedPlaceholderMarkup($placeholderName, $placeholderContents);
    }

    /**
     * parseSyntaxFields converts syntax fields in the content to Twig markup
     */
    public function parseSyntaxFields($content)
    {
        try {
            return SyntaxParser::parse($content, [
                'varPrefix' => 'extraData.',
                'tagPrefix' => 'page:'
            ])->toTwig();
        }
        catch (Exception $ex) {
            return $content;
        }
    }
}
