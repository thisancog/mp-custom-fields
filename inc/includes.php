<?php

require_once('modules.php');
require_once('mpcf-settings.php');
require_once('mpcf-register-metaboxes.php');
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



/******************************************
	Field helper functions
 ******************************************/

function mpcf_get_input_class($field, $append = '') {
	$classes	= 'mpcf-input-' . $field->name;
	$fieldArgs	= $field->args;
	$paramClass	= (isset($fieldArgs['inputClass']) && !empty($fieldArgs['inputClass']) ? ' ' . $fieldArgs['inputClass'] : '');

	return $classes . $paramClass . (!empty($append) ? ' ' . $append : '');
}

function mpcf_input_class($field, $append = '') {
	return ' class="' . mpcf_get_input_class($field, $append) . '"';
}

function mpcf_get_input_id($field) {
	$id			= uniqid('mpcf-input-' . $field->name . '-');
	$fieldArgs	= $field->args;
	$paramId	= (isset($fieldArgs['inputId']) && !empty($fieldArgs['inputId']) ? ' ' . $fieldArgs['inputId'] : '');

	return $id . $paramId;
}

function mpcf_input_id($field) {
	return ' id="' . mpcf_get_input_id($field) . '"';
}

function mpcf_get_input_param($field, $param) {
	$fieldArgs = $field->args;
	return isset($fieldArgs[$param]) && !empty($fieldArgs[$param]) ? $fieldArgs[$param] : null;
}

function mpcf_input_param($field, $param) {
	$paramName = is_string($param) ? $param : (is_array($param) ? $param['name'] : null);
	if ($paramName === null) return;

	$value = mpcf_get_input_param($field, $paramName);
	if (mpcf_is_simple_param($param)) {
		$output = $value ? ' ' . $paramName : '';
	} else {
		$output = $value ? ' ' . $paramName . '="' . $value . '"' : '';
	}

	return $output;
}

function mpcf_is_simple_param($param) {
	if (is_array($param) && isset($param['simple']) && $param['simple'])
		return true;

	$paramName = is_string($param) ? $param : (is_array($param) ? $param['name'] : null);
	if ($paramName === null)
		return;

	$simples = array(
				'autofocus', 'checked', 'disabled',
				'formnovalidate', 'multiple', 'novalidate',
				'readonly', 'required'
			);

	return in_array($paramName, $simples);
}

function mpcf_list_input_params($field, $params = array()) {
	$output = mpcf_input_class($field) . mpcf_input_id($field);
	if ($params === false) return $output;

	if (empty($params)) {
		$params = array_map(function($param) {
			return isset($param['name']) ? $param['name'] : '';
		}, $field->parameters);
	}

	if (is_string($params))
		$params = array($params);

	foreach ($params as $param) {
		$output .= mpcf_input_param($field, $param);
	}

	return $output;
}



?>