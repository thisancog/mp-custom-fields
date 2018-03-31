<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFDateTimeField')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFDateTimeField extends MPCFModule {
	public $name = 'datetime';
	public $label = 'Date-time field';
	

	function __construct() {
		parent::__construct();

		// If this field contains html5 input elements and therefore requires
		// a browser compatibility check
		$this->html5 = true;

		// Parameters for the field which can be set by the user
		// 'description' will be automatically added and ouput by the plugin
		$this->parameters = array(
			'max',
			'min',
			'placeholder',
			'required'
		);
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