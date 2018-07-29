<?php

/*****************************************************
	Build graphical user interface
 *****************************************************/

function mpcf_meta_box_init($post, $metabox) {
	global $post;
	$boxes = get_option('mpcf_meta_boxes', array());
	$box = $boxes[$metabox['id']];
	$values = get_post_meta($post->ID, '', true);
	wp_nonce_field('mpcf_meta_box_nonce', 'mpcf_meta_box_nonce'); ?>

	<div class="mpcf-parent">

<?php 	if (isset($box['panels'])) {
			mpcf_build_gui_as_panels($box['panels'], $values);
		} else if (isset($box['fields'])) {
			mpcf_build_gui_from_fields($box['fields'], $values);
		} ?>

	</div>

<?php 
}


function mpcf_build_gui_as_panels($panels, $values) { 
	$activetab = isset($values['activetab']) ? $values['activetab'][0] : 0; ?>

	<div class="mpcf-panels">
		<input type="hidden" name="activetab" class="activetab" value="<?php echo $activetab; ?>" ?>
		<ul class="mpcf-panels-menu">

<?php	for ($i = 0; $i < count($panels); $i++) { ?>
			<li class="mpcf-panel-item" data-index="<?php echo $i; ?>">

<?php 		if (isset($panels[$i]['icon'])) {
				if (strpos($panels[$i]['icon'], 'dashicons') > -1) { ?>
					<div class="mpcf-panel-icon dashicons <?php echo $panels[$i]['icon']; ?>"></div>
<?php 			} else { ?>
					<div class="mpcf-panel-icon"><img src="<?php echo $panels[$i]['icon']; ?>" alt=""></div>
<?php 			}
			} ?>

				<span class="mpcf-panel-title"><?php echo $panels[$i]['name']; ?></span>
			</li>
<?php	} ?>

		</ul>

		<div class="mpcf-panels-tabs">

<?php	for ($i = 0; $i < count($panels); $i++) { ?>
			<div class="mpcf-panel" data-index="<?php echo $i; ?>">
				<?php mpcf_build_gui_from_fields($panels[$i]['fields'], $values); ?>
			</div>
<?php	} ?>

		</div>

	</div>

<?php
}



function mpcf_build_gui_from_fields($fields, $values, $echoRequired = true) {
	$o = get_option('mpcf_options');
	setlocale(LC_TIME, get_locale());
	$required = false;

	foreach ($fields as $field) {
		if (!isset($field['type'])) continue;

		$type = $field['type'];
		$field = mpcf_sanitize_args($field);

		if (!isset($field['value']) || empty($field['value']))
			$field['value'] = isset($values[$field['name']]) ? $values[$field['name']] : $field['default'];

		if ($type !== 'repeater')
			$field['value'] = is_array($field['value']) && isset($field['value'][0]) ? $field['value'][0] : $field['value'];

		$required = !$required && $field['required'] ? true : $required;
		$hasRequireds = false;


		if (isset($o['modules'][$type])) {
			$classname = $o['modules'][$type]['name'];
			$module = new $classname();

			$isRequired = isset($field['required']) && $field['required'] ? ' mpcf-required' : '';
			$hasHTML5   = isset($module->html5) && $module->html5 ? ' mpcf-nohtml5' : '';
			$html5Test  = isset($module->html5) && $module->html5 ? ' data-invalid-test="Not-a-valid-value"' : '';
			$wrapperClasses = isset($module->wrapperClasses) ? $module->wrapperClasses : ''; ?>

			<div class="mpcf-<?php echo $type; ?>-input mpcf-field-option<?php echo $hasHTML5 . $isRequired; ?>"<?php echo $html5Test; ?>>

<?php		if (isset($field['title']) && !empty($field['title'])) { ?>
				<div class="mpcf-title"><label for="<?php echo $field['name']; ?>"><?php echo $field['title']; ?></label></div>
<?php 		} ?>
						
				<div class="mpcf-field <?php echo $wrapperClasses; ?>">
<?php				$result = $module->build_field($field);
					$hasRequireds = $hasRequireds || $result;

					if (!empty($field['description']) && $field['description'] !== false) ?>
						<div class="mpcf-description"><?php echo $field['description']; ?></div>
				</div>
			</div>
<?php	}

		if ($hasRequireds)
			$required = true;
	}

	if ($required && $echoRequired) {
		mpcf_required_hint();
	}
}



/*****************************************************
	Sanitize fields parameters
 *****************************************************/

