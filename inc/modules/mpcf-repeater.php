<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFRepeaterField')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFRepeaterField extends MPCFModule {
	public $name = 'repeater';

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
			array('name' => 'fields')
		);
	}

	function label() {
		return __('Repeater', 'mpcf');
	}

	function display_before($id, $field, $value) {
		if (empty($value))
			$value = array();
		return $value;
	}

	function build_field($args = array()) {
		array_walk_recursive($args['value'], function(&$item, $key) {
			$item = mpcf_mknice($item);
		});

		$required = false;
		?>

		<ol class="mpcf-repeater-wrapper" data-basename="<?php echo $args['name']; ?>"
			data-fields="<?php echo esc_attr(json_encode($args['fields'], JSON_HEX_QUOT | JSON_HEX_APOS)); ?>"
			data-values="<?php echo esc_attr(json_encode($args['value'], JSON_HEX_QUOT | JSON_HEX_APOS)); ?>"></ol>

		<div class="mpcf-loading-container mpcf-loading-active"></div>

		<div class="mpcf-repeater-controls">
			<input type="button" class="mpcf-repeater-add-row mpcf-button" value="<?php _e('Add', 'mpcf'); ?>" />
		</div>
		
<?php	foreach ($args['fields'] as $field => $data) {
			if (isset($data['required']) && $data['required'] === true)
				$required = true;
		}

		return $required;

	}
}



endif;

?>
