<?php

/*
Plugin Name: MP Custom Fields
Plugin URI: http://www.matthias-planitzer.de/
Description: A plugin to easily create a backend custom field interface.
Author: Matthias Planitzer
*/

/*****************************************
	Actions
 *****************************************/

require_once('inc/includes.php');

add_action('admin_init', 'mpcf_admin_init');
add_action('admin_menu', 'mpcf_setup_theme_admin_menu');
add_action('init', 'mpcf_init');
add_action('plugins_loaded', 'mpcf_load_textdomain');
add_action('post_edit_form_tag', 'mpcf_update_edit_form');
add_action('save_post', 'mpcf_save_meta_boxes', 10, 2);

register_deactivation_hook(__FILE__, 'mpcf_deactivate');

function mpcf_load_textdomain() {
	load_plugin_textdomain('mpcf', false, plugin_basename(dirname( __FILE__ )) . '/inc/languages/');
}


/*****************************************
	Init on admin pages
 *****************************************/

function mpcf_init() {
	$o = get_option('mpcf_options');
	if (!isset($o) || empty($o))
		update_option('mpcf_options', mpcf_default_options());
	
	mpcf_register_modules();
}


function mpcf_deactivate() {
	flush_rewrite_rules();
}

function mpcf_admin_init() {
	$language = array (
		'addMedia' 			=> __('Add media', 'mpcf'),
		'changeMedia' 		=> __('Change media', 'mpcf'),
		'chooseMedia' 		=> __('Choose media', 'mpcf'),
		'filesSelected' 	=> __('files selected', 'mpcf'),
		'fileUpload'		=> __('Upload file', 'mpcf'),
		'remove'	 		=> __('Remove', 'mpcf'),
	);
	wp_localize_script('mpcf-admin-script', 'localizedmpcf', $language);
	wp_register_script('mpcf-admin-script', plugins_url('inc/admin.js', __FILE__), array('jquery', 'jquery-ui-sortable'));
//	wp_register_script('mpcf-admin-script', plugins_url('inc/admin.min.js', __FILE__), array('jquery', 'jquery-ui-sortable'));
}

function mpcf_setup_theme_admin_menu() {
	$o = get_option('mpcf_options');
	$mapskey = (isset($o['googlemapskey']) ? $o['googlemapskey'] : false);

	add_options_page(__('MP Custom Fields', 'mpcf'), __('MP Custom Fields', 'mpcf'), 'manage_options', 'mpcf-options', 'mpcf_options');

	wp_enqueue_script('mpcf-admin-script', plugins_url('inc/admin.js', __FILE__), array('jquery', 'jquery-ui-sortable', 'wp-color-picker'));
//	wp_enqueue_script('mpcf-admin-script', plugins_url('inc/admin.min.js', __FILE__), array('jquery', 'jquery-ui-sortable', 'wp-color-picker'));

	if ($mapskey) {
		wp_enqueue_script('mpcf-google-maps', 'https://maps.googleapis.com/maps/api/js?key=' . $mapskey . '&libraries=places&callback=initGoogleMap');
	}
	

	wp_enqueue_style('mpcf-admin-styles', plugins_url('inc/admin.css', __FILE__));
	wp_enqueue_style('wp-color-picker');

	mpcf_add_metaboxes();
}


?>