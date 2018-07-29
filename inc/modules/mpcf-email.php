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

		// include additional classes for the wrapper of this field
		$this->wrapperClasses = '';

		// Parameters for the field which can be set by the user
		// 'description' will be automatically added and ouput by the plugin
		$this->parameters = array(
			'maxlength',
			'minlength',
			'multiple',
			'placeholder',
			'required'
		);
	}

	function label() {
		return __('Email', 'mpcf');
	}

	function build_field($args = array()) {
		$required = isset($args['required']) && $args['required'] === true ? ' required' : '';
		$multiple = (isset($args['multiple']) && !empty($args['multiple']) ? ' multiple' : '');
		$placeholder = (isset($args['placeholder']) && !empty($args['placeholder']) ? ' placeholder="' . $args['placeholder'] . '"' : '');
		$minlength = (isset($args['minlength']) && !empty($args['minlength']) ? ' minlength="' . $args['minlength'] . '"' : '');
		$maxlength = (isset($args['maxlength']) && !empty($args['maxlength']) ? ' maxlength="' . $args['maxlength'] . '"' : ''); ?>

		<input  type="email"
				name="<?php echo $args['name']; ?>"
				id="<?php echo $args['name']; ?>"
				value="<?php echo $args['value']; ?>"
				<?php echo $required . $multiple . $placeholder . $minlength . $maxlength; ?>>
<?php
	}
}

endif;

?>