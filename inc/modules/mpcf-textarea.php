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

		// If this field could hold translatable content.
		// This will flag the input tag with a "mpcf-multilingual" class.
		$this->translatable = true;

		// include additional classes for the wrapper of this field
		$this->wrapperClasses = '';

		// Parameters for the field which can be set by the user
		// 'description' will be automatically added and ouput by the plugin
		$this->parameters = array(
			array(
				'name'			=> 'addparagraphs',
				'title' 		=> __('Add paragraphs', 'mpcf'),
				'type'			=> 'truefalse',
				'default'		=> false,
				'description' 	=> __('whether to automatically add paragraphs', 'mpcf')
			),
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
				'description' => '',
				'default'	=> false
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

	function display_before($post_id, $field, $value) {
		$value = mpcf_mknice($value);

		if (isset($field['addparagraphs']) && $field['addparagraphs'] == true)
			$value = wpautop($value);

		return $value;
	}

	function build_field($args = array()) { ?>
		<textarea name="<?php echo $args['name']; ?>" id="<?php echo $args['name']; ?>"<?php echo mpcf_list_input_params($this); ?>><?php echo mpcf_mknice($args['value']); ?></textarea>
<?php
	}
}

endif;

?>
