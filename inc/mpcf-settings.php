<?php

/*****************************************
	Options page
 *****************************************/

function mpcf_default_settings() {
	$options = array(
		'googlemapskey'		=> '',
		'multilingualclass'	=> 'mpcf-multilingual'
	);

	return $options;
}


function mpcf_settings() {
	if (!current_user_can('manage_options')) {
		wp_die(__('You do not have sufficient permissions to access this page.', 'mpcf'));
	}

	$panels = array(
		array(
			'name'		=> __('General', 'mpcf'),
			'icon'		=> 'dashicons-admin-settings',
			'fields'	=> array(
				array(
					'name'			=> 'multilingualclass',
					'type'			=> 'text',
					'title'			=> __('Class for multilingual fields', 'mpcf'),
					'description'	=> __('Specify a class to be added to fields to flag for plugins providing multilingual support.', 'mpcf'),
					'default'		=> 'mpcf-multilingual',
					'notranslate'	=> true
				)
			)
		),
		array(
			'name'		=> __('Interfaces', 'mpcf'),
			'icon'		=> 'dashicons-location-alt',
			'fields'	=> array(
				array(
					'name'			=> 'googlemapskey',
					'type'			=> 'text',
					'title'			=> __('Google Maps API Key', 'mpcf'),
					'description'	=> sprintf(__('Generate a free API key <a href="%s" target="_blank" rel="noopener">here</a> to use Google Maps for the map picker and enter it here.', 'mpcf'), 'https://developers.google.com/maps/documentation/javascript/get-api-key'),
					'notranslate'	=> true
				)
			)
		)
	);

	?>

	<div class="mpcf-options">
		<h2><?php _e('MP Custom Fields Options', 'mpcf'); ?></h2>
		<?php mpcf_build_admin_gui($panels, 'mpcf_options'); ?>
	</div>
<?php

}




?>