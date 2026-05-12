<?php

/*****************************************************
	Register a new custom field box
 *****************************************************/

function mpcf_add_custom_fields_user($roles, $id, $arguments = array()) {
	$boxes = mpcf_get_user_boxes();

	$defaults = array(
		'position'		=> 'top',
		'title'			=> '',
		'multilingual'	=> false,
		'panels'		=> array()
	);

	$newbox = array_merge($defaults, $arguments);

	$roles           = !is_array($roles) && $roles !== 'all' ? [ $roles ] : $roles;
	$newbox['roles'] = $roles;

//	Give generic title if needed
	if (empty($newbox['title'])) {
		$newbox['title'] = __('User options', 'mpcf');
	}

	$newbox['panels'] = mpcf_assign_order_to_select_fields($newbox['panels']);
	$boxes[$id]       = $newbox;

	update_option('mpcf_user_boxes', $boxes);
	return $newbox;
}



/*****************************************************
	Deregister a custom field box
 *****************************************************/

function mpcf_remove_custom_fields_user($boxID) {
	$boxes   = mpcf_get_user_boxes();
	$keys    = array_keys($boxes);
	$removed = null;

	array_walk($keys, function($id) use ($boxID, &$boxes, &$removed) {
		if ($id === $boxID) {
			$removed = $boxes[$id];
			unset($boxes[$id]);
		}
	});

	update_option('mpcf_user_boxes', $boxes);
	return $removed;
}


function mpcf_remove_all_custom_fields_user() {
	update_option('mpcf_user_boxes', array());
}



/*****************************************************
	Send meta boxes to Wordpress
 *****************************************************/

function mpcf_add_metaboxes_user() {
	$boxes = mpcf_get_user_boxes();

	foreach ($boxes as $id => $box) {
		function metaBoxLoader($user, $id, $box) {
			$roles           = $box['roles'];
			$registerThisBox = true;

			if (is_array($roles) && !empty($roles)) {
				$registerThisBox = count(array_intersect($roles, (array)$user->roles)) > 0;
			}

			if ($registerThisBox) {
				mpcf_meta_box_init_user($user, $id, $box);
			}
		}

		add_action('edit_user_profile',        function($user) use ($id, $box) { metaBoxLoader($user, $id, $box); });
		add_action('profile_personal_options', function($user) use ($id, $box) { metaBoxLoader($user, $id, $box); });
	}

	add_action('profile_update', 'mpcf_save_meta_boxes_user');
}


function mpcf_meta_box_init_user($user, $id, $metabox) {
	$boxes     = mpcf_get_user_boxes();
	$box       = $boxes[$id];
	$values    = get_user_meta($user->ID, '', true);
	$values    = mpcf_populate_user_meta($user, $values);

	$nonce     = 'mpcf_meta_box_user_nonce_' . $id;
	wp_nonce_field($nonce, $nonce); ?>
	<div class="mpcf-parent mpcf-parent-user" id="<?php echo $id; ?>">
		<header class="mpcf-user-header">
			<div class="mpcf-user-header-title"><?php echo $box['title']; ?></div>
		</header>

		<div class="mpcf-user-inner">
<?php 		mpcf_build_gui_as_panels($id, $box['panels'], $values); ?>
		</div>
	</div>
<?php
	add_action('admin_footer', function() use ($id, $box) {
		global $pagenow;
		if ($pagenow !== 'user-edit.php' && $pagenow !== 'profile.php') return;

		echo mpcf_get_meta_box_user_position($id, $box);
	});
}


function mpcf_populate_user_meta($user, $values) {
	$values['user_login']	= $user->user_login;
	$values['email']        = $user->user_email;
	$values['user_url']     = $user->user_url;
	$values['display_name'] = $user->display_name;

	return $values;
}


function mpcf_get_meta_box_user_position($id, $box) {
	$selector = '';

	switch ($box['position']) {
		case 'after_title':
			$selector = 'h2';
			break;
		case 'after_preferences_block':
			$selector = substr(str_repeat('.form-table ~ ', 1), 0, -3);
			break;
		case 'after_name_block':
			$selector = substr(str_repeat('.form-table ~ ', 2), 0, -3);
			break;
		case 'after_contact_block':
			$selector = substr(str_repeat('.form-table ~ ', 3), 0, -3);
			break;
		case 'after_about_block':
			$selector = substr(str_repeat('.form-table ~ ', 4), 0, -3);
			break;
		case 'after_account_management_block':
			$selector = substr(str_repeat('.form-table ~ ', 5), 0, -3);
			break;
		case 'after_app_passwords_block':
			$selector = '.application-passwords';
			break;
		case 'after_capabilities_block':
			$selector = '.application-passwords ~ .form-table';
			break;
		case 'before_submit':
			$selector = '#user_id';
			break;
		case 'top':
		default:
			$selector = 'p';
			break;
	}

	$script = "(() => { var box = document.querySelector('#{$id}'), prev = document.querySelector('form#your-profile {$selector}'); prev && prev.after(box); })();";

	return '<script type="text/javascript">' . $script . '</script>';
}



/*****************************************************
	Save meta box form contents
 *****************************************************/

function mpcf_save_meta_boxes_user($userID) {
	$boxes = mpcf_get_user_boxes();

	foreach ($boxes as $id => $box) {
		$nonce = 'mpcf_meta_box_user_nonce_' . $id;
		if (!isset($_POST[$nonce]) || !wp_verify_nonce($_POST[$nonce], $nonce)) continue;

		$fields = array();
		$result = mpcf_add_bulk_copypaste_panels($id, $box['panels']);
			
		array_walk($result['panels'], function($panel) use (&$fields) {				
			$fields = array_merge($fields, $panel['fields']);
		});

		foreach ($fields as $field) {
			if (!isset($field['name'])) continue;
			
			$field['context'] = 'user';
			$actions = isset($field['actions']) ? $field['actions'] : array();		

			$oldValue = get_user_meta($userID, $field['name'], true);
			$value    = isset($_POST[$field['name']]) ? mpcf_mksafe($_POST[$field['name']]) : false;
			$value    = mpcf_before_save($field, $userID, $value);;

			if (in_array($field['name'], [ 'user_url', 'display_name' ])) {
				remove_action('profile_update', 'mpcf_save_meta_boxes_user');
				wp_update_user([ 'ID' => $userID, $field['name'] => $value ]);
				add_action('profile_update', 'mpcf_save_meta_boxes_user');
			} else {
				update_user_meta($userID, $field['name'], $value);
			}

			mpcf_after_save($field, $userID, $value, $oldValue);
		}
	}

	array_walk($_POST, function($value, $key) use ($userID) {
		if (strpos($key, 'mpcf-activetab') === false) return;
		update_post_meta($userID, $key, $value);
	});
}



/*****************************************************
	Build graphical user interface for user pages
 *****************************************************/

function mpcf_get_user_boxes() {
	$boxes = get_option('mpcf_user_boxes', array());
	return $boxes;
}



/*****************************************************
	Field getters
 *****************************************************/

function mpcf_get_user_field($fieldName = null, $userID = null) {
	return mpcf_get_field($fieldName, $userID, 'user');
}

function mpcf_has_user_field($fieldName = null, $userID = null) {
	return mpcf_has_field($fieldName, $userID, 'user');
}

function mpcf_the_user_field($fieldName = null, $userID = null) {
	echo mpcf_get_user_field($fieldName, $userID);
}




?>