<?php
/**
* @package Springest for Wordpress
*/

add_action('admin_menu', 'SA_reg_admin');
add_action('admin_menu', 'SA_reg_settings');
add_action('admin_menu', 'SA_load_javascript');
add_action('admin_head', 'SA_load_styles');
add_action('admin_head', 'SA_load_local_js');

function SA_reg_admin() {
	add_menu_page(__('Springest', 'springest'), 'Springest', 7, 'springest-management', 'SA_groups');
}

function SA_reg_settings() {
    add_submenu_page('options-general.php', __('Springest Settings', 'springest'), __('Springest', 'springest'), 'manage_options', 'springest-settings', 'SA_settings' );
}

function SA_load_javascript() {
    $pluginDir = get_settings('siteurl').'/wp-content/plugins/'.dirname(plugin_basename(__FILE__));
	wp_enqueue_script('springest-admin', $pluginDir . '/springest-admin.js');
	//wp_enqueue_style( 'jquery-ui-autocomplete', plugins_url( 'css/jquery-ui-1.8.2.custom.css', __FILE__ ) );
	wp_enqueue_script('jquery-ui-autocomplete');
	
}

function SA_load_styles() {
    $pluginDir = get_settings('siteurl').'/wp-content/plugins/'.dirname(plugin_basename(__FILE__));
	echo '<link rel="stylesheet" href="'.$pluginDir.'/springest-admin.css" type="text/css" />';
}

function SA_load_local_js() {
    global $SpringestDomains;
    $extension = get_option('springest_domain', 'nl');
    $domain = $SpringestDomains[$extension];
    
    # Set API URL
    $apiURL = esc_url(trailingslashit($domain['api_url']));
    $apiURL .= "autocomplete/%s.jsonp?api_key=".get_option('springest_public_api_key');
    
    echo '<script type="text/javascript">';
    echo "\n";
    echo 'api_url = "'.$apiURL.'"';
    echo "\n";
    echo '</script>';
}

