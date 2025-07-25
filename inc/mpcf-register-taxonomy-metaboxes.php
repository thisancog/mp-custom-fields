<?php

/*****************************************************
	Register a new custom field box
 *****************************************************/

function mpcf_add_custom_fields_taxonomy($tax, $id, $arguments = array()) {
	if (!taxonomy_exists($tax)) return;

	$boxes = get_option('mpcf_taxonomy_boxes', array());

	$defaults = array(
		'taxonomy'		=> 'category',
		'title'			=> '',
		'multilingual'	=> false,
		'priority'		=> 0,
		'panels'		=> array()
	);

	$newbox = array_merge($defaults, $arguments);
	$newbox['taxonomy'] = $tax;

//	Give generic title if needed
	if (empty($newbox['title'])) {
		$obj = get_object_taxonomies($tax);
		$newbox['title'] = sprintf(__('%s Options', 'mpcf'), $obj->labels->singular_name);
	}

	$boxes[$id] = $newbox;

	update_option('mpcf_taxonomy_boxes', $boxes);
	return $newbox;
}



/*****************************************************
	Deregister a custom field box
 *****************************************************/

function mpcf_remove_custom_fields_taxonomy($boxID) {
	$boxes = get_option('mpcf_taxonomy_boxes', array());
	$keys = array_keys($boxes);
	$removed = null;

	array_walk($keys, function($id) use ($boxID, &$boxes, &$removed) {
		if ($id === $boxID) {
			$removed = $boxes[$id];
			unset($boxes[$id]);
		}
	});

	update_option('mpcf_taxonomy_boxes', $boxes);
	return $removed;
}

function mpcf_remove_all_custom_fields_taxonomies() {
	update_option('mpcf_taxonomy_boxes', array());
}


/*****************************************************
	Send meta boxes to Wordpress
 *****************************************************/

function mpcf_add_metaboxes_to_taxonomies() {
	$boxes = get_option('mpcf_taxonomy_boxes', array());

	foreach ($boxes as $id => $box) {
		$tax = $box['taxonomy'];

		add_action($tax . '_add_form_fields', 'mpcf_build_taxonomy_gui', 10, 2);
		add_action($tax . '_edit_form', 'mpcf_edit_custom_fields_taxonomy', 10, 2);
		add_action('create_' . $tax, 'mpcf_save_custom_fields_taxonomy', 10);
		add_action('edited_' . $tax, 'mpcf_save_custom_fields_taxonomy', 10);
	}
}



/*****************************************************
	Build graphical user interface for taxonomy pages
 *****************************************************/

function mpcf_get_taxonomy_boxes($tax) {
	$boxes = get_option('mpcf_taxonomy_boxes', array());
	$boxes = array_filter($boxes, function($box) use ($tax) {
		return $box['taxonomy'] === $tax;
	});

	usort($boxes, function($a, $b) {
		return $a['priority'] - $b['priority'];
	});

	return $boxes;
}

function mpcf_build_taxonomy_gui($tax = null, $term = null) {
	if ($tax === null) {
		global $current_screen;
		$tax = $current_screen->taxonomy;
	}

	$values = $term !== null ? get_term_meta($term->term_id, '', true) : array();
	$boxes = mpcf_get_taxonomy_boxes($tax);

	foreach ($boxes as $id => $box) {
		$panels = $box['panels']; ?>

		<div class="mpcf-tax-box">
			<h2 class="mpcf-tax-box-title"><?php echo $box['title']; ?></h2>
			<div class="mpcf-parent">
				<?php mpcf_build_gui_as_panels($id, $panels, $values); ?>
			</div>
		</div>
<?php
	}
}

function mpcf_edit_custom_fields_taxonomy($term, $tax) {
	mpcf_build_taxonomy_gui($tax, $term);
}

function mpcf_save_custom_fields_taxonomy($term_id) {
	$termObj = get_term($term_id);
	$tax = $termObj->taxonomy;
	$boxes = mpcf_get_taxonomy_boxes($tax);

	foreach ($boxes as $boxId => $box) {
		$panels = $box['panels'];

		foreach ($panels as $panel) {
			foreach ($panel['fields'] as $field) {
				$name = $field['name'];
				$type = $field['type'];
				$field['context'] = 'taxonomy';

				$actions = isset($field['actions']) ? $field['actions'] : array();

				$oldValue = mpcf_get_tax_field($name, $term_id);
				$value    = isset($_POST[$name]) ? mpcf_mksafe($_POST[$name]) : false;
				$value    = mpcf_before_save($field, $term_id, $value);

				update_term_meta($term_id, $name, $value);
				mpcf_after_save($field, $term_id, $value, $oldValue);
			}
		}
	}
}



/*****************************************************
	Field getters
 *****************************************************/

function mpcf_get_tax_field($fieldName = null, $id = null) {
	return mpcf_get_field($fieldName, $id, 'tax');
}

function mpcf_has_tax_field($fieldName = null, $id = null) {
	return mpcf_has_field($fieldName, $id, 'tax');
}

function mpcf_the_tax_field($fieldName = null) {
	echo mpcf_get_tax_field($fieldName);
}



?>
