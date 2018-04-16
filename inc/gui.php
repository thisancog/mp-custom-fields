<?php

/*****************************************************
	Build graphical user interface
 *****************************************************/

function mpcf_meta_box_init($post, $metabox) {
	global $post;
	$boxes = get_option('mpcf_meta_boxes', array());
	$box = $boxes[$metabox['id']];
	$values = get_post_meta($post->ID, '', true);
	wp_nonce_field('mpcf_meta_box_nonce', 'mpcf_meta_box_nonce'); ?>

	<div class="mpcf-parent">

<?php 	if (isset($box['panels'])) {
			mpcf_build_gui_as_panels($box['panels'], $values);
		} else if (isset($box['fields'])) {
			mpcf_build_gui_from_fields($box['fields'], $values);
		} ?>

	</div>

<?php 
}


function mpcf_build_gui_as_panels($panels, $values) { 
	$activetab = isset($values['activetab']) ? $values['activetab'][0] : 0; ?>

	<div class="mpcf-panels">
		<input type="hidden" name="activetab" class="activetab" value="<?php echo $activetab; ?>" ?>
		<ul class="mpcf-panels-menu">

<?php	for ($i = 0; $i < count($panels); $i++) { ?>
			<li class="mpcf-panel-item" data-index="<?php echo $i; ?>">

<?php 		if (isset($panels[$i]['icon'])) {
				if (strpos($panels[$i]['icon'], 'dashicons') > -1) { ?>
					<div class="mpcf-panel-icon dashicons <?php echo $panels[$i]['icon']; ?>"></div>
<?php 			} else { ?>
					<div class="mpcf-panel-icon"><img src="<?php echo $panels[$i]['icon']; ?>" alt=""></div>
<?php 			}
			} ?>

				<span class="mpcf-panel-title"><?php echo $panels[$i]['name']; ?></span>
			</li>
<?php	} ?>

		</ul>

		<div class="mpcf-panels-tabs">

<?php	for ($i = 0; $i < count($panels); $i++) { ?>
			<div class="mpcf-panel" data-index="<?php echo $i; ?>">
				<?php mpcf_build_gui_from_fields($panels[$i]['fields'], $values); ?>
			</div>
<?php	} ?>

		</div>

	</div>

<?php
}



function mpcf_build_gui_from_fields($fields, $values, $echoRequired = true) {
	$o = get_option('mpcf_options');
	setlocale(LC_TIME, get_locale());
	$required = false;

	foreach ($fields as $field) {
		if (!isset($field['type'])) continue;

		$field = mpcf_sanitize_args($field);

		$field['value'] = isset($values[$field['name']]) ? $values[$field['name']] : $field['default'];
		if ($field['type'] !== 'repeater')
			$field['value'] = is_array($field['value']) && isset($field['value'][0]) ? $field['value'][0] : $field['value'];

		$required = !$required && $field['required'] ? true : $required;
		$hasRequireds = false;

		switch ($field['type']) {
			case 'buttongroup':	mpcf_build_buttongroup($field); break;
			case 'checkbox':	mpcf_build_checkbox($field); break;
			case 'color':		mpcf_build_color_input($field); break;
			case 'editor':		mpcf_build_editor($field); break;
			case 'email':		mpcf_build_email_input($field); break;
			case 'file':		mpcf_build_file_input($field); break;
			case 'hidden':		mpcf_build_hidden_input($field); break;
			case 'map':			mpcf_build_map($field); break;
			case 'media':		mpcf_build_media_selector($field); break;
			case 'month':		mpcf_build_month_input($field); break;
			case 'number':		mpcf_build_number_input($field); break;
			case 'radio':		mpcf_build_radio_input($field); break;
			case 'range':		mpcf_build_range_input($field); break;
			case 'repeater':	$hasRequireds = mpcf_build_repeater($field); break;
			case 'select':		mpcf_build_select_input($field); break;
			case 'time':		mpcf_build_time_input($field); break;
			case 'week':		mpcf_build_week_input($field); break;

		/*			
			case 'truefalse':	mpcf_build_truefalse($field); break;
		*/


			default: 
				$type = $field['type'];

				if (isset($o['modules'][$type])) {
					$classname = $o['modules'][$type]['name'];
					$class = new $classname();

					$isRequired = isset($field['required']) && $field['required'] ? ' mpcf-required' : '';
					$hasHTML5  = isset($class->html5) && $class->html5 ? ' mpcf-nohtml5' : '';
					$html5Test = isset($class->html5) && $class->html5 ? ' data-invalid-test="Not-a-valid-value"' : '' ?>

					<div class="mpcf-<?php echo $type; ?>-input mpcf-field-option<?php echo $hasHTML5 . $isRequired; ?>"<?php echo $html5Test; ?>>

			<?php		if (isset($field['label']) && !empty($field['label'])) { ?>
							<div class="mpcf-label">
							<label for="<?php echo $field['name']; ?>"><?php echo $field['label']; ?></label>
						</div>
			<?php 		} ?>
						
						<div class="mpcf-field">
			
			<?php			$class->build_field($field);
							mpcf_build_description($field['description']); ?>

						</div>
					</div>
<?php			}

				break;
		}

		if ($hasRequireds) $required = true;
	}

	if ($required && $echoRequired) {
		mpcf_required_hint();
	}
}


