<?php

if (!defined('ABSPATH')) exit;

/*****************************************************
	Security of the AJAX requests

	New repeater rows, the fields of a conditional field,
	the panel of a conditional panels field and new modules
	are rendered via AJAX. The browser sends the definition
	of the fields to render.

	Field definitions can contain callbacks (custom fields,
	post select queries, actions), so a request is only
	carried out if:

	1. it carries a valid nonce, and
	2. the definition is part of a registered box that the
	   current user may edit - the server then renders its
	   own copy of the definition, never the one sent by
	   the browser.

	Users who can manage options (administrators) may also
	request definitions which are not registered, e.g. the
	ones on the plugin's own settings page. Other cases can
	be allowed with the filter mpcf_ajax_definition_allowed.
 *****************************************************/

define('MPCF_AJAX_NONCE_ACTION', 'mpcf_ajax');


//	nonce for the AJAX requests, passed to admin.js in localizedmpcf.nonce
function mpcf_ajax_nonce() {
	return wp_create_nonce(MPCF_AJAX_NONCE_ACTION);
}


//	keep the nonce fresh on screens that stay open for a long time (via the WordPress heartbeat)
add_filter('heartbeat_received', 'mpcf_ajax_refresh_nonce', 10, 2);

function mpcf_ajax_refresh_nonce($response, $data) {
	if (!empty($data['mpcf_refresh_nonce']))
		$response['mpcf_nonce'] = mpcf_ajax_nonce();

	return $response;
}



/*****************************************************
	Checking a request
 *****************************************************/

//	Returns the definition to render, or ends the request with an error.
//	$posted: the raw (slashed) JSON string sent by the browser.
function mpcf_ajax_get_verified_definition($posted) {
	if (!check_ajax_referer(MPCF_AJAX_NONCE_ACTION, 'nonce', false))
		mpcf_ajax_fail('The request could not be verified (invalid or expired nonce). Please reload the page.', 403);

	if (!is_string($posted) || $posted === '')
		mpcf_ajax_fail('The request contains no field definition.', 400);

//	the browser's copy, decoded the way it always has been - and correctly unslashed as a fallback
	$candidates = array_filter(array(
		json_decode(stripcslashes($posted), true),
		json_decode(wp_unslash($posted), true)
	), 'is_array');

	if (empty($candidates))
		mpcf_ajax_fail('The field definition could not be read.', 400);

	foreach ($candidates as $candidate) {
		$registered = mpcf_find_registered_definition($candidate);
		if ($registered !== null)
			return $registered;
	}

	$definition = reset($candidates);
	$allowed    = current_user_can('manage_options');
	$allowed    = (bool) apply_filters('mpcf_ajax_definition_allowed', $allowed, $definition);

	if (!$allowed)
		mpcf_ajax_fail('The requested fields are not part of a box you may edit.', 403);

	return $definition;
}


//	Ends the AJAX request with an error status, which admin.js reports in the console.
function mpcf_ajax_fail($message, $status = 400) {
	mpcf_log('AJAX request "' . (isset($_REQUEST['action']) ? sanitize_key($_REQUEST['action']) : '') . '" refused: ' . $message);
	wp_die(esc_html($message), '', array('response' => $status));
}


//	Logs a message to the PHP error log if WP_DEBUG is enabled.
function mpcf_log($message) {
	if (defined('WP_DEBUG') && WP_DEBUG)
		error_log('[MP Custom Fields] ' . $message);
}



/*****************************************************
	Finding a definition in the registered boxes
 *****************************************************/

//	Returns the registered copy of $definition from a box the current user may edit, or null.
function mpcf_find_registered_definition($definition) {
	$needle = mpcf_canonical_definition($definition);
	$count  = count($definition);

	foreach (mpcf_get_registered_boxes() as $entry) {
		if (!mpcf_user_can_edit_box($entry['context'], $entry['box'])) continue;

		$found = mpcf_search_definition($entry['box'], $needle, $count);
		if ($found !== null)
			return $found;
	}

	return null;
}


//	all registered boxes of all contexts
function mpcf_get_registered_boxes() {
	$options = array(
		'post'      => 'mpcf_meta_boxes',			// incl. module boxes
		'tax'       => 'mpcf_taxonomy_boxes',
		'user'      => 'mpcf_user_boxes',
		'archive'   => 'mpcf_archive_boxes',
		'singleton' => 'mpcf_singleton_boxes'
	);

	$boxes = array();

	foreach ($options as $context => $option) {
		$registered = get_option($option, array());
		if (!is_array($registered)) continue;

		foreach ($registered as $box) {
			if (is_array($box))
				$boxes[] = array('context' => $context, 'box' => $box);
		}
	}

	return $boxes;
}


//	whether the current user may edit the fields of a box
function mpcf_user_can_edit_box($context, $box) {
	switch ($context) {
		case 'post':
			$postType = isset($box['post_type']) ? get_post_type_object($box['post_type']) : null;
			return $postType && current_user_can($postType->cap->edit_posts);

		case 'tax':
			$taxonomy = isset($box['taxonomy']) ? get_taxonomy($box['taxonomy']) : null;
			return $taxonomy && current_user_can($taxonomy->cap->edit_terms);

		case 'user':
		//	users edit their own profile; boxes may be limited to roles
			if (!is_user_logged_in()) return false;
			if (current_user_can('edit_users')) return true;

			$roles = isset($box['roles']) ? $box['roles'] : 'all';
			if ($roles === 'all' || empty($roles)) return true;

			return count(array_intersect((array) $roles, (array) wp_get_current_user()->roles)) > 0;

		case 'archive':
			return current_user_can(isset($box['capability']) ? $box['capability'] : 'edit_posts');

		case 'singleton':
			$singleton = isset($box['singleton']) && function_exists('mpcf_get_singleton') ? mpcf_get_singleton($box['singleton']) : null;
			return current_user_can(is_array($singleton) && isset($singleton['capability']) ? $singleton['capability'] : 'edit_pages');
	}

	return false;
}


//	Depth-first search for an array equal to the canonical needle.
function mpcf_search_definition($haystack, $needle, $count) {
	if (!is_array($haystack)) return null;

	if (count($haystack) === $count && mpcf_canonical_definition($haystack) === $needle)
		return $haystack;

	foreach ($haystack as $child) {
		if (!is_array($child)) continue;

		$found = mpcf_search_definition($child, $needle, $count);
		if ($found !== null)
			return $found;
	}

	return null;
}


//	A comparable form of a definition: the browser changes the order of numeric keys and
//	turns whole floats into integers (1.0 -> 1), so keys are sorted and numbers normalised.
function mpcf_canonical_definition($value) {
	return serialize(mpcf_canonical_value($value));		// unlike json_encode() never fails, e.g. on invalid UTF-8
}

function mpcf_canonical_value($value) {
	if (is_array($value)) {
		ksort($value, SORT_STRING);
		return array_map('mpcf_canonical_value', $value);
	}

	if (is_float($value) && floor($value) == $value && abs($value) < PHP_INT_MAX)
		return (int) $value;

	return $value;
}

?>
