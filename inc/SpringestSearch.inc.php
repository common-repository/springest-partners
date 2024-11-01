<?php

class SpringestSearch {
    
    var $searchTerms;
    var $results;
    var $resultsTotalNum = 0;
     
    function __construct() {
        if(isset($_GET['ts'])) {
            $this->searchTerms = $_GET['ts'];
        }
    }
         
    /**
    * Get a list of trainings that match the search terms as an object
    *
    * @param string $searchTerms The terms to search on
    */
    function createPage() {
        $this->loadResults($this->searchTerms);
        $output = '';
        if($this->results) {
            $output .= '<div class="springest-trainings-list">';
            foreach($this->results as $training) {
                $metaInfo = array();
                $metaInfo[] = $training->institute->name;
                if($training->price) {
                    $metaInfo[] = $training->currency." ".$training->price.",-";
                }
                if($training->time) {
                    $metaInfo[] = __('Total time: ').$training->time;
                }
                if($training->level) {
                    $metaInfo[] = __('Level: ').$training->level;
                }
                if($training->reviews) {
                    $metaInfo[] = __('Rating: ', 'springest').$training->averageRating.' ('.sprintf(_n(__('1 review', 'springest'), __('%d reviews', 'springest'), $training->reviews, 'springest'), $training->reviews).')';
                }
                $output .= '<div class="springest-training-summary">';
                $output .= '<h2><a href="'.get_option('siteurl').'/'.SPRINGEST_BASE.'/'.$training->id.'">'.$training->name.'</a></h2>';
                $output .= '<div><strong>';
                $output .= implode(" | ", $metaInfo);
                $output .= '</strong></div>';
                $output .= '<img src="'.$training->institute->logo.'" alt="'.$training->institute->name.'" width="190" height="100" align="right" class="alignright" />';
                // $output .= '<img src="'.$training->institute->logo.'" alt="'.$training->institute->name.'" align="right">';
                $output .= '<p>'.$training->excerpt.'</p>';
                $output .= '</div>';
            }
            $output .= '</div>';
            $output .= $this->createPagination();
        }
        else {
            $output .= __('Your search did not match any results.', 'springest');
        }
        return $output;
    }
    
    /**
    *
    */
    function loadResults() {
        $page = get_query_var('paged');
        $offset = ($page > 0) ? (($page-1) * TRAININGS_PER_PAGE) : 0;
        # Initiate the API        
        $api = new SpringestApi();
        $results = $api->search($this->searchTerms, $offset);
        $this->resultsTotalNum = $results->meta->results;
        if(count($results->trainings)) {
            $trainingIds = array();
            foreach($results->trainings as $training) {
                $trainingIds[] = $training->id;
            }
            if(count($trainingIds)) {
                $specifiedTrainings = $api->getSpecifiedTrainings($trainingIds);
                foreach($specifiedTrainings->trainings as $training) {
                    $this->results[] = new SpringestTraining(0, $training);
                }                
            }
        }
    }
    
    /**
    * Return the HTML for the pagination tool
    * 
    * @param string $base The base for the pagination URLs created, including category and subject slugs
    * @return string The HTML for the pagination tool
    */
    function createPagination() {
        $maxPages = ceil($this->resultsTotalNum / TRAININGS_PER_PAGE);
        $page = get_query_var('paged');
        $currentResults = ($page > 0 ) ? (($page+1)*TRAININGS_PER_PAGE) : TRAININGS_PER_PAGE;
        $output = "";
        $base = get_option('siteurl').'/'.SPRINGEST_BASE;
        if($page || ($currentResults < $this->resultsTotalNum)) {
            $output .= '<div class="pagination">';
            if($page) {
                $ext = ($page == 2) ? '' : '/page/'.($page-1);
                $output .= '<div class="nav-previous"><a href="'.$base.$ext.'?ts='.$this->searchTerms.'">« '.__('Previous', 'springest').'</a></div>';
            }
            if($currentResults < $this->resultsTotalNum) {
                $nextPage = ($page > 0) ? ($page+1) : 2;
                $output .= '<div class="nav-next"><a href="'.$base.'/page/'.$nextPage.'?ts='.$this->searchTerms.'">'.__('Next', 'springest').' »</a></div>';
            }
            $output .= '</div>';
        }
        return $output;
    }
    
  
}
?>