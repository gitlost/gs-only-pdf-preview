<?php

/**
 * Test plugin code.
 * @group gopp
 */

class Tests_GOPP extends WP_UnitTestCase {

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

	/**
	 * Test activation hook.
	 */
	function test_gopp_plugin_activation_hook() {
		$this->assertSame( 10, has_action( 'admin_init', 'gopp_plugin_admin_init' ) );

		if ( in_array( 'exec', array_map( 'trim', explode( ',', strtolower( ini_get( 'disable_functions' ) ) ) ), true ) ) {
			$this->markTestSkipped( 'exec() disabled.' );
		}

		global $wp_version;
		$old_wp_version = $wp_version;

		$wp_version = GOPP_PLUGIN_WP_UP_TO_VERSION;
		gopp_plugin_activation_hook();
		$admin_notices = get_transient( 'gopp_plugin_admin_notices' );
		$this->assertEmpty( $admin_notices );

		$wp_version = '9999.9999.9999';
		gopp_plugin_activation_hook();
		$admin_notices = get_transient( 'gopp_plugin_admin_notices' );
		$this->assertSame( 1, count( $admin_notices ) );
		$this->assertSame( 2, count( $admin_notices[0] ) );
		$this->assertSame( 'warning', $admin_notices[0][0] );
		$this->assertTrue( false !== stripos( $admin_notices[0][1], 'version' ) );

		ob_start();
		gopp_plugin_admin_notices();
		$output = ob_get_clean();
		$this->assertTrue( false !== stripos( $output, 'warning' ) );
		$this->assertTrue( false !== stripos( $output, 'version' ) );
		$this->assertEmpty( get_transient( 'gopp_plugin_admin_notices' ) );

		gopp_plugin_activation_hook();
		$this->assertNotEmpty( get_transient( 'gopp_plugin_admin_notices' ) );
		$this->assertFalse( defined( 'WP_UNINSTALL_PLUGIN' ) );
		define( 'WP_UNINSTALL_PLUGIN', dirname( dirname( __FILE__ ) ) . '/uninstall.php' );
		require_once WP_UNINSTALL_PLUGIN;
		$this->assertEmpty( get_transient( 'gopp_plugin_admin_notices' ) );

		$wp_version = '4.6.1';
		try {
			gopp_plugin_activation_hook();
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$args = self::$func_args['wp_die'][0];
		$this->assertTrue( false !== stripos( $args['message'], 'activated' ) );

		$wp_version = GOPP_PLUGIN_WP_UP_TO_VERSION;
		add_filter( 'gopp_image_have_gs', array( $this, 'filter_gopp_image_have_gs_false' ) );
		gopp_plugin_activation_hook();
		remove_filter( 'gopp_image_have_gs', array( $this, 'filter_gopp_image_have_gs_false' ) );
		$admin_notices = get_transient( 'gopp_plugin_admin_notices' );
		$this->assertSame( 1, count( $admin_notices ) );
		$this->assertSame( 2, count( $admin_notices[0] ) );
		$this->assertSame( 'warning', $admin_notices[0][0] );
		$this->assertTrue( false !== stripos( $admin_notices[0][1], 'executable' ) );

		$wp_version = $old_wp_version;
	}

	function filter_gopp_image_have_gs_false( $gs_cmd_path ) {
		return false;
	}

	/**
	 * Test activation hook with exec() disabled.
	 */
	function test_gopp_plugin_activation_hook_exec_disabled() {
		if ( ! in_array( 'exec', array_map( 'trim', explode( ',', strtolower( ini_get( 'disable_functions' ) ) ) ), true ) ) {
			$this->markTestSkipped( 'exec() enabled.' );
		}

		try {
			gopp_plugin_activation_hook();
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$args = self::$func_args['wp_die'][0];
		$this->assertTrue( false !== stripos( $args['message'], 'activated' ) );
	}

	/**
	 * Test admin init.
	 */
	function test_gopp_plugin_admin_init() {
		$admin_notices_action = is_network_admin() ? 'network_admin_notices' : ( is_user_admin() ? 'user_admin_notices' : 'admin_notices' );

		gopp_plugin_admin_init();
		$this->assertSame( 10, has_filter( 'wp_image_editors', 'gopp_plugin_wp_image_editors' ) );
		$this->assertSame( 10, has_action( $admin_notices_action, 'gopp_plugin_admin_notices' ) );
	}

	/**
	 * Test image editors filter.
	 */
	function test_gopp_plugin_wp_image_editors() {
		$image_editors = array( 'blah' );
		$output = gopp_plugin_wp_image_editors( $image_editors );
		$this->assertContains( 'GOPP_Image_Editor_GS', $output );
		$image_editors = $output;
		$output = gopp_plugin_wp_image_editors( $image_editors );
		$this->assertContains( 'GOPP_Image_Editor_GS', $output );
		$this->assertSame( $output, $image_editors );
	}
}
