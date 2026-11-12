<?php

/*****************************************************
	AJAX requests: nonce, capabilities, registered fields
	(inc/mpcf-ajax.php)

	Field definitions take the same way as in the browser:
	json_encode() -> HTML attribute -> JSON.parse() ->
	JSON.stringify() (run in Node) -> slashed by WordPress.
 *****************************************************/

require __DIR__ . '/bootstrap.php';

function mpcf_get_singleton($id) { return array('capability' => 'edit_pages'); }

require MPCF_PLUGIN . '/inc/mpcf-ajax.php';


$repeaterFields = array(
	array('name' => 'person', 'type' => 'text', 'title' => 'Name "quoted" <b>bold</b> & ümlaut'),
	array('name' => 'portrait', 'type' => 'media'),
	array('name' => 'size', 'type' => 'select', 'options' => array(3 => 'Three', 1 => 'One', 'b' => 'Bee'), 'original_order' => '[3,1,&quot;b&quot;]', 'step' => 0.5, 'factor' => 2.0)
);
$conditionalOptions = array(
	'color' => array('title' => 'Color', 'fields' => array(array('name' => 'c', 'type' => 'color'))),
	'img'   => array('title' => 'Image', 'fields' => array(array('name' => 'i', 'type' => 'media', 'description' => 'with a \\ backslash')))
);
$panelOptions = array('hero' => array('title' => 'Hero', 'panel' => array('name' => 'Hero', 'icon' => 'dashicons-format-image', 'fields' => array(
	array('name' => 'headline', 'type' => 'text'),
	array('name' => 'list', 'type' => 'repeater', 'fields' => $repeaterFields)
))));
$modules = array('text' => array('title' => 'Text', 'fields' => array(array('name' => 'content', 'type' => 'editor', 'options' => array()))));

$wp['options'] = array(
	'mpcf_meta_boxes' => array(
		'page-box' => array('post_type' => 'page', 'type' => 'metabox', 'panels' => array(array('name' => 'Main', 'fields' => array(
			array('name' => 'team',   'type' => 'repeater',          'fields'  => $repeaterFields),
			array('name' => 'bg',     'type' => 'conditional',       'options' => $conditionalOptions),
			array('name' => 'module', 'type' => 'conditionalpanels', 'options' => $panelOptions)
		)))),
		'module-box' => array('post_type' => 'page', 'type' => 'module-metabox', 'modules' => $modules)
	),
	'mpcf_user_boxes' => array(
		'user-box' => array('roles' => array('editor'), 'panels' => array(array('name' => 'U', 'fields' => array(
			array('name' => 'links', 'type' => 'repeater', 'fields' => array(array('name' => 'url', 'type' => 'text')))
		))))
	)
);


//	what the browser sends: part of the JSON in an HTML attribute, parsed and stringified again
function browser_sends($json, $path = array()) {
	$script = 'let o = JSON.parse(process.argv[1]); for (const k of JSON.parse(process.argv[2])) o = o[k]; process.stdout.write(JSON.stringify(o));';
	$output = shell_exec('node -e ' . escapeshellarg($script) . ' ' . escapeshellarg($json) . ' ' . escapeshellarg(json_encode($path)));

	if ($output === null || $output === '') throw new TestFailure('Node.js is required for this test.');
	return addslashes($output);		// WordPress adds slashes to $_POST
}

$attribute = function($value, $flags = 0) { return json_encode($value, JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP | $flags); };

function refused($fn) {
	try {
		$fn();
	} catch (WPDieException $e) {
		return $e->status;
	}
	return false;
}


echo "AJAX requests\n";

test('a repeater row of a box the user may edit is rendered from the server\'s copy', function() use ($attribute, $repeaterFields) {
	global $wp;
	$wp['caps'] = array('edit_pages');

	$definition = mpcf_ajax_get_verified_definition(browser_sends($attribute($repeaterFields)));
	assert_same($repeaterFields, $definition, 'rendered definition');
});

test('conditional fields, conditional panels (with nested repeater) and modules are accepted', function() use ($attribute, $conditionalOptions, $panelOptions, $modules) {
	global $wp;
	$wp['caps'] = array('edit_pages');

	assert_same($conditionalOptions['img']['fields'], mpcf_ajax_get_verified_definition(browser_sends($attribute($conditionalOptions), array('img', 'fields'))), 'conditional field');
	assert_same($panelOptions['hero']['panel'],       mpcf_ajax_get_verified_definition(browser_sends($attribute($panelOptions), array('hero', 'panel'))), 'conditional panel');
	assert_same($modules['text'],                     mpcf_ajax_get_verified_definition(browser_sends($attribute($modules, JSON_FORCE_OBJECT), array('text'))), 'module');
});

test('requests without a valid nonce are refused', function() use ($attribute, $repeaterFields) {
	global $wp;
	$wp['caps']     = array('edit_pages');
	$wp['nonce_ok'] = false;

	assert_same(403, refused(function() use ($attribute, $repeaterFields) { mpcf_ajax_get_verified_definition(browser_sends($attribute($repeaterFields))); }), 'status');
});

test('users without the capability of the box are refused', function() use ($attribute, $repeaterFields) {
	assert_same(403, refused(function() use ($attribute, $repeaterFields) { mpcf_ajax_get_verified_definition(browser_sends($attribute($repeaterFields))); }), 'status');
});

test('user profile boxes: only for the roles of the box', function() {
	global $wp;
	$fields = browser_sends(json_encode(array(array('name' => 'url', 'type' => 'text'))));

	assert_same(403, refused(function() use ($fields) { mpcf_ajax_get_verified_definition($fields); }), 'subscriber');

	$wp['roles'] = array('editor');
	assert_same(array(array('name' => 'url', 'type' => 'text')), mpcf_ajax_get_verified_definition($fields), 'editor');
});

test('definitions with injected callbacks or fields are refused', function() use ($repeaterFields) {
	global $wp;
	$wp['caps'] = array('edit_pages');

	$custom     = browser_sends(json_encode(array(array('name' => 'x', 'type' => 'custom', 'callback' => 'phpinfo'))));
	$postselect = browser_sends(json_encode(array_merge($repeaterFields, array(array('name' => 'p', 'type' => 'postselect', 'query' => array('post_status' => 'private'))))));

	assert_same(403, refused(function() use ($custom) { mpcf_ajax_get_verified_definition($custom); }), 'custom callback');
	assert_same(403, refused(function() use ($postselect) { mpcf_ajax_get_verified_definition($postselect); }), 'injected post select');
});

test('administrators may request definitions which are not registered', function() {
	global $wp;
	$wp['caps'] = array('manage_options');

	$definition = mpcf_ajax_get_verified_definition(browser_sends(json_encode(array(array('name' => 'x', 'type' => 'text')))));
	assert_same(array(array('name' => 'x', 'type' => 'text')), $definition, 'definition');
});

test('empty or unreadable requests are refused', function() {
	global $wp;
	$wp['caps'] = array('manage_options');

	assert_same(400, refused(function() { mpcf_ajax_get_verified_definition(null); }), 'no definition');
	assert_same(400, refused(function() { mpcf_ajax_get_verified_definition('{not json'); }), 'invalid JSON');
});
