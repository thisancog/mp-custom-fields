<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFHiddenField')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFHiddenField extends MPCFModule {
	public $name = 'hidden';

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
		$this->parameters = array();
	}

	function label() {
		return __('Hidden field', 'mpcf');
	}

	function build_field($args = array()) { ?>
		<input type="hidden" value="<?php echo $args['value']; ?>"<?php echo mpcf_list_input_params($this, false); ?>>
<?php
	}
}


endif;

?>