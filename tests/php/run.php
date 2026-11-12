<?php

/*****************************************************
	PHP tests

	php tests/php/run.php                      run all tests
	php tests/php/run.php --update-snapshots   store the current results as reference

	Every test-*.php file runs in its own PHP process, as the
	tests define different stand-ins for WordPress functions.
 *****************************************************/

$passed = 0;
$failed = 0;
$args   = in_array('--update-snapshots', $argv, true) ? ' --update-snapshots' : '';

foreach (glob(__DIR__ . '/test-*.php') as $file) {
	echo basename($file) . "\n";

	$output = array();
	exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . $args . ' 2>&1', $output, $status);

	$result = null;
	foreach ($output as $line) {
		if (preg_match('/^RESULT (\d+) (\d+)$/', $line, $result)) continue;
		echo $line . "\n";
	}

	$lastLine = end($output);
	if (preg_match('/^RESULT (\d+) (\d+)$/', $lastLine, $result)) {
		$passed += (int) $result[1];
		$failed += (int) $result[2];
	} else {
		echo "  \u{2717} the test file stopped unexpectedly (exit code $status)\n";
		$failed++;
	}
}

echo "\n$passed passed, $failed failed\n";
exit($failed ? 1 : 0);
