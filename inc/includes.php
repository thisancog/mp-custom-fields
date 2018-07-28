<?php

require_once('modules.php');
require_once('mpcf-admin.php');
require_once('mpcf-options.php');
require_once('mpcf-register-metaboxes.php');
require_once('gui.php');



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





?>