<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFPasswordField')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFPasswordField extends MPCFModule {
	public $name = 'password';

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
				'title'	=> __('Required', 'mpcf'),
				'type'	=> 'truefalse'
			),
			array(
				'name'	=> 'minlength',
				'title' => __('Minimum length', 'mpcf'),
				'type'	=> 'number'
			),
			array(
				'name'	=> 'maxlength',
				'title' => __('Maximum length', 'mpcf'),
				'type'	=> 'number'
			)
		);
	}

	function label() {
		return __('Password', 'mpcf');
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