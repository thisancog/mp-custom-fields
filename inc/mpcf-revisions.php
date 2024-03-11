<?php

add_action('save_post', 'mpcf_save_revision_with_meta', 10, 2);
add_action('wp_restore_post_revision', 'mpcf_restore_revision', 10, 2);

add_filter('_wp_post_revision_field_custom_fields', 'mpcf_fill_revision_field', 10, 3);
add_filter('_wp_post_revision_fields', 'mpcf_create_new_revision_field', 10, 1);
add_filter('process_text_diff_html', 'mpcf_filter_revision_html_processing', 10, 3);
add_filter('wp_save_post_revision_post_has_changed', 'mpcf_has_meta_changed', 10, 3);





/*****************************************************
	Save and restore revision meta data
 *****************************************************/

function mpcf_save_revision_with_meta($post_id, $post) {
	if ($parent_id = wp_is_post_revision($post_id)) {
		$meta = mpcf_get_cleaned_revision_meta(get_post($parent_id)->ID);
		if ($meta == false) return;

		mpcf_insert_revision_meta($post_id, $meta);
	}
}

function mpcf_restore_revision($post_id, $revision_id) {
	$meta = mpcf_get_cleaned_revision_meta($revision_id);
	mpcf_delete_revision_meta($post_id);
	mpcf_insert_revision_meta($post_id, $meta);

	$revisions = wp_get_post_revisions($post_id);
	if (count($revisions) == 0) return;
	
	$lastRevision = current($revisions);
	mpcf_delete_revision_meta($lastRevision->ID);
	mpcf_insert_revision_meta($lastRevision->ID, $meta);
}



/*****************************************************
	Has the meta data changed?
 *****************************************************/

function mpcf_has_meta_changed($post_has_changed, $last_revision, $post) {
	if ($post_has_changed) return true;

	$meta    = mpcf_get_cleaned_revision_meta(get_post($last_revision)->ID);
	$metaNew = mpcf_get_cleaned_revision_meta($post->ID);
	return $meta !== $metaNew;
}



function mpcf_revisions_enabled() {
	$option = mpcf_get_option('includerevisions', 'mpcf_options');
	return $option == 'checked' || $option == true || $option == 1;
}


/*****************************************************
	Revisions screen
 *****************************************************/

function mpcf_create_new_revision_field($fields) {
	if (!mpcf_revisions_enabled()) return $fields;
	$fields['custom_fields'] = __('Custom Fields', 'mpcf');
	return $fields;
}

function mpcf_fill_revision_field($value, $field, $revision) {
	if (!mpcf_revisions_enabled()) return '';

	$meta = mpcf_get_cleaned_revision_meta($revision->ID);

	$html = "";
	foreach ($meta as $key => $valueOrig) {
		if (substr($key, 0, 14) == 'mpcf-activetab') continue;
		$value = is_array($valueOrig)                                       ? $valueOrig[0]       : $valueOrig;
		$value = is_serialized($value)                                      ? unserialize($value) : $value;
		$value = is_array($value) && count($value) == 1 && isset($value[0]) ? $value[0]           : $value;
		$value = is_serialized($value)                                      ? unserialize($value) : $value;
		$value = is_array($value)                                           ? json_encode($value) : $value;
		$newLine = $key . ': ' . $value . "\n";

		$newLine = apply_filters('mpcf_revision_field_line', $newLine, $key, $valueOrig, $value);
		$html .= $newLine;
	}

	return $html;
}



/*****************************************************
	Helpers
 *****************************************************/

function mpcf_get_cleaned_revision_meta($post_id) {
	$meta     = get_metadata('post', $post_id);
	$filtered = []; 

	foreach ($meta as $key => $value) {
		if (substr($key, 0, 1) !== '_')
			$filtered[$key] = $value;
	}

	return $filtered;
}

function mpcf_insert_revision_meta($post_id, $meta) {
	foreach ($meta as $key => $value) {
		while (is_serialized($value) ||
			(is_array($value) && count($value) == 1 && isset($value[0]) && !isset($value[0]['module']))) {
			if (is_serialized($value))
				$value = unserialize($value);

			if (is_array($value) && count($value) == 1 && isset($value[0]) && !isset($value[0]['module']))
				$value = $value[0];
		}

		add_metadata('post', $post_id, $key, $value);
	}
}

function mpcf_delete_revision_meta($post_id) {
	$meta = mpcf_get_cleaned_revision_meta($post_id);

	foreach ($meta as $key => $value) {
		delete_metadata('post', $post_id, $key);
	}
}


function mpcf_filter_revision_html_processing($processed_line, $line, $mode) {
	return $line;
}

?>