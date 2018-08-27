<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFEditorField')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFEditorField extends MPCFModule {
	public $name = 'editor';

	function __construct() {
		parent::__construct();

		// Supply a category for the field selection menu
		// possible values: date, text, options, number, misc
		// default: misc
		$this->category = 'text';

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
				'name'	=> 'addparagraphs',
				'title' => __('Add paragraphs', 'mpcf'),
				'type'	=> 'truefalse',
				'default'	=> true,
				'description' => __('whether to automatically add paragraphs', 'mpcf')
			),
			array(
				'name'	=> 'dragdrop',
				'title' => __('Drag & Drop upload', 'mpcf'),
				'type'	=> 'truefalse',
				'default'	=> true,
				'description' => __('whether to enable drag & drop upload', 'mpcf')
			),
			array(
				'name'	=> 'css',
				'title' => __('Custom CSS', 'mpcf'),
				'type'	=> 'textarea',
				'description' => __('include custom CSS without &lt;style&gt; tags', 'mpcf')
			),
			array(
				'name'	=> 'height',
				'title' => __('Height', 'mpcf'),
				'type'	=> 'number',
				'description' => __('Set editor height in pixels. Takes precedence over rows option.', 'mpcf')
			),
			array(
				'name'	=> 'mediabuttons',
				'title' => __('Media buttons', 'mpcf'),
				'type'	=> 'truefalse',
				'default'	=> true,
				'description' => __('whether to display media buttons', 'mpcf')
			),
			array(
				'name'	=> 'minimaleditor',
				'title' => __('Minimal editor', 'mpcf'),
				'type'	=> 'truefalse',
				'default'	=> false
			),
			array(
				'name'	=> 'rows',
				'title' => __('Rows', 'mpcf'),
				'type'	=> 'number',
				'description' => __('Set editor height in rows. Will be overruled if height option is set.', 'mpcf')
			)
		);
	}

	function label() {
		return __('Editor', 'mpcf');
	}

	function build_field($args = array()) {
		$id = str_replace(array('-', '_'), '', strtolower($args['name']));
		$rows = isset($args['rows']) ? $args['rows'] : 10;

		$editorargs = array(
			'drag_drop_upload'	=> isset($args['dragdrop']) ? $args['dragdrop'] : false,
			'editor_class'		=> mpcf_get_input_class($this),
			'editor_css'		=> isset($args['css']) ? '<style>' . $args['css'] . '</style>' : null,
			'editor_height'		=> isset($args['height']) ? $args['height'] : null,
			'media_buttons'		=> isset($args['mediabuttons']) ? $args['mediabuttons'] : true,
			'teeny'				=> isset($args['minimaleditor']) ? $args['minimaleditor'] : false,
			'textarea_rows'		=> $rows,
			'wpautop'			=> isset($args['addparagraphs']) ? boolval($args['addparagraphs']) : true,
		);

		/* ?>

		<textarea 	class="<?php echo mpcf_get_input_class($this); ?>"
					id="<?php echo $id; ?>"
					name="<?php echo $args['name']; ?>"
					data-settings="<?php echo esc_attr(json_encode($editorargs, JSON_HEX_QUOT | JSON_HEX_APOS)); ?>"
					rows=""><?php echo mpcf_mknice($args['value']); ?></textarea>

<?php 	*/
		wp_editor(mpcf_mknice($args['value']), $id, $editorargs);
	}
}



endif;

?>
