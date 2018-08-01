<?php

/*****************************************************
	Register a new custom field box
 *****************************************************/

function mpcf_add_custom_fields($type, $id, $arguments = array()) {
	if (!post_type_exists($type)) return;

	$boxes = get_option('mpcf_meta_boxes', array());
	if (false !== array_search($id, array_keys($boxes)))
		return;

	$defaults = array(
		'post_type'		=> 'post',
		'page_template'	=> '',
		'title'			=> '',
		'context'		=> 'normal',
		'priority'		=> 'default',
		'multilingual'	=> false,
		'fields'		=> array(),
		'panels'		=> array()
	);

	$newbox = array_merge($defaults, $arguments);
	$newbox['post_type'] = $type;

//	Give generic title if needed
	if (empty($newbox['title'])) {
		$obj = get_post_type_object($type);
		$newbox['title'] = sprintf(__('%s Options', 'mpcf'), $obj->labels->singular_name);
	}


	$boxes[$id] = $newbox;

	update_option('mpcf_meta_boxes', $boxes);
	return $newbox;
}



/*****************************************************
	Deregister a custom field box
 *****************************************************/

function mpcf_remove_custom_fields($boxID) {
	$boxes = get_option('mpcf_meta_boxes', array());
	$removed = null;

	$boxes = array_filter($boxes, function($box, $id) use ($boxID, &$removed) {
		if ($id === $boxID) {
			$removed = $box;
			return false;
		}

		return true;
	}, ARRAY_FILTER_USE_BOTH);

	update_option('mpcf_meta_boxes', $boxes);
	return $removed;
}

function mpcf_remove_all_custom_fields() {
	update_option('mpcf_meta_boxes', array());
}


/*****************************************************
	Send meta boxes to Wordpress
 *****************************************************/

function mpcf_add_metaboxes() {
	global $post;
	$boxes = get_option('mpcf_meta_boxes', array());
	$currentTemplate = get_post_meta($post->ID, '_wp_page_template', true);

	foreach ($boxes as $id => $box) {
		$post_type = $box['post_type'];
		$page_template = $box['page_template'];

		if ($post_type === 'page' && !empty($page_template)) {
			if (is_string($page_template))
				$page_template = explode(',', $page_template);

			$page_template = array_map('trim', $page_template);

			$valids = array_filter($page_template, function($template) {
				return substr($template, 0, 1) !== '-';
			});

			$invalids = array_filter($page_template, function($template) {
				return substr($template, 0, 1) === '-';
			});

			if (!empty($invalids) && in_array('-' . $currentTemplate, $invalids))
				continue;

			if (!empty($valids) && !in_array($currentTemplate, $valids))
				continue;
		}

		add_meta_box($id, $box['title'], 'mpcf_meta_box_init', $post_type, $box['context'], $box['priority']);
	}
}

?>
