<?php
/**
* @package Springest for Wordpress
*/

class SpringestSearchString {

    var $id;
    var $groupId;
    var $name;
    var $value;
    var $position;
    var $trainings = array();
    var $trainingsTotalNum;

    function __construct($id = null) {
        if($id > 0) {
            $this->loadBy('id', $id);
        }        
    }
    
    /**
    * Load the current search string instance with data from the database. The $trainings property is loaded separately for better performance since it's not always needed 
    *
    * @param string $field "id" to load the object by search string ID, "slug" to load it by slug/shortname
    * @param int|string $identifier The ID or short name identifier by which to retrieve the search string from the database
    * @return none
    */
    function loadBy($field, $identifier) {
        global $wpdb;
        switch($field) {
            case "id" :
                $query = $wpdb->prepare("SELECT * FROM $wpdb->springest_search_strings WHERE id = %d", $identifier);
            break;
            case "slug" : 
                $query = $wpdb->prepare("SELECT * FROM $wpdb->springest_search_strings WHERE slug = %s", $identifier);
            break;
        }
        $result = $wpdb->get_row($query);
    
        if($result) {
            $this->id = $result->id;
            $this->name = $result->name;
            $this->slug = $result->slug;
            $this->value = $result->value;
            $this->position = $result->position;
        }
    }
    
    /**
    * Fill this instance's $trainings var with the associated Springest trainings acquired through the API
    * Also sets the $trainingsTotalNum property
    */
    function loadTrainings() {
        $page = get_query_var('paged');
        $offset = ($page > 0) ? (($page-1) * TRAININGS_PER_PAGE) : 0;
        $api = new SpringestApi();
        $trainings = $api->search($this->value, $offset);
        $this->trainingsTotalNum = $trainings->meta->results;
        if(count($trainings->trainings)) {
            $trainingIds = array();
            foreach($trainings->trainings as $training) {
                $trainingIds[] = $training->id;
            }
            if(count($trainingIds)) {
                $specifiedTrainings = $api->getSpecifiedTrainings($trainingIds);
                foreach($specifiedTrainings->trainings as $training) {
                    $this->trainings[] = new SpringestTraining(0, $training);
                }                
            }
        }        
    }
    
    
    /**
    * Save the current subject instance to the database
    *
    * @todo Subject save needs data validation
    */
    function save() {
       global $wpdb;
       $query = $wpdb->prepare("INSERT INTO $wpdb->springest_search_strings (group_id, name, slug, value, position) VALUES(%d, %s, %s, %s, %d)", $this->groupId, $this->name, $this->slug, $this->value, $this->position);
       $insert = $wpdb->query($query);
    }
    
    
     /**
     * Return the HTML for the subject view
     * 
     * @return string The HTML for the page content
     */
     function createPage() {
        $output = "";
        $this->loadTrainings();
        $group = new SpringestGroup();
        $group->loadBy('slug', get_query_var('springest_group'));
        if(count($this->trainings)) {
            //print_r($this->trainings);
            $output .= '<div class="springest-trainings-list">';
            foreach($this->trainings as $training) {
                $metaInfo = array();
                $metaInfo[] = $training->institute->name;
                if($training->price) {
                    $metaInfo[] = $training->currency." ".$training->price.",-";
                }
                if($training->time) {
                    $metaInfo[] = __('Total time: ', 'springest').$training->time;
                }
                if($training->level) {
                    $metaInfo[] = __('Level: ', 'springest').$training->level;
                }
                if($training->reviews) {
                    $metaInfo[] = __('Rating: ', 'springest').$training->averageRating.' ('.sprintf(_n(__('1 review', 'springest'), __('%d reviews', 'springest'), $training->reviews, 'springest'), $training->reviews).')';
                }
                $output .= '<div class="springest-training-summary">';
                $output .= '<h2><a href="'.get_option('siteurl').'/'.SPRINGEST_BASE.'/'.$training->id.'">'.$training->name.'</a></h2>';
                $output .= '<div><strong>';
                $output .= implode(" | ", $metaInfo);
                $output .= '</strong></div>';
                $output .= '<img src="'.$training->institute->logo.'" alt="'.$training->institute->name.'" width="190" height="100" align="right" class="alignright">';
                // $output .= '<img src="'.$training->institute->logo.'" alt="'.$training->institute->name.'" align="right">';
                $output .= '<p>'.$training->excerpt.'</p>';
                $output .= '</div>';
            }
            $output .= '</div>';
            $base = get_option('siteurl').'/'.SPRINGEST_BASE.'/'.$group->slug.'/'.$this->slug;
            $output .= $this->createPagination($base);
        }
        return $output;
     }
    
     /**
     * Return the HTML for the pagination tool
     * 
     * @param string $base The base for the pagination URLs created, including category and search string slugs
     * @return string The HTML for the pagination tool
     */
     function createPagination($base) {
         $maxPages = ceil($this->trainingsTotalNum / TRAININGS_PER_PAGE);
         $page = get_query_var('paged');
         $currentResults = ($page > 0 ) ? (($page+1)*TRAININGS_PER_PAGE) : TRAININGS_PER_PAGE;
         $output = "";
         if($page || ($currentResults < $this->trainingsTotalNum)) {
             $output .= '<div class="pagination">';
             if($page) {
                 $ext = ($page == 2) ? '' : '/page/'.($page-1);
                 $output .= '<div class="nav-previous"><a href="'.$base.$ext.'">« '.__('Previous', 'springest').'</a></div>';
             }
             if($currentResults < $this->trainingsTotalNum) {
                 $nextPage = ($page > 0) ? ($page+1) : 2;
                 $output .= '<div class="nav-next"><a href="'.$base.'/page/'.$nextPage.'">'.__('Next', 'springest').' »</a></div>';
             }
             $output .= '</div>';
         }
         return $output;
     }
    
    
    
}