function SA_settings() {

    global $SpringestDomains;

    $SpringestHelper = new SpringestHelper();

    $SpringestHelper->checkTransferMethods();
    
    if($_SERVER['REQUEST_METHOD'] == "POST") {
        
        # Save API url
        update_option('springest_domain', $_POST['SA_domain']);
        
        # Test API key
        $apiKey = esc_attr($_POST['SA_api_key']);
        $api = new SpringestApi();
        $apiKeyError = false;
        if(!$api->testKey($apiKey)) {
            echo '<div class="error below-h2">';
            echo '<p><strong>'.__('WARNING', 'springest').'</strong>: '.__('API key and/or API URL <strong>invalid</strong>.', 'springest').'</p>';
            echo '</div>';
            $apiKeyError = true;
        }
        update_option('springest_api_key', $apiKey);

        # Save "show subjects" option
        $showSubjects = (int) $_POST['SA_show_subjects'];
        update_option("springest_show_subjects", $showSubjects);
        
        # Save "show search" option
        $showSearch = (int) $_POST['SA_show_search'];
        update_option("springest_show_search", $showSearch);
        
        # Save "show breadcrumbs" option
        $showBreadcrumbs = (int) $_POST['SA_show_breadcrumbs'];
        update_option("springest_show_breadcrumbs", $showBreadcrumbs);
        
        # Save "load front-end CSS" option
        $loadCSS = (int) $_POST['SA_load_css'];
        update_option("springest_load_css", $loadCSS);
        
        # Save base page ID
        if(isset($_POST['SA_base_page']) && $_POST['SA_base_page'] > 0) {
            $basePageId = (int) $_POST['SA_base_page'];
            $SpringestHelper->updateBasePage($basePageId);
        }
        else {
            delete_option('springest_base_page');
        }
        
        # Save trainings per page
        if(isset($_POST['SA_trainings_per_page'])) {
        	$trainingsPerPage = (int) $_POST['SA_trainings_per_page'];
        	update_option('springest_trainings_per_page', $trainingsPerPage);
        }
        
        # Save relation taxonomies
        if(isset($_POST['SA_relation_taxonomies'])) {
            update_option('springest_relation_taxonomies', $_POST['SA_relation_taxonomies']);
        }
        else {
            update_option('springest_relation_taxonomies', null);
        }
        
        if(!$apiKeyError) {
            echo '<div id="setting-error-settings_updated" class="updated settings-error"><p><strong>'.__('Settings saved', 'springest').'</strong></p></div>';
        }
    }
    
    $apiKey = get_option('springest_api_key', '');
    $relationTaxonomies = get_option('springest_relation_taxonomies', array('post_tag', 'category'));
    $exclude = array('nav_menu', 'link_category', 'post_format');
    $basePage = get_option('springest_base_page');
    $trainingsPerPage = get_option('springest_trainings_per_page', 15);
        
    echo '<div class="wrap">';
    echo '<div id="icon-edit" class="icon32 icon32-posts-post"><br></div>';
    echo '<h2>'.__('Edit Springest settings', 'springest').'</h2>';
    echo '<form action="?page=springest-settings" method="post">';
    
    echo '<p style="float:right; margin: 20px 20px 10px 0"><a href="admin.php?page=springest-management">'.__('Edit Springest groups', 'springest').'</a></p>';
    
    echo '<table class="form-table">';
    echo '<tbody>';
    echo '<tr valign="top">';
    echo '<th scope="row"><label for="SA_domain">'.__('Springest API', 'springest').'</label></th>';
    echo '<td>';
    
    # Generate list of available API's
    echo '<select name="SA_domain" id="SA_domain" class="large-text">';
    echo $currentExtension = get_option("springest_domain");
    foreach($SpringestDomains as $extension => $domain) {
        echo '<option value="'.$extension.'"';
        echo ($currentExtension == $extension) ? ' selected="selected"' : '';
        echo '>'.$domain['name'].' ('.$domain['api_url'].')</option>';
    }    
    echo '</select>';
    
    echo '</tr>';
    echo '<tr valign="top">';
    echo '<th scope="row"><label for="SA_api_key">'.__('Springest API key', 'springest').'</label></th>';
    echo '<td><input type="text" name="SA_api_key" id="SA_api_key" value="'.$apiKey.'" class="large-text" /></td>';
    echo '</tr>';
    echo '<tr valign="top">';
    echo '<th scope="row"><label for="SA_trainings_per_page">'.__('Trainings per page', 'springest').'</label></th>';
    echo '<td><select name="SA_trainings_per_page" id="SA_trainings_per_page">';
    foreach(array(5, 10, 15, 25, 50) as $num) {
	    echo '<option value="'.$num.'"';
	    echo ($trainingsPerPage == $num) ? ' selected="selected"' : '';
	    echo '>'.$num.'</option>';
	}
    echo '</select></td>';
    echo '</tr>';
    echo '<tr valign="top">';
    echo '<th scope="row"><label for="">'.__('Display search queries / subjects on trainings overview page', 'springest').'</label></th>';
    echo '<td>';
    echo '<p><label for="SA_show_subjects_yes"><input type="radio" name="SA_show_subjects" id="SA_show_subjects_yes" value="1" '.((get_option('springest_show_subjects', 1) == 1) ? ' checked="checked"' : '').'/> '.__('Yes', 'springest').'</label> &nbsp; &nbsp;';
    echo ' <label for="SA_show_subjects_no"><input type="radio" name="SA_show_subjects" id="SA_show_subjects_no" value="0" '.((get_option('springest_show_subjects', 1) != 1) ? ' checked="checked"' : '').' /> '.__('No', 'springest').'</label></p>';
    echo '</td>';
    echo '</tr>';
    echo '<tr valign="top">';
    echo '<th scope="row"><label for="">'.__('Automatically display Springest search box', 'springest').'</label></th>';
    echo '<td>';
    echo '<p><label for="SA_show_search_yes"><input type="radio" name="SA_show_search" id="SA_show_search_yes" value="1" '.((get_option('springest_show_search', 1) == 1) ? ' checked="checked"' : '').'/> '.__('Yes', 'springest').'</label> &nbsp; &nbsp;';
    echo ' <label for="SA_show_search_no"><input type="radio" name="SA_show_search" id="SA_show_search_no" value="0" '.((get_option('springest_show_search', 1) != 1) ? ' checked="checked"' : '').' /> '.__('No', 'springest').'</label></p>';
    echo '</td>';
    echo '</tr>';
    echo '<tr valign="top">';
    echo '<th scope="row"><label for="">'.__('Automatically display breadcrumbs', 'springest').'</label></th>';
    echo '<td>';
    echo '<p><label for="SA_show_breadcrumbs_yes"><input type="radio" name="SA_show_breadcrumbs" id="SA_show_breadcrumbs_yes" value="1" '.((get_option('springest_show_breadcrumbs', 1) == 1) ? ' checked="checked"' : '').'/> '.__('Yes', 'springest').'</label> &nbsp; &nbsp;';
    echo ' <label for="SA_show_breadcrumbs_no"><input type="radio" name="SA_show_breadcrumbs" id="SA_show_breadcrumbs_no" value="0" '.((get_option('springest_show_breadcrumbs', 1) != 1) ? ' checked="checked"' : '').' /> '.__('No', 'springest').'</label></p>';
    echo '</td>';
    echo '</tr>';
    echo '<tr valign="top">';
    echo '<th scope="row"><label for="">'.__('Load default Springest CSS', 'springest').'</label></th>';
    echo '<td>';
    echo '<p><label for="SA_load_css_yes"><input type="radio" name="SA_load_css" id="SA_load_css_yes" value="1" '.((get_option('springest_load_css', 1) == 1) ? ' checked="checked"' : '').'/> '.__('Yes', 'springest').'</label> &nbsp; &nbsp;';
    echo ' <label for="SA_load_css_no"><input type="radio" name="SA_load_css" id="SA_load_css_no" value="0" '.((get_option('springest_load_css', 1) != 1) ? ' checked="checked"' : '').' /> '.__('No', 'springest').'</label></p>';
    echo '</td>';
    echo '</tr>';
    echo '<tr valign="top">';
    echo '<th scope="row"><label for="SA_api_key">'.__('Springest Base Page', 'springest').'</label></th>';
    echo '<td>';
    $pages = get_posts("post_type=page");
    if(count($pages)) {
        echo '<select name="SA_base_page" class="SA_base_page">';
        echo '<option value="0">'.__('Select page...', 'springest').'</option>';
        foreach($pages as $page) {
            echo '<option value="'.$page->ID.'"';
            echo ($basePage == $page->ID) ? ' selected="selected"' : '';
            echo '>'.$page->post_title.'</option>';
        }
        echo '</select>';
    }
    echo '<a target="_blank" href="post-new.php?post_type=page"> + '.__('Add new page', 'springest').'</a>';
    if($basePage) {
        echo ' | <a target="_blank" href="'.get_permalink($basePage).'">'.__('View base page', 'springest').'</a>';
    }
    echo '</td>';
    echo '</tr>';
    echo '<tr valign="top">';
    echo '<th scope="row"><label>'.__('Taxonomies for relations', 'springest').'</label></th>';
    echo '<td><ul>';
    foreach(get_taxonomies('', 'objects') as $taxonomy) {
        if(!in_array($taxonomy->name, $exclude)) {
            echo '<li><label for="SA_tax_'.$taxonomy->name.'"><input type="checkbox" name="SA_relation_taxonomies[]" value="'.$taxonomy->name.'" id="SA_tax_'.$taxonomy->name.'"';
            if(in_array($taxonomy->name, $relationTaxonomies)) {
                echo ' checked="checked"';
            }
            echo '/> '.$taxonomy->labels->name.'</label></li>';
        }
    }
    echo '</ul></td>';
    echo '</tr>';
    echo '</tbody>';
    echo '</table>';
    echo '<p class="submit"><input type="submit" name="submit" id="submit" class="button-primary" value="'.__('Save changes', 'springest').'"></p>';
    echo '</form></div>';
    
}


