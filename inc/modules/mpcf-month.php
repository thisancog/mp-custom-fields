<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFMonthField')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFMonthField extends MPCFModule {
	public $name = 'month';

	function __construct() {
		parent::__construct();

		// Supply a category for the field selection menu
		// possible values: date, text, options, number, misc
		// default: misc
		$this->category = 'date';

		// If this field contains html5 input elements and therefore requires
		// a browser compatibility check
		$this->html5 = true;

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
				'name'	=> 'placeholder',
				'title' => __('Placeholder', 'mpcf'),
				'type'	=> $this->name,
			),
			array(
				'name'	=> 'min',
				'title' => __('Minimum value', 'mpcf'),
				'type'	=> $this->name
			),
			array(
				'name'	=> 'max',
				'title' => __('Maximum value', 'mpcf'),
				'type'	=> $this->name
			),
			array(
				'name'	=> 'step',
				'title' => __('Step', 'mpcf'),
				'type'	=> 'number',
				'description' => __('size of steps between possible values', 'mpcf'),
				'default' => 1
			)
		);
	}

	function label() {
		return __('Month', 'mpcf');
	}

	function build_field($args = array()) { ?>
		<input type="month" value="<?php echo $args['value']; ?>"<?php echo mpcf_list_input_params($this); ?>>
		<div class="mpcf-nohtml5-description"><?php echo sprintf(__('format: yyyy-mm (e.g. %s)', 'mpcf'), current_time('Y-m')); ?></div>
<?php
	}
}


endif;

?>