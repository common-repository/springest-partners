<?php
/**
* @package Springest for Wordpress
*/

class SpringestInstitute {
    
    var $id;
    var $name;
    var $logo;
    var $short_name;

    /**
    * Initiate an institute object
    *
    * @param int $id The ID of the institute to acquire
    */    
    function __construct($id, $properties = null) {
        if(!isset($properties)) {
            # Load up Springest API
            $api = new SpringestApi();
            # Call the API for the institute data
            $properties = $api->getInstitute($id);
        }
        if($properties) { 
            $this->id = $properties->id;
            $this->name = $properties->name;
            $this->logo = $properties->logo;
            $this->short_name = $properties->short_name;
        }
    } 
        
}