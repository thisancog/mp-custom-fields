<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFPostSelectField')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFPostSelectField extends MPCFModule {
	public $name = 'postselect';

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
				'name'		=> 'query',
				'title' 	=> __('Query arguments', 'mpcf'),
				'type'		=> 'text',
				'description'	=> __('A query to pull posts from. If given as a string, it will look for a function with that name instead. The first argument will be the option list (post_id => post_title), the second argument the complete query result.', 'mpcf')
			),
			array(
				'name'		=> 'required',
				'title' 	=> __('Required', 'mpcf'),
				'type'		=> 'truefalse',
				'default'	=> false
			),
			array(
				'name'			=> 'filter',
				'title' 		=> __('Filter', 'mpcf'),
				'type'			=> 'string',
				'description'	=> __('The name of a function to call which will filter the query results.', 'mpcf')
			),
		);
	}

	function label() {
		return __('Post select', 'mpcf');
	}

	function display_before($id, $field, $value) {
		if (empty($value) || $value == -1)
			$value = false;
		return $value;
	}

	function build_field($args = array()) {
		$value = $args['value'];

		wp_reset_query();
		wp_reset_postdata();

		$queryArgs = isset($args['query'])  && !empty($args['query'])  ? $args['query']  : array();
		$filter    = isset($args['filter']) && !empty($args['filter']) ? $args['filter'] : null;

		if (is_string($queryArgs) && function_exists($queryArgs))
			$queryArgs = $queryArgs($args);


		$posts   = get_posts($queryArgs);
		
		wp_reset_query();
		wp_reset_postdata();

		$options = [];

		foreach ($posts as $post) {
			$options[$post->ID] = $post->post_title;
		}

		if (is_string($filter) && function_exists($filter)) {
			$options = $filter($options, $posts);
		}

		$params = mpcf_list_input_params($this, array('required'), true);
		$required = $args['required']; ?>

		<select<?php echo mpcf_input_name($this) . mpcf_input_own_name($this) . mpcf_input_id($this) . $params; ?>>
<?php 	if (!empty($required)) { ?>
			<option value="" disabled<?php echo (empty($value) ? ' selected' : ''); ?>>-----</option>
<?php 	}

 		foreach ($options as $name => $title) {
			$selected = $value == $name || (is_array($value) && in_array($name, $value)); ?>
			<option value="<?php echo $name; ?>"<?php echo ($selected ? ' selected' : ''); ?>><?php echo $title; ?></option>
<?php	} ?>
		</select>

		<div class="postselect-edit-link">
			<a href="" class="mpcf-button" target="_blank" rel="noopener" data-baseuri="<?php echo get_admin_url(); ?>post.php?action=edit&post="><?php _e('Edit', 'mpcf'); ?></a>
		</div>

<?php	}
}


endif;

?>