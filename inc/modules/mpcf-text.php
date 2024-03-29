<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFTextField')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFTextField extends MPCFModule {
	public $name = 'text';

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
				'name'	=> 'list',
				'title' => __('Suggestion list', 'mpcf'),
				'type'	=> 'textarea',
				'description' => __('Add possible suggestions to choose from as a comma seperated list', 'mpcf')
			),
			array(
				'name'	=> 'minlength',
				'title' => __('Minimum length', 'mpcf'),
				'type'	=> 'number'
			),
			array(
				'name'	=> 'maxlength',
				'title' => __('Maximum length', 'mpcf'),
				'type'	=> 'number'
			),
			array(
				'name'	=> 'placeholder',
				'title' => __('Placeholder', 'mpcf'),
				'type'	=> $this->name,
			),
			array(
				'name'	=> 'required',
				'title' => __('Required', 'mpcf'),
				'type'	=> 'truefalse',
				'default'	=> false
			)
		);
	}

	function label() {
		return __('Text field', 'mpcf');
	}

	function build_field($args = array()) {
		$params    = mpcf_list_input_params($this, array('required', 'placeholder', 'minlength', 'maxlength'));
		$value     = htmlspecialchars(stripslashes($args['value']));

		$hasList   = isset($args['list']) && !empty($args['list']) ? $args['list'] : false;
		$list      = $hasList ? ' list="' . $args['name'] . '-list"' : '';
		$listItems = $hasList ? $args['list'] : '';
		$listId    = $args['name'] . '-list'; ?>

		<input type="text" value="<?php echo mpcf_mknice($value); ?>"<?php echo $params . ($hasList ? 'list="' . $listId . '"' : '');?>>
<?php 	if ($hasList) {
			$listItems = explode(',', $listItems); ?>

			<datalist id="<?php echo $listId; ?>">
<?php 		foreach ($listItems as $item) {
				echo '<option value="' . trim($item) . '">';
 			} ?>
			</datalist>
<?php
		}
	}
}

endif;

?>