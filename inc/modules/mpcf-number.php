<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFNumberField')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFNumberField extends MPCFModule {
	public $name = 'number';

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
			'max',
			'min',
			'placeholder',
			'required',
			'step'
		);
	}

	function label() {
		return __('Number', 'mpcf');
	}

	function build_field($args = array()) {
		$placeholder = isset($args['placeholder']) && !empty($args['placeholder']) ? ' placeholder="' . $args['placeholder'] . '"' : '';
		$required = isset($args['required']) && $args['required'] === true ? ' required' : '';
		$step     = (isset($args['step']) && !empty($args['step']) ? ' step="' . $args['step'] . '"' : '');
		$min 	 = (isset($args['min']) && !empty($args['min']) ? ' min="' . $args['min'] . '"' : '');
		$max 	 = (isset($args['max']) && !empty($args['max']) ? ' max="' . $args['max'] . '"' : ''); ?>

		<input  type="number"
				name="<?php echo $args['name']; ?>"
				id="<?php echo $args['name']; ?>"
				value="<?php echo $args['value']; ?>"
				pattern="[0-9]+"
				<?php $placeholder . $required . $step . $min . $max; ?>>>

<?php
	}
}


endif;

?>