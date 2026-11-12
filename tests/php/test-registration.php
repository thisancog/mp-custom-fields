<?php

/*****************************************************
	Registering and removing boxes
 *****************************************************/

require __DIR__ . '/bootstrap.php';

function get_post_type_object_label() {}
function mpcf_assign_order_to_select_fields($panels) { return $panels; }
function get_user_meta() { return array(); }

require MPCF_PLUGIN . '/inc/mpcf-ajax.php';
require MPCF_PLUGIN . '/inc/mpcf-register-metaboxes.php';
require MPCF_PLUGIN . '/inc/mpcf-register-archive-metaboxes.php';
require MPCF_PLUGIN . '/inc/mpcf-register-taxonomy-metaboxes.php';
require MPCF_PLUGIN . '/inc/mpcf-register-user-metaboxes.php';
require MPCF_PLUGIN . '/inc/mpcf-register-singleton-metaboxes.php';

$version = function($type, $id) { return md5($type . '#' . $id); };


echo "Registration\n";

test('removing an archive box forgets its version and keeps the other boxes\' IDs', function() use ($version) {
	global $wp;
	$wp['options'] = array(
		'mpcf_archive_boxes'    => array('box-project' => array('post_type' => 'project', 'priority' => 0, 'panels' => array()), 'box-news' => array('post_type' => 'news', 'priority' => 0, 'panels' => array())),
		'mpcf_archive_meta'     => array('project' => array('intro' => 'x')),
		'mpcf_metabox_versions' => array($version('archive', 'box-project') => 'a', $version('archive', 'box-news') => 'b')
	);

	mpcf_remove_custom_fields_archive('project');

	assert_same(array('box-news'), array_keys(get_option('mpcf_archive_boxes')), 'remaining boxes');
	assert_same(array($version('archive', 'box-news')), array_keys(get_option('mpcf_metabox_versions')), 'remaining versions');
});

test('"remove all" functions also forget the versions, so boxes can be registered again', function() use ($version) {
	global $wp;
	$wp['options'] = array(
		'mpcf_meta_boxes'      => array('p' => array('type' => 'metabox'), 'm' => array('type' => 'module-metabox')),
		'mpcf_taxonomy_boxes'  => array('t' => array()),
		'mpcf_user_boxes'      => array('u' => array()),
		'mpcf_archive_boxes'   => array('a' => array()),
		'mpcf_singleton_boxes' => array('s' => array()),
		'mpcf_metabox_versions' => array_fill_keys(array($version('post', 'p'), $version('module', 'm'), $version('tax', 't'), $version('user', 'u'), $version('archive', 'a'), $version('singleton', 's')), 'v')
	);

	mpcf_remove_all_custom_fields();
	mpcf_remove_all_custom_fields_taxonomies();
	mpcf_remove_all_custom_fields_user();
	mpcf_remove_all_custom_fields_archives();
	mpcf_remove_all_custom_fields_singleton();

	assert_same(array(), get_option('mpcf_metabox_versions'), 'remaining versions');
});

test('several user profile boxes can be registered', function() {
	global $wp;
	$wp['options'] = array('mpcf_user_boxes' => array('a' => array('roles' => 'all', 'panels' => array()), 'b' => array('roles' => 'all', 'panels' => array())));

	mpcf_add_metaboxes_user();
	assert_true(function_exists('metaBoxLoader'), 'loader declared once');
});
