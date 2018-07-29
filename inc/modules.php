<?php


/*****************************************************
	Register modules
 *****************************************************/

function mpcf_register_modules() {
	$o = get_option('mpcf_options');
	$modules = array();

	foreach (glob(__DIR__ . '/modules/*.php') as $module) {
		include_once($module);
	}

	$declared = get_declared_classes();
	foreach ($declared as $class) {
		if (get_parent_class($class) === 'MPCFModule') {
			$vars = get_class_vars($class);

			if (isset($vars['name']) && !isset($modules[$vars['name']])) {
				$label = isset($vars['label']) ? $vars['label'] : null;

				if (method_exists($class, 'label')) {
					$instance = new $class();
					$label = $instance->label();
				}

				$modules[$vars['name']] = array(
					'name'		=> $class,
					'field'		=> $vars['name'],
					'label'		=> $label
				);
			}
		}
	}

	$o['modules'] = $modules;
	update_option('mpcf_options', $o);
}



/*********************************************************
	Get list of all registered modules and their options
 *********************************************************/

function mpcf_get_all_registered_modules() {
	$o = get_option('mpcf_options');
	return $o['modules'];
}



/*********************************************************
	Compile list of all options of all registered modules
 *********************************************************/

function mpcf_get_all_registered_modules_options() {
	$allOptions = array();
	$modules = mpcf_get_all_registered_modules();

	foreach ($modules as $module) {
		$classname = $module['name'];
		$classFieldName = $module['field'];
		$classPrettyName = $module['label'];
		$class = new $classname();

		if (!isset($class->parameters)) {
			continue;
		}

		$parameters = $class->parameters;
		$options = array(
			'name'	=> array(
				'type'		=> 'text',
				'title'		=> __('Name', 'mpcf'),
				'required'	=> true
			),
			'title' => array(
				'type'		=> 'text',
				'title'		=> __('Title', 'mpcf')
			)
		);

		foreach ($parameters as $name => $data) {
			if (is_array($data)) {
				$optionSet = array();
				foreach ($data as $dataTitle => $dataValue) {
					$optionSet[$dataTitle] = $dataValue;
				}

				$options[$name] = $optionSet;
			}
		}

		if (!empty($options)) {
			$allOptions[$classFieldName]['title'] = $classPrettyName;
			$allOptions[$classFieldName]['fields'] = $options;
		}
	}

	usort($allOptions, function($a, $b) {
		return strcmp($a['title'], $b['title']);
	});

	return $allOptions;
}





/*****************************************************
	Base field module
 *****************************************************/

class MPCFModule {
	public $name = 'base';
	public $label = 'Base Class';

	function __construct() {
		
	}

	function build_field($args = array()) {
		
	}
}

?>