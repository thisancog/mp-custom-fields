<?php

/*****************************************************
	Register a new custom field box
 *****************************************************/

function mpcf_add_custom_fields_archive($post_type, $id, $arguments = array()) {
	if (!post_type_exists($post_type)) return;

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

	$boxes[$id] = $newbox;

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

function mpcf_remove_custom_fields_archive($boxID) {
	$boxes = get_option('mpcf_archive_boxes', array());
	$removed = null;

	$boxes = array_filter($boxes, function($box, $id) use ($boxID, &$removed) {
		if ($id === $boxID) {
			$removed = $box;
			return false;
		}

		return true;
	}, ARRAY_FILTER_USE_BOTH);

	update_option('mpcf_archive_boxes', $boxes);

	$meta = get_option('mpcf_archive_meta', array());
	unset($meta[$post_type]);
	update_option('mpcf_archive_meta', $meta);

	return $removed;
}

function mpcf_remove_all_custom_fields_archives() {
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

function mpcf_get_archive_boxes($post_type) {
	$boxes = get_option('mpcf_archive_boxes', array());

	$boxes = array_filter($boxes, function($box) use ($post_type) {
		return $box['post_type'] === $post_type;
	});

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
		$message = __('Options were saved.', 'mpcf');
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
						<?php mpcf_build_gui_as_panels($box['panels'], $values); ?>
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

					$value = isset($_POST[$name]) ? mpcf_mksafe($_POST[$name]) : false;
					$value = mpcf_before_save($field, null, $value);

					mpcf_update_archive_meta($post_type, $name, $value);	
					mpcf_after_save($field, null, $value);
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
	$boxes = array_filter($boxes, function($box, $id) use ($post_type) {
		return $box['post_type'] === $post_type;
	}, ARRAY_FILTER_USE_BOTH);

	return $boxes;
}

?>
