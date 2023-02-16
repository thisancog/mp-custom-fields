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
		$name    = mpcf_get_input_name($this);
		$id      = str_replace(array('-', '_', '[', ']'), '', strtolower($name)) . uniqid();
		$wpautop = isset($args['addparagraphs']) ? boolval($args['addparagraphs']) : true;

		$defaultTinyMCE = array(
			'wpautop'	=> $wpautop,
			'plugins' 	=> 'charmap colorpicker compat3x directionality fullscreen hr image lists media paste tabfocus textcolor wordpress wpautoresize wpdialogs wpeditimage wpemoji wpgallery wplink wptextpattern wpview',
			'toolbar1'	=> 'formatselect bold italic | bullist numlist | blockquote | alignleft aligncenter alignright | link unlink | wp_more | spellchecker'
		);

		$editorargs = array(
			'drag_drop_upload'	=> isset($args['dragdrop']) ? $args['dragdrop'] : false,
			'editor_class'		=> mpcf_get_input_class($this),
			'editor_css'		=> isset($args['css']) ? '<style>' . $args['css'] . '</style>' : null,
			'media_buttons'		=> isset($args['mediabuttons']) ? $args['mediabuttons'] : true,
			'teeny'				=> isset($args['minimaleditor']) ? $args['minimaleditor'] : false,
			'tinymce'			=> isset($args['tinymce']) ? $args['tinymce'] : $defaultTinyMCE,
			'wpautop'			=> $wpautop,
			'textarea_name'		=> $name
		);

		if      (isset($args['rows']))		$editorargs['textarea_rows'] = $args['rows'];
		else if (isset($args['height']))	$editorargs['editor_height'] = $args['height'];
		else 								$editorargs['editor_height'] = 200;

		$editorArgsJS = $editorargs;
		$editorArgsJS['mediaButtons'] = $editorArgsJS['media_buttons'];
		$editorArgsJS['quicktags']    = true;
		unset($editorArgsJS['media_buttons']);

		$settings = esc_attr(json_encode($editorArgsJS, JSON_HEX_QUOT | JSON_HEX_APOS));
	// 	@ Wordpress Bug: editor_height is not honored
		$minHeight = isset($editorargs['editor_height']) ? $editorargs['editor_height']
				   : $this->get_editor_min_height_from_rows($editorargs['textarea_rows']); ?>
		
		<div class="mpcf-editor-inner" data-settings="<?php echo $settings; ?>">
<?php		wp_editor(mpcf_mknice($args['value']), $id, $editorargs); ?>
			<style>#<?php echo mpcf_mknice($id); ?>_ifr, .mpcf-input-editor[name="<?php echo mpcf_mknice($id); ?>"] { min-height: <?php echo $minHeight; ?>px; }</style>
		</div>
<?php	
	}

	function get_editor_min_height_from_rows($rows) {
		$height = max(100 + ($rows - 3) * 18.57, 100);
		return $height;
	}
}



endif;

?>
