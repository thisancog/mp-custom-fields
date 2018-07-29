<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFCheckbox')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFCheckbox extends MPCFModule {
	public $name = 'checkbox';

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
				'name'	=> 'label',
				'title' => __('Label', 'mpcf'),
				'type'	=> 'text'
			)
		);
	}

	function label() {
		return __('Checkbox', 'mpcf');
	}

	function save_before($post_id, $fieldName, $value) {
		return isset($_POST[$fieldName]) ? $value : false;
	}

	function build_field($args = array()) {
		$label = isset($args['label']) && !empty($args['label']) ? $args['label'] : ''; ?>

		<input  type="checkbox"
				name="<?php echo $args['name']; ?>"
				id="<?php echo $args['name']; ?>"
				value="checked"
				<?php echo ($args['value'] === 'checked' ? ' checked' : ''); ?>>
			<label for="<?php echo $args['name']; ?>"><?php echo $args['label']; ?></label>
<?php
	}
}


endif;

?>