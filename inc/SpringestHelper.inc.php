<?php
/**
* @package Springest for Wordpress
*/

class SpringestHelper {
    
    var $warningType;
    var $rewriteBase;
    var $pageBreadcrumbs;
    var $pageTitle;
    var $pageContent;
    var $htmlTitle;
    var $processed = false;
    
    /**
    * Initiate the SpringestHelper object 
    */
    function __construct() {

        # If we are in the WP admin, check of the SA_base_page option is already set
        if(is_admin() && !isset($_POST['SA_base_page'])) {
            if(!get_option('springest_base_page')) {
                # Display a warning 
                $this->warningType = 'missing-base-page';
                add_action('admin_notices', array(&$this, 'displayWarning'));
            }
        }
        
        # Set the global URL rewrite base for the Springest pages
        $this->rewriteBase = $this->getRewriteBase();
        
        # Load CSS for front-end, if not disabled
        if(get_option('springest_load_css', 1)) {
            add_action('wp_head', array(&$this, 'loadCSS'));
        }
        
        # Set the TRAININGS_PER_PAGE constant which holds the max amount of trainings to display before pagination on a subject page
        if(!defined('TRAININGS_PER_PAGE')) {
            define('TRAININGS_PER_PAGE', (int) get_option('springest_trainings_per_page', 15));
        }
    }
    
    /**
    * Check if plugin tables are installed
    */
    function isInstalled() {
        global $wpdb;
        $query = "SHOW TABLES LIKE '".$wpdb->prefix."springest_groups'";
        if(!count($wpdb->get_results($query))) {
            return false;
        }
        return true;
    }
    
    /**
    * Install plugin tables
    */
    function install() {
        echo "INSTALLING";
        global $wpdb;
        # Create table for groups
        $query = "CREATE TABLE IF NOT EXISTS `".$wpdb->prefix."springest_groups` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `name` varchar(255) DEFAULT NULL,
          `slug` varchar(255) DEFAULT NULL,
          `description` longtext,
          `active` tinyint(1) unsigned DEFAULT NULL,
          `last_modified` datetime DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=MyISAM AUTO_INCREMENT=15 DEFAULT CHARSET=latin1;";
        $create = $wpdb->query($query);
        
        # Create table for relations
        $query = "CREATE TABLE IF NOT EXISTS `".$wpdb->prefix."springest_relations` (
          `group_id` int(11) unsigned DEFAULT NULL,
          `term_id` int(11) unsigned DEFAULT NULL
        ) ENGINE=MyISAM DEFAULT CHARSET=latin1;";
        $create = $wpdb->query($query);
        
        # Create table for search strings
        $query = "CREATE TABLE IF NOT EXISTS `".$wpdb->prefix."springest_search_strings` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `group_id` int(11) unsigned NOT NULL,
          `name` varchar(255) DEFAULT NULL,
          `slug` varchar(255) DEFAULT NULL,
          `value` text,
          `position` smallint(6) DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=MyISAM AUTO_INCREMENT=110 DEFAULT CHARSET=latin1;";
        $create = $wpdb->query($query);
        
        # Set default relations for categories and tags
        update_option("springest_relation_taxonomies", array('category', 'post_tag'));
        
        # Set default "show search" to true
        update_option("springest_show_search", true);
        
        # Set default "show breadcrumbs" to true
        update_option("springest_show_breadcrumbs", true);
        
