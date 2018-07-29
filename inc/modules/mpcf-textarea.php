<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFTextareaField')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFTextareaField extends MPCFModule {
	public $name = 'textarea';

	function __construct() {
		parent::__construct();

		// Supply a category for the field selection menu
		// possible values: date, text, options, number, misc
		// default: misc
		$this->category = 'text';

		// If this field contains html5 input elements and therefore requires
		// a browser compatibility check
		$this->html5 = false;

		// include additional classes for the wrapper of this field
		$this->wrapperClasses = '';

		// Parameters for the field which can be set by the user
		// 'description' will be automatically added and ouput by the plugin
		$this->parameters = array(
			array(
				'name'	=> 'placeholder',
				'title' => __('Placeholder', 'mpcf'),
				'type'	=> 'text',
				'description' => ''
			),
			array(
				'name'	=> 'required',
				'title' => __('Required', 'mpcf'),
				'type' => 'truefalse',
				'description' => ''
			),
			array(
				'name'	=> 'rows',
				'title' => __('Rows', 'mpcf'),
				'type' => 'number',
				'description' => __('number of rows', 'mpcf')
			)
		);
	}

	function label() {
		return __('Text area', 'mpcf');
	}

	function build_field($args = array()) {
		$required = isset($args['required']) && $args['required'] === true ? ' required' : '';
		$placeholder = (isset($args['placeholder']) && !empty($args['placeholder']) ? ' placeholder="' . $args['placeholder'] . '"' : '');
		$rows = isset($args['rows']) && !empty($args['rows']) ? ' rows="' . $args['rows'] . '"' : ''; ?>
		
		<textarea name="<?php echo $args['name']; ?>" id="<?php echo $args['name']; ?>" <?php echo $required . $placeholder . $rows; ?>><?php echo $args['value']; ?></textarea>

<?php
	}
}

endif;

?>
