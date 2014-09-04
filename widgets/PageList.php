<?php namespace RainLab\Pages\Widgets;

use Backend\Classes\WidgetBase;
use Cms\Classes\Theme;
use RainLab\Pages\Classes\PageList as StaticPageList;
use Input;
use Response;
use Request;
use Str;
use Lang;

/**
 * Static page list widget.
 *
 * @package rainlab\pages
 * @author Alexey Bobkov, Samuel Georges
 */
class PageList extends WidgetBase
{
    protected $searchTerm = false;

    protected $theme;

    protected $groupStatusCache = false;

    protected $selectedPagesCache = false;

    protected $dataIdPrefix;

    /**
     * @var string Message to display when the Delete button is clicked.
     */
    public $deleteConfirmation = 'rainlab.pages::lang.page.delete_confirmation';

    public $noRecordsMessage = 'rainlab.pages::lang.page.no_records';

    public $addSubpageLabel = 'rainlab.pages::lang.page.add_subpage';

    public function __construct($controller, $alias)
    {
        $this->alias = $alias;
        $this->theme = Theme::getEditTheme();
        $this->dataIdPrefix = 'page-'.$this->theme->getDirName();
        $this->addSubpageLabel = trans($this->addSubpageLabel);

        parent::__construct($controller, []);
        $this->bindToController();
    }

    /**
     * Renders the widget.
     * @return string
     */
    public function render()
    {
        return $this->makePartial('body', [
            'data'=>$this->getData()
        ]);
    }

    /**
     * Returns information about this widget, including name and description.
     */
    public function widgetDetails() {}

    /*
     * Event handlers
     */

    public function onGroupStatusUpdate()
    {
        $this->setGroupStatus(Input::get('group'), Input::get('status'));
    }

    public function onReorder()
    {
        $structure = json_decode(Input::get('structure'), true);
        if (!$structure)
            throw new SystemException('Invalid structure data posted.');

        $pageList = new StaticPageList($this->theme);
        $pageList->updateStructure($structure);
    }

    public function onUpdate()
    {
        $this->extendSelection();

        return $this->updateList();
    }

    public function onSelect()
    {
        $this->extendSelection();
    }

    public function onSearch()
    {
        $this->setSearchTerm(Input::get('search'));
        $this->extendSelection();

        return $this->updateList();
    }

    /*
     * Methods for th internal use
     */

    protected function getData()
    {
        $pageList = new StaticPageList($this->theme);
        $pages = $pageList->getPageTree(true);

        $searchTerm = Str::lower($this->getSearchTerm());

        if (strlen($searchTerm)) {
            $words = explode(' ', $searchTerm);

            $iterator = function($pages) use (&$iterator, $words) {
                $result = [];

                foreach ($pages as $page) {
                    if ($this->textMatchesSearch($words, $this->subtreeToText($page))) {
                        $result[] = (object)[
                            'page' => $page->page,
                            'subpages' => $iterator($page->subpages)
                        ];
                    }
                }

                return $result;
            };

            $pages = $iterator($pages);
        }

        return $pages;
    }

    protected function getSearchTerm()
    {
        return $this->searchTerm !== false ? $this->searchTerm : $this->getSession('search');
    }

    protected function setSearchTerm($term)
    {
        $this->searchTerm = trim($term);
        $this->putSession('search', $this->searchTerm);
    }

    protected function getGroupStatuses()
    {
        if ($this->groupStatusCache !== false)
            return $this->groupStatusCache;

        $groups = $this->getSession($this->getThemeSessionKey('groups'), []);
        if (!is_array($groups))
            return $this->groupStatusCache = [];

        return $this->groupStatusCache = $groups;
    }

    protected function setGroupStatus($group, $status)
    {
        $statuses = $this->getGroupStatuses();
        $statuses[$group] = $status;
        $this->groupStatusCache = $statuses;
        $this->putSession($this->getThemeSessionKey('groups'), $statuses);
    }

    protected function getGroupStatus($group)
    {
        $statuses = $this->getGroupStatuses();
        if (array_key_exists($group, $statuses))
            return $statuses[$group];

        return true;
    }

    protected function getThemeSessionKey($prefix)
    {
        return $prefix.$this->theme->getDirName();
    }

    protected function updateList()
    {
        return ['#'.$this->getId('page-list') => $this->makePartial('items', ['items'=>$this->getData()])];
    }

    protected function getSelectedPages()
    {
        if ($this->selectedPagesCache !== false)
            return $this->selectedPagesCache;

        $pages = $this->getSession($this->getThemeSessionKey('selected'), []);
        if (!is_array($pages))
            return $this->selectedPagesCache = [];

        return $this->selectedPagesCache = $pages;
    }

    protected function extendSelection()
    {
        $items =Input::get('object', []);
        $currentSelection = $this->getSelectedPages();

        $this->putSession($this->getThemeSessionKey('selected'), array_merge($currentSelection, $items));
    }

    protected function resetSelection()
    {
        $this->putSession($this->getThemeSessionKey('selected'), []);
    }

    protected function isPageSelected($page)
    {
        $selectedPages = $this->getSelectedPages();
        if (!is_array($selectedPages) || !isset($selectedPages[$page->getBaseFileName()]))
            return false;

        return $selectedPages[$page->getBaseFileName()];
    }

    protected function subtreeToText($page)
    {
        $result = $this->pageToText($page->page);

        $iterator = function($pages) use (&$iterator, &$result) {
            foreach ($pages as $page) {
                $result .= ' '.$this->pageToText($page->page);
                $iterator($page->subpages);
            }
        };

        $iterator($page->subpages);

        return $result;
    }

    protected function pageToText($page)
    {
        $viewBag = $page->getViewBag();

        return $page->getViewBag()->property('title').' '.$page->getViewBag()->property('url');
    }

    protected function textMatchesSearch(&$words, $text)
    {
        foreach ($words as $word) {
            $word = trim($word);
            if (!strlen($word))
                continue;

            if (Str::contains(Str::lower($text), $word))
                return true;
        }

        return false;
    }
}