/*****************************************************
	Sanitize fields parameters
 *****************************************************/

function mpcf_sanitize_args($args) {
	isset($args['default'])		|| $args['default'] = '';
	isset($args['description'])	|| $args['description'] = '';
	isset($args['label'])		|| $args['label'] = '';
	isset($args['label2'])		|| $args['label2'] = '';
	isset($args['name'])		|| $args['name'] = '';
	isset($args['required'])	|| $args['required'] = false;

	return $args;
}


/*****************************************************
	Save meta box form contents
 *****************************************************/

function mpcf_save_meta_boxes($post_id) {
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (!isset($_POST['mpcf_meta_box_nonce']) || !wp_verify_nonce($_POST['mpcf_meta_box_nonce'], 'mpcf_meta_box_nonce')) return;
	if (!current_user_can('edit_post', $post_id)) return;

	$boxes = get_option('mpcf_meta_boxes', array());

	foreach ($boxes as $box) {
		if ($box['post_type'] !== get_post_type($post_id)) continue;

		$fields = $box['fields'];
		if (isset($box['panels'])) {
			array_walk($box['panels'], function($panel) use (&$fields) {
				$fields = array_merge($fields, $panel['fields']);
			});
		}

		foreach ($fields as $field) {
			if (isset($_POST[$field['name']])) {
				update_post_meta($post_id, $field['name'], mpcf_mksafe($_POST[$field['name']]));
			} else if ($field['type'] === 'checkbox') {
				update_post_meta($post_id, $field['name'], false);
			} else if ($field['type'] === 'file') {
				if (!empty($_FILES[$field['name']]['name'])) {
					$file = $_FILES[$field['name']]['name'];

					if (isset($field['accept']) && !empty($field['accept'])) {
						$supported = array_map('trim', explode(',', $field['accept']));
						$type = wp_check_filetype(basename($file))['type'];

						if (!in_array($type, $supported)) continue;
					}

					$upload = wp_upload_bits($file, null, file_get_contents($_FILES[$field['name']]['tmp_name']));
					if (isset($upload['error']) && $upload['error'] != 0) {
						wp_die(sprintf(__('An error uploading the file for "%s" occurred: %s', 'mpcf'), $field['label'], $upload['error']));
					} else {
						update_post_meta($post_id, $field['name'], $upload);
					}
				} else {
					$file = get_post_meta($post_id, $field['name'], true);
					$urlfield = isset($_POST[$field['name'] . '-url']) ? $_POST[$field['name'] . '-url'] : '';

					if (strlen(trim($file['url'])) > 0 && strlen(trim($urlfield)) == 0) {
						if (unlink($file['file'])) {
							update_post_meta($post_id, $field['name'], null);
							update_post_meta($post_id, $field['name'] . '-url', '');
						} else {
							wp_die(sprintf(__('There was an error trying to delete the file for "%s".', 'mpcf'), $field['label']));
						}
					}
				}
			}
		}
	}

	if (isset($_POST['activetab']))		update_post_meta($post_id, 'activetab', $_POST['activetab']);
}


/*****************************************************
	Build graphical user interface with AJAX
 *****************************************************/

