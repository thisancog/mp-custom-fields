<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('MPCFBulkMediaSelector')) :

/*****************************************************
	URL field
 *****************************************************/

class MPCFBulkMediaSelector extends MPCFModule {
	public $name = 'bulkmedia';

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
			array(
				'name'		=> 'extensions',
				'title' 	=> __('Extensions', 'mpcf'),
				'type'		=> 'text',
				'default'	=> 'jpg,jpeg,png,svg,webp,avif,mp4,webm'
			)
		);

		add_action('wp_ajax_mpcf_bulk_media_action', 'mpcf_bulk_media_action');
		add_action('wp_ajax_mpcf_bulk_media_fetch_action', 'mpcf_bulk_media_fetch_action');
	}

	function label() {
		return __('Bulk media selector', 'mpcf');
	}

	function build_field($args = array()) {
		$id            = uniqid();

		$value         = !empty($args['value']) ? $args['value'] : [];
		$nameAttr      = mpcf_get_input_name($this) . '[]';
		$ownNameAttr   = mpcf_input_own_name($this);
		$inputClass    = mpcf_get_input_class($this);

		$maxUploadSize = wp_max_upload_size();
		$maxUploadSize = !$maxUploadSize ? 0 : $maxUploadSize;
		$extensions    = mpcf_get_input_param($this, 'extensions');

		$pluploadArgs  = array(
			'browse_button'    => 'mpcf-bulk-browse-button-' . $id,
			'container'        => 'mpcf-bulkmediapicker-' . $id,
			'drop_element'     => 'mpcf-drop-zone-' . $id,
			'file_data_name'   => 'async-upload',
			'url'              => admin_url('admin-ajax.php'),
			'filters'          => array(
				'max_file_size'		=> $maxUploadSize . 'b',
				'mime_types'		=> [ [ 'extensions' => $extensions ] ],
			),
			'multipart_params' => array(
				'action'			=> 'mpcf_bulk_media_action',
				'_wpnonce'			=> wp_create_nonce('mpcf_bulk_media_upload_form'),
			),
		);

		if (wp_is_mobile() && strpos($_SERVER['HTTP_USER_AGENT'], 'OS 7_') !== false && strpos($_SERVER['HTTP_USER_AGENT'], 'like Mac OS X') !== false) {
			$pluploadArgs['multi_selection'] = false;
		} ?>

		<div class="mpcf-bulkmediapicker drag-drop" id="mpcf-bulkmediapicker-<?php echo $id; ?>" data-id="<?php echo $id; ?>" name="<?php echo $nameAttr; ?>" <?php echo $ownNameAttr; ?>>
			<div class="mpcf-bulkmedia-items">
<?php 		foreach ($value as $img) {
				mpcf_get_bulk_media_row($img, $nameAttr, $args['name'], $inputClass);
			} ?>
			</div>


			<div class="mpcf-drop-zone" id="mpcf-drop-zone-<?php echo $id; ?>">
				<div class="drag-drop-inside">
					<p class="drag-drop-info"><?php _e('Drop files to upload', 'mpcf'); ?></p>
					<p class="drag-drop-buttons">
						<input class="mpcf-bulk-browse-button button" id="mpcf-bulk-browse-button-<?php echo $id; ?>" type="button" value="<?php _e('Select files', 'mpcf'); ?>" />
						<span><?php _e('or', 'mpcf'); ?></span>
						<input class="mpcf-bulk-choose-button button" id="mpcf-bulk-choose-button-<?php echo $id; ?>" type="button" value="<?php _e('Choose from media library', 'mpcf'); ?>" />
					</p>

					<p class="max-upload-size">
<?php 					printf(__('Maximum upload file size: %s.', 'mpcf'), esc_html(size_format($maxUploadSize))); ?></p>

				</div>
			</div>

			<div class="mpcf-media-upload-error"></div>


			<script type="text/javascript">
				var uploaderInit<?php echo $id; ?> = <?php echo wp_json_encode($pluploadArgs); ?>;
			</script>
		</div>
<?php

		wp_enqueue_script('plupload');
	}






	/*****************************************************
		Attach media to post
 	*****************************************************/

	function save_after($post_id, $field, $value, $oldValue) {
		$this->attach_media_to_post($post_id, $field, $value, $oldValue);
	}

	function attach_media_to_post($post_id, $field, $value, $oldValue) {
		if ($post_id == null) return;

		$attID = null;

		if ($value !== '') {
			$attID  = $value;
			$atts   = get_post_meta($attID, 'mpcf-attached-media', true);
			$atts   = !is_array($atts) ? [] : $atts;
			$atts[] = $post_id;
			$atts   = array_unique($atts);
		} else {
			$attID  = $oldValue;
			$atts   = get_post_meta($attID, 'mpcf-attached-media', true);
			$atts   = !is_array($atts) ? [] : $atts;
			$atts   = array_filter($atts, function($v) use ($post_id) { return $v !== $post_id; });
		}

		if ($attID == null) return;

		update_post_meta($attID, 'mpcf-attached-media', $atts);
	}
}



