<?php


/*****************************************************
	Register a new modularised metabox
 *****************************************************/

function mpcf_add_module_metabox($type, $id, $arguments = array()) {
	if (!post_type_exists($type)) return;

	$isUpdated = mpcf_check_metabox_version($id, $arguments, 'module');
	if (!$isUpdated) return;

	$boxes = get_option('mpcf_meta_boxes', array());

	$defaults = array(
		'post_type'		=> 'post',
		'post_format'	=> '',
		'page_template'	=> '',
		'title'			=> '',
		'context'		=> 'normal',
		'priority'		=> 'default',
		'multilingual'	=> false,
		'modules'		=> array(),
		'base_name'		=> 'modules'
	);

	$newbox = array_merge($defaults, $arguments);
	$newbox['post_type'] = $type;
	$newbox['type']      = 'module-metabox';

//	Give generic title if needed
	if (empty($newbox['title'])) {
		$obj = get_post_type_object($type);
		$newbox['title'] = sprintf(__('%s Options', 'mpcf'), $obj->labels->singular_name);
	}

	$newbox['modules'] = mpcf_assign_order_to_select_fields($newbox['modules']);
	$boxes[$id]        = $newbox;

	update_option('mpcf_meta_boxes', $boxes);
	return $newbox;
}


function mpcf_remove_module_metabox($boxID) {
	mpcf_remove_metabox_from_versions($boxID, 'module');
	return mpcf_remove_custom_fields($boxID);
}




/*****************************************************
	GUI
 *****************************************************/

function mpcf_build_gui_as_modules($id, $modules, $values, $baseName, $active) {
	$newModuleID = 'newmodule-' . uniqid();
	$moduleID    = 'mpcf-activetab-' . $id;
	$active      = explode(',', $active);
	$values      = !empty($values) ? $values : [];


//	Backwward compitibility to transition from a series of conditional panels with "modules" / "module" naming
	$values      = array_map(function($value) {
		if (!is_array($value) || count($value) !== 1 || !isset($value['module'])) return $value;
		$value = $value['module'];

		if (!isset($value['module_type']) && isset($value['type'])) {
			$value['module_type'] = $value['type'];
			unset($value['type']);
		}

		return $value;
	}, $values);

	$modulesJSON = esc_attr(json_encode($modules, JSON_FORCE_OBJECT | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP)); ?>

	<div class="mpcf-modules" data-basename="<?php echo $baseName; ?>">
		<div class="mpcf-modules-inner">
<?php 	for ($i = 0; $i < count($values); $i++) {
	 			$valueModule = $values[$i];
				if (!is_array($valueModule)) continue;

			//	Backwward compitibility to transition from a series of conditional panels
			//	with "modules" / "module" naming
				if (isset($valueModule['module'])) $valueModule = $valueModule['module'];
				if (!isset($valueModule['module_type'])) continue;

				$type = $valueModule['module_type'];
				if (!isset($modules[$type])) continue;

				$isActive = in_array($i, $active);

				mpcf_build_module($modules[$type], $type, $valueModule, $i, $baseName, $isActive);
			} ?>			
		</div>

		<div class="mpcf-modules-controls">
			<div class="mpcf-modules-controls-inner">
				<select id="<?php echo $newModuleID; ?>" data-modules="<?php echo $modulesJSON; ?>" data-basename="<?php echo $baseName; ?>">
<?php 			foreach ($modules as $module => $info) { ?>
					<option value="<?php echo $module; ?>"><?php echo $info['title']; ?></option>
<?php			} ?>
				</select>

				<input type="button" class="mpcf-modules-add-module mpcf-button" value="<?php _e('Add new module', 'mpcf'); ?>"/>
				<input type="hidden" name="<?php echo $moduleID; ?>" class="activetab" value="<?php echo implode(',', $active); ?>" />
			</div>
		</div>
	</div>
<?php

	// Register TinyMCE editor scripts

	_WP_Editors::enqueue_scripts();
	print_footer_scripts();
	\_WP_Editors::editor_js();
}



function mpcf_build_module($module, $type, $values, $i, $baseName, $isActive = false) {
	$id         = uniqid();
	$icon       = isset($module['icon'])  ? $module['icon']  : '';
	$title      = isset($module['title']) ? $module['title'] : __('Module', 'mpcf');
	$className  = 'mpcf-module' . (isset($module['class_name']) ? ' ' . $module['class_name'] : '');
	$namePrefix = $baseName . '[' . $i . ']';
	$typeName   = $namePrefix . '[module_type]';
	$className .= $isActive ? ' active' : '';

	$fields     = array_map(function($field) use ($namePrefix) {
		$field['baseName'] = $namePrefix;
		return $field;
	}, $module['fields']); ?>
	<section class="<?php echo $className; ?>" data-module-id="<?php echo $id; ?>" data-basename="<?php echo $baseName; ?>[<?php echo $i; ?>]">
		<header class="mpcf-module-header">
			<div class="mpcf-module-info">
<?php 		if (!empty($icon)) {
				if (strpos($icon, 'dashicons') > -1) { ?>
					<div class="mpcf-module-icon dashicons <?php echo $icon; ?>"></div>
<?php 			} else { ?>
					<div class="mpcf-module-icon mpcf-module-icon-svg" style="background-image: url(<?php echo $icon; ?>);"></div>
<?php 			}
			} ?>

				<div class="mpcf-module-title"><?php echo $title; ?></div>
			</div>

			<div class="mpcf-module-controls">
				<button type="button" class="mpcf-btn-remove-module" title="<?php _e('Remove module', 'mpcf'); ?>"><span></span></button>

				<button type="button" class="mpcf-btn-move-module-up" title="<?php _e('Move up', 'mpcf'); ?>"><span></span></button>

				<button type="button" class="mpcf-btn-move-module-down" title="<?php _e('Move down', 'mpcf'); ?>"><span></span></button>

				<button type="button" class="mpcf-btn-show-module" title="<?php _e('Show module', 'mpcf'); ?>"><span></span></button>

				<input type="hidden" class="mpcf-module-type" name="<?php echo $typeName; ?>" value="<?php echo $type; ?>" />
			</div>
		</header>

		<main class="mpcf-module-inner">
			<?php mpcf_build_gui_from_fields($fields, $values); ?>
		</main>
	</section>
<?php
}



function mpcf_ajax_get_module_fields() {
	$module   = json_decode(stripcslashes($_POST['module']), true);
	$type     = $_POST['type'];
	$i        = intval($_POST['i']);
	$baseName = $_POST['baseName'];

	ob_start();
	mpcf_build_module($module, $type, [], $i, $baseName);
	$moduleContent = ob_get_contents();
	ob_end_clean();

	echo $moduleContent;
	wp_die();
}

?>