add_action('wp_ajax_mpcf_get_component', 'mpcf_ajax_get_component');
function mpcf_ajax_get_component() {
	$fields = json_decode(stripcslashes($_POST['fields']), true);
	$buttons = '<div class="mpcf-repeater-row-controls"><div class="mpcf-repeater-row-remove dashicons-before dashicons-trash"></div><div class="mpcf-repeater-row-move dashicons-before dashicons-move"></div></div>';

	ob_start();
	if (isset($_POST['values'])) {

		$values = json_decode(stripcslashes($_POST['values']), true);

		foreach ($values as $i => $row) { ?>
			<li class="mpcf-repeater-row">
				<?php mpcf_build_gui_from_fields($fields, $row, false); ?>
				<?php echo $buttons; ?>
			</li>
<?php	}
	} else {
		mpcf_build_gui_from_fields($fields, $values, false);
		echo $buttons;
	}

	$components = ob_get_contents();
	ob_end_clean();
	echo $components;

	wp_die();
}



/*****************************************************
	Build graphical user interface component wise
 *****************************************************/

function mpcf_build_description($desc = false) {
	if (!empty($desc) && $desc !== false) { ?>
		<div class="mpcf-description"><?php echo $desc; ?></div>
<?php 	
	}
}

function mpcf_required_hint() { ?>
	<div class="mpcf-required-hint mpcf-field-option">
		<div class="mpcf-label"></div>
		<div class="mpcf-field"><?php _e('* required fields', 'mpcf'); ?></div>
	</div>
<?php
}



function mpcf_build_buttongroup($args) { ?>
	<div class="mpcf-buttongroup mpcf-field-option<?php echo ($args['required'] ? ' mpcf-required' : ''); ?>">
		<div class="mpcf-label"><label for="<?php echo $args['name']; ?>"><?php echo $args['label']; ?></label></div>
		<div class="mpcf-field">
			<div class="mpcf-buttongroup-wrapper">
<?php 		foreach ($args['options'] as $name => $title) {
				$id = $args['name'] . '-' . $name; ?>

			<div class="mpcf-buttongroup-option">
				<input
					type="radio"
					name="<?php echo $args['name']; ?>"
					id="<?php echo $id; ?>"
					value="<?php echo $name; ?>"
					<?php echo ($args['value'] === $name ? ' checked' : ''); ?>>
				<label for="<?php echo $id; ?>"><?php echo $title; ?></label>
			</div>

<?php		} ?>
			</div>
			<?php mpcf_build_description($args['description']) ?>
		</div>
	</div>

<?php
}




function mpcf_build_checkbox($args) { ?>
	<div class="mpcf-checkbox mpcf-field-option">
		<div class="mpcf-label"><label for="<?php echo $args['name']; ?>"><?php echo $args['label']; ?></label></div>
		<div class="mpcf-field">
			<input
				type="checkbox"
				name="<?php echo $args['name']; ?>"
				id="<?php echo $args['name']; ?>"
				value="checked"
				<?php echo ($args['value'] === 'checked' ? ' checked' : ''); ?>>
			<label for="<?php echo $args['name']; ?>"><?php echo $args['label2']; ?></label>
			<?php mpcf_build_description($args['description']) ?>
		</div>
	</div>

<?php
}


function mpcf_build_color_input($args) { ?>
	<div class="mpcf-color-input mpcf-field-option<?php echo ($args['required'] ? ' mpcf-required' : ''); ?>">
		<div class="mpcf-label"><label for="<?php echo $args['name']; ?>"><?php echo $args['label']; ?></label></div>
		<div class="mpcf-field">
			<input 
				type="text"
				name="<?php echo $args['name']; ?>"
				id="<?php echo $args['name']; ?>"
				value="<?php echo $args['value']; ?>"
				<?php echo ($args['required'] ? ' required' : ''); ?>>

			<?php mpcf_build_description($args['description']) ?>
		</div>
	</div>

<?php
}


function mpcf_update_edit_form() {
	echo ' enctype="multipart/form-data"';
}



function mpcf_build_editor($args) {
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
		'wpautop'			=> isset($args['addparagraphs']) ? !!$args['addparagraphs'] : true,
	); ?>

	<div class="mpcf-editor mpcf-field-option<?php echo ($args['required'] ? ' mpcf-required' : ''); ?>">
		<div class="mpcf-label"><label for="<?php echo $args['name']; ?>"><?php echo $args['label']; ?></label></div>
		<div class="mpcf-field">
			<?php wp_editor($args['value'], $id, $editorargs); ?>
			<?php mpcf_build_description($args['description']) ?>
		</div>
	</div>

