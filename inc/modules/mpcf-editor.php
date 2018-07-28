<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFEditorField')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFEditorField extends MPCFModule {
	public $name = 'editor';
	public $label = 'Editor';

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
			'addparagraphs',
			'dfw',
			'dragdrop',
			'class',
			'css',
			'height',
			'mediabuttons',
			'quicktags',
			'minimaleditor',
			'rows',
			'tinymce'
		);
	}

	function build_field($args = array()) {
		$id = str_replace('-', '', $args['name']);
		$editorargs = array(
			'dfw'				=> isset($args['dfw']) ? $args['dfw'] : false,
			'drag_drop_upload'	=> isset($args['dragdrop']) ? $args['dragdrop'] : false,
			'editor_class'		=> isset($args['class']) ? $args['class'] : '',
			'editor_css'		=> isset($args['css']) ? $args['css'] : null,
			'editor_height'		=> isset($args['height']) ? $args['height'] : null,
			'media_buttons'		=> isset($args['mediabuttons']) ? $args['mediabuttons'] : true,
			'quicktags'			=> isset($args['quicktags']) ? $args['quicktags'] : true,
			'teeny'				=> isset($args['minimaleditor']) ? $args['minimaleditor'] : false,
			'textarea_rows'		=> isset($args['rows']) ? $args['rows'] : 10,
			'textarea_name'		=> $args['name'],
			'tinymce'			=> isset($args['tinymce']) ? $args['tinymce'] : true,
			'wpautop'			=> isset($args['addparagraphs']) ? boolval($args['addparagraphs']) : true,
		);

		wp_editor(mpcf_mknice($args['value']), $id, $editorargs);
	}
}



endif;

?>
