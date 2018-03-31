<?php

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
				$modules[$vars['name']] = array(
					'name'		=> $class,
					'field'		=> $vars['name'],
					'label'		=> isset($vars['label']) ? $vars['label'] : null
				);
			}
		}
	}

	$o['modules'] = $modules;
	update_option('mpcf_options', $o);
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