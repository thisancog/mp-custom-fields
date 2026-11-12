<?php

/*****************************************************
	Register a new custom field box
 *****************************************************/

function mpcf_add_custom_fields_archive($post_type, $id, $arguments = array()) {
	if (!post_type_exists($post_type)) return;

	$isUpdated = mpcf_check_metabox_version($id, $arguments, 'archive');
	if (!$isUpdated) return;

	$boxes = get_option('mpcf_archive_boxes', array());

	$defaults = array(
		'post_type'		=> 'post',
		'title'			=> '',
		'multilingual'	=> false,
		'capability'	=> 'edit_posts',
		'priority'		=> 0,
		'panels'		=> array()
	);

	$newbox = array_merge($defaults, $arguments);
	$newbox['post_type'] = $post_type;

//	Give generic title if needed
	if (empty($newbox['title'])) {
		$obj = get_post_type_object($post_type);
		$newbox['title'] = sprintf(__('%s Options', 'mpcf'), $obj->labels->singular_name);
	}

	$newbox['panels'] = mpcf_assign_order_to_select_fields($newbox['panels']);
	$boxes[$id]       = $newbox;

	update_option('mpcf_archive_boxes', $boxes);

	add_option('mpcf_archive_meta', array());
	$meta = get_option('mpcf_archive_meta', array());

	if (!isset($meta[$post_type])) {
		$meta[$post_type] = array();
		update_option('mpcf_archive_meta', $meta);
	}
	
	return $newbox;
}



/*****************************************************
	Deregister a custom field box
 *****************************************************/

function mpcf_remove_custom_fields_archive($post_type) {
//	read directly: mpcf_get_archive_boxes() sorts the boxes and loses their IDs
	$boxes      = get_option('mpcf_archive_boxes', array());
	$removed    = null;
	$removedIDs = array();

	$boxes = array_filter($boxes, function($box, $id) use ($post_type, &$removed, &$removedIDs) {
		if ($box['post_type'] === $post_type) {
			$removed      = $box;
			$removedIDs[] = $id;
			return false;
		}

		return true;
	}, ARRAY_FILTER_USE_BOTH);

	update_option('mpcf_archive_boxes', $boxes);

	$meta = get_option('mpcf_archive_meta', array());
	unset($meta[$post_type]);
	update_option('mpcf_archive_meta', $meta);

//	forget the registered versions, so the boxes are registered again when added anew
	foreach ($removedIDs as $id)
		mpcf_remove_metabox_from_versions($id, 'archive');

	return $removed;
}

function mpcf_remove_all_custom_fields_archives() {
//	forget the registered versions, so the boxes are registered again when added anew
	foreach (array_keys(get_option('mpcf_archive_boxes', array())) as $id)
		mpcf_remove_metabox_from_versions($id, 'archive');

	update_option('mpcf_archive_boxes', array());
}


/*****************************************************
	Send meta boxes to Wordpress
 *****************************************************/

function mpcf_add_metaboxes_to_archives() {
	$boxes = get_option('mpcf_archive_boxes', array());

	foreach ($boxes as $id => $box) {
		$post_type = $box['post_type'];

		$parent_slug = 'edit.php' . ($post_type !== 'post' ? '?post_type=' . $post_type : '');
		$menu_slug = mpcf_beautify_string('archive-' . $box['post_type']);

		add_submenu_page($parent_slug, $box['title'], __('Archive', 'mpcf'), $box['capability'], $menu_slug, 'mpcf_build_archive_gui');
	}
}



/*****************************************************
	Build graphical user interface for archive pages
 *****************************************************/

function mpcf_get_archive_boxes($post_type = null) {
	$boxes = get_option('mpcf_archive_boxes', array());

	if ($post_type !== null) {
		$boxes = array_filter($boxes, function($box) use ($post_type) {
			return $box['post_type'] === $post_type;
		});
	}

	usort($boxes, function($a, $b) {
		return $a['priority'] - $b['priority'];
	});

	return $boxes;
}

