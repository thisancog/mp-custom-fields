<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFTrueFalseField')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFTrueFalseField extends MPCFModule {
	public $name = 'truefalse';

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
		$this->wrapperClasses = 'mpcf-buttongroup-input';

		// Parameters for the field which can be set by the user
		// 'description' will be automatically added and ouput by the plugin
		$this->parameters = array(
			array(
				'name'	=> 'default',
				'title' => __('Default', 'mpcf'),
				'type'  => 'truefalse'
			)
		);
	}

	function label() {
		return __('True/false button', 'mpcf');
	}

	function save_before($post_id, $field, $value) {
		return ($value === 'true');
	}

	function build_field($args = array()) {
		$options = array('true' => __('yes', 'mpcf'), 'false' => __('no', 'mpcf'));
		$class 	 = mpcf_input_class($this); ?>

		<div class="mpcf-truefalse-wrapper mpcf-buttongroup-wrapper">
<?php 		foreach ($options as $name => $title) {
				$id = mpcf_get_input_id($this, $name);
				$checked = ($name === 'true'  &&  $args['value']) ||
						   ($name === 'false' && !$args['value']); ?>

				<div class="mpcf-truefalse-option mpcf-buttongroup-option">
					<input type="radio" id="<?php echo $id; ?>"<?php echo mpcf_input_name($this) . mpcf_input_own_name($this); ?> value="<?php echo $name; ?>" <?php echo $class . ($checked ? ' checked' : ''); ?>>
					<label for="<?php echo $id; ?>"><?php echo $title; ?></label>
				</div>

<?php		} ?>
		</div>
<?php
	}
}

endif;

?>