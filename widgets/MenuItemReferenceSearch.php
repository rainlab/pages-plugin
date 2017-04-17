<?php namespace RainLab\Pages\Widgets;

use Str;
use Lang;
use Input;
use Request;
use Response;
use Backend\Classes\WidgetBase;
use Cms\Classes\Theme;
use RainLab\Pages\Classes\MenuItem;

/**
 * Search of all available menu item references regardless of type
 *
 * @package rainlab\pages
 * @author Alexey Bobkov, Samuel Georges
 */
class MenuItemReferenceSearch extends WidgetBase
{
    use \Backend\Traits\SearchableWidget;
    
    
    public $noRecordsMessage = 'rainlab.pages::lang.menuitem.search_not_found';
    
    public $optionHeaderMessage = 'rainlab.pages::lang.menuitem.search_result';
    
    public $searchPlaceholderMessage = 'rainlab.pages::lang.menuitem.search_placeholder';
    
    public $selectResultMessage = 'rainlab.pages::lang.menuitem.search_select';

    
    /**
     * Renders the widget.
     * @return string
     */
    public function render()
    {
        return $this->makePartial('body', $this->getData());
    }

    /*
     * Event handlers
     */

    public function onSearch()
    {
        $this->setSearchTerm(Input::get('search'));

        return $this->updateResults();
    }

    public function getMatchesPartial()
    {
        return $this->makePartial('matches', $this->getData());
    }
    
    /*
     * Methods for internal use
     */

    protected function getData()
    {
        $matches = $this->getMatches();
        $total = 0;
        foreach ($matches as $type) {
            $total += count($type['references']);
        }
        
        return [
            'matches' => $matches,
            'total' => $total
        ];
    }
    
    protected function getMatches()
    {
        $searchTerm = Str::lower($this->getSearchTerm());
        if (!strlen($searchTerm)) {
            return [];
        }
        
        $words = explode(' ', $searchTerm);
        
        $types = [];
        $item = new MenuItem();
        foreach ($item->getTypeOptions() as $type => $typeTitle) {
            $typeInfo = MenuItem::getTypeInfo($type);
            if (empty($typeInfo['references'])) {
                continue;
            }
            
            $typeMatches = [];
            foreach ($typeInfo['references'] as $key => $referenceInfo) {
                $title = is_array($referenceInfo) ? $referenceInfo['title'] : $referenceInfo;
                
                if ($this->textMatchesSearch($words, $title)) {
                    $typeMatches[$key] = $title;
                }
            }
            
            if (!empty($typeMatches)) {
                $types[] = [
                    'code'       => $type,
                    'title'      => $typeTitle,
                    'references' => $typeMatches
                ];
            }
        }
        
        return $types;
    }

    protected function updateResults()
    {
        return ['#'.$this->getId('matches') => $this->getMatchesPartial()];
    }
    
    /* Ignore session usage -- do not save search value */
    
    protected function getSession($key = null, $default = null)
    {
        return $default;
    }

    protected function putSession($key, $value) 
    {
    }
}
