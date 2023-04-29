<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFCustomField')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFCustomField extends MPCFModule {
	public $name = 'custom';

	function __construct() {
		parent::__construct();

		// Supply a category for the field selection menu
		// possible values: date, text, options, number, misc
		// default: misc
		$this->category = 'misc';

		// If this field contains html5 input elements and therefore requires
		// a browser compatibility check
		$this->html5 = false;

		// If this field could hold translatable content.
		// This will flag the input tag with a "mpcf-multilingual" class.
		$this->translatable = true;

		// include additional classes for the wrapper of this field
		$this->wrapperClasses = '';

		// Parameters for the field which can be set by the user
		// 'description' will be automatically added and ouput by the plugin
		$this->parameters = array(
			array(
				'name'	=> 'callback',
				'title' => __('Callback', 'mpcf'),
				'type'	=> 'text',
				'description'	=> __('a callback function to be applied to populate this field, called with arguments $field and $args, i.e. cb($field, $args)', 'mpcf')
			),
			array(
				'name'	=> 'required',
				'title' => __('Required', 'mpcf'),
				'type'	=> 'truefalse',
				'default'	=> false
			)
		);
	}

	function label() {
		return __('Text field', 'mpcf');
	}

	function build_field($args = array()) {
		$cb = $args['callback'];

		if (is_string($cb) && !function_exists($cb)) return;
		if (is_array($cb) && !is_object($cb[0])) return;
		if (is_array($cb) && !method_exists($cb[0], $cb[1])) return;

		call_user_func($cb, $this, $args);
	}
}

endif;

?>