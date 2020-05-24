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
		$parent   = $class;
		$isModule = false;

		while ($parent !== false && !$isModule) {
			$parent   = get_parent_class($parent);
			$isModule = $isModule || $parent === 'MPCFModule';
		}

		if ($isModule) {
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
	Retrieve fields
 ******************************************/

function mpcf_get_field($fieldName = null, $id = null, $context = 'post') {
	if ($fieldName === null) return;

	$value = null;
	$boxes = array();
	$registeredFields = [];

	if ($context === 'post') {
		$id = $id !== null && get_post_status($id) !== false ? $id : get_the_ID();
		$value = get_post_meta($id, $fieldName, true);

		$post_type = get_post_type($id);
		$boxes = mpcf_get_metaboxes_for_type($post_type);
	} else if ($context == 'archive') {
		$id = $id !== null ? $id : $wp_query->query['post_type'];
		$value = mpcf_get_archive_meta($id, $fieldName);
		$boxes = mpcf_get_archive_metaboxes_for_type($id);
	}

	array_walk($boxes, function($box) use (&$registeredFields, $fieldName) {
		array_walk($box['panels'], function($panelInBox) use (&$registeredFields, $fieldName) {
			array_walk($panelInBox['fields'], function($fieldInPanel) use (&$registeredFields, $fieldName) {
				if ($fieldInPanel['name'] === $fieldName)
					$registeredFields[] = $fieldInPanel;
			});
		});
	});

	if (!empty($registeredFields)) {
		$registeredField = $registeredFields[0];
		$value = mpcf_display_before($registeredField, $id, $value);
	}

	return $value;
}

function mpcf_has_field($field = null, $id = null) {
	$value = mpcf_get_field($field, $id);
	return $value !== null && !empty($value);
}

function mpcf_the_field($field = null) {
	echo mpcf_get_field($field);
}


/******************************************
	Retrieve options
 ******************************************/

function mpcf_get_option($fieldName = null, $parentOption = null) {
	if ($fieldName === null || $parentOption === null) return;

	/* ToDo: store and retrieve option boxes in/from a variable */

//	WPML support for multilingual options
	if (has_action('wpml_multilingual_options')) {
		do_action('wpml_multilingual_options', $parentOption);
	}

	$o = get_option($parentOption);
	$value = isset($o[$fieldName]) ? $o[$fieldName] : null;
	return $value;
}

function mpcf_has_option($fieldName = null, $parentOption = null) {
	$value = mpcf_get_option($fieldName, $parentOption);
	return $value !== null && !empty($value);
}

function mpcf_the_option($fieldName = null, $parentOption = null) {
	echo mpcf_get_option($fieldName, $parentOption);
}




/******************************************
	Utility functions
 ******************************************/


function mpcf_get_multingual_class() {
	$o = get_option('mpcf_options');
	return isset($o['multilingualclass']) && !empty($o['multilingualclass']) ? $o['multilingualclass'] : '';
}

function mpcf_is_translatable($field) {
	$args = $field->args;
	$dontTranslate = isset($args['notranslate']) ? $args['notranslate'] : false;
	return !$dontTranslate && $field->translatable;
}

function mpcf_get_input_class($field, $append = '') {
	$classes	= 'mpcf-input-' . $field->name;
	$args	= $field->args;
	$paramClass	= (isset($args['inputClass']) && !empty($args['inputClass']) ? ' ' . $args['inputClass'] : '');
	$translatable = mpcf_is_translatable($field) ? ' ' . mpcf_get_multingual_class() : '';

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
