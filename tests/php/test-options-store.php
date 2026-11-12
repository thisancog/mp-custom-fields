<?php

/*****************************************************
	Options of conditional fields, printed once per page
	(mpcf_options_data_attribute() in inc/gui.php)
 *****************************************************/

require __DIR__ . '/bootstrap.php';
require MPCF_PLUGIN . '/inc/mpcf-ajax.php';
require MPCF_PLUGIN . '/inc/gui.php';

$large = array();
for ($i = 0; $i < 30; $i++)
	$large["option$i"] = array('title' => "Tïtle $i </script><!-- \"quoted\" 'single' & <b>bold</b> 😀 \\back\\slash", 'fields' => array(array('name' => "f$i", 'type' => 'text')));

$small = array('x' => array('title' => 'small <&> "q"', 'fields' => array()));

function attribute_value($html, $attribute) {
	return preg_match('/ ' . $attribute . '="([^"]*)"/', $html, $m) ? html_entity_decode($m[1], ENT_QUOTES, 'UTF-8') : null;
}

function print_store() {
	ob_start();
	mpcf_print_options_store();
	return ob_get_clean();
}


echo "Option store\n";

test('large options are referenced and printed once per page', function() use ($large) {
	$GLOBALS['mpcf_options_store']['depth'] = 1;
	$first  = mpcf_options_data_attribute($large);
	$second = mpcf_options_data_attribute($large);
	$GLOBALS['mpcf_options_store']['depth'] = 0;

	$ref = attribute_value($first, 'data-options-ref');
	assert_true($ref !== null, 'reference instead of inline options');
	assert_same($first, $second, 'same reference for the same options');

	$store = print_store();
	assert_same(1, substr_count($store, 'class="mpcf-options-store"'), 'printed once');
	assert_same('', print_store(), 'not printed again');

	preg_match('/<script[^>]*id="mpcf-options-' . $ref . '">(.*)<\/script>/s', $store, $m);
	assert_true(isset($m[1]) && strpos($m[1], '<') === false && strpos($m[1], '>') === false, 'no "<" or ">" inside the script element');
	assert_same($large, json_decode($m[1], true), 'stored options decode to the same options');
});

test('small options stay inline, unchanged', function() use ($small) {
	$GLOBALS['mpcf_options_store']['depth'] = 1;
	$html = mpcf_options_data_attribute($small);
	$GLOBALS['mpcf_options_store']['depth'] = 0;

	assert_same(' data-options="' . esc_attr(json_encode($small, JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP)) . '"', $html, 'attribute');
});

test('AJAX responses keep the options inline', function() use ($large) {
	global $wp;
	$wp['ajax'] = true;

	$GLOBALS['mpcf_options_store']['depth'] = 1;
	$html = mpcf_options_data_attribute($large);
	$GLOBALS['mpcf_options_store']['depth'] = 0;

	assert_same($large, json_decode(attribute_value($html, 'data-options'), true), 'inline options');
});
