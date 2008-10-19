<?php

$root = dirname(dirname(dirname(dirname(dirname(__FILE__)))));

if (file_exists($root . "/wp-load.php")) {
	// WP 2.6
	require_once($root . "/wp-load.php");
} else {
	// Before 2.6
	require_once($root . "/wp-config.php");
}

if (isset($AudioPlayer)) {
	$AudioPlayer->checkAudioFolder();
}

?>