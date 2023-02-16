<?php

/*****************************************************
	Build graphical user interface for posts
 *****************************************************/

function mpcf_meta_box_init($post, $metabox) {
	global $post;
	$boxes  = get_option('mpcf_meta_boxes', array());
	$box    = $boxes[$metabox['id']];
	$values = get_post_meta($post->ID, '', true);
	$nonce  = 'mpcf_meta_box_nonce_' . $metabox['id'];
	wp_nonce_field($nonce, $nonce); ?>
	<div class="mpcf-parent">
<?php 	if (isset($box['panels'])) {
			mpcf_build_gui_as_panels($metabox['id'], $box['panels'], $values);
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
				$field['context'] = 'option';
				$actions = isset($field['actions']) ? $field['actions'] : array();

				if (isset($_POST[$name]) || $type === 'checkbox' || $type === 'conditional') {
					$value = isset($_POST[$name]) ? mpcf_mksafe($_POST[$name]) : false;
					$value = mpcf_before_save($field, $name, $value);
					$values[$name] = $value;

					mpcf_after_save($field, null, $value);
				}
			}
		}

		$tab = 'mpcf-activetab-' . $optionName;
		$values[$tab] = isset($_POST[$tab]) ? $_POST[$tab] : 0;
		update_option($optionName, $values);
	}

	$values = get_option($optionName);
	$message = '';

	$formName = 'mpcf-options-' . $optionName;

	if (isset($_POST['update_settings'])) {
		$message = __('Options were saved.', 'mpcf');
	} ?>

	<div class="mpcf-options">
		<form method="post" name="<?php echo $formName; ?>" id="<?php echo $formName; ?>" action="">

<?php	if (!empty($message)) { ?>
			<div id="message" class="mpcf-message updated fade"><p><strong><?php echo $message; ?></strong></p></div>
<?php	}

			mpcf_build_gui_as_panels($optionName, $panels, $values); ?>

			<div class="mpcf-options-inputs">
				<input type="hidden" name="update_settings" id="update_settings" value="Y" />
				<input type="submit" value="<?php _e('Save', 'mpcf'); ?>" id="submit" class="mpcf-submit-button button button-primary button-large" />
			</div>

		</form>
	</div>
<?php

	mpcf_create_i18n_file($formName);
}



/*****************************************************
	Build graphical user interface component wise
 *****************************************************/

function mpcf_build_gui_as_panels($id, $panels, $values) {
	$updated = mpcf_add_conditional_panels($panels, $values);
	$panels = $updated['panels'];
	$values = $updated['values'];

	$panels = array_map(function($panel) {
		$panel['panel_id']       = isset($panel['panel_id'])       ? $panel['panel_id']       : uniqid();
		$panel['panel_basename'] = isset($panel['panel_basename']) ? $panel['panel_basename'] : '';
		return $panel;
	}, $panels);

	$tab        = 'mpcf-activetab-' . $id;
	$activetab  = isset($values[$tab]) ? $values[$tab][0] : 0;
	$hasEditors = false; ?>

	<div class="mpcf-panels">
		<input type="hidden" name="<?php echo $tab; ?>" class="activetab" value="<?php echo $activetab; ?>" />
		<ul class="mpcf-panels-menu">
<?php	for ($i = 0; $i < count($panels); $i++) {
			mpcf_build_panel_menu_item($panels[$i], $i);
		} ?>
		</ul>

		<div class="mpcf-panels-tabs">
<?php	for ($i = 0; $i < count($panels); $i++) {
			$hasThisTabEditors = mpcf_build_panel_tab($panels[$i], $values, $i);
			$hasEditors        = $hasEditors || $hasThisTabEditors;
		} ?>
		</div>

	</div>

<?php

	// Preload editor as an instance for repeater fields

	if ($hasEditors) {
		$screen = get_current_screen();
		if ($screen->parent_base !== 'edit') { ?>
			<div class="mpcf-editor-instance"><?php wp_editor('', 'mpcf-editor-instance'); ?></div>
<?php	}
	}
}




