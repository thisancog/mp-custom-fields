<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFMediaSelector')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFMediaSelector extends MPCFModule {
	public $name = 'media';

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
		$this->parameters = array();
	}

	function label() {
		return __('Media selector', 'mpcf');
	}

	function build_field($args = array()) {
		$type	= !empty($args['value']) ? get_post_mime_type($args['value']) : '';
		$media	= (strpos($type, 'image') > -1)
				? wp_get_attachment_image_src($args['value'], 'small')
				: wp_get_attachment_url($args['value']);

		$image		= (strpos($type, 'image') > -1) ? $media[0] : '';
		$video		= (strpos($type, 'video') > -1) ? $media : '';

		$vidclass	= (strpos($type, 'video') > -1) ? '' : ' hidden';
		$imgclass	= (strpos($type, 'image') > -1 || empty($args['value'])) ? '' : ' hidden';
		$caption	= (!empty($args['value'])) ? __('Change', 'mpcf') : __('Add', 'mpcf');
		$clearclass	= !empty($args['value']) ? '' : 'hidden';
		$class		= mpcf_input_class($this, 'mpcf-media-id');
		$id = uniqid('mpcf-changemedia-' . $args['name']); ?>

		<div class="mpcf-mediapicker">
			<div class="mpcf-preview-content dashicons-format-image dashicons-before">
				<img src="<?php echo $image; ?>" class="mpcf-imagepreview<?php echo $imgclass; ?>">
				<video class="mpcf-videopreview<?php echo $vidclass; ?>" autoplay loop muted>
					<source src="<?php echo $video; ?>">
				</video>
			</div>
			<div class="mpcf-content-buttons">
				<input type="hidden"<?php echo $class; ?> name="<?php echo $args['name']; ?>" value="<?php echo $args['value']; ?>">
				<input type="button" class="mpcf-changemedia mpcf-button" id="<?php echo $id; ?>" value="<?php echo $caption; ?>">
				<input type="button" class="mpcf-clearmedia mpcf-button <?php echo $clearclass; ?>" value="<?php _e('Remove', 'mpcf'); ?>" />
			</div>
		</div>
<?php
	}
}



endif;

?>