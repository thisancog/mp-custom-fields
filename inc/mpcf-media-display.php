<?php

add_filter('attachment_fields_to_edit', 'mpcf_add_usedIn_attachment_info_modal', 1, 2);
add_filter('manage_media_columns', 'mpcf_add_usedIn_media_column', 10, 1);

add_action('attachment_submitbox_misc_actions', 'mpcf_add_usedIn_attachment_info_standalone', 11, 1);
add_action('manage_media_custom_column', 'mpcf_populate_usedIn_media_column', 10, 2);




/*****************************************
	Add "used in" column to media post table
 *****************************************/

function mpcf_add_usedIn_media_column($columns) {
	$position = 4;
	$columns   = array_slice($columns, 0, $position, true)
			   + [ 'usedin' => __('Used in', 'mpcf')]
			   + array_slice($columns, $position, NULL, true);
	return $columns;
}

function mpcf_populate_usedIn_media_column($columnName, $post_id) {
	if ($columnName !== 'usedin') return;
	echo mpcf_get_usedIn_content($post_id);
}

function mpcf_get_usedIn_content($post_id) {
	$usedIn = get_post_meta($post_id, 'mpcf-attached-media', true);
	if (empty($usedIn)) return '&mdash;';

	$posts = [];

	foreach ($usedIn as $postID) {
		$posts[] = '<a href="' . get_edit_post_link($postID) . '" target="_blank" rel="noppener">' . get_the_title($postID) . '</a>';
	}

	return join(', ', $posts);
}




/*****************************************
	Add "used in" field to media edit modal
 *****************************************/

function mpcf_add_usedIn_attachment_info_modal($form_fields, $post) {
	$form_fields['mpcf-media-usedin'] = array(
			'label'         => __('Used in', 'mpcf'),
			'input'         => 'html',
			'html'          => mpcf_get_usedIn_content($post->ID),
			'show_in_modal' => true,
			'show_in_edit'  => false,
		);

	return $form_fields;
}


/*****************************************
	Add "used in" field to media edit page
 *****************************************/

function mpcf_add_usedIn_attachment_info_standalone($post) {
	echo '<div class="misc-pub-section misc-pub-regenerate-thumbnails"><span class="dashicons dashicons-admin-post"></span> ';
	echo __('Used in', 'mpcf') . ': ' . mpcf_get_usedIn_content($post->ID);
	echo '</div>';
}

?>