function mpcf_build_panel_menu_item($panel, $i) { ?>
	<li class="mpcf-panel-item" data-index="<?php echo $i; ?>" data-panel-id="<?php echo $panel['panel_id']; ?>" data-basename="<?php echo $panel['panel_basename']; ?>">
<?php 	if (isset($panel['icon'])) {
			if (strpos($panel['icon'], 'dashicons') > -1) { ?>
			<div class="mpcf-panel-icon dashicons <?php echo $panel['icon']; ?>"></div>
<?php 		} else { ?>
			<div class="mpcf-panel-icon mpcf-panel-icon-svg" style="background-image: url(<?php echo $panel['icon']; ?>);"></div>
<?php 		}
		}

		$title = isset($panel['name'])  ? $panel['name']  : '';
		$title = isset($panel['title']) ? $panel['title'] : $title; ?>
			<span class="mpcf-panel-title"><?php echo $title; ?></span>
	</li>
<?php
}


function mpcf_build_panel_tab($panel, $values, $i) {
	$className = 'mpcf-panel' . (isset($panel['class_name']) ? ' ' . $panel['class_name'] : ''); ?>
	<div class="<?php echo $className; ?>" data-index="<?php echo $i; ?>" data-panel-id="<?php echo $panel['panel_id']; ?>" data-basename="<?php echo $panel['panel_basename']; ?>">
<?php 	mpcf_build_gui_from_fields($panel['fields'], $values);
		$hasEditors = mpcf_ajax_enqueue_editors($panel['fields']); ?>
	</div>
<?php

	return $hasEditors;
}


