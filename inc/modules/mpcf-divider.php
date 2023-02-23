<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFDivider')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFDivider extends MPCFModule {
	public $name = 'divider';

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
		$this->translatable = false;

		// include additional classes for the wrapper of this field
		$this->wrapperClasses = '';

		// Parameters for the field which can be set by the user
		// 'description' will be automatically added and ouput by the plugin
		$this->parameters = array();
	}

	function label() {
		return __('Divider', 'mpcf');
	}

	function build_field($args = array()) {
	}
}

endif;

?>