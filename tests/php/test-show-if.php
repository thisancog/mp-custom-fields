<?php

/*****************************************************
	Fields shown depending on other fields: normalising
	the setting show_if (mpcf_normalize_show_if() and
	mpcf_show_if_attribute() in inc/gui.php)
 *****************************************************/

require __DIR__ . '/bootstrap.php';
require MPCF_PLUGIN . '/inc/mpcf-ajax.php';
require MPCF_PLUGIN . '/inc/gui.php';

function cond($field, $operator, $value = null) {
	return array('field' => $field, 'operator' => $operator, 'value' => $value);
}


echo "show_if\n";

test('one condition, operator defaults to =', function() {
	assert_same(cond('layout', '=', 'gallery'), mpcf_normalize_show_if(array('field' => 'layout', 'value' => 'gallery')), 'condition');
	assert_same(cond('url', 'not_empty'),       mpcf_normalize_show_if(array('field' => 'url', 'operator' => 'NOT_EMPTY ')), 'operator without value');
});

test('shorthands', function() {
	assert_same(cond('layout', '=', 'gallery'), mpcf_normalize_show_if(array('layout' => 'gallery')), 'name => value');
	assert_same(cond('layout', 'in', array('a', 'b')), mpcf_normalize_show_if(array('layout' => array('a', 'b'))), 'name => values');
	assert_same(array('all' => array(cond('a', '=', 1), cond('b', '=', true))), mpcf_normalize_show_if(array('a' => 1, 'b' => true)), 'several names: all of them');
});

test('lists, any and all, nested', function() {
	$result = mpcf_normalize_show_if(array(
		array('field' => 'layout', 'value' => 'gallery'),
		array('any' => array(
			array('field' => 'columns', 'operator' => '>', 'value' => 2),
			array('field' => 'fullwidth', 'operator' => 'checked')
		))
	));

	assert_same(array('all' => array(
		cond('layout', '=', 'gallery'),
		array('any' => array(cond('columns', '>', 2), cond('fullwidth', 'checked')))
	)), $result, 'nested conditions');

	assert_same(cond('a', '=', 'x'), mpcf_normalize_show_if(array(array('field' => 'a', 'value' => 'x'))), 'a list with one condition');
	assert_same(array('all' => array(cond('x', '!=', '1'))), mpcf_normalize_show_if(array('all' => array(array('field' => 'x', 'operator' => '<>', 'value' => '1')))), 'written as all, <> is !=');
});

test('arrays with = and != become in and not_in', function() {
	assert_same(cond('a', 'in', array('x', 'y')),     mpcf_normalize_show_if(array('field' => 'a', 'value' => array('k' => 'x', 'y'))), '=');
	assert_same(cond('a', 'not_in', array('x', 'y')), mpcf_normalize_show_if(array('field' => 'a', 'operator' => '!=', 'value' => array('x', 'y'))), '!=');
});

test('mistakes are left out and never hide a field', function() {
	assert_same(null, mpcf_normalize_show_if('layout'), 'not an array');
	assert_same(null, mpcf_normalize_show_if(array('field' => 'a', 'operator' => 'like', 'value' => 'x')), 'unknown operator');
	assert_same(cond('b', '=', 'y'), mpcf_normalize_show_if(array(array('field' => 'a', 'operator' => 'like'), array('field' => 'b', 'value' => 'y'))), 'only the invalid condition is left out');
	assert_same('', mpcf_show_if_attribute(array('field' => 'a', 'operator' => 'like')), 'no attribute');
});

test('the attribute survives special characters', function() {
	$html = mpcf_show_if_attribute(array('title' => 'Tom & "Jerry" <b>\'s</b> &amp;'));
	preg_match('/ data-show-if="([^"]*)"/', $html, $m);
	assert_same(cond('title', '=', 'Tom & "Jerry" <b>\'s</b> &amp;'), json_decode(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'), true), 'decoded attribute');
});
