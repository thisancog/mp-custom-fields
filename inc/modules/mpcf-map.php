<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFMapField')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFMapField extends MPCFModule {
	public $name = 'map';

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
			'center',
			'height',
			'zoom'
		);
	}

	function label() {
		return __('Map', 'mpcf');
	}

	function build_field($args = array()) {
		$center = isset($args['center']) && !empty($args['center']) ? ' center="' . json_encode($args['center']) . '"' : '';
		$height = isset($args['height']) && !empty($args['height']) ? ' style="height: ' . $args['height'] . ';"' : '';
		$zoom = isset($args['zoom']) && !empty($args['zoom']) ? ' zoom="' . $args['zoom'] . '"' : ''; ?>

		<input type="hidden" class="mpcf-mapcoords" name="<?php echo $args['name']; ?>" id="<?php echo $args['name']; ?>" value="<?php echo $args['value']; ?>">
		<input type="text" class="mpcf-mapsearch" placeholder="<?php _e('Search for address or place…', 'mpcf'); ?>">

		<div class="mpcf-map"<?php echo $center . $zoom . $height; ?>>
			 <div class="mpcf-nomap">
			 	<span><?php echo sprintf(__('No map showing up? Generate a free Google Maps API key and enter it <a href="%s" target="_blank">here</a>.', 'mpcf'), menu_page_url('mpcf-options', false)); ?></span>
			 </div>
		</div>

<?php
	}
}



endif;

?>