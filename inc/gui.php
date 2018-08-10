<?php

/*****************************************************
	Build graphical user interface for posts
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



/*****************************************************
	Build graphical user interface for admin pages
 *****************************************************/

function mpcf_build_admin_gui($panels, $optionName) {
	if (isset($_POST['update_settings'])) {
		$values = get_option($optionName);

		foreach ($panels as $panel) {
			foreach ($panel['fields'] as $field) {
				$name = $field['name'];
				$type = $field['type'];
				$actions = isset($field['actions']) ? $field['actions'] : array();

				$value = isset($_POST[$name]) ? mpcf_mksafe($_POST[$name]) : false;
				if (isset($actions['save_before']))
					$value = call_user_func($actions['save_before'], null, $name, $value);

				$value = mpcf_before_save($type, null, $name, $value);
				$values[$name] = $value;
				
				if (isset($actions['save_after']))
					call_user_func($actions['save_after'], null, $name, $value);

				mpcf_after_save($type, null, $name, $value);
			}
		}

		update_option($optionName, $values);
	}

	$values = get_option($optionName);
	$message = '';

	if (isset($_POST['update_settings'])) {
		$message = __('Options were saved.', 'mpcf');
	} ?>

	<div class="mpcf-options">
		<form method="post" name="mpcf-options-<?php echo $optionName; ?>" id="mpcf-options-<?php echo $optionName; ?>" action="">

<?php	if (!empty($message)) { ?>
			<div id="message" class="mpcf-message updated fade"><p><strong><?php echo $message; ?></strong></p></div>
<?php	}

			mpcf_build_gui_as_panels($panels, $values); ?>

			<div class="mpcf-options-inputs">
				<input type="hidden" name="update_settings" id="update_settings" value="Y" />
				<input type="submit" value="<?php _e('Save', 'mpcf'); ?>" id="submit" class="mpcf-submit-button button button-primary button-large" />
			</div>

		</form>
	</div>
<?php

	mpcf_create_i18n_file($optionName);
}



/*****************************************************
	Build graphical user interface component wise
 *****************************************************/

function mpcf_build_gui_as_panels($panels, $values) { 
	$activetab = isset($values['mpcf-activetab']) ? $values['mpcf-activetab'][0] : 0; ?>

	<div class="mpcf-panels">
		<input type="hidden" name="mpcf-activetab" class="activetab" value="<?php echo $activetab; ?>" ?>
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

		if (!isset($field['value']) || empty($field['value'])) {
			$field['value'] = isset($values[$field['name']]) ? $values[$field['name']] : $field['default'];
		}

		if ($type !== 'repeater' && $type !== 'conditional')
			$field['value'] = is_array($field['value']) && isset($field['value'][0]) ? $field['value'][0] : $field['value'];

		$required = !$required && $field['required'] ? true : $required;
		$hasRequireds = false;

		if (isset($o['modules'][$type])) {
			$classname = $o['modules'][$type]['name'];
			$module = new $classname();

			$isRequired = isset($field['required']) && $field['required'] ? ' mpcf-required' : '';
			$hasHTML5   = isset($module->html5) && $module->html5 ? ' mpcf-nohtml5' : '';
			$html5Test  = isset($module->html5) && $module->html5 ? ' data-invalid-test="Not-a-valid-value"' : '';
			$wrapperClasses = isset($module->wrapperClasses) && !empty($module->wrapperClasses) ? ' ' . $module->wrapperClasses : ''; ?>

			<div class="mpcf-<?php echo $type; ?>-input mpcf-field-option<?php echo $hasHTML5 . $isRequired; ?>"<?php echo $html5Test; ?>>

<?php		if (isset($field['title']) && !empty($field['title'])) { ?>
				<div class="mpcf-title"><label for="<?php echo $field['name']; ?>"><?php echo $field['title']; ?></label></div>
<?php 		} ?>
						
				<div class="mpcf-field<?php echo $wrapperClasses; ?>">
<?php				$module->args = $field;
					$result = $module->build_field($field);
					$hasRequireds = $hasRequireds || $result;

					if (!empty($field['description']) && $field['description'] !== false) { ?>
						<div class="mpcf-description"><?php echo $field['description']; ?></div>
<?php 				} ?>
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
	isset($args['inputClass'])	|| $args['inputClass'] = '';

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
	Graphical user interface bits
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



/*****************************************************
	Create internationalisation file
 *****************************************************/

function mpcf_create_i18n_file($optionName = false) {
	if (!defined('QTRANSLATE_FILE')) return;

	$fileName = dirname(dirname(__FILE__)) . '/i18n-config.json';

	if (!file_exists($fileName))
		fopen($fileName, 'w') or die(sprintf(__("Can't create internationalisation file %s."), $fileName));

	$obj = file_get_contents($fileName);

	if (empty($obj)) {
		$obj = new stdClass();
		$obj->vendor = new stdClass();
		$obj->vendor->{'plugins/mp-custom-fields'} = '1.0';

		$obj->{'admin-config'} = new stdClass();
		$obj->{'admin-config'}->options = new stdClass();
		$obj->{'admin-config'}->options->pages = new stdClass();
		$obj->{'admin-config'}->options->pages->{'options-general.php'} = '';
		$obj->{'admin-config'}->options->pages->{'admin.php'} = '';
		$obj->{'admin-config'}->options->forms = new stdClass();

		$obj->{'admin-config'}->posts = new stdClass();
		$obj->{'admin-config'}->posts->pages = new stdClass();
		$obj->{'admin-config'}->posts->pages->{'post.php'} = '';
		$obj->{'admin-config'}->posts->pages->{'post-new.php'} = '';
		$obj->{'admin-config'}->posts->forms = new stdClass();
		$obj->{'admin-config'}->posts->forms->post = new stdClass();
		$obj->{'admin-config'}->posts->forms->post->fields = new stdClass();
		$obj->{'admin-config'}->posts->forms->post->fields->jquery = '.' . mpcf_get_multingual_class();
	} else {
		$obj = json_decode($obj);
	}

	if ($optionName !== false) {
		$formName = 'mpcf-options-'. $optionName;
		$obj->{'admin-config'}->options->forms->{$formName} = new stdClass();
		$obj->{'admin-config'}->options->forms->{$formName}->fields = new stdClass();
		$obj->{'admin-config'}->options->forms->{$formName}->fields->jquery = '.' . mpcf_get_multingual_class();
	}

	$obj = json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	file_put_contents($fileName, $obj);
}


?>