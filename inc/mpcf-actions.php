<?php

/*****************************************************
	Fire 'get_default' function of module
 *****************************************************/

function mpcf_get_default($field, $post_id, $value) {
	$modules = mpcf_get_all_registered_modules();
	$type = $field['type'];

	if (isset($field['default']))
		$value = $field['default'];

	if (isset($modules[$type])) {
		$classname = $modules[$type]['name'];
		$module = new $classname();
		
		if (method_exists($module, 'get_default')) {
			$result = $module->get_default($post_id, $field, $value);
			if ($result !== null)
				$value = $result;
		}
	}

	if (isset($field['actions']) && isset($field['actions']['get_default'])) {
		$value = call_user_func($field['actions']['get_default'], $post_id, $field['name'], $value);
	}

	return $value;
}


/*****************************************************
	Fire 'before_save' function of module
 *****************************************************/

function mpcf_before_save($field, $post_id, $value) {
	if (!isset($field['type'])) return $value;
	
	$modules = mpcf_get_all_registered_modules();
	$type = $field['type'];

	if (isset($modules[$type])) {
		$classname = $modules[$type]['name'];
		$module = new $classname();

		if (method_exists($module, 'save_before')) {
			$result = $module->save_before($post_id, $field, $value);
			if ($result !== null)
				$value = $result;
		}
	}

	if (isset($field['actions']) && isset($field['actions']['save_before'])) {
		$value = call_user_func($field['actions']['save_before'], $post_id, $field['name'], $value);
	}

	return $value;
}


/*****************************************************
	Fire 'after_save' function of module
 *****************************************************/

function mpcf_after_save($field, $post_id, $value) {
	if (!isset($field['type'])) return $value;
	$modules = mpcf_get_all_registered_modules();
	$type = $field['type'];

	if (isset($modules[$type])) {
		$classname = $modules[$type]['name'];
		$module = new $classname();

		if (method_exists($module, 'save_after'))
			$module->save_after($post_id, $field, $value);
	}

	if (isset($field['actions']) && isset($field['actions']['save_after'])) {
		$value = call_user_func($field['actions']['save_after'], $post_id, $field['name'], $value);
	}
}


/*****************************************************
	Fire 'display_before' function of module
 *****************************************************/

function mpcf_display_before($field, $post_id, $value) {
	$modules = mpcf_get_all_registered_modules();
	$type = $field['type'];

	if (isset($modules[$type])) {
		$classname = $modules[$type]['name'];
		$module = new $classname();
		
		if (method_exists($module, 'display_before')) {
			$result = $module->display_before($post_id, $field, $value);
			if ($result !== null)
				$value = $result;
		}
	}

	if (isset($field['actions']) && isset($field['actions']['display_before'])) {
		$value = call_user_func($field['actions']['display_before'], $post_id, $field['name'], $value);
	}

	return $value;
}



?>