<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFColorField')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFColorField extends MPCFModule {
	public $name = 'color';

	function __construct() {
		parent::__construct();

		// Supply a category for the field selection menu
		// possible values: date, text, options, number, misc
		// default: misc
		$this->category = 'misc';

		// If this field contains html5 input elements and therefore requires
		// a browser compatibility check
		$this->html5 = false;

		// include additional classes for the wrapper of this field
		$this->wrapperClasses = '';

		// Parameters for the field which can be set by the user
		// 'description' will be automatically added and ouput by the plugin
		$this->parameters = array(
			array(
				'name'	=> 'required',
				'title' => __('Required', 'mpcf'),
				'type'	=> 'truefalse'
			)
		);
	}

	function label() {
		return __('Color picker', 'mpcf');
	}

	function build_field($args = array()) {
		$required = isset($args['required']) && $args['required'] === true ? ' required' : ''; ?>

		<input  type="text"
				name="<?php echo $args['name']; ?>"
				id="<?php echo $args['name']; ?>"
				value="<?php echo $args['value']; ?>"
				<?php echo $required; ?>>
<?php
	}
}


endif;

?>