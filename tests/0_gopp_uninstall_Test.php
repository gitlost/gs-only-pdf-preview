<?php

/**
 * Test uninstall code.
 * @group gopp-uninstall
 */

class Tests_GOPP_Uninstall extends GOPP_UnitTestCase {

	/**
	 * Test test().
	 */
	function test_uninstall() {
		set_transient( 'gopp_plugin_admin_notices', array( 'error', 'Error' ) );
		set_transient( 'gopp_plugin_poll_rpp', array( 0, 0 ) );
		set_transient( 'gopp_image_gs_cmd_path', '/usr/bin/gs' );

		$this->assertFalse( defined( 'WP_UNINSTALL_PLUGIN' ) );
		define( 'WP_UNINSTALL_PLUGIN', self::$dirdirname . '/uninstall.php' );
		require_once WP_UNINSTALL_PLUGIN;

		$this->assertEmpty( get_transient( 'gopp_plugin_admin_notices' ) );
		$this->assertEmpty( get_transient( 'gopp_plugin_poll_rpp' ) );
		$this->assertEmpty( get_transient( 'gopp_image_gs_cmd_path' ) );
	}
}
