<?php
/**
* @package Springest for Wordpress
*/
/*
Plugin Name: Springest for Wordpress
Plugin URI: http://www.springest.com/
Description: Automagically implement the Springest affiliate system using the Springest API
Version: 0.1.4
Author: Super Interactive for Springest
Author URI: http://www.superinteractive.com/
*/

# Include class files
require('inc/SpringestHelper.inc.php');
require('inc/SpringestApi.inc.php');
require('inc/SpringestGroup.inc.php');
require('inc/SpringestSearchString.inc.php');
require('inc/SpringestTraining.inc.php');
require('inc/SpringestInstitute.inc.php');
require('inc/SpringestSearch.inc.php');
require('inc/SpringestWidget.inc.php');

# Set Database version
define('SPRINGEST_DB_VERSION', '0.1.1');
define('SPRINGEST_VERSION', '0.1.3');

# Map Springest tables in WP database object
$wpdb->springest_groups = $wpdb->prefix.'springest_groups';
$wpdb->springest_relations = $wpdb->prefix.'springest_relations';
$wpdb->springest_search_strings = $wpdb->prefix.'springest_search_strings';

# Available Springest API's
$SpringestDomains = array(
    "nl" => array(
        "name" => __("the Netherlands", 'springest'),
        "api_url" => "http://data.springest.nl",
        "home" => "http://www.springest.nl"
    ),
    "be" => array(
        "name" => __("Belgium, Dutch", 'springest'),
        "api_url" => "http://data.nl.springest.be",
        "home" => "http://nl.springest.be"
    ),
    "co.uk" => array(
        "name" => __("United Kingdom", 'springest'),
        "api_url" => "http://data.springest.co.uk",
        "home" => "http://www.springest.co.uk"
    ),
    "de" => array(
        "name" => __("Germany", 'springest'),
        "api_url" => "http://data.springest.de",
        "home" => "http://www.springest.de"
    )
);

# Run at activation 
register_activation_hook(__FILE__, 'SpringestActivate');

# Check for DB upgrade
add_action('admin_init', 'SpringestUpgrade');

# Load text domain
load_plugin_textdomain('springest', false, dirname(plugin_basename(__FILE__)) . '/lang' );

# Initiate helper
$SpringestHelper = new SpringestHelper();

# Initiate AJAX listener
if(is_admin()) {
    require('springest-admin.php');
    add_action('init', array(&$SpringestHelper, 'ajaxListener'));
}

# Define URL base as constant
define('SPRINGEST_BASE', $SpringestHelper->getRewriteBase());

# Define Springest plugin root directory as constant
define('SPRINGEST_PATH', __FILE__);

# Add filters for the custom URL rewriting
add_filter('rewrite_rules_array', array(&$SpringestHelper, 'addRewriteRules'));
add_filter('query_vars', array(&$SpringestHelper, 'insertQueryVar'));

# Load widgets
add_action("widgets_init", "SpringestLoadWidgets");

# Load Springest
add_action('wp', 'SpringestLoad');

function SpringestLoad() {

    global $SpringestHelper;
    
    # If this is a Springest page, get page title and content 
    if($SpringestHelper->springestObject()) {
        $SpringestHelper->loadPage();
    }
}

function SpringestActivate() {
    $SpringestHelper = new SpringestHelper();
    if(!$SpringestHelper->isInstalled()) {
        $SpringestHelper->install();
    }
}

function SpringestUpgrade() {
    $db_version = get_option('springest_db_version');

    if(version_compare($db_version, SPRINGEST_DB_VERSION, '==')) {
        return;
    }

    if(!$current_version || version_compare($current_version, SPRINGEST_DB_VERSION, '<')) {
        
        # Upgrading to 0.1.1
        global $SpringestHelper;
        $SpringestHelper->install();
        update_option('springest_db_version', SPRINGEST_DB_VERSION);

    }

}

function SpringestLoadWidgets() {
    register_widget("wp_widget_springest");
}