function mpcf_sanitize_args($args) {
	isset($args['default'])		|| $args['default'] = '';
	isset($args['description'])	|| $args['description'] = '';
	isset($args['title'])		|| $args['title'] = '';
	isset($args['name'])		|| $args['name'] = '';
	isset($args['required'])	|| $args['required'] = false;

	return $args;
}


/*****************************************************
	Save meta box form contents
 *****************************************************/

function mpcf_save_meta_boxes($post_id) {
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (!isset($_POST['mpcf_meta_box_nonce']) || !wp_verify_nonce($_POST['mpcf_meta_box_nonce'], 'mpcf_meta_box_nonce')) return;
	if (!current_user_can('edit_post', $post_id)) return;

	$boxes = get_option('mpcf_meta_boxes', array());

	foreach ($boxes as $box) {
		if ($box['post_type'] !== get_post_type($post_id)) continue;

		$fields = $box['fields'];
		if (isset($box['panels'])) {
			array_walk($box['panels'], function($panel) use (&$fields) {
				$fields = array_merge($fields, $panel['fields']);
			});
		}

		foreach ($fields as $field) {
			$value = isset($_POST[$field['name']]) ? mpcf_mksafe($_POST[$field['name']]) : false;
			$actions = isset($field['actions']) ? $field['actions'] : array();

			if (isset($actions['save_before'])) {
				$value = call_user_func($actions['save_before'], $post_id, $field['name'], $value);
			}

			$value = mpcf_before_save($field['type'], $post_id, $field['name'], $value);
			update_post_meta($post_id, $field['name'], $value);

			if (isset($actions['save_after'])) {
				call_user_func($actions['save_after'], $post_id, $field['name'], $value);
			}

			mpcf_after_save($field['type'], $post_id, $field['name'], $value);
		}
	}


	if (isset($_POST['activetab']))	
		update_post_meta($post_id, 'activetab', $_POST['activetab']);
}



/*****************************************************
	Fire 'before_save' function of module
 *****************************************************/

function mpcf_before_save($type, $post_id, $name, $value) {
	$o = get_option('mpcf_options');

	if (isset($o['modules'][$type])) {
		$classname = $o['modules'][$type]['name'];
		$module = new $classname();
		if (method_exists($module, 'save_before')) {
			$result = $module->save_before($post_id, $name, $value);
			if ($result !== null)
				$value = $result;
		}
	}

	return $value;
}


/*****************************************************
	Fire 'after_save' function of module
 *****************************************************/

function mpcf_after_save($type, $post_id, $name, $value) {
	$o = get_option('mpcf_options');

	if (isset($o['modules'][$type])) {
		$classname = $o['modules'][$type]['name'];
		$module = new $classname();

		if (method_exists($module, 'save_before'))
			$module->save_before($post_id, $name, $value);
	}
}


/*****************************************************
	Build graphical user interface with AJAX
 *****************************************************/

function mpcf_ajax_get_repeater_row() {
	$fields = json_decode(stripcslashes($_POST['fields']), true);

	$buttons = '<div class="mpcf-repeater-row-controls"><div class="mpcf-repeater-row-remove dashicons-before dashicons-trash"></div><div class="mpcf-repeater-row-move dashicons-before dashicons-move"></div></div>';

	ob_start();
	if (isset($_POST['values'])) {
		$values = json_decode(stripcslashes($_POST['values']), true);

		foreach ($values as $i => $row) { ?>
			<li class="mpcf-repeater-row">
<?php 			mpcf_build_gui_from_fields($fields, $row, false);
				echo $buttons; ?>
			</li>
<?php	}

	} else {
		mpcf_build_gui_from_fields($fields, $values, false);
		echo $buttons;
	}

	$components = ob_get_contents();
	ob_end_clean();
	echo $components;

	wp_die();
}


function mpcf_ajax_get_conditional_fields() {
	$fields = json_decode(stripcslashes($_POST['fields']), true);
	$values = array();

	if (isset($_POST['values']) && $_POST['values'] !== 'false') {
	//	$values = json_decode(stripcslashes($_POST['values']), true);
		$values = $_POST['values'];
	}

	ob_start();
	mpcf_build_gui_from_fields($fields, $values, false);

	$components = ob_get_contents();
	ob_end_clean();
	echo $components;

	wp_die();
}



/*****************************************************
	Build graphical user interface component wise
 *****************************************************/


function mpcf_required_hint() { ?>
	<div class="mpcf-required-hint mpcf-field-option">
		<div class="mpcf-title"></div>
		<div class="mpcf-field"><?php _e('* required fields', 'mpcf'); ?></div>
	</div>
<?php
}

function mpcf_update_edit_form() {
	echo ' enctype="multipart/form-data"';
}










?>