<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFTimeField')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFTimeField extends MPCFModule {
	public $name = 'time';
	public $label = 'Time';

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

	function build_field($args = array()) {
		$required = isset($args['required']) && $args['required'] === true ? ' required' : '';
		$step     = (isset($args['step']) && !empty($args['step']) ? ' step="' . $args['step'] . '"' : '');
		$min 	 = (isset($args['min']) && !empty($args['min']) ? ' min="' . $args['min'] . '"' : '');
		$max 	 = (isset($args['max']) && !empty($args['max']) ? ' max="' . $args['max'] . '"' : ''); ?>

		<input  type="week"
				name="<?php echo $args['name']; ?>"
				id="<?php echo $args['name']; ?>"
				value="<?php echo $args['value']; ?>"
				pattern="[0-9]{2}:[0-9]{2}"
				<?php $required . $step . $min . $max; ?>>>

		<div class="mpcf-nohtml5-description"><?php echo sprintf(__('format: hh:mm:ss (e.g. %s)', 'mpcf'), current_time('H:i:s')); ?></div>
<?php
	}
}


endif;

?>