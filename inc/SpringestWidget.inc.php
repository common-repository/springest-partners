<?php
class WP_Widget_Springest extends WP_Widget {

    /**
     * WP_Widget_Springest - Constructor function 
     */
	function WP_Widget_Springest() {
		$widget_ops = array('classname' => 'widget_springest', 'description' => __("Springest", 'springest'));
		$this->WP_Widget('widget_springest', __('Springest', 'springest'), $widget_ops);
	}
	
	/**
	 * widget - Standard function called to display widget contents
	 *
	 * @param array $args Display arguments including before_title, after_title, before_widget, and after_widget.
 	 * @param array $instance The settings for the particular instance of the widget
	 */
	function widget($args, $instance) {
	    global $SpringestHelper;
	    extract($args);
	    
	    echo $before_widget;
	    
	    echo $before_title;
	    echo $instance['title'];
	    echo $after_title;
	    
	    $trainings_displayed = false;	    
	    if(is_single()) {
	        global $post;
    	    $relatedTrainings = $SpringestHelper->getRelatedTrainings($post->ID, 5);
    	    if($relatedTrainings) {
    	        echo '<ul>';
    	        foreach($relatedTrainings as $training) {
    	            echo '<li>';
    	            echo '<a href="';
    	            echo get_option('siteurl').'/'.SPRINGEST_BASE.'/'.$training->id;
    	            echo '">';
    	            echo $training->name;
    	            echo '</a>';
    	            echo '</li>';
    	        }
    	        echo '</ul>';
    	        $trainings_displayed = true;
    	    }
    	}
    	if(!$trainings_displayed) {
    	    $_group = new SpringestGroup();
    	    $groups = $_group->getAllGroups();
    	    if($groups) {
    	        echo '<ul>';
    	        foreach($groups as $group) {
    	            echo '<li>';
    	            echo '<a href="';
    	            echo get_option('siteurl').'/'.SPRINGEST_BASE.'/'.$group->slug; 
    	            echo '">';
    	            echo $group->name;
    	            echo '</a>';
    	            echo '</li>';
    	        }
    	        echo '</ul>';
    	    }
    	}
        
        if($instance['show_search']) {
            echo '<form action="'.get_option('siteurl').'/'.SPRINGEST_BASE.'" method="get" class="springest-search-form">';
            echo '<fieldset><label for="s_ts">'.__('Search trainings', 'springest').':</label> ';
            echo '<input type="text" id="s_ts" name="ts" size="30"';
            echo (isset($_GET['ts'])) ? ' value="'.$_GET['ts'].'"' : '';
            echo ' class="springest-search-field">';
            echo '<input type="submit" value="'.__('Search', 'springest').'" class="springest-search-submit">';
            echo '</fieldset>';
            echo '</form>';
        }
	    echo $after_widget;
	    
	 }
	 
	 /**
	  * form - Create the form for the widget
	  *
	  * @param array $instance Current settings
	  */
	 function form($instance) {
	 	$instance = wp_parse_args((array) $instance, array('title' => '', 'show_search' => 1));
	 	$title = esc_attr($instance['title']);
	 	$show_search = (int) $instance['show_search'];
	 	echo '<p><label for="'.$this->get_field_id('title').'">'.__('Title', 'springest').':</label> <input class="widefat" id="'.$this->get_field_id('title').'" name="'.$this->get_field_name('title').'" type="text" value="'.$title.'"></p>';
	 	echo '<p><label for="'.$this->get_field_id('show_search').'"><input class="checkbox" id="'.$this->get_field_id('show_search').'" name="'.$this->get_field_name('show_search').'" type="checkbox" '.(($show_search==1)? 'checked="checked"' : '').'" /> '.__('Display search box', 'springest').'</label></p>';
	 }
	 
	 /**
	  * update - Save the settings for the widgets
	  *
	  * @param array $new_instance New settings for this instance as input by the user via form()
	 	 * @param array $old_instance Old settings for this instance
	  */
	 function update( $new_instance, $old_instance ) {
	 	$instance = $old_instance;
	 	$instance['title'] = $new_instance['title'];
	 	$instance['show_search'] = $new_instance['show_search'] ? 1 : 0;
	 	return $instance;
	 }
	 

}
?>