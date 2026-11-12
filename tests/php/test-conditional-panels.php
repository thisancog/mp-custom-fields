<?php

/*****************************************************
	Finding the conditional panels to add
	(mpcf_add_conditional_panels() in inc/gui.php)

	The results are compared with snapshots created with the
	original implementation. MPCF_GUI_PHP=path/to/gui.php
	runs the tests with another version of gui.php.
 *****************************************************/

require __DIR__ . '/bootstrap.php';
require MPCF_PLUGIN . '/inc/mpcf-ajax.php';
require getenv('MPCF_GUI_PHP') ?: MPCF_PLUGIN . '/inc/gui.php';


//	a configuration like a page built from modules: a repeater "modules" with a conditional panels field per row
function build_configuration() {
	mt_srand(7);

	$words = function($n) { $w = array('alpha', 'beta', 'gamma', 'delta', 'lorem', 'ipsum'); $o = array(); for ($i = 0; $i < $n; $i++) $o[] = $w[mt_rand(0, 5)]; return implode(' ', $o); };

	$options = function($depth) use (&$options, $words) {
		$result = array();
		foreach (array('x', 'y', 'z') as $key) {
			$fields = array();
			for ($i = 0; $i < 4; $i++) $fields[] = array('name' => "f$i", 'type' => 'select', 'options' => array_combine(range(1, 6), array_map($words, array_fill(0, 6, 2))));
			if ($depth > 0) $fields[] = array('name' => 'deep', 'type' => 'conditional', 'options' => $options($depth - 1));
			$result[$key] = array('title' => $words(2), 'fields' => $fields);
		}
		return $result;
	};

	$panelOptions = array();
	foreach (array('hero', 'text', 'gallery') as $type) {
		$panelOptions[$type] = array('title' => ucfirst($type), 'panel' => array('name' => ucfirst($type), 'fields' => array(
			array('name' => 'headline', 'type' => 'text'),
			array('name' => 'layout', 'type' => 'conditional', 'options' => $options(1)),
			array('name' => 'items', 'type' => 'repeater', 'fields' => array(array('name' => 'label', 'type' => 'text')))
		)));
	}

	$panels = array(
		array('name' => 'Main', 'fields' => array(
			array('name' => 'title', 'type' => 'text'),
			array('name' => 'modules', 'type' => 'repeater', 'fields' => array(
				array('name' => 'module', 'type' => 'conditionalpanels', 'options' => $panelOptions),
				array('name' => 'bg', 'type' => 'conditional', 'options' => $options(1))
			))
		)),
		array('name' => 'SEO', 'fields' => array(array('name' => 'seotitle', 'type' => 'text')))
	);

	$types = array_keys($panelOptions);
	$rows  = array();
	for ($r = 0; $r < 6; $r++)
		$rows[] = array('module' => array('type' => $types[$r % 3], 'headline' => $words(10), 'layout' => array('type' => 'y'), 'items' => array(array('label' => $words(5)))), 'bg' => array('type' => 'x'));

	return array($panels, $rows);
}

//	a readable summary of the added panels and a checksum of the complete result
function run_case($panels, $values) {
	$result = mpcf_add_conditional_panels($panels, $values);
	$export = preg_replace('/[0-9a-f]{13}/', '<uniqid>', var_export($result, true));		// panel IDs are generated with uniqid()

	$summary = array('panels: ' . count($result['panels']));
	foreach (array_slice($result['panels'], count($panels)) as $panel) {
		$summary[] = '  added: ' . $panel['name'] . ', basename ' . $panel['panel_basename'] . ', fields '
				   . implode(' ', array_map(function($field) { return $field['name'] . (isset($field['baseName']) ? '@' . $field['baseName'] : ''); }, $panel['fields']));
	}
	$summary[] = 'values: ' . implode(' ', array_keys($result['values']));
	$summary[] = 'checksum of the complete result: ' . md5($export);

	return implode("\n", $summary) . "\n";
}

list($panels, $rows) = build_configuration();
$values = array('title' => array('Hello'), 'modules' => array(serialize($rows)), 'seotitle' => array('SEO'));


echo "Conditional panels\n";

test('panels and values with saved choices', function() use ($panels, $values) {
	assert_snapshot('conditional-panels-saved', run_case($panels, $values));
});

test('new post without values', function() use ($panels) {
	assert_snapshot('conditional-panels-empty', run_case($panels, array()));
});

test('rows without a chosen panel', function() use ($panels, $rows, $values) {
	foreach ($rows as $i => &$row) if ($i % 2) unset($row['module']['type']);
	assert_snapshot('conditional-panels-partly-chosen', run_case($panels, array('modules' => array(serialize($rows))) + $values));
});

test('a top-level conditional panels field', function() use ($panels, $values) {
	$panels[1]['fields'][] = array('name' => 'extra', 'type' => 'conditionalpanels', 'options' => array('k' => array('title' => 'K', 'panel' => array('name' => 'K', 'fields' => array(array('name' => 'z', 'type' => 'text'))))));
	assert_snapshot('conditional-panels-top-level', run_case($panels, array('extra' => array(serialize(array('type' => 'k', 'z' => 'q')))) + $values));
});
