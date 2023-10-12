<?php

require_once('mpcf-modules.php');
require_once('mpcf-actions.php');
require_once('mpcf-register-metaboxes.php');
require_once('mpcf-register-taxonomy-metaboxes.php');
require_once('mpcf-register-archive-metaboxes.php');
require_once('mpcf-revisions.php');
require_once('gui.php');

if (file_exists(__DIR__ . '/mpcf-pluginsettings.php'))
    require_once('mpcf-pluginsettings.php');


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


/*****************************************************
	Dev helper functions
 *****************************************************/

function mpcf_dev_only($user = '') {
	if (!is_user_logged_in() || empty($user)) return false;
	if (is_numeric($user) && get_current_user_id() == $user) return true;
	$currentUser = wp_get_current_user();
	if (is_string($user)  && ($currentUser->user_login == $user || $currentUser->display_name == $user)) return true;
	return false;
}

function mpcf_dump($var) {
	if (!is_user_logged_in()) return;

	echo '<div class="mpcf-dump" style="position: fixed; top: 0px; left: 0px; z-index: 999999; max-width: 100vw; max-height: 100vh; overflow: scroll; background: #FFF; padding: 10px; border: 1px solid #DDD; font: 12px/1.3 Helvetica; color: #000;"><pre>';
	var_dump($var);
	echo '</pre></div>';
}



?>