function mpcf_build_gui_from_fields($fields, $values, $echoRequired = true) {
	$o = get_option('mpcf_options');
	setlocale(LC_TIME, get_locale());
	$required = false;

	$id = mpcf_get_queried_object_id();

	foreach ($fields as $field) {
		if (!isset($field['type'])) continue;

		$type             = $field['type'];
		$field            = mpcf_sanitize_args($field);

		$field['post_id'] = $id;
		$field['value']   = mpcf_get_field_value($field, $values);
		$field            = mpcf_resolve_deep_fields($field);

		$required         = !$required && $field['required'] ? true : $required;
		$hasRequireds     = false;

		if (isset($o['modules'][$type])) {
			$classname = $o['modules'][$type]['name'];
			$module    = new $classname();

			$classes  = isset($field['required']) && $field['required'] ? ' mpcf-required' : '';
			$classes .= isset($module->html5) && $module->html5 ? ' mpcf-nohtml5' : '';
			$attrs    = isset($module->html5) && $module->html5 ? ' data-invalid-test="Not-a-valid-value"' : '';

			if ($type == 'conditionalpanels' && isset($field['value'][$field['name']]['panel_id'])) {
				$attrs  .= ' data-panel-id="' . $field['value'][$field['name']]['panel_id'] . '"';
				unset($field['value'][$field['name']]['panel_id']);
			}

			$wrapperClasses = isset($module->wrapperClasses) && !empty($module->wrapperClasses) ? ' ' . $module->wrapperClasses : ''; ?>

			<div class="mpcf-<?php echo $type; ?>-input mpcf-field-option<?php echo $classes; ?>" id="mpcf-field-<?php echo $field['name']; ?>"<?php echo $attrs; ?>>
				<?php mpcf_insert_field_title($field); ?>
				<div class="mpcf-field<?php echo $wrapperClasses; ?>">
<?php				$module->args = $field;
					$result = $module->build_field($field);
					$hasRequireds = $hasRequireds || $result;
					mpcf_insert_field_description($field); ?>
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




function mpcf_get_field_value($field, $values) {
	$value = isset($field['value']) ? $field['value'] : null;

	if ($value === null || empty($value)) {
		$value = isset($values[$field['name']]) ? $values[$field['name']] : $field['default'];
		$value = mpcf_resolve_sanitized_fields($value);
	}

	if ($field['type'] == 'conditionalpanels') {
		$value = $values;
	}

	if (isset($field['conditional_extracted_value'])) {
		$value = $field['conditional_extracted_value'];
		$value = mpcf_resolve_sanitized_fields($value);
		$value = isset($value[$field['name']]) ? $value[$field['name']] : $field['default'];
		if  ($field['type'] == 'repeater' &&
			(is_array($value) && array_keys($value) !== range(0, count($value) - 1))) {
			$value = array($value);
		}
	}

//	repeater values must be sequential arrays, each value for one row
	if ($field['type'] == 'repeater') {
		if (is_array($value) && array_keys($value) !== range(0, count($value) - 1)) {
			$value = array($value);
		}
	}

	if (isset($field['actions']) && isset($field['actions']['display_before'])) {
		$value = call_user_func($field['actions']['display_before'], $id, $field['name'], $value);
	}

	return $value;
}

function mpcf_insert_field_title($field) {
	if (isset($field['title']) && !empty($field['title'])) { ?>
<div class="mpcf-title"><label for="<?php echo $field['name']; ?>"><?php echo $field['title']; ?></label></div>
<?php 
	}
}

function mpcf_insert_field_description($field) {
	if (!empty($field['description']) && $field['description'] !== false) { ?>
<div class="mpcf-description"><?php echo $field['description']; ?></div>
<?php 	}
}



function mpcf_resolve_sanitized_fields($var) {
	if (is_array($var)) {
		$var = array_map('mpcf_resolve_sanitized_fields', $var);

		if (count($var) == 1 && isset($var[0]) && is_array($var[0]))
			$var = $var[0];
	} else if (is_string($var)) {
		$data = @unserialize($var);
		if ($data !== false) $var = $data;
	}

	return $var;
}

function mpcf_resolve_deep_fields($field) {
	$type = $field['type'];
	$deepFields = array('repeater', 'conditional', 'dragdroplist', 'table');
	$isDeep = in_array($type, $deepFields);

	if ($type === 'select' && isset($field['multiple']) && $field['multiple'] === true)
		$isDeep = true;

	if ($isDeep) {
		$field['value'] = $field['value'] === false || $field['value'] === '' ? array() : $field['value'];
		return $field;
	}


	$field['value'] = is_array($field['value']) && isset($field['value'][0]) ? $field['value'][0] : $field['value'];
	return $field;
}

function mpcf_tidy_up_wpml_traces($field) {
	if (!function_exists('icl_object_id')) return $field;

	if ($field['type'] == 'dragdroplist') {
		if ($field['name'] == 'modules') {
			if (isset($field['value'][0]) && is_array($field['value'][0]))
				$field['value'] = $field['value'][0];
		}
	}

	return $field;
}


function mpcf_get_queried_object_id() {
	global $post;
	global $tag_ID;

	if ($post)			return $post->ID;
	else if ($tag_ID) 	return $tag_ID;

	return null;
}




/*****************************************************
	Add conditional panels
 *****************************************************/

function mpcf_add_conditional_panels($panels, $values) {
	$adder = new MPCFRecursiveConditionalPanelsAdder($panels, $values);
	$adder->start_search();

	return [ 'panels' => $adder->get_merged_panels(), 'values' => $adder->get_merged_values() ];
}

Class MPCFRecursiveConditionalPanelsAdder {
	public $allPanels;
	public $allValues;
	public $conditionalPanels = array();

	function __construct($allPanels, $allValues = array()) {
		$this->allPanels = $allPanels;
		$this->allValues = $allValues;
	}

	public function start_search() {
		$this->add_layer($this->allPanels, null, [ 'path' => [], 'name' => [] ]);
	}

	public function add_layer($layer, $key, $args = []) {
		if (!is_array($layer)) return;

		if ($key !== null)
			$args['path'][] = $key;

		if (isset($layer['name']))
			$args['name'][] = $layer['name'];

		$baseName = $this->get_field_base_name($args);
		$baseNameConcat = implode('', array_map(function($i) use ($baseName) {
			return $i == 0 ? $baseName[$i] : '[' . $baseName[$i] . ']';
		}, array_keys($baseName)));

		if (isset($layer['type']) && $layer['type'] == 'conditionalpanels') {
			$value = $this->get_value($baseName);
			if ($value == null) return;

			if (!isset($value['type']))
				return array_walk($layer, array($this, 'add_layer'), $args);

			$type = $value['type'];

			if (!isset($layer['options'][$type])) return;
			$id = uniqid();

			$chosenOption                   = $layer['options'][$type]['panel'];
			$chosenOption['class_name']     = 'mpcf-conditionalpanel';
			$chosenOption['panel_id']       = $id;
			$chosenOption['panel_basename'] = count($args['name']) > 1 ? $args['name'][1] : $args['name'][0];

			$chosenOption['fields']         = array_map(function($field) use ($value, $baseNameConcat, $chosenOption) {
				if (!is_array($field)) return $field;

				$field['conditional_extracted_value'] = $value;				
				$field['baseName'] = $baseNameConcat;
				return $field;
			}, $chosenOption['fields']);

			$chosenOption['fields'] = array_values(array_filter($chosenOption['fields']));

			$this->add_panel_id_to_field($baseName, $id);			
			$this->conditionalPanels[] = $chosenOption;
		} else if (isset($layer['type']) && $layer['type'] == 'repeater') {
			$repeaterValues = $this->get_value($baseName);
			$repeaterValues = !is_array($repeaterValues) ? [ $repeaterValues ] : $repeaterValues;		

			foreach ($repeaterValues as $index => $row) {
				$newArgs = $args;
				$newArgs['path'][] = $index;
				$newArgs['name'][] = $index;
				array_walk($layer['fields'], array($this, 'add_layer'), $newArgs);
			}

			return;
		}

		array_walk($layer, array($this, 'add_layer'), $args);
	}

	public function get_field_base_name($args) {
		$baseName = [];

		$value     = $this->allValues;
		$fields    = $this->allPanels;
		$firstName = true;
		$fieldNames = $args['name'];

		while (count($fieldNames) > 0) {
			$name = array_shift($fieldNames);
			if (!isset($value[$name])) {
				if ($firstName) {
					$firstName = false;
					continue;
				} else {
					$baseName[] = $name;
					break;
				}
			}

			$baseName[] = $name;
			$value = $value[$name];

			if (is_array($value) && count($value) == 1 && isset($value[0]) && is_serialized($value[0])) {
				$value = unserialize($value[0]);
			}
		}

		return $baseName;
	}


	public function get_value($baseName) {
		if (empty($baseName)) return array();
		
	//	apply field tree to values, could be handled in above while loop as well

		$value = $this->allValues;
		$name = $baseName[0];
		
		while (count($baseName) > 0) {
			$name = array_shift($baseName);

			if (!isset($value[$name]))
				return $value;

			$value = $value[$name];

			if (is_array($value) && count($value) == 1 && isset($value[0]) && is_serialized($value[0])) {
				$value = unserialize($value[0]);
			}
		}

		return $value;
	}

	public function add_panel_id_to_field($baseName, $id) {
		$baseNameCopy = $baseName;

		$pointer      = &$this->allValues;
		$name         = $baseNameCopy[0];
		
		while (count($baseNameCopy) > 0) {
			$name = array_shift($baseNameCopy);

			if (!isset($pointer[$name]))
				break;

			$pointer = &$pointer[$name];

			if (is_array($pointer) && count($pointer) == 1 && isset($pointer[0]) && is_serialized($pointer[0])) {
				$pointer = unserialize($pointer[0]);
				$pointer = &$pointer;
			}
		}

		if (is_array($pointer))
			$pointer['panel_id'] = $id;
		unset($pointer);

		$name = $baseName[0];
		$this->allValues[$name] = [ serialize($this->allValues[$name]) ];
	}

	public function get_merged_panels() {
		return array_merge($this->allPanels, $this->conditionalPanels);
	}

	public function get_merged_values() {
		return $this->allValues;
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
	if (!current_user_can('edit_post', $post_id)) return;

	$post_type = get_post_type($post_id);
	$boxes     = mpcf_get_metaboxes_for_type($post_type);

	foreach ($boxes as $id => $box) {
		$nonce = 'mpcf_meta_box_nonce_' . $id;
		if (!isset($_POST[$nonce]) || !wp_verify_nonce($_POST[$nonce], $nonce)) return;

		$fields = array();
		if (isset($box['panels'])) {
			array_walk($box['panels'], function($panel) use (&$fields) {
				$fields = array_merge($fields, $panel['fields']);
			});
		}

		foreach ($fields as $field) {
			$field['context'] = 'post';
			$actions = isset($field['actions']) ? $field['actions'] : array();

			$value   = isset($_POST[$field['name']]) ? mpcf_mksafe($_POST[$field['name']]) : false;
			$value   = mpcf_before_save($field, $post_id, $value);

			update_post_meta($post_id, $field['name'], $value);
			mpcf_after_save($field, $post_id, $value);
		}
	}

	array_walk($_POST, function($value, $key) use ($post_id) {
		if (strpos($key, 'mpcf-activetab') === false) return;
		update_post_meta($post_id, $key, $value);
	});
}



/*****************************************************
	Build graphical user interface with AJAX
 *****************************************************/

function mpcf_ajax_get_repeater_row() {
	$fields = json_decode(stripcslashes($_POST['fields']), true);
	$buttons = '<div class="mpcf-repeater-row-controls"><div class="mpcf-repeater-row-remove dashicons-before dashicons-trash"></div><div class="mpcf-repeater-row-move dashicons-before dashicons-move"></div></div>';

	$enqueueEditor = false;

	ob_start();
	mpcf_build_gui_from_fields($fields, array(), false);
	$enqueueEditor = $enqueueEditor || mpcf_ajax_enqueue_editors($fields);
	echo $buttons;

	if ($enqueueEditor) {
		\_WP_Editors::enqueue_scripts();
		print_footer_scripts();
		\_WP_Editors::editor_js();
	}

	$components = ob_get_contents();
	ob_end_clean();
	echo $components;

	wp_die();
}

function mpcf_ajax_enqueue_editors($fields) {
	$hasEditors = false;
	if (isset($fields[0]['fields']))
		$hasEditors = $hasEditors || mpcf_ajax_enqueue_editors($fields[0]['fields']);

	return $hasEditors || count(array_filter($fields, function($field) {
		return $field['type'] === 'editor';
	})) > 0;
}


function mpcf_ajax_get_conditional_fields() {
	if (!isset($_POST['fields']))
		return '';
	
	$fields = json_decode(stripcslashes($_POST['fields']), true);
	$values = array();

	if (isset($_POST['values']) && $_POST['values'] !== 'false') {
		$values = $_POST['values'];

	//	This causes values to go blank when a conditional field contains a repeater field. Not sure why.
		
	//	foreach ($values as $prop => $value) {
	//		$values[$prop] = stripcslashes($value);
	//	}
	}

	ob_start();
	mpcf_build_gui_from_fields($fields, $values, false);

	$components = ob_get_contents();
	ob_end_clean();
	echo $components;

	wp_die();
}

function mpcf_ajax_get_conditional_panels_fields() {
	$panel  = json_decode(stripcslashes($_POST['panel']), true);
	$values = array();

	$panel['panel_id']       = isset($panel['panel_id'])       ? $panel['panel_id']       : uniqid();
	$panel['panel_basename'] = isset($panel['panel_basename']) ? $panel['panel_basename'] : '';

	$tab    = '';
	$menu   = '';

	if (isset($_POST['values']) && $_POST['values'] !== 'false') {
		$values = $_POST['values'];
	}

	ob_start();
	mpcf_build_panel_menu_item($panel, -1);
	$menu = ob_get_contents();
	ob_end_clean();

	ob_start();
	mpcf_build_panel_tab($panel, $values, -1);
	$tab = ob_get_contents();
	ob_end_clean();

	echo json_encode(array('tab' => $tab, 'menu' => $menu, 'values' => $values));
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

function mpcf_create_i18n_file($formName = false) {
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
		$obj->{'admin-config'}->options->pages->{'edit.php'} = '';
		$obj->{'admin-config'}->options->forms = new stdClass();

		$obj->{'admin-config'}->posts = new stdClass();
		$obj->{'admin-config'}->posts->pages = new stdClass();
		$obj->{'admin-config'}->posts->pages->{'post.php'} = '';
		$obj->{'admin-config'}->posts->pages->{'post-new.php'} = '';
		$obj->{'admin-config'}->posts->forms = new stdClass();
		$obj->{'admin-config'}->posts->forms->post = new stdClass();
		$obj->{'admin-config'}->posts->forms->post->fields = new stdClass();
		$obj->{'admin-config'}->posts->forms->post->fields->jquery = '.' . mpcf_get_multingual_class();

		$obj->{'admin-config'}->options->anchors = new stdClass();
		$obj->{'admin-config'}->options->anchors->{'mpcf-options'} = new stdClass();
		$obj->{'admin-config'}->options->anchors->{'mpcf-options'}->{'jquery'} = '.mpcf-options .mpcf-panels';
		$obj->{'admin-config'}->options->anchors->{'mpcf-options'}->{'where'} = 'before';
	} else {
		$obj = json_decode($obj);
	}

	if ($formName !== false) {
		$obj->{'admin-config'}->options->forms->{$formName} = new stdClass();
		$obj->{'admin-config'}->options->forms->{$formName}->fields = new stdClass();
		$obj->{'admin-config'}->options->forms->{$formName}->fields->all = new stdClass();
		$obj->{'admin-config'}->options->forms->{$formName}->fields->all->jquery = '.' . mpcf_get_multingual_class();
	}

	$obj = json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	file_put_contents($fileName, $obj);
}


?>
