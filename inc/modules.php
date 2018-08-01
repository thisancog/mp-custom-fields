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
	mpcf_create_i18n_file();
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

		// If this field could hold translatable content.
		// This will flag the input tag with a "mpcf-multilingual" class.
		$this->translatable = false;

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



/******************************************
	Field helper functions
 ******************************************/

function mpcf_get_multingual_class() {
	$o = get_option('mpcf_options');
	return isset($o['multilingualclass']) && !empty($o['multilingualclass']) ? $o['multilingualclass'] : '';
}

function mpcf_get_input_class($field, $append = '') {
	$classes	= 'mpcf-input-' . $field->name;
	$args	= $field->args;
	$paramClass	= (isset($args['inputClass']) && !empty($args['inputClass']) ? ' ' . $args['inputClass'] : '');

	$dontTranslate = isset($args['notranslate']) ? $args['notranslate'] : false;
	$translatable = !$dontTranslate && $field->translatable ? ' ' . mpcf_get_multingual_class() : '';

	return $classes . $paramClass . $translatable . (!empty($append) ? ' ' . $append : '');
}

function mpcf_input_class($field, $append = '') {
	return ' class="' . mpcf_get_input_class($field, $append) . '"';
}

function mpcf_get_input_id($field) {
	$id			= uniqid('mpcf-input-' . $field->name . '-');
	$fieldArgs	= $field->args;
	$paramId	= (isset($fieldArgs['inputId']) && !empty($fieldArgs['inputId']) ? ' ' . $fieldArgs['inputId'] : '');

	return $id . $paramId;
}

function mpcf_input_id($field) {
	return ' id="' . mpcf_get_input_id($field) . '"';
}

function mpcf_get_input_param($field, $param) {
	$fieldArgs = $field->args;
	return isset($fieldArgs[$param]) && !empty($fieldArgs[$param]) ? $fieldArgs[$param] : null;
}

function mpcf_input_param($field, $param) {
	$paramName = is_string($param) ? $param : (is_array($param) ? $param['name'] : null);
	if ($paramName === null) return;

	$value = mpcf_get_input_param($field, $paramName);
	if (mpcf_is_simple_param($param)) {
		$output = $value ? ' ' . $paramName : '';
	} else {
		$output = $value ? ' ' . $paramName . '="' . $value . '"' : '';
	}

	return $output;
}

function mpcf_is_simple_param($param) {
	if (is_array($param) && isset($param['simple']) && $param['simple'])
		return true;

	$paramName = is_string($param) ? $param : (is_array($param) ? $param['name'] : null);
	if ($paramName === null)
		return;

	$simples = array(
				'autofocus', 'checked', 'disabled',
				'formnovalidate', 'multiple', 'novalidate',
				'readonly', 'required'
			);

	return in_array($paramName, $simples);
}

function mpcf_list_input_params($field, $params = array()) {
	$output = mpcf_input_class($field) . mpcf_input_id($field);
	if ($params === false) return $output;

	if (empty($params)) {
		$params = array_map(function($param) {
			return isset($param['name']) ? $param['name'] : '';
		}, $field->parameters);
	}

	if (is_string($params))
		$params = array($params);

	foreach ($params as $param) {
		$output .= mpcf_input_param($field, $param);
	}

	return $output;
}


?>