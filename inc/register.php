<?php

/*****************************************************
	Register a new custom field box
 *****************************************************/

function mpcf_add_custom_fields($type, $arguments = array()) {
	if (!post_type_exists($type)) return;

	$boxes = get_option('mpcf_meta_boxes', array());

	$defaults = array(
		'post_type'		=> 'post',
		'page_template'	=> '',
		'title'			=> '',
		'context'		=> 'normal',
		'priority'		=> 'default',
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


// 	Find unique ID for this custom field box
	$baseID = 'mpcf-' . mpcf_beautify_string($newbox['title']);
	$id = $baseID;
	$i = 0;

	while (false !== array_search($id, array_column($boxes, 'id'))) {
		$id = $baseID . ($i > 0 ? '-' . $i : '');
		$i++;
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
			if (is_string($page_template)) $page_template = array($page_template);
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
