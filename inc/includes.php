<?php

require_once('modules.php');
require_once('mpcf-settings.php');
require_once('mpcf-register-metaboxes.php');
require_once('mpcf-register-taxonomy-metaboxes.php');
require_once('gui.php');

if (file_exists(__DIR__ . '/mpcf-admin.php'))
    require_once('mpcf-admin.php');


function mpcf_helper_exists_checker() {
	return true;
}

function mpcf_mknice($value) {
	if (!is_array($value)) 	return htmlspecialchars_decode(stripslashes($value));
	else 					return array_map('mpcf_mknice', $value);
}

function mpcf_mksafe($value) {
	if (!is_array($value)) 	return htmlspecialchars($value);
	else 					return array_map('mpcf_mksafe', $value);
}

function mpcf_beautify_string($string) {
	$string = strtolower(htmlentities($string));
	$string = str_replace(get_html_translation_table(), '-', $string);
	$string = str_replace(' ', '-', $string);
	return preg_replace('/[-]+/i', '-', $string);
}

function mpcf_translate_string($string = '') {
	$string = __($string);

	if (function_exists('qtranxf_gettext'))
		$string = qtranxf_gettext($string);

	return $string;
}


function mpcf_filter_admin_body_class() {
	global $current_screen;

	$isActive = false;

	if (!empty($current_screen->taxonomy)) {
		$boxes = mpcf_get_taxonomy_boxes($current_screen->taxonomy);
		if (count($boxes) > 0) $isActive = true;
	}

	return $isActive ? 'mpcf-active' : '';
}



?>