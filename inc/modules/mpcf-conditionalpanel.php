<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFConditionalPanelField')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFConditionalPanelField extends MPCFModule {
	public $name = 'conditionalpanels';

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
		return __('Conditional panels field', 'mpcf');
	}

	function build_field($args = array()) {
		$name    = $args['name'];

		$value   = isset($args['value']) ? $args['value'] : array();
		$value   = isset($value[$name])  ? $value[$name]  : $value;

		$label   = isset($args['label']) && !empty($args['label']) ? $args['label'] : '';
		$options = isset($args['options']) && !empty($args['options']) ? $args['options'] : array();
		$noSelection = !isset($value['type']) ? ' selected' : '';

		foreach ($value as $prop => $val) {
			$value[$prop] = mpcf_mknice($val); 
		}

		$optionsJSON = esc_attr(json_encode($options, JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP));
		$valuesJSON  = esc_attr(json_encode($value, JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP));

		$attrs       = mpcf_input_name($this, 'type') . mpcf_input_id($this, 'type') . ' data-own-name="type"'
					 . ' data-basename="' . $args['name'] . '" data-options="' . $optionsJSON . '"'
					 . ' data-values="' . $valuesJSON . '"'; ?>

		<div class="mpcf-conditionalpanels-container" data-basename="<?php echo $args['name']; ?>">
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

			<input type="checkbox"<?php echo $attrs; ?> value="<?php echo $name; ?>"
					<?php echo ($selected ? ' checked' : '') . $disabled; ?>>
			<label for="<?php echo $args['name']; ?>-type"><?php echo $title; ?></label>

<?php		} else { ?>

				<select<?php echo $attrs; ?>>
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
		</div>
<?php
	}


	/*****************************************************
		Attach media to post
 	*****************************************************/

	function save_after($post_id, $field, $value, $oldValue) {
		$this->attach_media_to_post($post_id, $field, $value, $oldValue);
	}

	function attach_media_to_post($post_id, $field, $values, $oldValues) {
		if ($post_id == null) return;

		$options = isset($field['options']) && !empty($field['options']) ? $field['options'] : array();
		if (empty($options)) return;

		$o           = get_option('mpcf_options');
		$mediaFields = mpcf_get_media_storing_fields();

		if (!isset($values['type'])) return;
		$option = $values['type'];

		$currentOption = $options[$option];
		$fields        = $currentOption['panel']['fields'];

		foreach ($fields as $field) {
			if (empty($field)) continue;

			$type = $field['type'];
			if (!in_array($type, $mediaFields)) continue;

			if (!isset($values[$field['name']])) continue;

			$value    = $values[$field['name']];
			$oldValue = $oldValues[$field['name']];
			$oldValue = !empty($oldValue) ? $oldValue : '';

			$classname = $o['modules'][$type]['name'];
			$module    = new $classname();
			$module->attach_media_to_post($post_id, $field, $value, $oldValue);
			unset($module);
		}
	}
}

endif;

?>
