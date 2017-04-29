<?php

global $wp_version;
error_log( "\nWordPress $wp_version\n" );

/**
 * Base class for unit tests.
 */

class GOPP_UnitTestCase extends WP_UnitTestCase {
	static $is_win;
	static $have_exec;
	static $have_gs;
	static $dirname;
	static $dirdirname;

	static function wpSetUpBeforeClass() {
		self::$is_win = 0 === strncasecmp( 'WIN', PHP_OS, 3 );

		self::$have_exec = ! self::is_exec_disabled();

		if ( ! self::$is_win && self::$have_exec ) {
			$return_var = -1;
			$output = array();
			exec( 'which gs', $output, $return_var );
			self::$have_gs = 0 === $return_var;
		}

		self::$dirname = dirname( __FILE__ );
		self::$dirdirname = dirname( self::$dirname );
	}

	function setUp() {
		parent::setUp();

		if ( ! isset( $_SERVER['SCRIPT_NAME'] ) ) { // Suppress internal phpunit PHP Warning bug.
			$_SERVER['SCRIPT_NAME'] = __FILE__;
		}
	}

	function tearDown() {
		parent::tearDown();
	}

	static function is_exec_disabled() {
		$ini_get = ini_get( 'disable_functions' );
		if ( $ini_get && in_array( 'exec', array_map( 'trim', explode( ',', strtolower( $ini_get ) ) ), true ) ) {
			return true;
		}
		if ( extension_loaded( 'suhosin' ) || extension_loaded( 'suhosin7' ) || ( version_compare( PHP_VERSION, '5.3', '>=' ) && ini_get( 'enable_dl' ) && @ dl( 'suhosin' ) ) ) {
			$ini_get = ini_get( 'suhosin.executor.func.whitelist' );
			if ( $ini_get && in_array( 'exec', array_map( 'trim', explode( ',', strtolower( $ini_get ) ) ), true ) ) {
				return false;
			}
			$ini_get = ini_get( 'suhosin.executor.func.blacklist' );
			if ( $ini_get && in_array( 'exec', array_map( 'trim', explode( ',', strtolower( $ini_get ) ) ), true ) ) {
				return true;
			}
		}
		return false;
	}

	function get_wp_die_handler( $handler ) {
		return array( __CLASS__, 'wp_die' );
	}

	static $func_args = array();

	static function clear_func_args() {
		self::$func_args = array(
			'wp_clear_auth_cookie' => array(), 'wp_die' => array(), 'wp_redirect' => array(), 'wp_safe_redirect' => array(),
		);
	}

	static function wp_die( $message, $title = '', $args = array() ) {
		self::$func_args['wp_die'][] = compact( 'message', 'title', 'args' );
		throw new WPDieException( count( self::$func_args['wp_die'] ) - 1 );
	}

	static function wp_redirect( $location, $status = 302 ) {
		self::$func_args['wp_redirect'][] = compact( 'location', 'status' );
		return false;
	}

	static function remove_image_editor_imagick_filter( $image_editors ) {
		return array_diff( $image_editors, array( 'WP_Image_Editor_Imagick' ) );
	}
}
