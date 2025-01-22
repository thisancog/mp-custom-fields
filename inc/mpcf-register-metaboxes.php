<?php

/*****************************************************
	Register a new custom field box
 *****************************************************/

function mpcf_add_custom_fields($type, $id, $arguments = array()) {
	if (!post_type_exists($type)) return;

	$defaults = array(
		'post_type'		=> $type,
		'page_template'	=> '',
		'title'			=> '',
		'context'		=> 'normal',
		'priority'		=> 'default',
		'multilingual'	=> false,
		'panels'		=> array()
	);

	$newbox = array_merge($defaults, $arguments);

//	Give generic title if needed
	if (empty($newbox['title'])) {
		$obj             = get_post_type_object($type);
		$newbox['title'] = sprintf(__('%s Options', 'mpcf'), $obj->labels->singular_name);
	}

	$boxes = get_option('mpcf_meta_boxes', array());
	$boxes[$id] = $newbox;
	update_option('mpcf_meta_boxes', $boxes);

	return $newbox;
}



/*****************************************************
	Deregister a custom field box
 *****************************************************/

function mpcf_remove_custom_fields($boxID) {
	$boxes = get_option('mpcf_meta_boxes', array());
	$keys = array_keys($boxes);
	$removed = null;

	array_walk($keys, function($id) use ($boxID, &$boxes, &$removed) {
		if ($id === $boxID) {
			$removed = $boxes[$id];
			unset($boxes[$id]);
		}
	});

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

	$boxes           = get_option('mpcf_meta_boxes', array());
	$currentTemplate = $post !== null ? get_post_meta($post->ID, '_wp_page_template', true)                       : null;
	$isFrontpage     = $post !== null ? get_option('page_on_front')  && get_option('page_on_front')  == $post->ID : null;
	$isPostsPage     = $post !== null ? get_option('page_for_posts') && get_option('page_for_posts') == $post->ID : null;

	foreach ($boxes as $id => $box) {
		$post_type     = $box['post_type'];
		$screen        = $post_type;
		$page_template = isset($box['page_template']) ? $box['page_template'] : '';
		$context       = isset($box['context'])       ? $box['context']       : 'normal';
		$priority      = isset($box['priority'])      ? $box['priority']      : 'high';

		$registerThisBox = true;

		if ($post_type === 'page' && !empty($page_template)) {
			if (is_string($page_template))
				$page_template = explode(',', $page_template);

			$page_template = array_map('trim', $page_template);
			$onFrontpage = in_array('frontpage', $page_template);
			$onPostspage = in_array('postspage', $page_template);

			$valids = array_filter($page_template, function($template) {
				return substr($template, 0, 1) !== '-' && $template !== 'frontpage' && $template !== 'postspage';
			});

			$invalids = array_filter($page_template, function($template) {
				return substr($template, 0, 1) === '-';
			});

			if (!empty($invalids) && in_array('-' . $currentTemplate, $invalids))
				$registerThisBox = false;

			if (!empty($valids) && !in_array($currentTemplate, $valids))
				$registerThisBox = false;

			if (($onFrontpage && !$isFrontpage) || ($isFrontpage && in_array('-frontpage', $invalids)))
				$registerThisBox = false;

			if (($onPostspage && !$isPostsPage) || ($isPostsPage && in_array('-postspage', $invalids)))
				$registerThisBox = false;
		}

		if ($registerThisBox) {
			add_meta_box($id, $box['title'], 'mpcf_meta_box_init', $screen, $context, $priority);
		}
	}
}



/*****************************************************
	Get meta boxes for post type
 *****************************************************/

function mpcf_get_metaboxes_for_type($post_type = 'post') {
	if (!post_type_exists($post_type)) return array();

	$boxes = get_option('mpcf_meta_boxes', array());
	$boxes = array_filter($boxes, function($box) use ($post_type) {
		return $box['post_type'] === $post_type;
	});

	return $boxes;
}





/*****************************************************
	Copy-paste bulk
 *****************************************************/

function mpcf_add_bulk_copypaste_panels($id, $panels = array(), $values = array()) {
	$isEnabled = current_user_can('manage_options');
	$isEnabled = apply_filters('mpcf_enable_bulk_copypaste_panels', $isEnabled);

	if (!$isEnabled)
		return array('panels' => $panels, 'values' => $values);

	$values = is_array($values) ? $values : [];

	$bulkPanel = array(
		'title'		=> __('Bulk copy-paste', 'mpcf'),
		'icon'		=> 'dashicons-admin-tools',
		'fields'	=> array(
			array(
				'name'			=> 'mpcf-bulkcopy',
				'title'			=> __('Page metadata', 'mpcf'),
				'type'			=> 'custom',
				'callback'		=> 'mpcf_bulk_copy_field',
				'description'	=> __('Copy this page&rsquo;s entire metadata to another page. This does not include any changes made since the last save.', 'mpcf')
			),
			array(
				'name'			=> 'mpcf-bulkpaste-' . $id,
				'title'			=> ' ',
				'type'			=> 'custom',
				'callback'		=> 'mpcf_bulk_paste_field',
				'actions'		=> array(
					'save_before'		=> 'mpcf_bulk_paste_values'
				),
				'description'	=> __('Paste another page&rsquo;s entire metadata here.', 'mpcf')
			)
		)
	);

	$allValues = [];
	$toRemove = array('_edit_last', '_edit_lock', 'mpcf-bulkcopy');

	foreach ($panels as $panel) {
		foreach ($panel['fields'] as $field) {
			if (!isset($field['name'])) continue;

			$key = $field['name'];
			if (isset($values[$key]) && !in_array($key, $toRemove))
				$allValues[$key] = $values[$key];
		}
	}

	$panels[] = $bulkPanel;
	$values['mpcf-bulkcopy'] = $allValues;

	return array('panels' => $panels, 'values' => $values);
}

function mpcf_bulk_copy_field($module, $field) {
	$value = json_encode($field['value'], JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP); ?>
	<textarea rows="6" readonly><?php echo $value; ?></textarea>
<?php
}

function mpcf_bulk_paste_field($module, $field) { ?>
	<textarea name="<?php echo $field['name']; ?>" rows="6"></textarea>
<?php
}

function mpcf_bulk_paste_values($post_id, $fieldName, $values) {
	$values = mpcf_mknice($values);
	$values = json_decode($values, JSON_OBJECT_AS_ARRAY);
	if (!is_array($values)) return '';

	foreach ($values as $key => $value) {
		$value = is_array($value) && count($value) == 1 ? $value[0] : $value;
		update_post_meta($post_id, $key, $value);
	}

	return '';
}



?>
