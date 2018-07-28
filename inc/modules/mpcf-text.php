<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFTextField')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFTextField extends MPCFModule {
	public $name = 'text';
	public $label = 'Text field';

	function __construct() {
		parent::__construct();

		// If this field contains html5 input elements and therefore requires
		// a browser compatibility check
		$this->html5 = false;

		// include additional classes for the wrapper of this field
		$this->wrapperClasses = '';

		// Parameters for the field which can be set by the user
		// 'description' will be automatically added and ouput by the plugin
		$this->parameters = array(
			'maxlength',
			'minlength',
			'placeholder',
			'required'
		);
	}

	function build_field($args = array()) {
		$required = isset($args['required']) && $args['required'] === true ? ' required' : '';
		$placeholder = (isset($args['placeholder']) && !empty($args['placeholder']) ? ' placeholder="' . $args['placeholder'] . '"' : '');
		$minlength = (isset($args['minlength']) && !empty($args['minlength']) ? ' minlength="' . $args['minlength'] . '"' : '');
		$maxlength = (isset($args['maxlength']) && !empty($args['maxlength']) ? ' maxlength="' . $args['maxlength'] . '"' : ''); ?>

		<input type="text" name="<?php echo $args['name']; ?>" id="<?php echo $args['name']; ?>"
			value="<?php echo $args['value']; ?>"
			<?php echo $required . $placeholder . $minlength . $maxlength; ?>>
<?php
	}
}

endif;

?>