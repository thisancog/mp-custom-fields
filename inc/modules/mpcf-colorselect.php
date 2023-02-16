<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFColorSelectField')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFColorSelectField extends MPCFModule {
	public $name = 'colorselect';

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
				'title' => __('Required', 'mpcf'),
				'type'	=> 'truefalse',
				'default'	=> false
			),
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
						'name'	=> 'colorcode',
						'title'	=> __('Color code', 'mpcf'),
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
		);
	}

	function label() {
		return __('Color select', 'mpcf');
	}

	function get_color_row($option) {
		$title = isset($option['title']) && !empty($option['title'])
			   ? esc_html($option['title'])
			   : $option['colorcode'];
		return '<span class="mpcf-colorselect-color" style="--color: ' . $option['colorcode'] . ';"></span> <span class="mpcf-colorselect-title">' . $title . '</span>';
	}

	function build_field($args = array()) {
		$options = isset($args['options']) ? $args['options'] : array();
		$value   = $args['value'];
		$current = $value != '-1' && isset($options[$value]) ? $this->get_color_row($options[$value]) : '------';
		$params = mpcf_list_input_params($this, false); ?>
		<div class="mpcf-colorselect-inner">
			<div class="mpcf-colorselect-select" tabindex="0"><?php echo $current; ?></div>
			<div class="mpcf-colorselect-list">
				<div class="mpcf-colorselect-option" data-name="-1">------</div>
<?php 		foreach ($options as $name => $option) {
				 ?>
				<div class="mpcf-colorselect-option" data-name="<?php echo $name; ?>">
					<?php echo $this->get_color_row($option); ?>
				</div>
<?php 		} ?>
			</div>
		</div>
		<input type="hidden" class="mpcf-colorselect-hidden" value="<?php echo $value; ?>"<?php echo $params; ?>>
<?php
	}
}


endif;

?>