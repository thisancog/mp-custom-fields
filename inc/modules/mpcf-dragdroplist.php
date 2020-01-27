<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFDragDropList')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFDragDropList extends MPCFModule {
	public $name = 'dragdroplist';

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
				'name'		=> 'multiple',
				'title' 	=> __('Multiple', 'mpcf'),
				'type'		=> 'truefalse',
				'default'	=> false
			),
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
		return __('Select', 'mpcf');
	}

	function render_list($options, $namebase) {
		$i = 0;

		foreach ($options as $option) {
			$id = $option['id'];
			$title = $option['title'];
			$name = $namebase !== false ? $namebase . '[' . $i . ']' : ''; ?>

			<li class="mpcf-drag-drop-list-item">
				<div class="title"><?php echo $title; ?></div>
				<input type="hidden" value="<?php echo $id; ?>" name="<?php echo $name; ?>" />
			</li>

<?php		$i++;
		}
	}

	function build_field($args = array()) {
		$value = !empty($args['value']) && !empty($args['value'][0]) ? $args['value'] : array();

		$multiple = isset($args['multiple']) ? $args['multiple'] : false;
		$options  = isset($args['options']) && !empty($args['options']) ? $args['options'] : array();
		$params = mpcf_list_input_params($this, array('required', 'size'));

		$selection = array();
		$remaining = array();

		array_walk($options, function($option, $index) use (&$remaining) { $remaining[] = $index; });

		array_walk($value, function($option, $index) use (&$remaining, &$selection, $options, $multiple) {
			$set = array('id' => $option, 'title' => $options[$option]);
			$selection[] = $set;

			if (!$multiple)
				unset($remaining[array_search($option, $remaining)]);
		});

		$remaining = array_map(function($option) use ($options) { return array('id' => $option, 'title' => $options[$option]); }, $remaining); ?>

		<div class="mpcf-drag-drop-list-container" id="<?php echo $args['name']; ?>" data-basename="<?php echo $args['name']; ?>" data-multiple="<?php echo $multiple; ?>">
			<div class="mpcf-drag-drop-list-column">
				<div class="mpcf-drag-drop-list-column-header"><?php _e('Selection', 'mpcf'); ?></div>
				<ul class="mpcf-drag-drop-list-sublist mpcf-drag-drop-list-selection"><?php $this->render_list($selection, $args['name']) ?></ul>
			</div>

			<div class="mpcf-drag-drop-list-column">
				<div class="mpcf-drag-drop-list-column-header"><?php _e('Available options', 'mpcf'); ?></div>
				<ul class="mpcf-drag-drop-list-sublist mpcf-drag-drop-list-remaining"><?php $this->render_list($remaining, false) ?></ul>
			</div>
		

		</div>
<?php
	}
}
endif;

?>