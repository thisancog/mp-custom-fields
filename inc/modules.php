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
//	$allOptions = array('text' => array(), 'date' => array(), 'options' => array(), 'number' => array(), 'misc' => array());
	$modules = mpcf_get_all_registered_modules();

//	$categoryTitles = array('date' => __('Date and time', 'mpcf'), 'text' => __('Text', 'mpcf'), 'options' => __('Options', 'mpcf'), 'number' => __('Numbers', 'mpcf'), 'misc' => __('Miscellaneous', 'mpcf'));

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
			array(
				'name'		=> 'name',
				'type'		=> 'text',
				'title'		=> __('Name', 'mpcf'),
				'required'	=> true
			),
			array(
				'name'		=> 'title',
				'type'		=> 'text',
				'title'		=> __('Title', 'mpcf')
			)
		);

	//	$category = isset($class->category) ? $class->category : 'misc';

		foreach ($parameters as $name => $data) {
			if (is_array($data)) {
				$optionSet = array();
				foreach ($data as $dataTitle => $dataValue) {
					$optionSet[$dataTitle] = $dataValue;
				}

				$options[] = $optionSet;
			}
		}

		if (!empty($options)) {
		//	$allOptions[$category][$classFieldName]['title'] = $classPrettyName;
		//	$allOptions[$category][$classFieldName]['fields'] = $options;

			$allOptions[$classFieldName]['title'] = $classPrettyName;
			$allOptions[$classFieldName]['fields'] = $options;
		}
	}

	// $result = array();
	// foreach ($allOptions as $category => $data) {
	// 	$result['header-' . $category]['title'] = array('title' => $categoryTitles[$category], 'disabled' => true);

	// 	usort($data, function($a, $b) {
	// 		return strcmp($a['title'], $b['title']);
	// 	});

	// 	$result = array_merge($result, $data);
	// }
	// return $result;

	uasort($allOptions, function($a, $b) {
	 	return strcmp($a['title'], $b['title']);
	});

	return $allOptions;
}





/*****************************************************
	Base field module
 *****************************************************/

class MPCFModule {
	public $name = 'base';
	public $args = array();

	function __construct() {
		// Supply a category for the field selection menu
		// possible values: date, text, options, number, misc
		// default: misc
		$this->category = 'text';

		// If this field contains html5 input elements and therefore requires
		// a browser compatibility check
		$this->html5 = false;

		// include additional classes for the wrapper of this field
		$this->wrapperClasses = '';

		// Parameters for the field which can be set by the user
		// 'description' will be automatically added and ouput by the plugin
		$this->parameters = array();
	}

	function label() {
		return __('Base Class', 'mpcf');
	}

	function build_field($params = array()) {
		
	}
}

?>