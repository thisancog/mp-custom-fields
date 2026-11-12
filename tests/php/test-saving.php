<?php

/*****************************************************
	Saving only complete submissions (inc/mpcf-submission.php)
 *****************************************************/

require __DIR__ . '/bootstrap.php';

//	stand-ins for the plugin functions used when saving posts
function mpcf_get_metaboxes_for_type() { return array('box' => array('type' => 'metabox', 'panels' => array(array('fields' => array(array('name' => 'subtitle', 'type' => 'text')))))); }
function mpcf_add_bulk_copypaste_panels($id, $panels) { return array('panels' => $panels); }
function mpcf_mksafe($value) { return $value; }
function mpcf_before_save($field, $id, $value) { return $value; }
function mpcf_after_save() {}

require MPCF_PLUGIN . '/inc/mpcf-ajax.php';
require MPCF_PLUGIN . '/inc/mpcf-submission.php';
require MPCF_PLUGIN . '/inc/gui.php';


function save_post_with_marker($marker) {
	global $wp;
	$wp['caps'] = array('edit_post');
	$_POST = array('mpcf_meta_box_nonce_box' => 'x', 'subtitle' => 'Hello');
	if ($marker !== null) $_POST['mpcf-interface-ready'] = $marker;

	mpcf_save_meta_boxes(12);
	return get_post_meta(12, 'subtitle');
}

function notice_shown() {
	ob_start();
	mpcf_show_incomplete_submission_notice();
	return trim(ob_get_clean()) !== '';
}


echo "Saving\n";

test('a complete submission is saved', function() {
	assert_same('Hello', save_post_with_marker('1'), 'saved value');
	assert_true(!notice_shown(), 'no notice');
});

test('a submission sent while fields were loading is not saved, the user gets a notice', function() {
	assert_same('', save_post_with_marker('0'), 'saved value');
	assert_true(notice_shown(), 'notice');
	assert_true(!notice_shown(), 'notice shown only once');
});

test('submissions without the marker (e.g. quick edit) are saved as before', function() {
	assert_same('Hello', save_post_with_marker(null), 'saved value');
});

test('every box contains the marker with the value 0', function() {
	ob_start();
	mpcf_interface_ready_input();
	$html = ob_get_clean();

	assert_true(strpos($html, 'name="mpcf-interface-ready"') !== false && strpos($html, 'value="0"') !== false, 'marker markup: ' . trim($html));
});