<?php
}



function mpcf_build_email_input($args) { ?>
	<div class="mpcf-email-input mpcf-field-option<?php echo ($args['required'] ? ' mpcf-required' : ''); ?>">
		<div class="mpcf-label"><label for="<?php echo $args['name']; ?>"><?php echo $args['label']; ?></label></div>
		<div class="mpcf-field">
			<input 
				type="email"
				name="<?php echo $args['name']; ?>"
				id="<?php echo $args['name']; ?>"
				value="<?php echo $args['value']; ?>"
				<?php echo ($args['required'] ? ' required' : ''); ?>
				<?php echo (isset($args['multiple']) && !empty($args['multiple']) ? ' multiple' : ''); ?>
				<?php echo (isset($args['placeholder']) && !empty($args['placeholder']) ? ' placeholder="' . $args['placeholder'] . '"' : ''); ?>
				<?php echo (isset($args['minlength']) && !empty($args['minlength']) ? ' minlength="' . $args['minlength'] . '"' : ''); ?>
				<?php echo (isset($args['maxlength']) && !empty($args['maxlength']) ? ' maxlength="' . $args['maxlength'] . '"' : ''); ?>>

			<?php mpcf_build_description($args['description']) ?>
		</div>
	</div>

<?php
}



function mpcf_build_file_input($args) {
	$args['value'] = unserialize($args['value']); ?>
	<div class="mpcf-file-input mpcf-field-option<?php echo ($args['required'] ? ' mpcf-required' : ''); ?>">
		<div class="mpcf-label"><label for="<?php echo $args['name']; ?>"><?php echo $args['label']; ?></label></div>
		<div class="mpcf-field">

			<input 
				type="file"
				class="mpcf-file-picker"
				name="<?php echo $args['name']; ?>"
				id="<?php echo $args['name']; ?>"
				value=""
				<?php echo ($args['required'] ? ' required' : ''); ?>
				<?php echo (isset($args['multiple']) && !empty($args['multiple']) ? ' multiple' : ''); ?>
				<?php echo (isset($args['accept']) && !empty($args['accept']) ? ' accept="' . $args['accept'] . '"' : ''); ?>
				<?php echo (isset($args['size']) && !empty($args['size']) ? ' size="' . $args['size'] . '"' : ''); ?>>

<?php 		if (isset($args['value']['url']) && !empty($args['value']['url'])) { ?>
				<label class="mpcf-button" for="<?php echo $args['name']; ?>"><?php echo basename($args['value']['url']); ?></label>
				<input type="button" class="mpcf-remove-file mpcf-button" value="<?php _e('Delete file', 'mpcf'); ?>" />
				<input
					type="hidden"
					class="mpcf-file-url"
					name="<?php echo $args['name']; ?>-url"
					value="<?php echo $args['value']['url']; ?>" />
<?php 		} else { ?>
				<label class="mpcf-button" for="<?php echo $args['name']; ?>"><?php _e('Upload file', 'mpcf'); ?></label>
<?php 		} ?>

			<?php mpcf_build_description($args['description']) ?>
		</div>
	</div>

<?php
}



function mpcf_build_hidden_input($args) { ?>
	<input type="hidden" name="<?php echo $args['name']; ?>" id="<?php echo $args['name']; ?>" value="<?php echo $args['value']; ?>">
<?php
}


function mpcf_build_map($args) { ?>
	<div class="mpcf-map-input mpcf-field-option mpcf-inactive<?php echo ($args['required'] ? ' mpcf-required' : ''); ?>">
		<div class="mpcf-label"><label for="<?php echo $args['name']; ?>"><?php echo $args['label']; ?></label></div>
		<div class="mpcf-field">
			<input type="hidden" class="mpcf-mapcoords" name="<?php echo $args['name']; ?>" id="<?php echo $args['name']; ?>" value="<?php echo $args['value']; ?>">
			<input type="text" class="mpcf-mapsearch" placeholder="<?php _e('Search for address or place…', 'mpcf'); ?>">
			<div
				 class="mpcf-map"
				 <?php echo (isset($args['center']) && !empty($args['center']) ? ' center="' . json_encode($args['center']) . '"' : ''); ?>
				 <?php echo (isset($args['zoom']) && !empty($args['zoom']) ? ' zoom="' . $args['zoom'] . '"' : ''); ?>
				 <?php echo (isset($args['height']) && !empty($args['height']) ? ' style="height: ' . $args['height'] . ';"' : ''); ?>>
				 <div class="mpcf-nomap"><span><?php echo sprintf(__('No map showing up? Generate a free Google Maps API key and enter it <a href="%s" target="_blank">here</a>.', 'mpcf'), menu_page_url('mpcf-options', false)); ?></span></div>
			</div>

			<?php mpcf_build_description($args['description']) ?>
		</div>
	</div>

<?php
}


