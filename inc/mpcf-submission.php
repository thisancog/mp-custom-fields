<?php

if (!defined('ABSPATH')) exit;

/*****************************************************
	Only save complete submissions

	Values missing from a submission are saved as empty,
	which deletes them from the database. A submission
	is incomplete if the form is sent while the interface
	is still being built (e.g. while the page is loading)
	or while fields are being loaded via AJAX.

	Every box contains a hidden input "mpcf-interface-ready"
	with the value 0. admin.js sets it to 1 once all fields
	are loaded and back to 0 while fields are loading, and
	prevents submitting the form in the meantime. If a form
	is still sent with 0 (e.g. JavaScript failed), the
	custom fields are not saved at all - everything else
	(title, content etc.) is saved by WordPress as usual.
 *****************************************************/

define('MPCF_READY_INPUT', 'mpcf-interface-ready');


function mpcf_interface_ready_input() { ?>
	<input type="hidden" name="<?php echo MPCF_READY_INPUT; ?>" class="mpcf-interface-ready" value="0" />
<?php
}


//	true if the submission contains all fields - or comes from a form without the marker
//	(e.g. quick edit, which never contains custom fields)
function mpcf_submission_is_complete() {
	if (!isset($_POST[MPCF_READY_INPUT])) return true;

	return $_POST[MPCF_READY_INPUT] === '1';
}


//	Call when skipping an incomplete submission: logs it and shows a notice to the user.
function mpcf_report_incomplete_submission($context) {
	mpcf_log('Custom fields were not saved (' . $context . '): the form was sent before all fields were loaded.');

	if (function_exists('get_current_user_id') && get_current_user_id())
		set_transient('mpcf_incomplete_submission_' . get_current_user_id(), 1, 5 * MINUTE_IN_SECONDS);
}


function mpcf_incomplete_submission_message() {
	return __('The custom fields were not saved, as the form was sent before all fields had been loaded. Please check the fields and save again.', 'mpcf');
}


//	shown after the redirect following a save (posts, users, terms)
add_action('admin_notices', 'mpcf_show_incomplete_submission_notice');

function mpcf_show_incomplete_submission_notice() {
	$key = 'mpcf_incomplete_submission_' . get_current_user_id();
	if (!get_transient($key)) return;

	delete_transient($key); ?>
	<div class="notice notice-error is-dismissible"><p><?php echo esc_html(mpcf_incomplete_submission_message()); ?></p></div>
<?php
}

?>
