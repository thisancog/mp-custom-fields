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
			array(
				'name'	=> 'options',
				'title' => __('Choices', 'mpcf'),
				'type'	=> 'repeater',
				'fields' => array(
					array(
						'name'	=> 'name',
						'title'	=> __('Name', 'mpcf'),
						'type'	=> 'text',
						'required'	=> true
					),
					array(
						'name'	=> 'title',
						'title'	=> __('Title', 'mpcf'),
						'type'	=> 'text',
						'required'	=> true
					)
				)
			),
			array(
				'name'	=> 'default',
				'title'	=> __('Default', 'mpcf'),
				'type'	=> 'text'
			),
			array(
				'name'	=> 'required',
				'title' => __('Required', 'mpcf'),
				'type'	=> 'truefalse',
				'default'	=> false
			)
		);
	}

	function label() {
		return __('Radio button', 'mpcf');
	}

	function build_field($args = array()) {
		$options = isset($args['options']) && !empty($args['options']) ? $args['options'] : array();
		$params  = mpcf_list_input_params($this, 'required');
		$default = mpcf_get_input_param($this, 'default'); ?>

		<fieldset <?php echo mpcf_input_name($this) . mpcf_input_id($this); ?>"<?php echo $params; ?>>
<?php 		foreach ($options as $name => $title) {
				$id = $args['name'] . '-' . $name;
				if ($default && $default === $name)
					$title .= __(' (default)', 'mpcf'); ?>

			<div class="mpcf-radio-option">
				<input type="radio"<?php echo mpcf_input_name($this); ?> id="<?php echo $id; ?>" value="<?php echo $name; ?>" <?php echo ($args['value'] == $name ? ' checked' : ''); ?>>
				<label for="<?php echo $id; ?>"><?php echo $title; ?></label>
			</div>

<?php		} ?>
		</fieldset>
<?php
	}
}


endif;

?>