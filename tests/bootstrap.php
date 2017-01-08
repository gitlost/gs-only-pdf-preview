<?php

$_tests_dir = getenv('WP_TESTS_DIR');
if ( !$_tests_dir ) $_tests_dir = '/tmp/wordpress-tests-lib';

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {
	require dirname( __FILE__ ) . '/../ghostscript-only-pdf-preview.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

if ( ! file_exists( $_tests_dir . '/data' ) ) {
	mkdir( $_tests_dir . '/data' );
}
require $_tests_dir . '/includes/bootstrap.php';
