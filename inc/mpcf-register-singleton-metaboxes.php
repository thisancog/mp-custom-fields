<?php

add_action('admin_menu', 'mpcf_admin_menu_add_singletons');



/*****************************************************
	Register a new singleton
 *****************************************************/

function mpcf_register_singleton($id, $args = []) {
	$default = [
		'title'			=> $id,
		'slug'			=> $id,
		'optionName'	=> 'mpcf_' . $id,
		'icon'			=> 'dashicons-pressthis',
		'capability'	=> 'edit_pages',
		'parent'		=> null,
		'position'		=> null,
	];

	$args = array_merge($default, $args);

	$singletons = mpcf_get_singletons();
	$singletons[$id] = $args;
	update_option('mpcf_singletons', $singletons);

	return $args;
}



/*****************************************************
	Deregister a singleton
 *****************************************************/

function mpcf_remove_singleton($id) {
	$singletons = mpcf_get_singletons();
	$keys       = array_keys($singletons);
	$removed    = null;

	array_walk($keys, function($index) use ($id, &$singletons, &$removed) {
		if ($index === $id) {
			$removed = $singletons[$id];
			unset($singletons[$id]);
		}
	});

	update_option('mpcf_singletons', $singletons);
	return $removed;
}



/*****************************************************
	Get singletons
 *****************************************************/

function mpcf_get_singletons() {
	$singletons = get_option('mpcf_singletons', array());
	$singletons = empty($singletons) ? [] : $singletons;
	return $singletons;
}

function mpcf_get_singleton($id) {
	$singletons = mpcf_get_singletons();
	return isset($singletons[$id]) ? $singletons[$id] : null;
}

function mpcf_singleton_exists($id) {
	$singletons = mpcf_get_singletons();
	return isset($singletons[$id]);
}




/*****************************************************
	Register custom fields for singleton
 *****************************************************/

function mpcf_add_custom_fields_singleton($singletonID, $id, $arguments = array()) {
	if (!mpcf_singleton_exists($singletonID)) return;

	$singleton = mpcf_get_singleton($singletonID);

	$boxes = get_option('mpcf_singleton_boxes', array());

	$defaults = array(
		'title'			=> '',
		'multilingual'	=> false,
		'priority'		=> 0,
		'panels'		=> array()
	);

	$newbox = array_merge($defaults, $arguments);
	$newbox['singleton'] = $singletonID;

//	Give generic title if needed
	if (empty($newbox['title'])) {
		$newbox['title'] = sprintf(__('%s Options', 'mpcf'), $singleton['title']);
	}

	$boxes[$id] = $newbox;

	update_option('mpcf_singleton_boxes', $boxes);
	return $newbox;
}

function mpcf_get_singleton_boxes($singletonID) {
	$boxes = get_option('mpcf_singleton_boxes', array());
	$boxes = array_filter($boxes, function($box) use ($singletonID) {
		return $box['singleton'] === $singletonID;
	});

	$priorities = [ 'high', 'sorted', 'core', 'default', 'low' ];

	usort($boxes, function($a, $b) use ($priorities) {
		$a = isset($priorities[$a['priority']]) ? array_search($a['priority'], $priorities) : 99999;
		$b = isset($priorities[$b['priority']]) ? array_search($b['priority'], $priorities) : 99999;
		
		return $a - $b;
	});

	return $boxes;
}



/*****************************************************
	Deregister a custom field box
 *****************************************************/

function mpcf_remove_custom_fields_singleton($boxID) {
	$boxes   = get_option('mpcf_singleton_boxes', array());
	$keys    = array_keys($boxes);
	$removed = null;

	array_walk($keys, function($id) use ($boxID, &$boxes, &$removed) {
		if ($id === $boxID) {
			$removed = $boxes[$id];
			unset($boxes[$id]);
		}
	});

	update_option('mpcf_singleton_boxes', $boxes);
	return $removed;
}

function mpcf_remove_all_custom_fields_singleton() {
	update_option('mpcf_singleton_boxes', array());
}




/*****************************************************
	Add singletons in backend
 *****************************************************/

function mpcf_admin_menu_add_singletons() {
	$singletons = mpcf_get_singletons();
	foreach ($singletons as $id => $s) {
		$args   = [ $s['title'], $s['title'], $s['capability'], $s['slug'], 'mpcf_render_singleton', $s['icon'], $s['position'] ];
		if ($s['parent'] !== null) {
			add_submenu_page($s['parent'], $s['title'], $s['title'], $s['capability'], $s['slug'], 'mpcf_render_singleton', $s['position']);
		} else {
			add_menu_page($s['title'], $s['title'], $s['capability'], $s['slug'], 'mpcf_render_singleton', $s['icon'], $s['position']);
		}
	}
}


/*****************************************************
	Build graphical user interface for singleton pages
 *****************************************************/

function mpcf_render_singleton() {
	global $plugin_page;
	$singletons = mpcf_get_singletons();
	if (empty($singletons)) return;

	uasort($singletons, function($a, $b) {
		if ($a['position'] == null) return -1;
		if ($b['position'] == null) return 1;
		if ($a['position'] == $b['position']) return 0;

		return $a['position'] < $b['position'] ? 1 : -1;
	});

	$ids  = array_keys($singletons);
	$id   = $ids[0];
	
	array_walk($ids, function($s) use (&$singleton, &$id, $plugin_page, $singletons) {
		if (isset($singletons[$s]) && $singletons[$s]['slug'] == $plugin_page)
			$id = $s;
	});

	$singleton  = $singletons[$id];
	$boxes      = mpcf_get_singleton_boxes($id); ?>
	<div class="mpcf-options">
		<h2><?php echo $singleton['title']; ?></h2>
<?php 	foreach ($boxes as $box) {
			mpcf_build_admin_gui($box['panels'], $singleton['optionName']);
		} ?>
	</div>
<?php }






?>