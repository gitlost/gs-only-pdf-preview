<?php

/**
 * Test plugin code.
 * @group gopp
 */

class Tests_GOPP extends WP_UnitTestCase {

	function setUp() {
		parent::setUp();
		self::clear_func_args();
		add_filter( 'wp_redirect', array( __CLASS__, 'wp_redirect' ), 10, 2 );
	}

	function tearDown() {
		parent::tearDown();
		remove_filter( 'wp_redirect', array( __CLASS__, 'wp_redirect' ), 10, 2 );
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

		$wp_version = '4.6';
		self::clear_func_args();
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
		delete_transient( 'gopp_plugin_admin_notices' );

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

		self::clear_func_args();
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

	/**
	 * Test admin menu.
	 */
	function test_gopp_plugin_admin_menu() {
		global $gopp_plugin_hook_suffix;
		$this->assertTrue( null === $gopp_plugin_hook_suffix );

		gopp_plugin_admin_menu();
		$this->assertFalse( is_string( $gopp_plugin_hook_suffix ) );

		$out = wp_set_current_user( 1 ); // Need manage_options cap to add load-XXX

		gopp_plugin_admin_menu();
		$this->assertTrue( is_string( $gopp_plugin_hook_suffix ) );
		$this->assertSame( 10, has_action( 'load-' . $gopp_plugin_hook_suffix, 'gopp_plugin_load_regen_pdf_previews' ) );

		$out = wp_set_current_user( 0 );
	}

	/**
	 * Test load regen PDF previews.
	 */
	function test_gopp_plugin_load_regen_pdf_previews() {
		if ( ! defined( 'GOPP_TESTING' ) ) define( 'GOPP_TESTING', true );

		// No cap.
		self::clear_func_args();
		try {
			gopp_plugin_load_regen_pdf_previews();
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );

		$out = wp_set_current_user( 1 ); // Need manage_options cap to add load-XXX

		do_action( 'admin_init' );
		$this->assertSame( 10, has_filter( 'wp_image_editors', 'gopp_plugin_wp_image_editors' ) );

		// Do nothing.
		self::clear_func_args();
		try {
			gopp_plugin_load_regen_pdf_previews();
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 0, count( self::$func_args['wp_die'] ) );

		// Do with no pdfs.
		$_REQUEST = array();
		$_REQUEST[GOPP_REGEN_PDF_PREVIEWS_SLUG] = 'Regenerate PDF Previews';
		$_REQUEST['_wpnonce'] = wp_create_nonce( GOPP_REGEN_PDF_PREVIEWS_SLUG );
		$this->assertTrue( 1 === wp_verify_nonce( $_REQUEST['_wpnonce'], GOPP_REGEN_PDF_PREVIEWS_SLUG ) );

		self::clear_func_args();
		try {
			gopp_plugin_load_regen_pdf_previews();
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$args = self::$func_args['wp_die'][0];
		$this->assertSame( 'wp_redirect', $args['title'] );
		$this->assertNotEmpty( $args['args'] );
		$this->assertNotEmpty( $args['args'][0] );
		$this->assertSame( 'error', $args['args'][0][0] );
		$this->assertTrue( false !== stripos( $args['args'][0][1], 'no pdfs' ) );

		$this->assertSame( 1, count( self::$func_args['wp_redirect'] ) );
		$this->assertNotEmpty( self::$func_args['wp_redirect'][0] );

		$admin_notices = get_transient( 'gopp_plugin_admin_notices' );
		$this->assertSame( 1, count( $admin_notices ) );
		$this->assertSame( 2, count( $admin_notices[0] ) );
		$this->assertSame( 'error', $admin_notices[0][0] );
		delete_transient( 'gopp_plugin_admin_notices' );

		// Do with one pdf.
		$test_file = dirname( __FILE__ ) . '/images/test_alpha.pdf';
		$attachment_id = $this->factory->attachment->create_upload_object( $test_file );
		$this->assertNotEmpty( $attachment_id );

		self::clear_func_args();
		try {
			gopp_plugin_load_regen_pdf_previews();
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$args = self::$func_args['wp_die'][0];
		$this->assertSame( 'wp_redirect', $args['title'] );
		$this->assertNotEmpty( $args['args'] );
		$this->assertNotEmpty( $args['args'][0] );
		$this->assertSame( 'updated', $args['args'][0][0] );
		error_log( "args=" . print_r( $args, true ) );
		$this->assertTrue( false !== stripos( $args['args'][0][1], '1 pdf' ) );

		$this->assertSame( 1, count( self::$func_args['wp_redirect'] ) );
		$this->assertNotEmpty( self::$func_args['wp_redirect'][0] );

		$admin_notices = get_transient( 'gopp_plugin_admin_notices' );
		$this->assertSame( 1, count( $admin_notices ) );
		$this->assertSame( 2, count( $admin_notices[0] ) );
		$this->assertSame( 'updated', $admin_notices[0][0] );
		delete_transient( 'gopp_plugin_admin_notices' );

		$out = wp_set_current_user( 0 );
	}

	/**
	 * Test regen PDF previews.
	 */
	function test_gopp_plugin_regen_pdf_previews() {
		// No cap.
		self::clear_func_args();
		try {
			gopp_plugin_regen_pdf_previews();
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );

		$out = wp_set_current_user( 1 ); // Need manage_options cap.

		do_action( 'admin_init' );
		$this->assertSame( 10, has_filter( 'wp_image_editors', 'gopp_plugin_wp_image_editors' ) );

		// No pdfs.
		ob_start();
		gopp_plugin_regen_pdf_previews();
		$out = ob_get_clean();
		$this->assertTrue( false !== stripos( $out, 'nothing' ) );
		$this->assertTrue( false === stripos( $out, 'gopp_regen_pdf_previews_form' ) );
		$this->assertSame( force_balance_tags( $out ), $out );

		// Do with one pdf.
		$test_file = dirname( __FILE__ ) . '/images/test_alpha.pdf';
		$attachment_id = $this->factory->attachment->create_upload_object( $test_file );
		$this->assertNotEmpty( $attachment_id );

		ob_start();
		gopp_plugin_regen_pdf_previews();
		$out = ob_get_clean();
		$this->assertTrue( false !== stripos( $out, '1 PDF' ) );
		$this->assertTrue( false !== stripos( $out, 'gopp_regen_pdf_previews_form' ) );
		$this->assertSame( force_balance_tags( $out ), $out );

		$out = wp_set_current_user( 0 );
	}

	/**
	 * Test admin enqueue.
	 */
	function test_gopp_plugin_admin_enqueue_scripts() {
		global $gopp_plugin_hook_suffix;
		$this->assertTrue( is_string( $gopp_plugin_hook_suffix ) ); // Depends on admin_menu test succeeding.

		gopp_plugin_admin_enqueue_scripts( 'blah' );
		$this->assertFalse( has_action( 'admin_print_footer_scripts', 'gopp_plugin_admin_print_footer_scripts' ) );
		$this->assertFalse( has_action( 'admin_print_footer_scripts', 'gopp_plugin_admin_print_footer_scripts_upload' ) );

		gopp_plugin_admin_enqueue_scripts( $gopp_plugin_hook_suffix );
		$this->assertSame( 10, has_action( 'admin_print_footer_scripts', 'gopp_plugin_admin_print_footer_scripts' ) );
		$this->assertFalse( has_action( 'admin_print_footer_scripts', 'gopp_plugin_admin_print_footer_scripts_upload' ) );

		gopp_plugin_admin_enqueue_scripts( 'upload.php' );
		$this->assertSame( 10, has_action( 'admin_print_footer_scripts', 'gopp_plugin_admin_print_footer_scripts' ) );
		$this->assertSame( 10, has_action( 'admin_print_footer_scripts', 'gopp_plugin_admin_print_footer_scripts_upload' ) );

		remove_action( 'admin_print_footer_scripts', 'gopp_plugin_admin_print_footer_scripts' );
		remove_action( 'admin_print_footer_scripts', 'gopp_plugin_admin_print_footer_scripts_upload' );
	}

	/**
	 * Test admin print footer scripts for regen.
	 */
	function test_gopp_plugin_admin_print_footer_scripts() {
		ob_start();
		$out = gopp_plugin_admin_print_footer_scripts();
		$out = ob_get_clean();
		$this->assertTrue( false !== stripos( $out, 'please wait' ) );
	}

	/**
	 * Test admin print footer scripts for upload.
	 */
	function test_gopp_plugin_admin_print_footer_scripts_upload() {
		ob_start();
		$out = gopp_plugin_admin_print_footer_scripts_upload();
		$out = ob_get_clean();
		$this->assertNotEmpty( $out );
	}

	/**
	 * Test media row actions.
	 */
	function test_gopp_plugin_media_row_actions() {
		$out = wp_set_current_user( 1 ); // Need manage_options cap.

		$actions = array();
		$post = new stdClass;
		$post->ID = 1234;
		$post->post_mime_type = 'blah';
		$detached = null;

		$out = gopp_plugin_media_row_actions( $actions, $post, $detached );
		$this->assertEmpty( $out );

		$post->post_mime_type = 'application/pdf';

		$out = gopp_plugin_media_row_actions( $actions, $post, $detached );
		$this->assertNotEmpty( $out );
		$this->assertTrue( isset( $out['gopp_regen_pdf_preview'] ) );
		$this->assertTrue( false !== stripos( $out['gopp_regen_pdf_preview'], '1234' ) );

		$out = wp_set_current_user( 0 );
	}

	/**
	 * Test row action ajax callback.
	 */
	function test_gopp_plugin_gopp_media_row_action() {
		if ( ! defined( 'GOPP_TESTING' ) ) define( 'GOPP_TESTING', true );

		$out = gopp_plugin_gopp_media_row_action();
		$this->assertTrue( false !== stripos( $out, 'allowed' ) );

		$out = wp_set_current_user( 1 ); // Need manage_options cap.

		do_action( 'admin_init' );
		$this->assertSame( 10, has_filter( 'wp_image_editors', 'gopp_plugin_wp_image_editors' ) );

		$out = gopp_plugin_gopp_media_row_action();
		$this->assertTrue( false !== stripos( $out, 'invalid nonce' ) );

		// Bad id.
		$id = '0';
		$_POST = array();
		$_POST['id'] = $id;
		$_POST['nonce'] = wp_create_nonce( 'gopp_media_row_action_' . $id );
		$_REQUEST = $_POST;
		$this->assertTrue( 1 === wp_verify_nonce( $_POST['nonce'], 'gopp_media_row_action_' . $id ) );
		$out = gopp_plugin_gopp_media_row_action();
		$this->assertTrue( false !== stripos( $out, 'invalid id' ) );

		// Non-existent id.
		$id = '1';
		$_POST['id'] = $id;
		$_POST['nonce'] = wp_create_nonce( 'gopp_media_row_action_' . $id );
		$_REQUEST = $_POST;
		$this->assertTrue( 1 === wp_verify_nonce( $_POST['nonce'], 'gopp_media_row_action_' . $id ) );
		$out = gopp_plugin_gopp_media_row_action();
		$this->assertTrue( false !== stripos( $out, 'failed' ) );

		// Success.
		$test_file = dirname( __FILE__ ) . '/images/test_alpha.pdf';
		$id = $this->factory->attachment->create_upload_object( $test_file );
		$this->assertNotEmpty( $id );

		$_POST['id'] = (string) $id;
		$_POST['nonce'] = wp_create_nonce( 'gopp_media_row_action_' . $id );
		$_REQUEST = $_POST;
		$this->assertTrue( 1 === wp_verify_nonce( $_POST['nonce'], 'gopp_media_row_action_' . $id ) );
		$out = gopp_plugin_gopp_media_row_action();
		error_log( "out=" . print_r( $out, true ) );
		$this->assertTrue( false !== stripos( $out, 'success' ) );

		$out = wp_set_current_user( 0 );
	}
}
