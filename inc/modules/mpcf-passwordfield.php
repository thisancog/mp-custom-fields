<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFPasswordField')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFPasswordField extends MPCFModule {
	public $name = 'password';
	public $label = 'Password field';

	function __construct() {
		parent::__construct();

		// If this field contains html5 input elements and therefore requires
		// a browser compatibility check
		$this->html5 = false;

		// Parameters for the field which can be set by the user
		// 'description' will be automatically added and ouput by the plugin
		$this->parameters = array(
			'maxlength',
			'minlength',
			'required'
		);
	}

	function build_field($args = array()) {
		$required = isset($args['required']) && $args['required'] === true ? ' required' : '';
		$minlength = (isset($args['minlength']) && !empty($args['minlength']) ? ' minlength="' . $args['minlength'] . '"' : '');
		$maxlength = (isset($args['maxlength']) && !empty($args['maxlength']) ? ' maxlength="' . $args['maxlength'] . '"' : ''); ?>

		<input type="password" name="<?php echo $args['name']; ?>" id="<?php echo $args['name']; ?>"
			value="<?php echo $args['value']; ?>"
			<?php echo $required . $minlength . $maxlength; ?>>
<?php
	}
}

endif;

?>