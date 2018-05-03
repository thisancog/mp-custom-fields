<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFTextareaField')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFTextareaField extends MPCFModule {
	public $name = 'textarea';
	public $label = 'Textarea';

	function __construct() {
		parent::__construct();

		// If this field contains html5 input elements and therefore requires
		// a browser compatibility check
		$this->html5 = false;

		// Parameters for the field which can be set by the user
		// 'description' will be automatically added and ouput by the plugin
		$this->parameters = array(
			'placeholder',
			'required',
			'rows'
		);
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
