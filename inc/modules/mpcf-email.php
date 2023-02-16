<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFEmailField')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFEmailField extends MPCFModule {
	public $name = 'email';

	function __construct() {
		parent::__construct();

		// Supply a category for the field selection menu
		// possible values: date, text, options, number, misc
		// default: misc
		$this->category = 'misc';

		// If this field contains html5 input elements and therefore requires
		// a browser compatibility check
		$this->html5 = true;

		// If this field could hold translatable content.
		// This will flag the input tag with a "mpcf-multilingual" class.
		$this->translatable = true;

		// include additional classes for the wrapper of this field
		$this->wrapperClasses = '';

		// Parameters for the field which can be set by the user
		// 'description' will be automatically added and ouput by the plugin
		$this->parameters = array(
			array(
				'name'	=> 'minlength',
				'title' => __('Minimum length', 'mpcf'),
				'type'	=> 'number'
			),
			array(
				'name'	=> 'maxlength',
				'title' => __('Maximum length', 'mpcf'),
				'type'	=> 'number'
			),
			array(
				'name'	=> 'multiple',
				'title' => __('Multiple', 'mpcf'),
				'type'	=> 'truefalse'
			),
			array(
				'name'	=> 'autocomplete',
				'title'	=> __('Auto-complete', 'mpcf'),
				'type'	=> 'truefalse'
			),
			array(
				'name'	=> 'placeholder',
				'title' => __('Placeholder', 'mpcf'),
				'type'	=> $this->name,
			),
			array(
				'name'	=> 'required',
				'title' => __('Required', 'mpcf'),
				'type'	=> 'truefalse',
				'default'	=> false
			),
		);
	}

	function label() {
		return __('Email', 'mpcf');
	}

	function build_field($args = array()) { ?>
		<input type="email" value="<?php echo $args['value']; ?>"<?php echo mpcf_list_input_params($this); ?>>
<?php
	}
}

endif;

?>