function mpcf_build_media_selector($args) {
	$type	= !empty($args['value']) ? get_post_mime_type($args['value']) : '';
	$media	= (strpos($type, 'image') > -1)
			? wp_get_attachment_image_src($args['value'], 'small')
			: wp_get_attachment_url($args['value']);

	$image		= (strpos($type, 'image') > -1) ? $media[0] : '';
	$video		= (strpos($type, 'video') > -1) ? $media : '';

	$vidclass	= (strpos($type, 'video') > -1) ? '' : 'hidden';
	$imgclass	= (strpos($type, 'image') > -1 || empty($args['value'])) ? '' : 'hidden';
	$caption	= (!empty($args['value'])) ? __('Change media', 'mpcf') : __('Add media', 'mpcf');
	$clearclass	= !empty($args['value']) ? '' : 'hidden';
	$id = 'mpcf-changemedia-' . $args['name']; ?>

	<div class="mpcf-image-selector mpcf-field-option<?php echo ($args['required'] ? ' mpcf-required' : ''); ?>">

<?php 	if (isset($args['label']) && !empty($args['label'])) { ?>

		<div class="mpcf-label"><label for="<?php echo $id; ?>"><?php echo $args['label']; ?></label></div>

<?php 	} ?>

		<div class="mpcf-field mpcf-mediapicker">
			<div class="mpcf-preview-content dashicons-format-image dashicons-before">
				<img src="<?php echo $image; ?>" class="mpcf-imagepreview <?php echo $imgclass; ?>">
				<video class="mpcf-videopreview <?php echo $vidclass; ?>" autoplay loop muted>
					<source src="<?php echo $video; ?>">
				</video>
			</div>
			<div class="mpcf-content-buttons">
				<input type="hidden" class="mpcf-media-id" name="<?php echo $args['name']; ?>" value="<?php echo $args['value']; ?>">
				<input type="button" class="mpcf-changemedia mpcf-button" id="<?php echo $id; ?>" value="<?php echo $caption; ?>">
				<input type="button" class="mpcf-clearmedia mpcf-button <?php echo $clearclass; ?>" value="<?php _e('Remove', 'mpcf'); ?>" />
			</div>
			<?php mpcf_build_description($args['description']) ?>
		</div>
	</div>

<?php
}




function mpcf_build_month_input($args) { ?>
	<div class="mpcf-month-input mpcf-field-option mpcf-nohtml5<?php echo ($args['required'] ? ' mpcf-required' : ''); ?>" data-invalid-test="not-a-month">
		<div class="mpcf-label"><label for="<?php echo $args['name']; ?>"><?php echo $args['label']; ?></label></div>
		<div class="mpcf-field">
			<input
				type="month"
				name="<?php echo $args['name']; ?>"
				id="<?php echo $args['name']; ?>"
				value="<?php echo $args['value']; ?>"
				<?php echo ($args['required'] ? ' required' : ''); ?>
				<?php echo (isset($args['step']) && !empty($args['step']) ? ' step="' . $args['step'] . '"' : ''); ?>
				<?php echo (isset($args['min']) && !empty($args['min']) ? ' min="' . $args['min'] . '"' : ''); ?>
				<?php echo (isset($args['max']) && !empty($args['max']) ? ' max="' . $args['max'] . '"' : ''); ?>>

			<div class="mpcf-nohtml5-description"><?php echo sprintf(__('format: yyyy-mm (e.g. %s)', 'mpcf'), current_time('Y-m')); ?></div>
			<?php mpcf_build_description($args['description']) ?>
		</div>
	</div>

<?php
}