function mpcf_build_archive_gui() {
	$parent = get_admin_page_parent();
	preg_match('/(?:post_type=)(.+)/i', $parent, $post_type);
	$post_type = !empty($post_type) && isset($post_type[1]) ? $post_type[1] : 'post';

	mpcf_save_custom_fields_archive($post_type);

	$formName = 'mpcf-archive-' . $post_type;
	$archiveLink = get_post_type_archive_link($post_type);
	
	$values = mpcf_get_archive_meta($post_type);
	$boxes = mpcf_get_archive_boxes($post_type);

	if (isset($_POST['update_settings'])) {
		$message = mpcf_submission_is_complete() ? __('Options were saved.', 'mpcf') : mpcf_incomplete_submission_message();
	} ?>

	<div class="mpcf-options">
		<form method="post" name="<?php echo $formName; ?>" id="<?php echo $formName; ?>" action="">

<?php		if (!empty($message)) { ?>
				<div id="message" class="mpcf-message updated fade"><p><strong><?php echo $message; ?></strong></p></div>
<?php		}

			foreach ($boxes as $id => $box) { ?>

				<div class="mpcf-archive-box">
					<h2 class="mpcf-archive-box-title"><?php echo $box['title']; ?></h2>
					<span class="mpcf-archive-link"><a href="<?php echo $archiveLink; ?>"><?php _e('View archive', 'mpcf'); ?></a></span>
					<div class="mpcf-parent">
						<?php mpcf_build_gui_as_panels($id, $box['panels'], $values); ?>
					</div>
				</div> 

<?php 		} ?>

			<div class="mpcf-archive-inputs">
				<input type="hidden" name="update_settings" id="update_settings" value="Y" />
				<input type="submit" value="<?php _e('Save', 'mpcf'); ?>" id="submit" class="mpcf-submit-button button button-primary button-large" />
			</div>
		</form>
	</div>
<?php	

	mpcf_create_i18n_file($formName);
}


function mpcf_save_custom_fields_archive($post_type) {
	if (isset($_POST['update_settings']) && !mpcf_submission_is_complete()) {	// see inc/mpcf-submission.php
		mpcf_log('Archive fields of "' . $post_type . '" were not saved: the form was sent before all fields were loaded.');
		return;
	}

	if (isset($_POST['update_settings'])) {
		$boxes = mpcf_get_archive_boxes($post_type);

		foreach ($boxes as $boxId => $box) {
			$panels = $box['panels'];

			foreach ($panels as $panel) {
				foreach ($panel['fields'] as $field) {
					$name = $field['name'];
					$type = $field['type'];
					$field['context'] = 'archive';

					$actions = isset($field['actions']) ? $field['actions'] : array();

					$oldValue = mpcf_get_archive_meta($post_type, $name);
					$value    = isset($_POST[$name]) ? mpcf_mksafe($_POST[$name]) : false;
					$value    = mpcf_before_save($field, null, $value);

					mpcf_update_archive_meta($post_type, $name, $value);
					mpcf_after_save($field, null, $value, $oldValue);
				}
			}
		}
	}

}


/*****************************************************
	Get archive meta option
 *****************************************************/

function mpcf_get_archive_meta($post_type, $meta_key = null) {
	if ($post_type == null) return;

	$archiveOptions = get_option('mpcf_archive_meta', array());

	if (!isset($archiveOptions[$post_type]))
		return;

	if ($meta_key === null)
		return $archiveOptions[$post_type];

	if (isset($archiveOptions[$post_type][$meta_key]))
		return $archiveOptions[$post_type][$meta_key];

	return null;
}

function mpcf_update_archive_meta($post_type = null, $meta_key = null, $meta_value = null) {
	if ($post_type == null) return;

	$archiveOptions = get_option('mpcf_archive_meta', array());

	if ($meta_key === null || !isset($archiveOptions[$post_type]))
		return;

	$archiveOptions[$post_type][$meta_key] = $meta_value;
	update_option('mpcf_archive_meta', $archiveOptions);
	return true;
}


/*****************************************************
	Get meta boxes for post type archive
 *****************************************************/

function mpcf_get_archive_metaboxes_for_type($post_type = 'post') {
	if (!post_type_exists($post_type)) return;

	$boxes = get_option('mpcf_archive_boxes', array());
	$boxes = array_filter($boxes, function($box) use ($post_type) {
		return $box['post_type'] === $post_type;
	});

	return $boxes;
}

?>
