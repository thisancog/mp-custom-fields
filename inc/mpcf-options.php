<?php

/*****************************************
	Options page
 *****************************************/

function mpcf_default_options() {
	$options = array(
		'googlemapskey'	=> '',
	);

	return $options;
}


function mpcf_options() {
	wp_enqueue_media();
	if (!current_user_can('manage_options')) {
		wp_die(__('You do not have sufficient permissions to access this page.', 'mpcf'));
	}

	if (isset($_POST["update_settings"])) {
		$o = get_option('bf_helper_options');

		$options = array(
			'googlemapskey'			=> $_POST['googlemapskey']
		);

		update_option('mpcf_options', $options);
	}

	$o = get_option('mpcf_options');
	$message = '';

	$googlemapskey		= (isset($o['googlemapskey']) ? $o['googlemapskey'] : '');
	$gui = array(array(
		'name'		=> __('Interfaces', 'mpcf'),
		'icon'		=> 'dashicons-location-alt',
		'fields'	=> array(
			array(
				'name'			=> 'googlemapskey',
				'type'			=> 'text',
				'label'			=> __('Google Maps API Key', 'mpcf'),
				'description'	=> sprintf(__('Generate a free API key <a href="%s" target="_blank" rel="noopener">here</a> to use Google Maps for the map picker and enter it here.', 'mpcf'), 'https://developers.google.com/maps/documentation/javascript/get-api-key'),
			)
		)
	));

	?>

	<div class="mpcf-options"><form method="post" action="">
		<h2><?php _e('MP Custom Fields Options', 'mpcf'); ?></h2>

		<?php	if (isset($_POST['update_settings'])) {
					$message = __('Options were saved.', 'mpcf');
				}

				if (!empty($message)) { ?>
					<div id="message" class="mpcf-message updated fade"><p><strong><?php echo $message; ?></strong></p></div>
	<?php		} ?>

		<?php mpcf_build_gui_as_panels($gui, $o); ?>

		<div class="mpcf-options-inputs">
			<input type="hidden" name="update_settings" id="update_settings" value="Y" />
			<input type="submit" value="<?php _e('Save', 'mpcf'); ?>" id="submit" class="mpcf-submit-button button button-primary button-large" />
		</div>

	</form></div>
<?php	}




?>