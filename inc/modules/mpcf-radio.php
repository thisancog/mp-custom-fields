<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFRadioField')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFRadioField extends MPCFModule {
	public $name = 'radio';

	function __construct() {
		parent::__construct();

		// Supply a category for the field selection menu
		// possible values: date, text, options, number, misc
		// default: misc
		$this->category = 'options';

		// If this field contains html5 input elements and therefore requires
		// a browser compatibility check
		$this->html5 = false;

		// include additional classes for the wrapper of this field
		$this->wrapperClasses = '';

		// Parameters for the field which can be set by the user
		// 'description' will be automatically added and ouput by the plugin
		$this->parameters = array(
			'options'		=> array('title' => __('Options', 'mpcf'), 'type'  => 'repeater', 'fields' => array()),
			'required'		=> array('title' => __('Required', 'mpcf'), 'type' => 'truefalse', 'description' => '')
		);
	}

	function label() {
		return __('Radio button', 'mpcf');
	}

	function build_field($args = array()) {
		$options  = isset($args['options']) && !empty($args['options']) ? $args['options'] : array();
		$required = isset($args['required']) && $args['required'] === true ? ' required' : ''; ?>

		<fieldset name="<?php echo $args['name']; ?>" id="<?php echo $args['name']; ?>"<?php echo $required; ?>>
<?php 		foreach ($options as $name => $title) {
				$id = $args['name'] . '-' . $name; ?>

			<div class="mpcf-radio-option">
				<input
					type="radio"
					name="<?php echo $args['name']; ?>"
					id="<?php echo $id; ?>"
					value="<?php echo $name; ?>"
					<?php echo ($args['value'] === $name ? ' checked' : ''); ?>>
				<label for="<?php echo $id; ?>"><?php echo $title; ?></label>
			</div>

<?php		} ?>
		</fieldset>
<?php
	}
}


endif;

?>