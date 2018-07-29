<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFDateTimeField')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFDateTimeField extends MPCFModule {
	public $name = 'datetime';
	

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
			'required'		=> array('title' => __('Required', 'mpcf'), 'type' => 'truefalse', 'description' => ''),
			'placeholder'	=> array('title' => __('Placeholder', 'mpcf'), 'type' => 'datetime'),
			'min'			=> array('title' => __('Minimum value', 'mpcf'), 'type' => 'datetime'),
			'max'			=> array('title' => __('Maximum value', 'mpcf'), 'type' => 'datetime')
		);
	}

	function label() {
		return __('Date and time', 'mpcf');
	}

	function build_field($args = array()) {
		$required = isset($args['required']) && $args['required'] === true ? ' required' : '';
		$min = (isset($args['min']) && !empty($args['min']) ? ' min="' . $args['min'] . '"' : '');
		$max = (isset($args['max']) && !empty($args['max']) ? ' max="' . $args['max'] . '"' : ''); ?>

		<input type="datetime-local" name="<?php echo $args['name']; ?>" id="<?php echo $args['name']; ?>"
			value="<?php echo $args['value']; ?>" <?php echo $required . $min . $max; ?>>

		<div class="mpcf-nohtml5-description"><?php echo sprintf(__('format: yyyy-mm-ddThh:mm (e.g. %s)', 'mpcf'), current_time('Y-m-d\TH:i')); ?></div>

<?php
	}
}

endif;

?>