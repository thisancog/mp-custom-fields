<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFTableField')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFTableField extends MPCFModule {
	public $name = 'table';

	function __construct() {
		parent::__construct();

		// Supply a category for the field selection menu
		// possible values: date, text, options, number, misc
		// default: misc
		$this->category = 'misc';

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
				'name'	=> 'rows',
				'title' => __('Rows', 'mpcf'),
				'type'	=> 'number',
				'min'	=> 1,
				'description' => __('Amount of rows', 'mpcf')
			),
			array(
				'name'	=> 'columns',
				'title' => __('Columns', 'mpcf'),
				'type'	=> 'number',
				'min'	=> 1,
				'description' => __('Amount of columns', 'mpcf')
			),
			array(
				'name'	=> 'headings',
				'title' => __('Headings', 'mpcf'),
				'type'	=> 'repeater',
				'fields' => array(
					array(
						'name'	=> 'title',
						'title'	=> __('Title', 'mpcf'),
						'type'	=> 'text',
						'required'	=> true
					)
				)
			),
			array(
				'name'		=> 'editrows',
				'title' 	=> __('Add or remove rows', 'mpcf'),
				'type'		=> 'truefalse',
				'default'	=> false
			)
		);
	}

	function label() {
		return __('Table field', 'mpcf');
	}

	function build_field($args = array()) {
		$rows     = $args['rows'];
		$cols     = $args['columns'];
		$headings = isset($args['headings']) ? $args['headings'] : array();
		$editrows = isset($args['editrows']) ? $args['editrows'] : false;

		$classes  = empty($headings) ? ' has-no-heading' : ' has-heading';
		$value    = $args['value']; ?>

		<table class="mpcf-table-inner<?php echo $classes; ?>" data-editrows="<?php echo $editrows; ?>">
<?php 	if (!empty($headings)) { ?>
			<tr>

<?php		for ($c = 0; $c < $cols; $c++) {
				$heading = isset($headings[$c]) ? $headings[$c] : ''; ?>
				<th><?php echo $heading; ?></th>
<?php		} ?>
			</tr>
<?php	}

		for ($r = 0; $r < $rows; $r++) { ?>
			<tr>

<?php 		for ($c = 0; $c < $cols; $c++) {
				$val = isset($value[$r]) ? $value[$r] : '';
				$val = is_array($val) ? $val[$c] : $val; ?>

				<td><input type="text"
						   name="<?php echo $args['name']; ?>[<?php echo $r; ?>][<?php echo $c; ?>]"
						   value="<?php echo $val; ?>"></td>

<?php 		} ?>

			</tr>
<?php 	} ?>
		</table>

<?php
	}
}

endif;

?>