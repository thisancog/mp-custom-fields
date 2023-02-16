<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFImageButtonGroupField')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFImageButtonGroupField extends MPCFButtonGroupField {
	public $name = 'imagebuttongroup';

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
						'name'	=> 'image',
						'title'	=> __('Image', 'mpcf'),
						'type'	=> 'media',
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
		return __('Image button group', 'mpcf');
	}

	function build_field($args = array()) {
		$options = isset($args['options']) && !empty($args['options']) ? $args['options'] : array();
		$default = mpcf_get_input_param($this, 'default'); ?>

		<div class="mpcf-buttongroup-wrapper mpcf-imagebuttongroup-wrapper">
<?php 		foreach ($options as $name => $image) {
				$id = uniqid('mpcf-input-imagebuttongroup-' . $name . '-');
				$class = '';
				$attr  = '';
				if (strpos($image, 'dashicon') > -1) {
					$class .= ' class="dashicons ' . $image . '"';
				} else if (strpos($image, 'http://') > -1) {
					$attr = ' style="background-image: url(' . trim($image) . ');"';
					$image = '';
				} else {
					$class .= ' class="mpcf-has-svg-icon"';
					$attr  = ' style="background-image: url(' . trim($image) . ');"';
					$image = '';
				} ?>

				<div class="mpcf-buttongroup-option mpcf-imagebuttongroup-option">
					<input  type="radio"
							<?php echo mpcf_input_name($this); ?>
							id="<?php echo $id; ?>"
							value="<?php echo $name; ?>"
							<?php echo ($args['value'] == $name ? ' checked' : ''); ?>>
					<label <?php echo $class . $attr; ?> for="<?php echo $id; ?>"></label>
				</div>

<?php		} ?>
		</div>
<?php
	}
}

endif;

?>