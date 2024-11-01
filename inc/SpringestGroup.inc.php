<?php
/**
* @package Springest for Wordpress
*/

class SpringestGroup {
    
    var $id;
    var $name;
    var $slug;
    var $description;
    var $lastModified;
    var $searchStrings = array();
    var $relations = array();
    
    function __construct($id = null) {
        if($id > 0) {
            $this->loadBy('id', $id);
        }
    }

    function loadBy($field, $identifier) {
        global $wpdb;
        switch($field) {
            case "id" :
                $query = $wpdb->prepare("SELECT * FROM $wpdb->springest_groups WHERE id = %d", $identifier);
            break;
            case "slug" :
                $query = $wpdb->prepare("SELECT * FROM $wpdb->springest_groups WHERE slug = %s", $identifier);
            break;
        }
        $result = $wpdb->get_row($query);
        if($result) {
            $this->id = $result->id;
            $this->name = $result->name;
            $this->slug = $result->slug;
            $this->description = $result->description;
            $this->lastModified = $result->last_modified;
            $this->searchStrings = $this->getSearchStrings();
            $this->relations = $this->getRelations();
        }
    }
    
    function getAllGroups() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM $wpdb->springest_groups");
    }

    function getSearchStrings() {
        global $wpdb;
        if($this->id > 0) {
            return $results = $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM $wpdb->springest_search_strings WHERE group_id = %d ORDER BY position ASC", $this->id)
            );
        }
        return array();
    } 
    
    function save($data) {
        
        global $wpdb;
        
        $this->name = esc_attr($data['SA_name']);
        $this->slug = esc_attr($data['SA_slug']);
        $this->description = strip_tags($data['SA_description'],"<a><strong><b><i><em><blockquote><div><span><p><br><hr><q>");
        
        # Save new data to database. Generates new id for new groups
        $this->saveData();
        
        # Clear any existing subjects for this group
        $this->deleteSearchStrings();
        
        if(isset($data['SA_search_strings'])) {
            # Save subjects for this group
            $this->saveSearchStrings($data);
        }
        $this->deleteRelations();
        if(isset($data['SA_related_terms']) && count($data['SA_related_terms'])) {            
            # Save relations for this group
            foreach($data['SA_related_terms'] as $termId) {
                $this->saveRelation($termId);
            }
        }
        return true;
    }
    
    function saveData() {
        global $wpdb;
        if($this->id) {
            $update = $wpdb->query(
                $wpdb->prepare("UPDATE $wpdb->springest_groups SET name = %s, slug = %s, description = %s, last_modified = NOW() WHERE id = %d", $this->name, $this->slug, $this->description, $this->id)
            );
        }
        else {
            $insert = $wpdb->query(
                $wpdb->prepare("INSERT INTO $wpdb->springest_groups (name, slug, description, active, last_modified) VALUES(%s, %s, %s, 1, NOW())", $this->name, $this->slug, $this->description)
            );
            $this->id = $wpdb->insert_id;
        }
        return true;
    }
    
    function delete() {
        if($this->id) {
            global $wpdb;
            # Delete related subjects
            $this->deleteSearchStrings();
            # Delete relations
            $this->deleteRelations();
            # Delete seld
            $wpdb->query(
                $wpdb->prepare("DELETE FROM $wpdb->springest_groups WHERE id = %d LIMIT 1", $this->id)
            );
        }
    }
    
    function saveSearchStrings($data) {
        foreach($data['SA_search_strings'] as $position => $searchString) {
            $newSearchString = new SpringestSearchString();
            $newSearchString->groupId = $this->id;
            $newSearchString->name = esc_attr($searchString['name']);
            $newSearchString->value = esc_attr($searchString['value']);
            $newSearchString->slug = sanitize_title($searchString['name']);
            $newSearchString->position = $position;
            $newSearchString->save();
        }
    }
    
    function deleteSearchStrings() {
        global $wpdb;
        $delete = $wpdb->query(
            $wpdb->prepare("DELETE FROM $wpdb->springest_search_strings WHERE group_id = %d", $this->id)
        );
    }
    
    function getRelations() {
        global $wpdb;
        if($this->id > 0) {
            return $wpdb->get_col(
                $wpdb->prepare("SELECT term_id FROM $wpdb->springest_relations WHERE group_id = %d", $this->id)
            );
        }
        return array();
    }
    
    function deleteRelations() {
        global $wpdb;
        $delete = $wpdb->query(
            $wpdb->prepare("DELETE FROM $wpdb->springest_relations WHERE group_id = %d", $this->id)
        );
    }
    
    function saveRelation($termId) {
        global $wpdb;
        $insert = $wpdb->query(
            $wpdb->prepare("INSERT INTO $wpdb->springest_relations (group_id, term_id) VALUES(%d, %d)", $this->id, $termId)
        );
    }
    
    function createPage() {
        $output = wpautop(stripslashes($this->description));
        if(count($this->searchStrings)) {
            $output .= '<ul>';
            foreach($this->searchStrings as $searchString) {
                $output .= '<li><a href="'.get_option('siteurl').'/'.SPRINGEST_BASE.'/'.$this->slug.'/'.$searchString->slug.'">'.$searchString->name.'</a></li>';
            }
            $output .= '</ul>';
        }
        return $output;
    }
    
}

?>