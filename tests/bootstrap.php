<?php

$_tests_dir = getenv('WP_TESTS_DIR');
if ( !$_tests_dir ) $_tests_dir = '/tmp/wordpress-tests-lib';

if ( ! file_exists( $_tests_dir . '/data' ) ) {
	mkdir( $_tests_dir . '/data' );
}

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {
	require dirname( __FILE__ ) . '/../gs-only-pdf-preview.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

/**
 * Migration fixer for PHPUnit 6
 * From https://core.trac.wordpress.org/attachment/ticket/39822/39822-2.patch
 */
if ( class_exists( 'PHPUnit\Runner\Version' ) && ! file_exists( $_tests_dir . '/includes/phpunit6-compat.php' ) ) {
	require dirname( __FILE__ ) . '/phpunit6-compat.php';
}

require $_tests_dir . '/includes/bootstrap.php';

require dirname( __FILE__ ) . '/gopp_testcase.php';