function mpcf_build_number_input($args) { ?>
	<div class="mpcf-number-input mpcf-field-option <?php echo ($args['required'] ? ' mpcf-required' : ''); ?>">
		<div class="mpcf-label"><label for="<?php echo $args['name']; ?>"><?php echo $args['label']; ?></label></div>
		<div class="mpcf-field">
			<input 
				type="number"
				name="<?php echo $args['name']; ?>"
				id="<?php echo $args['name']; ?>"
				value="<?php echo $args['value']; ?>"
				pattern="[0-9]+"
				<?php echo ($args['required'] ? ' required' : ''); ?>
				<?php echo (isset($args['placeholder']) && !empty($args['placeholder']) ? ' placeholder="' . $args['placeholder'] . '"' : ''); ?>
				<?php echo (isset($args['step']) && !empty($args['step']) ? ' step="' . $args['step'] . '"' : ''); ?>
				<?php echo (isset($args['min']) && !empty($args['min']) ? ' min="' . $args['min'] . '"' : ''); ?>
				<?php echo (isset($args['max']) && !empty($args['max']) ? ' max="' . $args['max'] . '"' : ''); ?>>

			<?php mpcf_build_description($args['description']) ?>
		</div>
	</div>

<?php
}


function mpcf_build_radio_input($args) { ?>
	<div class="mpcf-radio-input mpcf-field-option<?php echo ($args['required'] ? ' mpcf-required' : ''); ?>">
		<div class="mpcf-label"><label for="<?php echo $args['name']; ?>"><?php echo $args['label']; ?></label></div>
		<div class="mpcf-field">
			<fieldset name="<?php echo $args['name']; ?>" id="<?php echo $args['name']; ?>">
<?php 		foreach ($args['options'] as $name => $title) {
				$id = $args['name'] . '-' . $name; ?>

			<div class="mpcf-radio-option">
				<input
					type="radio"
					name="<?php echo $args['name']; ?>"
					id="<?php echo $id; ?>"
					value="<?php echo $name; ?>"
					<?php echo ($args['value'] === $name ? ' checked' : ''); ?>>
				<label for="<?php echo $id; ?>"><?php echo $title; ?></label>
			</div>

<?php		} ?>
			</fieldset>
			<?php mpcf_build_description($args['description']) ?>
		</div>
	</div>

<?php
}


function mpcf_build_range_input($args) { ?>
	<div class="mpcf-range-input mpcf-field-option<?php echo ($args['required'] ? ' mpcf-required' : ''); ?>" data-invalid-test="not-a-range">
		<div class="mpcf-label"><label for="<?php echo $args['name']; ?>"><?php echo $args['label']; ?></label></div>
		<div class="mpcf-field">
			<input 
				type="range"
				name="<?php echo $args['name']; ?>"
				id="<?php echo $args['name']; ?>"
				value="<?php echo $args['value']; ?>"
				<?php echo ($args['required'] ? ' required' : ''); ?>
				<?php echo (isset($args['step']) && !empty($args['step']) ? ' step="' . $args['step'] . '"' : ''); ?>
				<?php echo (isset($args['min']) && !empty($args['min']) ? ' min="' . $args['min'] . '"' : ''); ?>
				<?php echo (isset($args['max']) && !empty($args['max']) ? ' max="' . $args['max'] . '"' : ''); ?>>

			<?php mpcf_build_description($args['description']) ?>
		</div>
	</div>

<?php
}



function mpcf_build_repeater($args) {
	if (is_array($args['value']) && is_string($args['value'][0]))
		$args['value'] = unserialize($args['value'][0]); ?>
	
	<div class="mpcf-repeater mpcf-field-option<?php echo ($args['required'] ? ' mpcf-required' : ''); ?>">
		<div class="mpcf-label"><label><?php echo $args['label']; ?></label></div>
		<div class="mpcf-field">

			<ol class="mpcf-repeater-wrapper" data-basename="<?php echo $args['name']; ?>"
				data-fields="<?php echo esc_attr(json_encode($args['fields'], JSON_HEX_QUOT | JSON_HEX_APOS)); ?>"
				data-values="<?php echo esc_attr(json_encode($args['value'], JSON_HEX_QUOT | JSON_HEX_APOS)); ?>">
			</ol>

			<div class="mpcf-loading-container mpcf-loading-active"></div>

			<div class="mpcf-repeater-controls">
				<input type="button" class="mpcf-repeater-add-row mpcf-button" value="<?php _e('Add', 'mpcf'); ?>" />
			</div>

<?php 		mpcf_build_description($args['description']) ?>
		</div>
	</div>
<?php

	$required = false;
	foreach ($args['fields'] as $field => $data) {
		if (isset($data['required']) && $data['required'] === true) {
			$required = true;
		}
	}

	return $required;
}