        # Set default API URL
        //update_option("springest_api_url", __('DEFAULT_API_URL', 'springest'));
    }

    /**
     * Check if file_get_contents or cURL extension is available
     */
    function checkTransferMethods() {
        if(ini_get('allow_url_fopen') != "On" && !in_array('curl', get_loaded_extensions())) {
            echo '<div class="error">';
            echo '<p><strong>'.__('WARNING', 'springest').'</strong>: '.__('It appears you do not have any transfer methods available to reach the Springest API. Please set "allow_url_fopen" to "On" in your php.ini settings, or use the PHP cURL extension.', 'springest').'</p>';
            echo '</div>';
        }
    }
      
    /**
     * Listen for AJAX requests
     */
    function ajaxListener() {
        # If no springest_action is set, return
        if(!isset($_GET['springest_action'])) {
            return;
        }
        
        # If AJAX call is made by none logged in user throw an error
        elseif(!current_user_can('manage_options')) {
            echo __('Please log in as an admin to use this feature.', 'springest');
            exit;
        }
        
        # AJAX request for a list of subjects associated with a specific category
        if($_GET['springest_action'] == "get_subjects" && isset($_GET['category_id'])) {
        
            # Get the category ID
            $categoryId = (int) $_GET['category_id'];
            
            # A list of ID's of subjects to exclude from result
            $exclude = explode(",", $_GET['exclude']);
            
            # Load up the Springest API
            $api = new SpringestApi();
            
            # Get a list of subjects from the API
            $subjects = $api->getSubjects($categoryId, $exclude);
            
            if($subjects) {
                foreach($subjects as $subject) {
                    # Output subjects as HTML list
                    echo '<li><a href="#" id="SA_subject_'.$subject->id.'" class="SA_add_subject" rel="'.$subject->id.'%%'.$subject->name.'%%'.$subject->short_name.'">'.$subject->name;
                    echo '</a></li>';
                }
            }
            exit;
        }
    }
    
    /**
    * Check if URL matches a Springest object
    *
    * @return bool|string False if no object matched, otherwise the type of object: training, subject, group, search or home. 
    *
    */
    function springestObject() {
        if(is_page(get_option('springest_base_page'))) {
            if(get_query_var('springest_training_id')) {
                return 'training';
            }
            elseif(get_query_var('springest_search_string')) {
                return 'search_string';
            }
            elseif(get_query_var('springest_group')) {
                return 'group';
            }
            elseif(isset($_GET['ts'])) {
                return 'search';
            }
            else {
                return 'home';
            }
        }
        return false;
    }
    
    /**
    * Generate page title, page content and <title> content for currently view Springest object and add to page through WordPress filters
    */
    function loadPage() {
        switch($this->springestObject()) {
        
            # Training
            case 'training' :
                # Initiate training object
                $trainingId = (int) get_query_var('springest_training_id');
                $training = new SpringestTraining($trainingId);
                
                # Check for invalid URL
                if(!$training->id) {
                    $this->generate404();
                }
                
                # Set page title
                $this->pageTitle = $training->name;
                
                # Set page content
                $this->pageContent = $training->createPage();
                
                # Set HTML <title> content
                $this->htmlTitle = $training->name." | ".get_the_title() . " | ";
                
                # Remove canonical URL to avoid problems
                remove_filter('wp_head', 'rel_canonical');
            break;
            
            # Subject
            case 'search_string' :
                # Initiate and load subject object
                $searchStringSlug = esc_attr(get_query_var('springest_search_string'));
                $searchString = new SpringestSearchString();
                $searchString->loadBy('slug', $searchStringSlug);
                
                # Check for invalid URL
                if(!$searchString->id) {
                    $this->generate404();
                }
                
                # Set page title
                $this->pageTitle = $searchString->name;
                
                # Set page content
                $this->pageContent = $searchString->createPage();
                
                # Initiate and load parent group object
                $groupSlug = esc_attr(get_query_var('springest_group'));
                $group = new SpringestGroup();
                $group->loadBy('slug', $groupSlug);
                
                # Generate breadcrumbs
                $basePage = $this->getBasePage();
                $this->pageBreadcrumbs = '<a href="'.get_permalink($basePage->ID).'">'.$basePage->post_title.'</a>';
                $this->pageBreadcrumbs .= ' » <a href="'.get_option('siteurl').'/'.$basePage->post_name.'/'.$group->slug.'">'.$group->name.'</a>';
                $this->pageBreadcrumbs .= ' » <strong>'.$searchString->name.'</strong>';
                
                # Set HTML <title> content
                $this->htmlTitle = $searchString->name." | ". $group->name . " | " . get_the_title() . " | ";
                
                # Remove canonical URL to avoid problems
                remove_filter('wp_head', 'rel_canonical');
            break;
            
            # Group
            case 'group' :
                # Initate and load group object
                $groupSlug = esc_attr(get_query_var('springest_group'));
                $group = new SpringestGroup();
                $group->loadBy('slug', $groupSlug);
                
                # Check for invalid URL
                if(!$group->id) {
                    $this->generate404();
                }
                
                # Set page title
                $this->pageTitle = $group->name;
                
                # Set page content
                $this->pageContent = $group->createPage();
                
                # Generate breadcrumbs
                $basePage = $this->getBasePage();
                $this->pageBreadcrumbs = '<a href="'.get_permalink($basePage->ID).'">'.$basePage->post_title.'</a>';
                $this->pageBreadcrumbs .= ' » <strong>'.$group->name.'</strong>';
                
                
                # Set HTML <title> content
                $this->htmlTitle = $group->name." | ".get_the_title() . " | ";
                
                # Remove canonical URL to avoid problems
                remove_filter('wp_head', 'rel_canonical');
            break;
            
            # Search
            case 'search' :
                # Initiate and load search object
                $search = new SpringestSearch();
                
                # Set page title
                $this->pageTitle = sprintf(__('Searched for %s', 'springest'), $search->searchTerms);
                
                # Generate breadcrumbs
                $basePage = $this->getBasePage();
                $this->pageBreadcrumbs = '<a href="'.get_permalink($basePage->ID).'">'.$basePage->post_title.'</a>';
                $this->pageBreadcrumbs .= ' » <strong>'.sprintf(__('Search for "%s"', 'springest'),$search->searchTerms).'</strong>';
                
                # Set page content
                $this->pageContent = $search->createPage();
                
                # Set HTML <title> content
                $this->htmlTitle = sprintf(__('Searched for %s', 'springest'), $search->searchTerms)." | ".get_the_title() . " | ";
            break;
            
            # Home
            default :
                # Set page content and title to regular WP page
                global $post;
                $this->pageTitle = get_the_title();
                $this->pageContent = wpautop($post->post_content . $this->homepage());
            break;
        }

        # Filter page title to add our title
        add_filter("the_title", array(&$this, 'title'));
        # Filter page content to add our content
        add_filter("the_content", array(&$this, 'content'));
        # Filter HTML <title> content to our own title
        add_filter("wp_title", array(&$this, 'htmlTitle'));
    }
    
    /**
    * Generate and return the HTML for the Springest search form
    *
    * @return string HTML for Springest search form
    */
    function getSearchForm() {
        $output = '<form action="'.get_option('siteurl').'/'.SPRINGEST_BASE.'" method="get" class="springest-search-form">';
        $output .= '<fieldset><label for="springest_ts">'.__('Search trainings', 'springest').':</label> ';
        $output .= '<input type="text" id="springest_ts" class="springest-search-field" name="ts" size="30"';
        $output .= (isset($_GET['ts'])) ? ' value="'.$_GET['ts'].'"' : '';
        $output .= '>';
        $output .= '<input class="springest-search-submit" type="submit" value="'.__('Search', 'springest').'">';
        $output .= '</fieldset>';
        $output .= '</form>';
        return $output;
    }
    
    /**
    * Validate the currently requested URL to avoid duplicate content
    *
    * @return bool|none Returns true if the URL validates, otherwise returns false
    */
    function validateUrl() {
        if($subjectSlug = get_query_var('springest_subject')) {
            if($groupSlug = get_query_var('springest_group')) {
                if($this->subjectExists($groupSlug, $subjectSlug)) {
                    return true;
                }
            }
        }
        elseif($slug = get_query_var('springest_group')) {
            if($this->groupExists($slug)) {
                return true;
            }
        }
        elseif($id = get_query_var('springest_training_id')) {
            $id = (int) $id;
            if($this->trainingExists($id)) {
                return true;
            }
        }
        return false;
    }
    
    /**
    * Save the WP page used for Springest index, and flush the WP rewrite rules
    */
    function updateBasePage($basePageId) {
        update_option('springest_base_page', $basePageId);
        $rewriteBase = $this->getRewriteBase();
        global $wp_rewrite;
	   	$wp_rewrite->flush_rules();
    } 
    
    /**
    * Add the rewrite rules for Springest to the WP rewrite object. This function is called by the "rewrite_rules_array" filter 
    * 
    * @param array $rules An array with the current rewrite rules before filtering
    * @return array An array with the new set of rewrite rules
    */
    function addRewriteRules($rules) {

        global $wp_rewrite;
        
        # Initiate the group model and get a list of all groups
        $groupModel = new SpringestGroup();
        $groups = $groupModel->getAllGroups();
        
        if($groups) {
            foreach($groups as $group) {
                
                # Add subjects keytag
             	$keytag = '%springest_search_string%';
             	$wp_rewrite->add_rewrite_tag($keytag, $group->slug.'/(.+?)', 'pagename='.$this->rewriteBase.'&springest_group='.$group->slug.'&springest_search_string=');
                $keywords_structure = $wp_rewrite->root.$this->rewriteBase."/$keytag";
            	$keywords_rewrite = $wp_rewrite->generate_rewrite_rules($keywords_structure);
            	$wp_rewrite->rules = $keywords_rewrite + $wp_rewrite->rules;
            	
            	# Add groups keytag
                $keytag = '%springest_group%';
                $wp_rewrite->add_rewrite_tag($keytag, '('.$group->slug.')', 'pagename='.$this->rewriteBase.'&springest_group=');
             	$keywords_structure = $wp_rewrite->root.$this->rewriteBase."/$keytag";
            	$keywords_rewrite = $wp_rewrite->generate_rewrite_rules($keywords_structure);
            	$wp_rewrite->rules = $keywords_rewrite + $wp_rewrite->rules;
            	
            }
            
            # Add trainings keytag
           	$keytag = '%springest_training_id%';
           	$wp_rewrite->add_rewrite_tag($keytag, '([0-9]+)', 'pagename='.$this->rewriteBase.'&springest_training_id=');
            $keywords_structure = $wp_rewrite->root.$this->rewriteBase."/$keytag";
          	$keywords_rewrite = $wp_rewrite->generate_rewrite_rules($keywords_structure);
          	$wp_rewrite->rules = $keywords_rewrite + $wp_rewrite->rules;
        	
        }

    	return $wp_rewrite->rules;
    }

    /**
    * Insert the Springest query vars. This is called by the "query_vars" filter
    *
    * @param array $vars The current set of query vars before filtering
    * @return array The new set of query vars
    */
    function insertQueryVar($vars) {
        array_push($vars, 'springest_group');
        array_push($vars, 'springest_search_string');
        array_push($vars, 'springest_training_id');
        return $vars;
    }
    
    /**
    * Get the current Springest URL base
    *
    * @return string The URL base
    */    
    function getRewriteBase() {
        $basePage = get_post(get_option("springest_base_page"));
        return $basePage->post_name;
    }
    
    /**
    * Get the Springest base page as an object
    *
    * @return string The URL base
    */    
    function getBasePage() {
        return get_post(get_option("springest_base_page"));
    }
    
    /**
    * Check if a group with the specified slug exists
    *
    * @param string $groupSlug The shortname for the group to check 
    * @return bool True if the group exists, false if not
    */
    function groupExists($groupSlug) {
        global $wpdb; 
        if($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $wpdb->springest_groups WHERE slug = %s", $groupSlug))) {
            return true;
        }
        return false;
    }
    
    /**
    * Check if a subject with the specified group slug and subject slug exists
    *
    * @param string $groupSlug The shortname for the group to check 
    * @param string $subjectSlug The shortname for the subject to check 
    * @return bool True if the subject exists, false if not
    */
    function subjectExists($groupSlug, $subjectSlug) {
        global $wpdb;
        if($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $wpdb->springest_subjects WHERE slug = %s AND group_id = (SELECT id FROM $wpdb->springest_groups WHERE slug = %s LIMIT 1)", $subjectSlug, $groupSlug))) {
            return true;
        }
        return false;
    }
    
    /**
    * Display a warning in the WP admin
    */
    function displayWarning() {
        switch($this->warningType) {
            case "missing-base-page" :
                echo '<div class="updated fade"><p>'.__('<strong>Warning:</strong> You need to <a href="options-general.php?page=springest-settings">select the base page for your Springest installation</a>.', 'springest').'</p></div>';
            break;
        }
    }
    
    /**
    * Filter the title for a Springest page. This is called by the "the_title" filter.
    *
    * @param string $title The old title
    * @return string The new title
    */
    function title($title) {
        if(in_the_loop() && !$this->processed) {
            return $this->pageTitle;
        }
        return $title;
    }
    
    /**
    * Filter the content for a Springest page. This is called by the "the_content" filter.
    *
    * @param string $content The old content
    * @return string The new content
    */
    function content($content) {
        if(in_the_loop() && !$this->processed) {
            $content = "";
            if(get_option('springest_show_breadcrumbs')) {
                $content .= $this->getBreadcrumbs();
            }
            $content .= $this->pageContent;
            if(get_option('springest_show_search')) {
                $content .= $this->getSearchForm();
            }
            $content .= $this->addSignature();
            $this->processed = true;
        }
        return $content;
    }
    
    /**
    * Return a string with the page breadcrumbs
    *
    */
    function getBreadcrumbs() {
        if($this->pageBreadcrumbs) {
            return '<div class="springest-breadcrumbs">'.$this->pageBreadcrumbs.'</div>';
        }
    }
    
    /**
    * Filter the HTML title for a Springest page. This is called by the "wp_title" filter.
    *
    * @param string $title The old HTML title
    * @return string $title The new HTML title
    */
    function htmlTitle($title) {
        if($this->htmlTitle) {
            return $this->htmlTitle;
        }
        return $title;
    }
    
    /**
    * Generate the Springest homepage
    *
    * @return string The HTML for the Springest homepage
    */
    function homepage() {

        $groupModel = new SpringestGroup();
        $groups = $groupModel->getAllGroups();

        $output = "";
        if(count($groups)) {
            foreach($groups as $_group) {
                $group = new SpringestGroup($_group->id);
                $output .= '<div class="springest-group">';

                # If subjects are displayed on home page, group titles are not linked
                if(get_option('springest_show_subjects', 1) == 1) {
                    $output .= '<h2>'.$group->name.'</h2>';
                }
                else {
                    $output .= '<h2><a href="'.trailingslashit(get_option('siteurl').'/'.SPRINGEST_BASE.'/'.$group->slug).'">'.$group->name.'</a></h2>';
                }
                if($group->searchStrings && get_option('springest_show_subjects', 1) == 1) {
                    $output .= '<ul>';
                    foreach($group->searchStrings as $searchString) {
                        $output .= '<li>';
                        $output .= '<a href="'.get_option('siteurl').'/'.SPRINGEST_BASE.'/'.$group->slug.'/'.$searchString->slug.'">'.$searchString->name.'</a>';
                        $output .= '</li>';
                    }
                    $output .= '</ul>';
                }
                $output .= '</div>';
            }        
        }
        return $output;
    }
    
    function addSignature() {
        global $SpringestDomains;
        $extension = get_option("springest_domain");
        $domain = $SpringestDomains[$extension];
        $logo = '<img width="60" height=""32" src="'.WP_PLUGIN_URL.'/'.plugin_basename('springest-partners').'/logo-springest.gif" alt="'.__('Find and compare training programmes and courses - Springest', 'springest').'" />';
        $footer_message = '<div class="springest-credits"><small>'.sprintf(__('In cooperation with <a href="%s">%s</a>', 'springest'), $domain['home'], $logo).'</small></div>';
        $footer_message .= "\n";
        $footer_message .= '<!-- Page generated by Springest Partners for WordPress version '.SPRINGEST_VERSION.' For more information see: http://www.wordpress.org/extend/plugins/springest-partners/ -->';
        $footer_message .= "\n";
        return $footer_message;
    }
    
    function loadCSS() {
         echo '<link type="text/css" rel="stylesheet" href="'.plugins_url('springest.css', SPRINGEST_PATH).'" />';
     }
    
    function getRelatedTrainings($post_id = null, $size = 5) {
        if(!$post_id) {
            return;
        }
        global $wpdb;
        $trainings = array();
        $subjects = $this->getRelatedSubjectsForPost($post_id);
        if($subjects) {
            # Get one random subject
            shuffle($subjects);
            foreach($subjects as $subject) {
                $api = new SpringestApi();
                $subject_trainings = $api->getTrainings($subject, 0, $size);
                if(isset($subject_trainings->trainings)) {
                    $trainings = $subject_trainings->trainings;
                    break;
                }
            }
        }
        return $trainings;
    }
    
    function getRelatedSubjectsForPost($post_id) {
        $taxonomies = get_option('springest_relation_taxonomies');
        $subjects = array();
        if(count($taxonomies)) {
            $terms = array();
            foreach($taxonomies as $taxonomy) {
                $post_tax = wp_get_post_terms($post_id, $taxonomy, array("fields" => "ids"));
                if($post_tax) {
                    foreach($post_tax as $term) {
                        $terms[] = $term;
                    }
                }
            }
            if($terms) {
                $group_ids = $this->getRelatedGroupsForTerms($terms);
                if($group_ids) {
                    $subjects = $this->getRemoteSubjectsForGroups($group_ids);
                    echo '<!--';
                    print_r($group_ids);
                    print_r($subjects);
                    echo '-->';
                }
            }
        }
        return $subjects;
    }
    
    function getRelatedGroupsForTerms($terms = array()) {
        global $wpdb;
        return $wpdb->get_col(
            $wpdb->prepare(
                "SELECT
                    id
                    FROM
                    $wpdb->springest_groups
                    LEFT JOIN
                    $wpdb->springest_relations
                    ON
                    $wpdb->springest_groups.id = $wpdb->springest_relations.group_id
                    WHERE
                    $wpdb->springest_relations.term_id IN (%s)
                ", implode(",", $terms)
            )
        );
    }
    
    function getRemoteSubjectsForGroups($group_ids = array()) {
        global $wpdb;
        return $wpdb->get_col(
            $wpdb->prepare(
                "SELECT
                    remote_id
                    FROM
                    $wpdb->springest_subjects
                    WHERE
                    group_id IN (%s)
                ", implode(",", $group_ids)
            )
        );
    }

    /**
    * Throw a 404 page of invalid URLs
    */
    function generate404() {
        global $wp_query;
        header("HTTP/1.0 404 Not Found - Archive Empty");
        $wp_query->set_404();
        require TEMPLATEPATH.'/404.php';
        exit;
    }
    
    # For debugging
    function showRules() {
        global $wp_rewrite, $wp_query;
        print_r($wp_query);
        echo '<hr />';
    	print_r($wp_rewrite->rewrite_rules());
    	echo '<hr />';
    }

}

?>