function SA_groups() {
    if(isset($_GET['group_id'])) {

        # Check if we are saving
        if($_SERVER['REQUEST_METHOD'] == "POST") {
            
            # Save data
            $id = (isset($_POST['SA_group_id'])) ? (int) $_POST['SA_group_id'] : null;
            $group = new SpringestGroup($id);
            $group->save($_POST);
            
            # Flush rewrite rules
            global $wp_rewrite;
    	   	$wp_rewrite->flush_rules();
            
            if($_GET['group_id'] > 0) {
                # Editing existing group
                SA_group_edit($group->id, 'edit');
            }
            elseif(isset($_POST['SA_group_id'])) {
                SA_group_edit($group->id, 'edit');
            }
            else {
                # Adding new group
                SA_group_edit($group->id, 'new');
            }
            
        }
        else {
            $groupId = (isset($_GET['group_id'])) ? ((int) $_GET['group_id']) : 0;
            # Show empty form for new group
            SA_group_edit($groupId);
        }
    }
    else {
        # List all groups
        SA_group_index();
    }
}


function SA_group_edit($groupId=0, $action=null) {
    
    global $wpdb, $SpringestDomains;
    
    $group = new SpringestGroup($groupId);
    
    echo '<div class="wrap">';
    echo '<div id="icon-edit" class="icon32 icon32-posts-post"><br></div>';
    if($group->id) {
        echo '<h2>'.__('Edit Springest group: ', 'springest').$group->name.' </h2>';
    }
    else {
        echo '<h2>'.__('Add new Springest group', 'springest').' </h2>';
    }
    
    if($action == "new") {
        echo '<div id="setting-error-settings_updated" class="updated settings-error"><p><strong>'.__('New group created', 'springest').'</strong></p></div>';
    }
    elseif($action == "edit") {
        echo '<div id="setting-error-settings_updated" class="updated settings-error"><p><strong>'.__('Group settings saved', 'springest').'</strong></p></div>';
    }
    
    $api = new SpringestApi();
    $springestCategories = $api->getCategories();

    echo '<form method="post" action="">';
    $domain = get_option('springest_domain');
    $api_url = $SpringestDomains[$domain]['api_url'];
    echo '<input type="hidden" name="api_url" value="'.$api_url.'" id="springest_api_url" />';
    echo '<p style="margin: 20px 20px 0 0; float: right; "><a href="admin.php?page=springest-management">« '.__('Back to groups overview', 'springest').'</a> | <a href="admin.php?page=springest-settings">'.__('Edit Springest settings', 'springest').'</a></p>';
    
    echo '<div style="margin: 20px 0 0 10px;width:100%;float:left;"><p class="description">';
    echo __('group_edit_explanation', 'springest');
    echo '</p></div>';
    
    if($group->id > 0) {
        echo '<input type="hidden" name="SA_group_id" value="'.$group->id.'" />';
    }
    echo '<table class="form-table">';
    echo '<tbody>';
    echo '<tr valign="top">';
    echo '<th scope="row"><label for="SA_name">'.__('Name', 'springest').'</label></th>';
    echo '<td><input name="SA_name" type="text" id="SA_name" value="';
    echo ($group->id > 0) ? $group->name : '';
    echo '" class="regular-text"></td>';
    echo '</tr>';
    echo '<tr valign="top">';
    echo '<th scope="row"><label for="SA_slug">'.__('Slug', 'springest').'</label></th>';
    echo '<td><input name="SA_slug" type="text" id="SA_slug" value="';
    echo ($group->id > 0) ? $group->slug : '';
    echo '" class="regular-text"></td>';
    echo '</tr>';
    echo '<tr valign="top">';
    echo '<th scope="row"><label for="SA_description">'.__('Description', 'springest').'</label></th>';
    echo '<td>';
    if (function_exists('wp_editor')) {
        $content = ($group->id > 0) ? stripslashes($group->description) : '';
        $editor_id = "sa_description";
        $settings = array(
            'wpautop' => true,
            'media_buttons' => false,
            'textarea_name' => 'SA_description',
            'tinymce' => array(
//                'plugins' => 'inlinepopups, wpdialogs, wplink, media, wpeditimage, wpgallery, paste, tabfocus',
                'forced_root_block' => false,
                'force_br_newlines' => false,
                'force_p_newlines' => false,
                'height' => 120,
            )
        );
        wp_editor($content, $editor_id, $settings);
    }
    else {
        echo '<textarea name="SA_description" id="SA_description" class="regular-text">';
        echo ($group->id > 0) ? stripslashes($group->description) : '';
        echo '</textarea></td>';
        if (function_exists('wp_tiny_mce')) {
            wp_tiny_mce(false, array(
                'mode' => 'exact',
                'elements' => 'SA_description',
//                'plugins' => 'inlinepopups, wpdialogs, wplink, media, wpeditimage, wpgallery, paste, tabfocus',
                'height' => 120,
                'forced_root_block' => false,
                'force_br_newlines' => false,
                'force_p_newlines' => false 
            ));
        }
    }
    echo '</td></tr>';
    echo '</table>';
    echo '<h3>'.__('Add new search string', 'springest').'</h3>';
    echo '<table class="form-table">';
    echo '<tr id="SA_news_search_string_error"><td colspan="2">'.__('Please provide a title for your search string, and some words to search on','springest').'</td></tr>';
    echo '<tr valign="top">';
    echo '<th scope="row"><label for="SA_add_subject">'.__('Title', 'springest').'</label></th>';
    echo '<td>';
    echo '<input type="text" class="regular-text" value="" id="SA_new_search_string_name" /> <span class="description">'.__('e.g. "Web development"', 'springest').'</span>';
    echo '</td>';
    echo '</tr>';
    echo '<tr valign="top">';
    echo '<th scope="row"><label for="SA_add_subject">'.__('Search string', 'springest').'</label></th>';
    echo '<td>';
    echo '<input type="text" class="regular-text" value="" id="SA_new_search_string_value" /> <span class="description">'.__('e.g. "HTML CSS PHP WordPress"', 'springest').'</span>';
    echo '</td>';
    echo '</tr>';
    echo '<tr valign="top">';
    echo '<th scope="row">&nbsp;</th>';
    echo '<td>';
    echo '<button class="button" id="SA_add_search_string">'.__('Add search string', 'springest').'</button>';
    echo '</td>';
    echo '</tr>';
    echo '<tr valign="top">';
    echo '<th scope="row" style="padding-top:20px"><label for="SA_subjects">'.__('Search strings in this group', 'springest').'</label></th>';
    echo '<td style="padding-top:20px">';
    echo '<ul class="SA_selected_search_strings SA_search_string_list" id="SA_selected_search_strings">';
    if(count($group->searchStrings)) { 
        $n = 0;
        foreach($group->searchStrings as $searchString) {
            echo '<li>'.$searchString->name.' <a href="#" class="SA_remove_search_string"><img src="images/xit.gif" alt="'.__('remove', 'springest').'" title="'.__('remove', 'springest').'" /></a><br/>';
            echo '<span class="description">'.$searchString->value.'</span>';
            echo '<input type="hidden" name="SA_search_strings['.$n.'][name]" value="'.$searchString->name.'" />';
            echo '<input type="hidden" name="SA_search_strings['.$n.'][value]" value="'.$searchString->value.'" />';
            echo '</li>';
            $n++;
        } 
    }
    else {
        echo '<li class="SA_empty" id="SA_search_strings_empty">'.__('This group does not contain any search strings', 'springest').'</li>';
    }
    echo '</ul>';
    echo '</td>';
    echo '</tr>';
    
    
    $relationTaxonomies = get_option('springest_relation_taxonomies', array('category', 'post_tag'));
    if(count($relationTaxonomies)) {
        foreach($relationTaxonomies as $taxonomyName) {
            $taxonomy = get_taxonomy($taxonomyName);
            echo '<tr valign="top">';
            echo '<th scope="row"><label for="SA_relations">'.__('Related', 'springest').' '.strtolower($taxonomy->labels->name).'</label></th>';
            echo '<td><div class="SA_taxonomy">';
            $terms = get_terms($taxonomy->name, array('hide_empty'=>false));
            if(count($terms)) {
                echo '<ul>';
                foreach($terms as $term) {
                    echo '<li><label for="SA_related_term_'.$term->term_id.'"><input type="checkbox" id="SA_related_term_'.$term->term_id.'" name="SA_related_terms[]" value="'.$term->term_id.'"';
                    echo (in_array($term->term_id, $group->relations)) ? 'checked="checked"' : '';
                    echo '> '.$term->name.'</label></li>';
                }
                echo '</ul>';
            }
            else {
                echo '<p class="empty">'.sprintf(__('No %s available', 'springest'), strtolower($taxonomy->labels->name)).'</p>';
            }
            echo '</div></td>';
            echo '</tr>';
        }
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '<p class="submit"><input type="submit" name="submit" id="submit" class="button-primary" value="'.__('Save changes', 'springest').'"></p>';
    echo '</form></div>';
}

function SA_group_index() {

    # Delete group?
    if(isset($_GET['delete']) && wp_verify_nonce($_GET['_wpnonce'])) {
        $id = (int) $_GET['delete'];
        $_group = new SpringestGroup($id);
        $_group->delete();
    }

    echo '<div class="wrap">';
    echo '<div id="icon-edit" class="icon32 icon32-posts-post"><br></div>';
    echo '<h2>'.__('Springest groups', 'springest').' <a href="?page=springest-management&group_id=0" class="add-new-h2">'.__('Add new', 'springest').'</a> </h2>';
    echo '<form action="" method="post">';
    echo '<p style="float:right; margin: 20px 0 10px 0"><a href="admin.php?page=springest-settings">'.__('Edit Springest settings', 'springest').'</a></p>';

    $groupModel = new SpringestGroup();
    $groups = $groupModel->getAllGroups();

    if($groups) {
        echo '<table class="wp-list-table widefat fixed posts" cellspacing="0">';
        echo '<thead>';
        echo '<tr>';
        echo '<th scope="col" class="manage-column" style=""><span>'.__('Name', 'springest').'</span></th>'; 
        echo '<th width="10%" scope="col" class="delete-column" style=""></th>'; 
        echo '</tr>';
        echo '</thead>';
        
        echo '<tbody id="the-list">';
        
        $n = 0;
        foreach($groups as $_group) {
            $group = new SpringestGroup($_group->id);
            echo '<tr id="post-1" class="';
            echo ($n % 2) ? 'alternate' : '';
            echo '" valign="top">';
            echo '<td class="post-title page-title column-title"><strong>';
            echo '<a href="admin.php?page=springest-management&group_id='.$group->id.'">';
            echo $group->name;
            echo ' (';
            echo count($group->searchStrings);
            echo ')</a></strong></td>';
            echo '<td width="10%"><a onclick="return confirm(\''.__('Are you sure you want to delete this group?', 'springest').'\')" href="';
            echo wp_nonce_url('admin.php?page=springest-management&delete='.$group->id);
            echo '">'.__('delete','springest').'</a></td>';
            echo '</tr>';
            $n++;
        }
        
        echo '</tbody></table>';
    
    }
    else {
        echo '<p>'.__('No groups','springest').'</p>';
    }

    echo '</form>';
    echo '</div>';
}

?>