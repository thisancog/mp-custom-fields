<?php

/*
Plugin Name: MP Custom Fields
Plugin URI: http://www.matthias-planitzer.de/
Description: A plugin to easily create a backend custom field interface.
Author: Matthias Planitzer
Version: 1.5
*/

/*****************************************
	Actions
 *****************************************/

require_once('inc/includes.php');

add_action('add_meta_boxes', 'mpcf_add_metaboxes');
add_action('admin_init', 'mpcf_admin_init');
add_action('admin_menu', 'mpcf_setup_theme_admin_menu');
add_action('init', 'mpcf_init');
add_action('plugins_loaded', 'mpcf_load_textdomain');
add_action('post_edit_form_tag', 'mpcf_update_edit_form');
add_action('save_post', 'mpcf_save_meta_boxes', 10, 2);
add_action('wp_ajax_mpcf_get_repeater_row', 'mpcf_ajax_get_repeater_row');
add_action('wp_ajax_mpcf_get_conditional_fields', 'mpcf_ajax_get_conditional_fields');
add_action('wp_ajax_mpcf_get_conditional_panels_fields', 'mpcf_ajax_get_conditional_panels_fields');

add_filter('admin_body_class', 'mpcf_filter_admin_body_class');

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
		update_option('mpcf_options', mpcf_default_settings());
	
	mpcf_register_modules();
}

function mpcf_default_settings() {
	$options = array(
		'googlemapskey'		=> '',
		'multilingualclass'	=> 'mpcf-multilingual',
		'showcopypastebulk'	=> true,
		'includerevisions'	=> true,
	);

	return $options;
}


function mpcf_deactivate() {
	flush_rewrite_rules();
}

function mpcf_admin_init() {
	$dependencies = array('jquery', 'jquery-ui-sortable');
	$language = array (
		'addFile' 			=> __('Add file', 'mpcf'),
		'addMedia' 			=> __('Add', 'mpcf'),
		'change' 			=> __('Change', 'mpcf'),
		'changeMedia' 		=> __('Change media', 'mpcf'),
		'chooseMedia' 		=> __('Choose media', 'mpcf'),
		'editBoxHeading'	=> __('Edit meta box: %s', 'mpcf'),
		'editBoxPanel'		=> __('Panel: %s', 'mpcf'),
		'filesSelected' 	=> __('files selected', 'mpcf'),
		'fileUpload'		=> __('Upload file', 'mpcf'),
		'remove'	 		=> __('Remove', 'mpcf'),
	);

	$ver = filemtime(plugin_dir_path(__FILE__) . '/inc/js/admin.js');
	wp_localize_script('mpcf-admin-script', 'localizedmpcf', $language);
	wp_register_script('mpcf-admin-script', plugins_url('inc/js/admin.js', __FILE__), $dependencies, $ver);
//	wp_register_script('mpcf-admin-script', plugins_url('inc/js/admin.min.js', __FILE__), $dependencies, $ver);

	mpcf_add_metaboxes_to_taxonomies();
}


function mpcf_setup_theme_admin_menu() {
	global $pagenow;
	
	$o = get_option('mpcf_options');
	$mapskey = (isset($o['googlemapskey']) ? $o['googlemapskey'] : false);

	$dependencies = array('jquery', 'jquery-ui-sortable', 'wp-color-picker');

	if (did_action('wp_enqueue_media') === 0) {
		wp_enqueue_media();
	}

	wp_enqueue_editor();

	$ver = filemtime(plugin_dir_path(__FILE__) . 'inc/js/admin.js');
	wp_enqueue_script('mpcf-admin-script', plugins_url('inc/js/admin.js', __FILE__), $dependencies, $ver);
//	wp_enqueue_script('mpcf-admin-script', plugins_url('inc/js/admin.min.js', __FILE__), $dependencies, $ver);

	if ($mapskey) {
		wp_enqueue_script('mpcf-google-maps', 'https://maps.googleapis.com/maps/api/js?key=' . $mapskey . '&libraries=places&callback=initGoogleMap');
	}
	
	$verCSS = filemtime(plugin_dir_path(__FILE__) . 'inc/admin.css');

	wp_enqueue_style('mpcf-admin-styles', plugins_url('inc/admin.css', __FILE__), array(), $verCSS);
	wp_enqueue_style('wp-color-picker');

	mpcf_add_metaboxes_to_archives();
}


?>
