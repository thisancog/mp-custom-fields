<?php

/*****************************************************
	PHP test bootstrap

	Minimal stand-ins for the WordPress functions the tested
	plugin code uses. Their behaviour is controlled with the
	global $wp (options, capabilities, nonce etc.).
	Each test file runs in its own PHP process (see run.php).
 *****************************************************/

define('ABSPATH', __DIR__ . '/');
define('WP_DEBUG', true);
define('MINUTE_IN_SECONDS', 60);

define('MPCF_PLUGIN', dirname(dirname(__DIR__)));

ini_set('error_log', sys_get_temp_dir() . '/mpcf-tests-' . getmypid() . '.log');

$wp = array(
	'options'    => array(),
	'transients' => array(),
	'post_meta'  => array(),
	'caps'       => array(),
	'roles'      => array('subscriber'),
	'logged_in'  => true,
	'nonce_ok'   => true,
	'ajax'       => false
);

class WPDieException extends Exception {
	public $status;
	function __construct($message, $status) { parent::__construct($message); $this->status = $status; }
}

function add_action() {}
function add_filter() {}
function do_action() {}
function apply_filters($hook, $value) { return $value; }
function did_action() { return 1; }
function __($s, $domain = null) { return $s; }
function _e($s, $domain = null) { echo $s; }
function esc_attr($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function esc_html($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function sanitize_key($s) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower($s)); }
function wp_unslash($s) { return is_string($s) ? stripslashes($s) : $s; }
function wp_doing_ajax() { global $wp; return $wp['ajax']; }

function get_option($name, $default = false) { global $wp; return array_key_exists($name, $wp['options']) ? $wp['options'][$name] : $default; }
function update_option($name, $value) { global $wp; $wp['options'][$name] = $value; return true; }
function add_option($name, $value) { global $wp; if (!array_key_exists($name, $wp['options'])) $wp['options'][$name] = $value; return true; }
function get_transient($key) { global $wp; return isset($wp['transients'][$key]) ? $wp['transients'][$key] : false; }
function set_transient($key, $value) { global $wp; $wp['transients'][$key] = $value; return true; }
function delete_transient($key) { global $wp; unset($wp['transients'][$key]); return true; }
function get_post_meta($id, $key = '', $single = false) { global $wp; return isset($wp['post_meta'][$id][$key]) ? $wp['post_meta'][$id][$key] : ''; }
function update_post_meta($id, $key, $value) { global $wp; $wp['post_meta'][$id][$key] = $value; return true; }

function current_user_can($cap) { global $wp; return in_array($cap, $wp['caps'], true); }
function is_user_logged_in() { global $wp; return $wp['logged_in']; }
function wp_get_current_user() { global $wp; return (object) array('roles' => $wp['roles']); }
function get_current_user_id() { global $wp; return $wp['logged_in'] ? 7 : 0; }
function wp_create_nonce($action) { return 'nonce-' . $action; }
function check_ajax_referer($action, $arg, $die = true) { global $wp; return $wp['nonce_ok']; }
function wp_verify_nonce() { return true; }
function wp_die($message = '', $title = '', $args = array()) { throw new WPDieException($message, isset($args['response']) ? $args['response'] : 500); }

function post_type_exists() { return true; }
function get_post_type() { return 'page'; }
function get_post_type_object($type) { return (object) array('cap' => (object) array('edit_posts' => $type === 'page' ? 'edit_pages' : 'edit_posts')); }
function get_taxonomy($tax) { return (object) array('cap' => (object) array('edit_terms' => 'manage_categories')); }

//	WordPress' is_serialized() (wp-includes/functions.php)
function is_serialized($data, $strict = true) {
	if (!is_string($data)) return false;
	$data = trim($data);
	if ('N;' === $data) return true;
	if (strlen($data) < 4 || ':' !== $data[1]) return false;
	if ($strict) {
		$lastc = substr($data, -1);
		if (';' !== $lastc && '}' !== $lastc) return false;
	}
	$token = $data[0];
	switch ($token) {
		case 's':
			if ($strict && '"' !== substr($data, -2, 1)) return false;
		case 'a': case 'O': case 'E':
			return (bool) preg_match("/^{$token}:[0-9]+:/s", $data);
		case 'b': case 'i': case 'd':
			return (bool) preg_match("/^{$token}:[0-9.E+-]+;\$/", $data);
	}
	return false;
}


/*****************************************************
	Test helpers
 *****************************************************/

$GLOBALS['mpcf_tests'] = array('passed' => 0, 'failed' => 0);

class TestFailure extends Exception {}

function test($name, $fn) {
	global $wp;
	$saved = $wp;

	try {
		$fn();
		$GLOBALS['mpcf_tests']['passed']++;
		echo "  \u{2713} $name\n";
	} catch (Throwable $e) {
		$GLOBALS['mpcf_tests']['failed']++;
		echo "  \u{2717} $name\n      " . ($e instanceof TestFailure ? '' : get_class($e) . ': ') . str_replace("\n", "\n      ", $e->getMessage()) . "\n";
	}

	$wp = $saved;
}

function assert_true($condition, $message) {
	if (!$condition) throw new TestFailure($message);
}

function assert_same($expected, $actual, $message) {
	if ($expected !== $actual)
		throw new TestFailure($message . "\nexpected: " . var_export($expected, true) . "\nactual:   " . var_export($actual, true));
}

//	compares with tests/php/snapshots/<name>.txt
function assert_snapshot($name, $value) {
	$file = __DIR__ . '/snapshots/' . $name . '.txt';

	if (!file_exists($file) || in_array('--update-snapshots', $GLOBALS['argv'], true)) {
		@mkdir(dirname($file), 0777, true);
		file_put_contents($file, $value);
		return;
	}

	assert_same(file_get_contents($file), $value, 'differs from snapshot "' . $name . '"');
}

register_shutdown_function(function() {
	echo 'RESULT ' . $GLOBALS['mpcf_tests']['passed'] . ' ' . $GLOBALS['mpcf_tests']['failed'] . "\n";
});
