<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFButtonGroupField')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFButtonGroupField extends MPCFModule {
	public $name = 'buttongroup';

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
				'name'	=> 'options',
				'title' => __('Choices', 'mpcf'),
				'type'	=> 'repeater',
				'fields' => array(
					array(
						'name'	=> 'name',
						'title'	=> __('Name', 'mpcf'),
						'type'	=> 'text',
						'required'	=> true
					),
					array(
						'name'	=> 'title',
						'title'	=> __('Title', 'mpcf'),
						'type'	=> 'text',
						'required'	=> true
					)
				)
			)
		);
	}

	function label() {
		return __('Button group', 'mpcf');
	}

	function build_field($args = array()) {
		$options = isset($args['options']) && !empty($args['options']) ? $args['options'] : array();
		$default = isset($args['default']) && !empty($args['default']) ? $args['default'] : false; ?>

		<div class="mpcf-buttongroup-wrapper">
<?php 		foreach ($options as $name => $title) {
				$id = $args['name'] . '-' . $name;
				if ($default && $default === $name)
					$title .= __(' (default)', 'mpcf'); ?>

				<div class="mpcf-buttongroup-option">
					<input  type="radio"
							name="<?php echo $args['name']; ?>"
							id="<?php echo $id; ?>"
							value="<?php echo $name; ?>"
							<?php echo ($args['value'] === $name ? ' checked' : ''); ?>>
					<label for="<?php echo $id; ?>"><?php echo $title; ?></label>
				</div>

<?php		} ?>
		</div>
<?php
	}
}

endif;

?>