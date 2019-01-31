<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFConditionalField')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFConditionalField extends MPCFModule {
	public $name = 'conditional';

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
		$this->parameters = array();
	}

	function label() {
		return __('Conditional field', 'mpcf');
	}

	function build_field($args = array()) {
		$value   = isset($args['value']) && !empty($args['value']) ? $args['value'] : array();

		if (count($value) == 1 && isset($value[0]) && is_string($value[0]) && unserialize($value[0]) !== false)
			$value = unserialize($value[0]);
		$value = !empty($value) ? $value : array();

		$label   = isset($args['label']) && !empty($args['label']) ? $args['label'] : '';
		$options = isset($args['options']) && !empty($args['options']) ? $args['options'] : array();
		$noSelection = !isset($value['type']) ? ' selected' : '';

		foreach ($value as $prop => $val) {
			$value[$prop] = mpcf_mknice($val); 
		} ?>

		<div class="mpcf-conditional-container" data-basename="<?php echo $args['name']; ?>">
			<div class="mpcf-conditional-choice">
<?php		if (!empty($label)) { ?>
				<div class="mpcf-title"><label for="<?php echo $args['name']; ?>-type"><?php echo $label; ?></label></div>
<?php 		}

			if (count($options) == 1) {
				$name = key($options);
				$params = $options[$name];

				if (is_array($params['title']))	extract($params['title']);
	 			else 							$title = $params['title'];

	 			$disabled = false;



	 			$selected = isset($value['type']) && $value['type'] == $name;
	 			$disabled = isset($disabled) && $disabled ? ' disabled' : '';
			 ?>

			<input	type="checkbox"
					name="<?php echo $args['name']; ?>[type]"
					id="<?php echo $args['name']; ?>-type"
					value="<?php echo $name; ?>"
					<?php echo ($selected ? ' checked' : '') . $disabled; ?>
					data-basename="<?php echo $args['name']; ?>"
					data-options="<?php echo esc_attr(json_encode($options, JSON_HEX_QUOT | JSON_HEX_APOS)); ?>"
					data-values="<?php echo esc_attr(json_encode($value, JSON_HEX_QUOT | JSON_HEX_APOS)); ?>">
			<label for="<?php echo $args['name']; ?>-type"><?php echo $title; ?></label>

<?php		} else { ?>

				<select name="<?php echo $args['name']; ?>[type]"
						id="<?php echo $args['name']; ?>-type"
						data-basename="<?php echo $args['name']; ?>"
						data-options="<?php echo esc_attr(json_encode($options, JSON_HEX_QUOT | JSON_HEX_APOS)); ?>"
						data-values="<?php echo esc_attr(json_encode($value, JSON_HEX_QUOT | JSON_HEX_APOS)); ?>">

					<option value="-1" <?php echo $noSelection; ?>>------</option>

<?php 			foreach ($options as $name => $params) {
					$disabled = false;

					if (is_array($params['title']))	extract($params['title']);
	 				else 							$title = $params['title'];

					$selected = isset($value['type']) && $value['type'] == $name;
					$disabled = isset($disabled) && $disabled ? ' disabled' : ''; ?>
					<option value="<?php echo $name; ?>" <?php echo ($selected ? ' selected' : '') . $disabled; ?>><?php echo $title; ?></option>
<?php			} ?>
				</select>
<?php 		} ?>
			</div>

			<div class="mpcf-conditional-wrapper"></div>
			<div class="mpcf-loading-container"></div>
		</div>
<?php
	}
}

endif;

?>