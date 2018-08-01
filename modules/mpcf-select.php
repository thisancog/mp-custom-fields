<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFSelectField')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFSelectField extends MPCFModule {
	public $name = 'select';

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
				'name'	=> 'multiple',
				'title' => __('Multiple', 'mpcf'),
				'type'	=> 'truefalse'
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
			),
			array(
				'name'	=> 'size',
				'title' => __('Size', 'mpcf'),
				'type'	=> 'number'
			),
		);
	}

	function label() {
		return __('Select', 'mpcf');
	}

	function build_field($args = array()) {
		$multiple = isset($args['mutiple']) && !empty($args['mutiple']) ? ' ' . $args['mutiple'] : '';
		$options  = isset($args['options']) && !empty($args['options']) ? $args['options'] : array();
		$args['value'] = !empty($multiple) ? unserialize(($args['value'])) : $args['value'];
		$params = mpcf_list_input_params($this, array('required', 'size')); ?>

		<select
			name="<?php echo $args['name']; ?><?php echo (!empty($multiple) ? '[]' : ''); ?>"
			id="<?php echo $args['name']; ?>"
			<?php echo $params . $multiple; ?>>

<?php 	if (!empty($required)) { ?>
			<option value="" disabled<?php echo (empty($args['value']) ? ' selected' : ''); ?>>-----</option>
<?php 	}

 		foreach ($options as $name => $title) {
 			if (is_array($title))
 				extract($title);

			$selected = $args['value'] == $name || (is_array($args['value']) && in_array($name, $args['value']));
			$disabled = isset($disabled) && $disabled ? ' disabled' : ''; ?>
			<option value="<?php echo $name; ?>" <?php echo ($selected ? ' selected' : '') . $disabled; ?>><?php echo $title; ?></option>
<?php	} ?>
		</select>

<?php	}
}


endif;

?>