endif;



function mpcf_get_bulk_media_row($attachmentID, $name, $ownName, $class, $removeOuter = false) {
	$type	  = get_post_mime_type($attachmentID);
	$media	  = (strpos($type, 'image') > -1)
			  ? wp_get_attachment_image_src($attachmentID, 'medium')
			  : wp_get_attachment_url($attachmentID);
	$image    = (strpos($type, 'image') > -1) ? $media[0] : '';
	$video    = (strpos($type, 'video') > -1) ? $media    : '';
	$vidClass = (strpos($type, 'video') > -1) ? '' : ' hidden';
	$imgClass = (strpos($type, 'image') > -1 || empty($attachmentID)) ? '' : ' hidden';

	if (!$removeOuter) { ?>
	<div class="mpcf-bulkmedia-item" id="mpcf-bulkmedia-item-<?php echo $attachmentID; ?>">
<?php 	} ?>
		<div class="mpcf-bulkmedia-item-media">
			<img src="<?php echo $image; ?>" class="mpcf-imagepreview<?php echo $imgClass; ?>">
			<video class="mpcf-videopreview<?php echo $vidClass; ?>" autoplay loop muted>
				<source src="<?php echo $video; ?>">
			</video>
		</div>
		<div class="mpcf-bulkmedia-item-actions">
			<div class="mpcf-bulkmedia-moveupdown">
				<input type="button" class="mpcf-bulkmedia-move-up dashicons" value="" />
				<input type="button" class="mpcf-bulkmedia-move-down dashicons" value="" />
			</div>

			<input type="button" class="mpcf-bulkmedia-remove dashicons" value="" />
			<input type="hidden" value="<?php echo $attachmentID; ?>" class="<?php echo $class; ?>"name="<?php echo $name; ?>" data-own-name="<?php echo $ownName; ?>">
		</div>
<?php 	if (!$removeOuter) { ?>
	</div>
<?php
		}
}



/*****************************************************
	AJAX upload
*****************************************************/

function mpcf_bulk_media_action() {
	check_ajax_referer('mpcf_bulk_media_upload_form');

	$post_id = 0;

	if (isset($_REQUEST['post_id'])) {
		$post_id = absint($_REQUEST['post_id']);
		if (!get_post($post_id) || !current_user_can('edit_post', $post_id)) {
			$post_id = 0;
		}
	}

	$id = media_handle_upload('async-upload', $post_id);

	if (is_wp_error($id)) {
		printf(
			'<div class="error-div error">%s <strong>%s</strong><br />%s</div>',
			sprintf(
				'<button type="button" class="dismiss button-link" onclick="jQuery(this).parents(\'div.media-item\').slideUp(200, function(){jQuery(this).remove();});">%s</button>',
				__('Dismiss', 'mpcf')
			),
			sprintf(
				__('&#8220;%s&#8221; has failed to upload.', 'mpcf'),
				esc_html($_FILES['async-upload']['name'])
			),
			esc_html( $id->get_error_message() )
		);
		exit;
	}

	if (isset($_REQUEST['short'])) {
		echo $id;
	} else {
		$type = isset($_REQUEST['type']) ? $_REQUEST['type'] : null;
		echo $id;
	}

	exit;
}


function mpcf_bulk_media_fetch_action() {
	if (!isset($_REQUEST['attachment_id']) || !intval($_REQUEST['attachment_id'])) exit;
	if (!isset($_REQUEST['fieldName'])     || !isset($_REQUEST['fieldOwnName']))   exit;

	$removeOuter = isset($_REQUEST['removeOuter']);

	$id          = intval($_REQUEST['attachment_id']);
	$name        = sanitize_text_field($_REQUEST['fieldName']);
	$ownName     = sanitize_text_field($_REQUEST['fieldOwnName']);
	$post        = get_post($id);

	if ($post->post_type !== 'attachment') {
		wp_die(__('Invalid post type.', 'mpcf'));
	}

	$thumb = wp_get_attachment_image_src($id, 'medium', true);
	if (!$thumb) exit;

	$class = mpcf_get_input_class(new MPCFBulkMediaSelector());
	$html  = mpcf_get_bulk_media_row($id, $name, $ownName, $class, true);
	echo $html;
	exit;
}


?>