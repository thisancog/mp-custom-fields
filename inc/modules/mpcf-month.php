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

		// If this field contains html5 input elements and therefore requires
		// a browser compatibility check
		$this->html5 = true;

		// include additional classes for the wrapper of this field
		$this->wrapperClasses = '';

		// Parameters for the field which can be set by the user
		// 'description' will be automatically added and ouput by the plugin
		$this->parameters = array(
			'max',
			'min',
			'required',
			'step'
		);
	}

	function label() {
		return __('Month', 'mpcf');
	}

	function build_field($args = array()) {
		$required = isset($args['required']) && $args['required'] === true ? ' required' : '';
		$step     = (isset($args['step']) && !empty($args['step']) ? ' step="' . $args['step'] . '"' : '');
		$min 	 = (isset($args['min']) && !empty($args['min']) ? ' min="' . $args['min'] . '"' : '');
		$max 	 = (isset($args['max']) && !empty($args['max']) ? ' max="' . $args['max'] . '"' : ''); ?>

		<input  type="month"
				name="<?php echo $args['name']; ?>"
				id="<?php echo $args['name']; ?>"
				value="<?php echo $args['value']; ?>"
				<?php $required . $step . $min . $max; ?>>>

		<div class="mpcf-nohtml5-description"><?php echo sprintf(__('format: yyyy-mm (e.g. %s)', 'mpcf'), current_time('Y-m')); ?></div>
<?php
	}
}


endif;

?>