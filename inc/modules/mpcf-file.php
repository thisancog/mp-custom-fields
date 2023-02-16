<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFFilePicker')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFFilePicker extends MPCFModule {
	public $name = 'file';

	function __construct() {
		parent::__construct();

		// Supply a category for the field selection menu
		// possible values: date, text, options, number, misc
		// default: misc
		$this->category = 'misc';

		// If this field could hold translatable content.
		// This will flag the input tag with a "mpcf-multilingual" class.
		$this->translatable = true;

		// If this field contains html5 input elements and therefore requires
		// a browser compatibility check
		$this->html5 = false;

		// include additional classes for the wrapper of this field
		$this->wrapperClasses = 'mpcf-mediapicker mpcf-filepicker';

		// Parameters for the field which can be set by the user
		// 'description' will be automatically added and ouput by the plugin
		$this->parameters = array();
	}

	function label() {
		return __('File picker', 'mpcf');
	}

	function build_field($args = array()) {
		$caption	= !empty($args['value']) ? __('Change', 'mpcf') : __('Add', 'mpcf');
		$clearclass	= !empty($args['value']) ? '' : 'hidden';
		$class		= mpcf_input_class($this, 'mpcf-media-id');
		$id			= uniqid('mpcf-changemedia-' . $args['name']);

		$file = get_attached_file(mpcf_translate_string($args['value']));
		$size = filesize($file);
		$sizes = array('b', 'KB', 'MB', 'GB');

		if ($size !== false) {
			$s = floor(log($size) / log(1024));
			$filesize = sprintf('%d ' . $sizes[$s], $size / pow(1024, floor($s)));
		} else {
			$filesize = __('0b', 'mpcf');
		}


	//	$accept = (isset($args['accept']) && !empty($args['accept']) ? ' accept="' . $args['accept'] . '"' : '');
	//	$size = (isset($args['size']) && !empty($args['size']) ? ' size="' . $args['size'] . '"' : '');
	//	$required = ($args['required'] ? ' required' : '');

		$multiple = isset($args['multiple']) && !empty($args['multiple']) ? 'true' : 'false'; ?>

		<div class="mpcf-preview-content mpcf-preview-content-file">
			<span class="filename"><?php echo basename($file); ?></span>
			<span class="filesize"><?php echo $filesize; ?></span>
		</div>
		<div class="mpcf-content-buttons">
			<input type="hidden"<?php echo $class . mpcf_input_name($this) . mpcf_input_own_name($this); ?> value="<?php echo $args['value']; ?>">
			<input type="button" class="mpcf-changemedia mpcf-button" id="<?php echo $id; ?>" value="<?php echo $caption; ?>">
			<input type="button" class="mpcf-clearfile mpcf-button <?php echo $clearclass; ?>" value="<?php _e('Remove', 'mpcf'); ?>" />
		</div>
<?php
	}
}




endif;

?>