function mpcf_build_select_input($args) {
	$args['value'] = isset($args['multiple']) && !empty($args['multiple']) ? unserialize(($args['value'])) : $args['value']; ?>

	<div class="mpcf-select-input mpcf-field-option<?php echo ($args['required'] ? ' mpcf-required' : ''); ?>">
		<div class="mpcf-label"><label for="<?php echo $args['name']; ?>"><?php echo $args['label']; ?></label></div>
		<div class="mpcf-field">
			<select
				name="<?php echo $args['name']; ?><?php echo (isset($args['multiple']) && !empty($args['multiple']) ? '[]' : ''); ?>"
				id="<?php echo $args['name']; ?>"
				<?php echo (isset($args['multiple']) && !empty($args['multiple']) ? ' multiple' : ''); ?>
				<?php echo (isset($args['size']) && !empty($args['size']) ? ' size="' . $args['size'] . '"' : ''); ?>>

<?php 		foreach ($args['options'] as $name => $title) {
				$selected = $args['value'] == $name || (is_array($args['value']) && in_array($name, $args['value'])); ?>
				<option value="<?php echo $name; ?>" <?php echo $selected ? ' selected' : ''; ?>><?php echo $title; ?></option>
<?php		} ?>
			</select>

			<?php mpcf_build_description($args['description']) ?>
		</div>
	</div>

<?php
}


function mpcf_build_time_input($args) { ?>
	<div class="mpcf-time-input mpcf-field-option mpcf-nohtml5<?php echo ($args['required'] ? ' mpcf-required' : ''); ?>" data-invalid-test="not-a-time">
		<div class="mpcf-label"><label for="<?php echo $args['name']; ?>"><?php echo $args['label']; ?></label></div>
		<div class="mpcf-field">
			<input
				type="time"
				name="<?php echo $args['name']; ?>"
				id="<?php echo $args['name']; ?>"
				value="<?php echo $args['value']; ?>"
				pattern="[0-9]{2}:[0-9]{2}"
				<?php echo ($args['required'] ? ' required' : ''); ?>
				<?php echo (isset($args['step']) && !empty($args['step']) ? ' step="' . $args['step'] . '"' : ''); ?>
				<?php echo (isset($args['min']) && !empty($args['min']) ? ' min="' . $args['min'] . '"' : ''); ?>
				<?php echo (isset($args['max']) && !empty($args['max']) ? ' max="' . $args['max'] . '"' : ''); ?>>

			<div class="mpcf-nohtml5-description"><?php echo sprintf(__('format: hh:mm:ss (e.g. %s)', 'mpcf'), current_time('H:i:s')); ?></div>
			<?php mpcf_build_description($args['description']) ?>
		</div>
	</div>

<?php
}



function mpcf_build_week_input($args) { ?>
	<div class="mpcf-week-input mpcf-field-option mpcf-nohtml5<?php echo ($args['required'] ? ' mpcf-required' : ''); ?>" data-invalid-test="not-a-week">
		<div class="mpcf-label"><label for="<?php echo $args['name']; ?>"><?php echo $args['label']; ?></label></div>
		<div class="mpcf-field">
			<input
				type="week"
				name="<?php echo $args['name']; ?>"
				id="<?php echo $args['name']; ?>"
				value="<?php echo $args['value']; ?>"
				<?php echo ($args['required'] ? ' required' : ''); ?>
				<?php echo (isset($args['step']) && !empty($args['step']) ? ' step="' . $args['step'] . '"' : ''); ?>
				<?php echo (isset($args['min']) && !empty($args['min']) ? ' min="' . $args['min'] . '"' : ''); ?>
				<?php echo (isset($args['max']) && !empty($args['max']) ? ' max="' . $args['max'] . '"' : ''); ?>>

			<div class="mpcf-nohtml5-description"><?php echo sprintf(__('format: yyyy-Www (e.g. %s)', 'mpcf'), current_time('Y-\WW')); ?></div>
			<?php mpcf_build_description($args['description']) ?>
		</div>
	</div>

<?php
}


?>
