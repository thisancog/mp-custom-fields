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
			array('name' => 'fields'),
			array(
				'name'		=> 'maxrows',
				'title'		=> __('Max rows', 'mpcf'),
				'type'		=> 'number',
				'default'	=> 0
			),
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
		$maxRows    = isset($args['maxrows']) && !empty($args['maxrows']) ? $args['maxrows'] : 0;
		$showAddBtn = '';

		if ($maxRows > 0 && $args['value'] !== false && !empty($args['value'])) {
			$showAddBtn = $maxRows <= count($args['value']) ? ' hide' : '';
		}

		$baseName   = isset($args['baseName']) ? $args['baseName'] . '[' . $args['name'] . ']' : $args['name'];
		$fieldsJSON = esc_attr(json_encode($args['fields'], JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP)); ?>
		<ol class="mpcf-repeater-wrapper" data-basename="<?php echo $args['name']; ?>"
			data-fields="<?php echo $fieldsJSON; ?>"
			data-maxrows="<?php echo $maxRows; ?>"
			data-uniqid="<?php echo $args['uniqid']; ?>">
<?php 			$this->get_repeater_row($baseName, $args['fields'], $args['value']); ?>
			</ol>

		<div class="mpcf-loading-container" data-uniqid="<?php echo $args['uniqid']; ?>"></div>

		<div class="mpcf-repeater-controls" data-uniqid="<?php echo $args['uniqid']; ?>">
			<input type="button" class="mpcf-repeater-add-row mpcf-button<?php echo $showAddBtn; ?>" value="<?php _e('Add', 'mpcf'); ?>" data-uniqid="<?php echo $args['uniqid']; ?>" />
			<input type="hidden" class="mpcf-repeater-empty" data-name="<?php echo mpcf_get_input_name($this); ?>"<?php echo mpcf_input_own_name($this); ?> data-uniqid="<?php echo $args['uniqid']; ?>" value="" />
		</div>
		
<?php	foreach ($args['fields'] as $field => $data) {
			if (isset($data['required']) && $data['required'] === true)
				$required = true;
		}

		return $required;

	}

	/*****************************************************
		Build graphical user interface for subfields
 	*****************************************************/

	function get_repeater_row($baseName, $fields, $values = array()) {
		global $post;
		$buttons = mpcf_get_repeater_buttons();

		if ($values !== false && !empty($values) && !(count($values) === 1 && empty($values[0]))) {
			foreach ($values as $i => $row) {
				$fields = array_map(function($field) use ($baseName, $i) {
					$field['baseName'] = $baseName . '[' . $i . ']';
					return $field;
				}, $fields);
		 ?>
				<li class="mpcf-repeater-row">
	<?php 			mpcf_build_gui_from_fields($fields, $row, false);
					echo $buttons; ?>
				</li>
	<?php	}
		}
	}





	/*****************************************************
		Attach media to post
 	*****************************************************/

	function save_after($post_id, $field, $value, $oldValue) {
		$this->attach_media_to_post($post_id, $field, $value, $oldValue);
	}

	function attach_media_to_post($post_id, $field, $values, $oldValues) {
		if ($post_id == null) return;

		$fields = isset($field['fields']) && !empty($field['fields']) ? $field['fields'] : array();
		if (empty($fields)) return;

		$o           = get_option('mpcf_options');
		$mediaFields = mpcf_get_media_storing_fields();

		$i = 0;

		foreach ($values as $value) {
			$j = 0;
			if (!is_array($value)) continue;

			foreach ($value as $subValueKey => $subValueInfo) {
				$currentField = null;

				array_walk($fields, function($field) use (&$currentField, $subValueKey) {
					if ($field == null || $field['name'] !== $subValueKey) return;
					$currentField = $field;
				});

				if ($currentField == null) {
					$j++;
					continue;
				}

				$type = $currentField['type'];
				if (!in_array($type, $mediaFields)) {
					$j++;
					continue;
				}

				$oldValue = is_array($oldValues) && !empty($oldValues)
						  ? array_slice($oldValues, $i, 1, false) : '';
				$oldValue = !empty($oldValue) ? $oldValue : '';
				$oldValue = is_array($oldValue) && isset($oldValue[0]) ? $oldValue[0] : '';

				$oldValue = is_array($oldValue) && isset($oldValue[$subValueKey])
						  ? $oldValue[$subValueKey] : '';

				$classname = $o['modules'][$type]['name'];
				$module    = new $classname();

				if (method_exists($module, 'attach_media_to_post'))
					$module->attach_media_to_post($post_id, $currentField, $subValueInfo, $oldValue);
				unset($module);

				$j++;
			}

			$i++;
		}
	}
}



endif;

?>
