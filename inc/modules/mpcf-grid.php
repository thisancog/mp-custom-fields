<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFGridField')) :

/*****************************************************
	Grid field
 *****************************************************/

class MPCFGridField extends MPCFModule {
	public $name = 'grid';

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
			array(
				'name'	=> 'columns',
				'title' => __('Columns', 'mpcf'),
				'type'	=> 'number',
				'min'	=> 1
			),
			array(
				'name'	=> 'rows',
				'title' => __('Rows', 'mpcf'),
				'type'	=> 'number',
				'min'	=> 1
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
		return __('Grid field', 'mpcf');
	}

	function get_default($post_id = null, $field = array(), $value = array()) {
		$defaults = array('startrow' => 0, 'endrow' => 0, 'startcol' => 0, 'endcol' => 0);
		if (isset($field['default'])) {
			array_walk(array_keys($defaults), function($key) use (&$defaults, $field) {
				if (isset($field['default'][$key]))
					$defaults[$key] = $field['default'][$key];
			});
		}

		return $defaults;
	}

	function display_before($post_id, $field, $value) {
		return json_decode(wp_specialchars_decode(htmlspecialchars_decode($value)), true);
	}


	function build_field($args = array()) {
		$params = mpcf_list_input_params($this, array('required', 'min', 'max'));
		$value = $args['value'];
		if (is_string($value))
			$value  = json_decode(wp_specialchars_decode(stripslashes($args['value'])), true);

		if (empty($value))
			$value = $this->get_default(null, $args, $args['value']);

		$valueJSON = esc_attr(json_encode($value, JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP)); ?>

		<div class="grid-field-grid">
<?php 		$startRow = isset($value['startrow']) ? $value['startrow'] : 0;
			$endRow   = isset($value['endrow']) ? $value['endrow'] : 0;
			$startCol = isset($value['startcol']) ? $value['startcol'] : 0;
			$endCol   = isset($value['endcol']) ? $value['endcol'] : 0;

			for ($row = 0; $row < $args['rows']; $row++) { ?>
			<div class="row">
<?php			for ($col = 0; $col < $args['cols']; $col++) {
					$isSelected = $row >= $startRow && $row <= $endRow &&
								  $col >= $startCol && $col <= $endCol; ?>
					<div class="grid-cell<?php echo $isSelected ? ' selected' : ''; ?>" data-row="<?php echo $row; ?>" data-col="<?php echo $col; ?>" draggable="false"></div>
<?php			} ?>
			</div>
<?php 		} ?>
		</div>

		<input
			type="hidden"
			name="<?php echo $args['name']; ?>"
			value="<?php echo $valueJSON; ?>"
			class="grid-field-input"<?php echo $params; ?>>
<?php 
	}
}

endif;

?>