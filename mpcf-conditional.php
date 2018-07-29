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

		// If this field contains html5 input elements and therefore requires
		// a browser compatibility check
		$this->html5 = false;

		// include additional classes for the wrapper of this field
		$this->wrapperClasses = '';

		// Parameters for the field which can be set by the user
		// 'description' will be automatically added and ouput by the plugin
		$this->parameters = array(
			'options'
		);
	}

	function label() {
		return __('Conditional field', 'mpcf');
	}

	function build_field($args = array()) {
		$value   = isset($args['value']) ? $args['value'] : array();
		if (unserialize($value))
			$value = unserialize($value);

		$label   = isset($args['label']) && !empty($args['label']) ? $args['label'] : '';
		$options = isset($args['options']) && !empty($args['options']) ? $args['options'] : array();
		$noSelection = !isset($value['type']) ? ' selected' : ''; ?>

		<div class="mpcf-conditional-choice">
<?php	if (!empty($label)) { ?>
			<div class="mpcf-title"><label for="<?php echo $args['name']; ?>-type"><?php echo $label; ?></label></div>
<?php 	} ?>

			<select name="<?php echo $args['name']; ?>[type]"
					id="<?php echo $args['name']; ?>-type"
					data-basename="<?php echo $args['name']; ?>"
					data-options="<?php echo esc_attr(json_encode($options, JSON_HEX_QUOT | JSON_HEX_APOS)); ?>"
					data-values="<?php echo esc_attr(json_encode($value, JSON_HEX_QUOT | JSON_HEX_APOS)); ?>">

				<option value="-1" disabled<?php echo $noSelection; ?>>------</option>

<?php 		foreach ($options as $name => $values) {
				$selected = isset($value['type']) && $value['type'] == $name; ?>
				<option value="<?php echo $name; ?>" <?php echo $selected ? ' selected' : ''; ?>><?php echo $values['title']; ?></option>
<?php		} ?>
			</select>
		</div>

		<div class="mpcf-conditional-wrapper"></div>
		<div class="mpcf-loading-container"></div>
<?php
	}
}

endif;

?>