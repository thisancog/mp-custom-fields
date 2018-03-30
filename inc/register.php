<?php

/*****************************************************
	Register a new custom field box
 *****************************************************/

function mpcf_add_custom_fields($type, $arguments = array()) {
	if (!post_type_exists($type)) return;

	$boxes = get_option('mpcf_meta_boxes', array());
	$boxes = array();

	$defaults = array(
		'post_type'	=> 'post',
		'title'		=> '',
		'context'	=> 'normal',
		'priority'	=> 'default',
		'fields'	=> array(),
		'panels'	=> array()
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

	$boxes = array_filter($boxes, function($box) use ($boxID, &$removed) {
		if ($box['id'] === $boxID) {
			$removed = $box;
			return false;
		}

		return true;
	});

	update_option('mpcf_meta_boxes', $boxes);
	return $removed;
}


/*****************************************************
	Send meta boxes to Wordpress
 *****************************************************/

function mpcf_add_metaboxes() {
	$boxes = get_option('mpcf_meta_boxes', array());

	foreach ($boxes as $id => $box) {
		add_meta_box($id, $box['title'], 'mpcf_meta_box_init', $box['post_type'], $box['context'], $box['priority']);
	}
}

?>