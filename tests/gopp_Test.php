<?php

/**
 * Test plugin code.
 * @group gopp
 */

class Tests_GOPP extends WP_UnitTestCase {

	var $old_pagenow;

	static $is_win;
	static $have_gs;

	static function wpSetUpBeforeClass() {
		self::$is_win = 0 === strncasecmp( 'WIN', PHP_OS, 3 );
		if ( ! self::$is_win ) {
			exec( 'which gs', $output, $return_var );
			self::$have_gs = 0 === $return_var;
		}
	}

	static function wpTearDownAfterClass() {
	}

	function setUp() {
		parent::setUp();
		self::clear_func_args();
		add_filter( 'wp_redirect', array( __CLASS__, 'wp_redirect' ), 10, 2 );
		add_filter( 'wp_die_ajax_handler', array( $this, 'get_wp_die_handler' ) );

		if ( ! isset( $_SERVER['SCRIPT_NAME'] ) ) { // Suppress internal phpunit PHP Warning bug.
			$_SERVER['SCRIPT_NAME'] = __FILE__;
		}
	}

	function tearDown() {
		parent::tearDown();
		remove_filter( 'wp_redirect', array( __CLASS__, 'wp_redirect' ), 10, 2 );
		remove_filter( 'wp_die_ajax_handler', array( $this, 'get_wp_die_handler' ) );
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
	 * Test init.
	 */
	function test_init() {
		$this->assertFalse( is_admin() );
		GS_Only_PDF_Preview::init();
		$this->assertSame( 10, has_filter( 'wp_image_editors', array( 'GS_Only_PDF_Preview', 'wp_image_editors' ) ) );
		$this->assertFalse( has_action( 'admin_init', array( 'GS_Only_PDF_Preview', 'admin_init' ) ) );
		$this->assertFalse( has_action( 'wp_ajax_gopp_media_row_action', array( 'GS_Only_PDF_Preview', 'gopp_media_row_action' ) ) );

		remove_filter( 'wp_image_editors', array( 'GS_Only_PDF_Preview', 'wp_image_editors' ) );

		global $pagenow;
		$old_pagenow = $pagenow;
		$pagenow = 'tools.php';
		set_current_screen( $pagenow );

		$this->assertTrue( is_admin() );
		if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
			GS_Only_PDF_Preview::init();
			$this->assertSame( 10, has_filter( 'wp_image_editors', array( 'GS_Only_PDF_Preview', 'wp_image_editors' ) ) );
			$this->assertSame( 10, has_action( 'admin_init', array( 'GS_Only_PDF_Preview', 'admin_init' ) ) );
			$this->assertSame( 10, has_action( 'admin_menu', array( 'GS_Only_PDF_Preview', 'admin_menu' ) ) );
			$this->assertSame( 10, has_action( 'admin_enqueue_scripts', array( 'GS_Only_PDF_Preview', 'admin_enqueue_scripts' ) ) );

			$this->assertSame( 10, has_filter( 'bulk_actions-upload', array( 'GS_Only_PDF_Preview', 'bulk_actions_upload' ) ) );
			$this->assertSame( 10, has_filter( 'handle_bulk_actions-upload', array( 'GS_Only_PDF_Preview', 'handle_bulk_actions_upload' ) ) );
			$this->assertSame( 10, has_filter( 'removable_query_args', array( 'GS_Only_PDF_Preview', 'removable_query_args' ) ) );

			$this->assertSame( 10, has_action( 'current_screen', array( 'GS_Only_PDF_Preview', 'current_screen' ) ) );
			$this->assertSame( 100, has_action( 'media_row_actions', array( 'GS_Only_PDF_Preview', 'media_row_actions' ) ) );
			$this->assertSame( 10, has_action( 'wp_ajax_gopp_media_row_action', array( 'GS_Only_PDF_Preview', 'gopp_media_row_action' ) ) );
			$this->assertSame( 10, has_action( 'wp_ajax_gopp_poll_regen_pdf_previews', array( 'GS_Only_PDF_Preview', 'gopp_poll_regen_pdf_previews' ) ) );

			remove_filter( 'wp_image_editors', array( 'GS_Only_PDF_Preview', 'wp_image_editors' ) );
			remove_action( 'admin_init', array( 'GS_Only_PDF_Preview', 'admin_init' ) );
			remove_action( 'admin_menu', array( 'GS_Only_PDF_Preview', 'admin_menu' ) );
			remove_action( 'admin_enqueue_scripts', array( 'GS_Only_PDF_Preview', 'admin_enqueue_scripts' ) );

			remove_filter( 'bulk_actions-upload', array( 'GS_Only_PDF_Preview', 'bulk_actions_upload' ) );
			remove_filter( 'handle_bulk_actions-upload', array( 'GS_Only_PDF_Preview', 'handle_bulk_actions_upload' ) );
			remove_filter( 'removable_query_args', array( 'GS_Only_PDF_Preview', 'removable_query_args' ) );

			remove_action( 'current_screen', array( 'GS_Only_PDF_Preview', 'current_screen' ) );
			remove_action( 'media_row_actions', array( 'GS_Only_PDF_Preview', 'media_row_actions' ) );
			remove_action( 'wp_ajax_gopp_media_row_action', array( 'GS_Only_PDF_Preview', 'gopp_media_row_action' ) );
			remove_action( 'wp_ajax_gopp_poll_regen_pdf_previews', array( 'GS_Only_PDF_Preview', 'gopp_poll_regen_pdf_previews' ) );

			define( 'DOING_AJAX', true );
		}

		$this->assertTrue( DOING_AJAX );
		GS_Only_PDF_Preview::init();
		$this->assertSame( 10, has_action( 'media_send_to_editor', array( 'GS_Only_PDF_Preview', 'media_send_to_editor' ) ) );
		$this->assertSame( 10, has_action( 'wp_ajax_gopp_media_row_action', array( 'GS_Only_PDF_Preview', 'gopp_media_row_action' ) ) );
		$this->assertSame( 10, has_action( 'wp_ajax_gopp_poll_regen_pdf_previews', array( 'GS_Only_PDF_Preview', 'gopp_poll_regen_pdf_previews' ) ) );

		remove_action( 'media_send_to_editor', array( 'GS_Only_PDF_Preview', 'media_send_to_editor' ) );
		remove_action( 'wp_ajax_gopp_media_row_action', array( 'GS_Only_PDF_Preview', 'gopp_media_row_action' ) );
		remove_action( 'wp_ajax_gopp_poll_regen_pdf_previews', array( 'GS_Only_PDF_Preview', 'gopp_poll_regen_pdf_previews' ) );

		$pagenow = $old_pagenow;
	}

	/**
	 * Test image editors filter.
	 */
	function test_wp_image_editors() {
		$image_editors = array( 'blah' );
		$output = GS_Only_PDF_Preview::wp_image_editors( $image_editors );
		$this->assertContains( 'GOPP_Image_Editor_GS', $output );
		$image_editors = $output;
		$output = GS_Only_PDF_Preview::wp_image_editors( $image_editors );
		$this->assertContains( 'GOPP_Image_Editor_GS', $output );
		$this->assertSame( $output, $image_editors );
	}

	/**
	 * Test activation hook.
	 */
	function test_activation_hook() {
		if ( in_array( 'exec', array_map( 'trim', explode( ',', strtolower( ini_get( 'disable_functions' ) ) ) ), true ) ) {
			$this->markTestSkipped( 'exec() disabled.' );
		}

		global $wp_version;
		$old_wp_version = $wp_version;

		$wp_version = GOPP_PLUGIN_WP_UP_TO_VERSION;
		GS_Only_PDF_Preview::activation_hook();
		$admin_notices = get_transient( 'gopp_plugin_admin_notices' );
		if ( true !== self::$have_gs ) {
			$this->assertNotEmpty( $admin_notices );
		} else {
			$this->assertEmpty( $admin_notices );
		}

		$wp_version = '9999.9999.9999';
		GS_Only_PDF_Preview::activation_hook();
		$admin_notices = get_transient( 'gopp_plugin_admin_notices' );
		if ( true !== self::$have_gs ) {
			$this->assertSame( 2, count( $admin_notices ) );
			$this->assertSame( 2, count( $admin_notices[0] ) );
			$this->assertSame( 'warning', $admin_notices[0][0] );
			$this->assertTrue( false !== stripos( $admin_notices[0][1], 'version' ) );
			$this->assertSame( 2, count( $admin_notices[1] ) );
			$this->assertSame( 'warning', $admin_notices[1][0] );
			$this->assertTrue( false !== stripos( $admin_notices[1][1], 'no ghostscript' ) );
		} else {
			$this->assertSame( 1, count( $admin_notices ) );
			$this->assertSame( 2, count( $admin_notices[0] ) );
			$this->assertSame( 'warning', $admin_notices[0][0] );
			$this->assertTrue( false !== stripos( $admin_notices[0][1], 'version' ) );
		}

		ob_start();
		GS_Only_PDF_Preview::admin_notices();
		$output = ob_get_clean();
		$this->assertTrue( false !== stripos( $output, 'warning is-dismissible' ) );
		$this->assertTrue( false !== stripos( $output, 'version' ) );
		$this->assertEmpty( get_transient( 'gopp_plugin_admin_notices' ) );

		GS_Only_PDF_Preview::activation_hook();
		$this->assertNotEmpty( get_transient( 'gopp_plugin_admin_notices' ) );
		$this->assertFalse( defined( 'WP_UNINSTALL_PLUGIN' ) );
		define( 'WP_UNINSTALL_PLUGIN', dirname( dirname( __FILE__ ) ) . '/uninstall.php' );
		require_once WP_UNINSTALL_PLUGIN;
		$this->assertEmpty( get_transient( 'gopp_plugin_admin_notices' ) );

		$wp_version = '4.6';
		self::clear_func_args();
		try {
			GS_Only_PDF_Preview::activation_hook();
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$args = self::$func_args['wp_die'][0];
		$this->assertTrue( false !== stripos( $args['message'], 'activated' ) );

		$wp_version = $old_wp_version;
	}

	/**
	 * Test activation hook with exec() disabled.
	 */
	function test_activation_hook_exec_disabled() {
		if ( ! in_array( 'exec', array_map( 'trim', explode( ',', strtolower( ini_get( 'disable_functions' ) ) ) ), true ) ) {
			$this->markTestSkipped( 'exec() enabled.' );
		}

		self::clear_func_args();
		try {
			GS_Only_PDF_Preview::activation_hook();
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
	function test_admin_init() {
		$admin_notices_action = is_network_admin() ? 'network_admin_notices' : ( is_user_admin() ? 'user_admin_notices' : 'admin_notices' );

		GS_Only_PDF_Preview::admin_init();
		$this->assertSame( 10, has_filter( 'wp_image_editors', array( 'GS_Only_PDF_Preview', 'wp_image_editors' ) ) );
		$this->assertSame( 10, has_action( $admin_notices_action, array( 'GS_Only_PDF_Preview', 'admin_notices' ) ) );
	}

	/**
	 * Test admin notices.
	 */
	function test_admin_notices() {

		$admin_notices = array(
			array( 'error', 'Error' ),
			array( 'updated', 'Updated' ),
			array( 'warning', 'Warning' ),
			array( 'info', 'Info' ),
			array( 'notice', 'Notice' ),
		);

		set_transient( 'gopp_plugin_admin_notices', $admin_notices );

		ob_start();
		GS_Only_PDF_Preview::admin_notices();
		$output = ob_get_clean();
		$this->assertTrue( false !== strpos( $output, 'error is-dismissible' ) );
		$this->assertTrue( false !== strpos( $output, 'updated is-dismissible' ) );
		$this->assertTrue( false !== strpos( $output, 'warning is-dismissible' ) );
		$this->assertTrue( false !== strpos( $output, 'notice-info' ) );
		$this->assertTrue( false !== strpos( $output, 'notice-notice' ) );

		delete_transient( 'gopp_plugin_admin_notices' );

		$this->assertTrue( null === GS_Only_PDF_Preview::$hook_suffix );
		$current_screen = get_current_screen();
		$current_screen_id = $current_screen ? $current_screen->id : null;
		GS_Only_PDF_Preview::$hook_suffix = $current_screen_id;

		$_REQUEST['gopp_rpp'] = '200_102_98_99999.9';
		ob_start();
		GS_Only_PDF_Preview::admin_notices();
		$output = ob_get_clean();
		$this->assertTrue( false !== strpos( $output, 'error is-dismissible' ) );
		$this->assertTrue( false !== strpos( $output, 'updated is-dismissible' ) );
		$this->assertFalse( false !== strpos( $output, 'warning is-dismissible' ) );
		$this->assertTrue( false !== strpos( $output, 'notice-info' ) );
		$this->assertFalse( false !== strpos( $output, 'notice-notice' ) );
		$this->assertTrue( false !== strpos( $output, '102' ) );
		$this->assertTrue( false !== strpos( $output, '98' ) );
		$this->assertTrue( false !== strpos( $output, '99,999.9' ) );

		set_transient( 'gopp_plugin_admin_notices', array( array( 'warning', 'Warning' ) ) );
		ob_start();
		GS_Only_PDF_Preview::admin_notices();
		$output = ob_get_clean();
		$this->assertTrue( false !== strpos( $output, 'error is-dismissible' ) );
		$this->assertTrue( false !== strpos( $output, 'updated is-dismissible' ) );
		$this->assertTrue( false !== strpos( $output, 'warning is-dismissible' ) );
		$this->assertTrue( false !== strpos( $output, 'notice-info' ) );
		$this->assertFalse( false !== strpos( $output, 'notice-notice' ) );
		$this->assertTrue( false !== strpos( $output, '102' ) );
		$this->assertTrue( false !== strpos( $output, '98' ) );
		$this->assertTrue( false !== strpos( $output, '99,999.9' ) );

		delete_transient( 'gopp_plugin_admin_notices' );

		$_REQUEST['gopp_rpp'] = '0_1_0_0';
		ob_start();
		GS_Only_PDF_Preview::admin_notices();
		$output = ob_get_clean();
		$this->assertTrue( false !== strpos( $output, 'error is-dismissible' ) );
		$this->assertTrue( false !== stripos( $output, 'invalid arguments' ) );
		$this->assertFalse( false !== strpos( $output, 'updated is-dismissible' ) );
		$this->assertFalse( false !== strpos( $output, 'warning is-dismissible' ) );
		$this->assertTrue( false !== strpos( $output, 'notice-info' ) );
		$this->assertFalse( false !== strpos( $output, 'notice-notice' ) );

		$_REQUEST['gopp_rpp'] = '1_0_1_0';
		ob_start();
		GS_Only_PDF_Preview::admin_notices();
		$output = ob_get_clean();
		$this->assertTrue( false !== strpos( $output, 'error is-dismissible' ) );
		$this->assertFalse( false !== strpos( $output, 'updated is-dismissible' ) );
		$this->assertTrue( false !== strpos( $output, 'warning is-dismissible' ) );
		$this->assertTrue( false !== stripos( $output, 'nothing updated' ) );
		$this->assertTrue( false !== strpos( $output, 'notice-info' ) );
		$this->assertFalse( false !== strpos( $output, 'notice-notice' ) );

		$_REQUEST['gopp_rpp'] = '1_0_0_0';
		ob_start();
		GS_Only_PDF_Preview::admin_notices();
		$output = ob_get_clean();
		$this->assertFalse( false !== strpos( $output, 'error is-dismissible' ) );
		$this->assertFalse( false !== strpos( $output, 'updated is-dismissible' ) );
		$this->assertTrue( false !== strpos( $output, 'warning is-dismissible' ) );
		$this->assertTrue( false !== stripos( $output, 'nothing updateable' ) );
		$this->assertTrue( false !== strpos( $output, 'notice-info' ) );
		$this->assertFalse( false !== strpos( $output, 'notice-notice' ) );

		$_REQUEST['gopp_rpp'] = '0_0_0_0';
		ob_start();
		GS_Only_PDF_Preview::admin_notices();
		$output = ob_get_clean();
		$this->assertTrue( false !== strpos( $output, 'error is-dismissible' ) );
		$this->assertTrue( false !== stripos( $output, 'no pdfs' ) );
		$this->assertFalse( false !== strpos( $output, 'updated is-dismissible' ) );
		$this->assertFalse( false !== strpos( $output, 'warning is-dismissible' ) );
		$this->assertTrue( false !== strpos( $output, 'notice-info' ) );
		$this->assertFalse( false !== strpos( $output, 'notice-notice' ) );

		$_REQUEST['gopp_rpp'] = '4_1_1_0';
		ob_start();
		GS_Only_PDF_Preview::admin_notices();
		$output = ob_get_clean();
		$this->assertTrue( false !== strpos( $output, 'error is-dismissible' ) );
		$this->assertTrue( false !== strpos( $output, 'updated is-dismissible' ) );
		$this->assertTrue( false !== strpos( $output, 'warning is-dismissible' ) );
		$this->assertTrue( false !== stripos( $output, 'ignored' ) );
		$this->assertTrue( false !== strpos( $output, 'notice-info' ) );
		$this->assertFalse( false !== strpos( $output, 'notice-notice' ) );

		global $current_screen;
		$old_current_screen = $current_screen;
		$current_screen = new stdClass;
		$current_screen->id = 'upload';

		$_REQUEST['gopp_rpp'] = '0_0_0_0';
		ob_start();
		GS_Only_PDF_Preview::admin_notices();
		$output = ob_get_clean();
		$this->assertFalse( false !== strpos( $output, 'error is-dismissible' ) );
		$this->assertFalse( false !== strpos( $output, 'updated is-dismissible' ) );
		$this->assertFalse( false !== strpos( $output, 'warning is-dismissible' ) );
		$this->assertFalse( false !== strpos( $output, 'notice-info' ) );
		$this->assertFalse( false !== strpos( $output, 'notice-notice' ) );

		$current_screen = $old_current_screen;

		GS_Only_PDF_Preview::$hook_suffix = null;
	}

	/**
	 * Test admin menu.
	 */
	function test_admin_menu() {
		$this->assertTrue( null === GS_Only_PDF_Preview::$hook_suffix );

		GS_Only_PDF_Preview::admin_menu();
		$this->assertFalse( is_string( GS_Only_PDF_Preview::$hook_suffix ) );

		$out = wp_set_current_user( 1 ); // Need manage_options cap to add load-XXX

		GS_Only_PDF_Preview::admin_menu();
		$this->assertTrue( is_string( GS_Only_PDF_Preview::$hook_suffix ) );
		$this->assertSame( 10, has_action( 'load-' . GS_Only_PDF_Preview::$hook_suffix, array( 'GS_Only_PDF_Preview', 'load_regen_pdf_previews' ) ) );

		$out = wp_set_current_user( 0 );
	}

	/**
	 * Test load regen PDF previews.
	 */
	function test_load_regen_pdf_previews() {
		if ( ! defined( 'GOPP_TESTING' ) ) define( 'GOPP_TESTING', true );

		// No cap.
		self::clear_func_args();
		try {
			GS_Only_PDF_Preview::load_regen_pdf_previews();
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );

		$out = wp_set_current_user( 1 ); // Need manage_options cap to add load-XXX

		do_action( 'admin_init' );
		$this->assertSame( 10, has_filter( 'wp_image_editors', array( 'GS_Only_PDF_Preview', 'wp_image_editors' ) ) );
		GOPP_Image_Editor_GS::clear();

		// Do nothing.
		self::clear_func_args();
		try {
			GS_Only_PDF_Preview::load_regen_pdf_previews();
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
			GS_Only_PDF_Preview::load_regen_pdf_previews();
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$args = self::$func_args['wp_die'][0];
		$this->assertSame( 'wp_redirect', $args['title'] );
		$this->assertSame( 4, count( $args['args'] ) );
		$this->assertSame( 0, $args['args'][0] );
		$this->assertSame( 0, $args['args'][1] );
		$this->assertSame( 0, $args['args'][2] );

		$this->assertSame( 1, count( self::$func_args['wp_redirect'] ) );
		$this->assertNotEmpty( self::$func_args['wp_redirect'][0] );

		// Do with one pdf.
		$test_file = dirname( __FILE__ ) . '/images/test_alpha.pdf';
		$attachment_id = $this->factory->attachment->create_upload_object( $test_file );
		$this->assertNotEmpty( $attachment_id );

		self::clear_func_args();
		try {
			GS_Only_PDF_Preview::load_regen_pdf_previews();
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$args = self::$func_args['wp_die'][0];
		$this->assertSame( 'wp_redirect', $args['title'] );
		$this->assertSame( 4, count( $args['args'] ) );
		$this->assertSame( 1, $args['args'][0] );

		if ( false === self::$have_gs ) {
			$this->assertSame( 0, $args['args'][1] );
			$this->assertSame( 1, $args['args'][2] );
		} else {
			$this->assertSame( 1, $args['args'][1] );
			$this->assertSame( 0, $args['args'][2] );
		}

		$this->assertSame( 1, count( self::$func_args['wp_redirect'] ) );
		$this->assertNotEmpty( self::$func_args['wp_redirect'][0] );

		// With two pdfs.
		$test_file = dirname( __FILE__ ) . '/images/test_alpha.pdf';
		$attachment_id = $this->factory->attachment->create_upload_object( $test_file );
		$this->assertNotEmpty( $attachment_id );

		self::clear_func_args();
		try {
			GS_Only_PDF_Preview::load_regen_pdf_previews();
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$args = self::$func_args['wp_die'][0];
		$this->assertSame( 'wp_redirect', $args['title'] );
		$this->assertSame( 4, count( $args['args'] ) );
		$this->assertSame( 2, $args['args'][0] );

		if ( false === self::$have_gs ) {
			$this->assertSame( 2, $args['args'][0] );
			$this->assertSame( 0, $args['args'][1] );
		} else {
			$this->assertSame( 2, $args['args'][1] );
			$this->assertSame( 0, $args['args'][2] );
		}

		$this->assertSame( 1, count( self::$func_args['wp_redirect'] ) );
		$this->assertNotEmpty( self::$func_args['wp_redirect'][0] );

		// Fail.
		$file = get_attached_file( $attachment_id );
		unlink( $file );

		self::clear_func_args();
		try {
			GS_Only_PDF_Preview::load_regen_pdf_previews();
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$args = self::$func_args['wp_die'][0];
		$this->assertSame( 'wp_redirect', $args['title'] );
		$this->assertNotEmpty( $args['args'] );
		$this->assertSame( 4, count( $args['args'] ) );
		$this->assertSame( 2, $args['args'][0] );

		if ( false === self::$have_gs ) {
			$this->assertSame( 0, $args['args'][1] );
			$this->assertSame( 2, $args['args'][2] );
		} else {
			$this->assertSame( 1, $args['args'][1] );
			$this->assertSame( 1, $args['args'][2] );
		}

		$out = wp_set_current_user( 0 );
	}

	/**
	 * Test do regen PDF previews.
	 */
	function test_do_regen_pdf_previews() {
		// Bad ids.
		$output = GS_Only_PDF_Preview::do_regen_pdf_previews( array( 1234, 2345, 3456 ), false /*check_mime_type*/, false /*do_transient*/ );
		$this->assertSame( 4, count( $output ) );
		$this->assertSame( array( 3, 0, 3 ), array_slice( $output, 0, 3 ) );
		$this->assertEmpty( get_transient( 'gopp_plugin_poll_rpp' ) );

		// Bad ids check mime.
		$output = GS_Only_PDF_Preview::do_regen_pdf_previews( array( 1234, 2345, 3456 ), true /*check_mime_type*/, false /*do_transient*/ );
		$this->assertSame( 4, count( $output ) );
		$this->assertSame( array( 3, 0, 0 ), array_slice( $output, 0, 3 ) );

		// Bad ids transient.
		$output = GS_Only_PDF_Preview::do_regen_pdf_previews( array( 1234, 2345, 3456 ), false /*check_mime_type*/, true /*do_transient*/ );
		$this->assertSame( 4, count( $output ) );
		$this->assertSame( array( 3, 0, 3 ), array_slice( $output, 0, 3 ) );
		$this->assertEmpty( get_transient( 'gopp_plugin_poll_rpp' ) );

		// Add some PDFs and images.
		$ids = array();
		$test_file = dirname( __FILE__ ) . '/images/test_alpha.pdf';
		$attachment_id = $this->factory->attachment->create_upload_object( $test_file );
		$this->assertNotEmpty( $attachment_id );
		$ids[] = $attachment_id;
		$test_file = dirname( __FILE__ ) . '/images/test-image.jpg';
		$attachment_id = $this->factory->attachment->create_upload_object( $test_file );
		$this->assertNotEmpty( $attachment_id );
		$ids[] = $attachment_id;
		$test_file = dirname( __FILE__ ) . '/images/test_alpha.pdf';
		$attachment_id = $this->factory->attachment->create_upload_object( $test_file );
		$this->assertNotEmpty( $attachment_id );
		$ids[] = $attachment_id;
		$test_file = dirname( __FILE__ ) . '/images/test_alpha.pdf';
		$attachment_id = $this->factory->attachment->create_upload_object( $test_file );
		$this->assertNotEmpty( $attachment_id );
		$ids[] = $attachment_id;
		$test_file = dirname( __FILE__ ) . '/images/test-image.jpg';
		$attachment_id = $this->factory->attachment->create_upload_object( $test_file );
		$this->assertNotEmpty( $attachment_id );
		$ids[] = $attachment_id;

		// No mime check.
		$output = GS_Only_PDF_Preview::do_regen_pdf_previews( $ids, false /*check_mime_type*/, true /*do_transient*/ );
		$this->assertSame( 4, count( $output ) );
		if ( true !== self::$have_gs ) {
			$this->assertSame( array( count( $ids ), count( $ids ) - 3, 3 ), array_slice( $output, 0, 3 ) );
		} else {
			$this->assertSame( array( count( $ids ), count( $ids ), 0 ), array_slice( $output, 0, 3 ) );
		}
		$this->assertEmpty( get_transient( 'gopp_plugin_poll_rpp' ) );

		// Mime check.
		$output = GS_Only_PDF_Preview::do_regen_pdf_previews( $ids, true /*check_mime_type*/, true /*do_transient*/ );
		$this->assertSame( 4, count( $output ) );
		if ( true !== self::$have_gs ) {
			$this->assertSame( array( count( $ids ), 0, 3 ), array_slice( $output, 0, 3 ) );
		} else {
			$this->assertSame( array( count( $ids ), 3, 0 ), array_slice( $output, 0, 3 ) );
		}
		$this->assertEmpty( get_transient( 'gopp_plugin_poll_rpp' ) );

		unlink( get_attached_file( $ids[1] ) );
		unlink( get_attached_file( $ids[2] ) );

		// 2 bad no mime check
		$output = GS_Only_PDF_Preview::do_regen_pdf_previews( $ids, false /*check_mime_type*/, true /*do_transient*/ );
		$this->assertSame( 4, count( $output ) );
		if ( true !== self::$have_gs ) {
			$this->assertSame( array( count( $ids ), count( $ids ) - 4, 4 ), array_slice( $output, 0, 3 ) );
		} else {
			$this->assertSame( array( count( $ids ), count( $ids ) - 2, 2 ), array_slice( $output, 0, 3 ) );
		}
		$this->assertEmpty( get_transient( 'gopp_plugin_poll_rpp' ) );

		// 2 bad mime check
		$output = GS_Only_PDF_Preview::do_regen_pdf_previews( $ids, true /*check_mime_type*/, true /*do_transient*/ );
		$this->assertSame( 4, count( $output ) );
		if ( true !== self::$have_gs ) {
			$this->assertSame( array( count( $ids ), 0, 3 ), array_slice( $output, 0, 3 ) );
		} else {
			$this->assertSame( array( count( $ids ), 2, 1 ), array_slice( $output, 0, 3 ) );
		}
		$this->assertEmpty( get_transient( 'gopp_plugin_poll_rpp' ) );

		// metadata fail.
	}

	/**
	 * Test regen PDF previews.
	 */
	function test_regen_pdf_previews() {
		// No cap.
		self::clear_func_args();
		try {
			GS_Only_PDF_Preview::regen_pdf_previews();
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );

		$out = wp_set_current_user( 1 ); // Need manage_options cap.

		do_action( 'admin_init' );
		$this->assertSame( 10, has_filter( 'wp_image_editors', array( 'GS_Only_PDF_Preview', 'wp_image_editors' ) ) );
		GOPP_Image_Editor_GS::clear();

		// No pdfs.
		ob_start();
		GS_Only_PDF_Preview::regen_pdf_previews();
		$out = ob_get_clean();
		$this->assertTrue( false !== stripos( $out, 'nothing' ) );
		$this->assertTrue( false === stripos( $out, 'gopp_regen_pdf_previews_form' ) );
		$this->assertSame( force_balance_tags( $out ), $out );

		// Do with one pdf.
		$test_file = dirname( __FILE__ ) . '/images/test_alpha.pdf';
		$attachment_id = $this->factory->attachment->create_upload_object( $test_file );
		$this->assertNotEmpty( $attachment_id );

		$old_request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : null;
		$_SERVER['REQUEST_URI'] = 'http://example.org/wp-admin/tools.php?page=' . GOPP_REGEN_PDF_PREVIEWS_SLUG;

		ob_start();
		GS_Only_PDF_Preview::regen_pdf_previews();
		$out = ob_get_clean();
		$this->assertTrue( false !== stripos( $out, '<strong>1</strong> PDF' ) );
		$this->assertTrue( false !== stripos( $out, 'gopp_regen_pdf_previews_form' ) );
		$this->assertSame( force_balance_tags( $out ), $out );

		// Do with 11 pdfs.
		for ( $i = 0; $i < 10; $i++ ) {
			$attachment_id = $this->factory->attachment->create_upload_object( $test_file );
			$this->assertNotEmpty( $attachment_id );
		}

		ob_start();
		GS_Only_PDF_Preview::regen_pdf_previews();
		$out = ob_get_clean();
		$this->assertTrue( false !== stripos( $out, '<strong>11</strong> PDF' ) );
		$this->assertTrue( false !== stripos( $out, 'gopp_regen_pdf_previews_form' ) );
		$this->assertTrue( false !== stripos( $out, 'can take a long time' ) );
		$this->assertSame( force_balance_tags( $out ), $out );

		$out = wp_set_current_user( 0 );

		if ( null !== $old_request_uri ) {
			$_SERVER['REQUEST_URI'] = $old_request_uri;
		}
	}

	/**
	 * Test admin enqueue.
	 */
	function test_admin_enqueue_scripts() {

		global $wp_scripts;
		$old_wp_scripts = $wp_scripts;

		$wp_scripts = null;
		GS_Only_PDF_Preview::admin_enqueue_scripts( 'blah' );
		ob_start();
		wp_scripts()->do_items();
		$out = ob_get_clean();
		$this->assertEmpty( $out );

		$wp_scripts = null;
		GS_Only_PDF_Preview::admin_enqueue_scripts( GS_Only_PDF_Preview::$hook_suffix );
		ob_start();
		wp_scripts()->do_items();
		$out = ob_get_clean();
		$this->assertTrue( false !== stripos( $out, 'jquery-migrate' ) );
		$this->assertTrue( false !== stripos( $out, 'gs-only-pdf-preview' ) );
		$this->assertTrue( false !== stripos( $out, 'gopp_plugin_params' ) );
		$this->assertTrue( false !== stripos( $out, 'please_wait_msg' ) );

		$wp_scripts = null;
		GS_Only_PDF_Preview::admin_enqueue_scripts( 'upload.php' );
		ob_start();
		wp_scripts()->do_items();
		$out = ob_get_clean();
		$this->assertTrue( false !== stripos( $out, 'jquery-migrate' ) );
		$this->assertTrue( false !== stripos( $out, 'gs-only-pdf-preview' ) );
		$this->assertTrue( false !== stripos( $out, 'gopp_plugin_params' ) );
		$this->assertTrue( false !== stripos( $out, 'no_items_selected_msg' ) );

		$wp_scripts = null;
		GS_Only_PDF_Preview::admin_enqueue_scripts( 'post.php' );
		ob_start();
		wp_scripts()->do_items();
		$out = ob_get_clean();
		$this->assertTrue( false !== stripos( $out, 'jquery-migrate' ) );
		$this->assertTrue( false !== stripos( $out, 'gs-only-pdf-preview' ) );
		$this->assertTrue( false !== stripos( $out, 'gopp_plugin_params' ) );
		$this->assertTrue( false !== stripos( $out, 'document_link_only' ) );

		$old_wp_scripts = $wp_scripts;
	}

	/**
	 * Test spinner.
	 */
	function test_spinner() {
		$output = GS_Only_PDF_Preview::spinner( 4, true );
		$this->assertFalse( false !== stripos( $output, '<img' ) );
		$this->assertTrue( false !== stripos( $output, 'margin-top:4px' ) );
		$this->assertTrue( false !== stripos( $output, 'is-active' ) );

		$output = GS_Only_PDF_Preview::spinner( -2, false );
		$this->assertFalse( false !== stripos( $output, '<img' ) );
		$this->assertTrue( false !== stripos( $output, 'margin-top:-2px' ) );
		$this->assertFalse( false !== stripos( $output, 'is-active' ) );

		$http_user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : null;	

		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.79 Safari/537.36 Edge/14.14393';
		$output = GS_Only_PDF_Preview::spinner( 0, true );
		$this->assertTrue( false !== stripos( $output, '<img' ) );
		$this->assertTrue( false !== stripos( $output, 'margin-top:0px' ) );
		$this->assertTrue( false !== stripos( $output, 'is-active' ) );

		$output = GS_Only_PDF_Preview::spinner( 0, false );
		$this->assertTrue( false !== stripos( $output, '<img' ) );
		$this->assertTrue( false !== stripos( $output, 'margin-top:0px' ) );
		$this->assertFalse( false !== stripos( $output, 'is-active' ) );

		$_SERVER['HTTP_USER_AGENT'] = $http_user_agent;	
	}

	/**
	 * Test media bulk actions upload filter.
	 */
	function test_bulk_actions_upload() {

		// Succeed.
		$out = wp_set_current_user( 1 ); // Need manage_options cap.
		$actions = array( 'trash' => 'Trash' );
		$output = GS_Only_PDF_Preview::bulk_actions_upload( $actions );
		$this->assertArrayHasKey( 'gopp_regen_pdf_previews', $output );

		// Fail.
		$out = wp_set_current_user( 0 ); // Need manage_options cap.
		$output = GS_Only_PDF_Preview::bulk_actions_upload( $actions );
		$this->assertArrayNotHasKey( 'gopp_regen_pdf_previews', $output );
	}

	/**
	 * Test media bulk actions upload filter.
	 */
	function test_handle_bulk_actions_upload() {

		$out = wp_set_current_user( 1 ); // Need manage_options cap.
		$location = 'blah';
		$doaction = 'gopp_regen_pdf_previews';
		$post_ids = array( 1 );
		$output = GS_Only_PDF_Preview::handle_bulk_actions_upload( $location, $doaction, $post_ids );
		$this->assertTrue( false !== stripos( $output, 'gopp_rpp' ) );

		// Fail.
		$out = wp_set_current_user( 0 ); // Need manage_options cap.
		$output = GS_Only_PDF_Preview::handle_bulk_actions_upload( $location, $doaction, $post_ids );
		$this->assertSame( $location, $output );
		$output = GS_Only_PDF_Preview::handle_bulk_actions_upload( $location, 'something', $post_ids );
		$this->assertSame( $location, $output );
	}

	/**
	 * Test media removable query args filter.
	 */
	function test_removable_query_args() {

		$removable_query_args = array( 'wow' );
		$output = GS_Only_PDF_Preview::removable_query_args( $removable_query_args );
		$this->assertContains( 'gopp_rpp', $output );
	}

	/**
	 * Test media removable query args filter.
	 */
	function test_current_screen() {

		$dummy_screen = new stdClass;
		$dummy_screen->id = 'admin_page_gopp-regen-pdf-previews';

		$old_request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : null;

		$_SERVER['REQUEST_URI'] = 'http://example.org/wp-admin/tools.php?page=' . GOPP_REGEN_PDF_PREVIEWS_SLUG . '&gopp_rpp=1_1_0_0.2';
		GS_Only_PDF_Preview::current_screen( $dummy_screen );
		$this->assertTrue( false === stripos( $_SERVER['REQUEST_URI'], 'gopp_rpp' ) );

		// Fail.
		$_SERVER['REQUEST_URI'] = 'http://example.org/wp-admin/tools.php?page=' . GOPP_REGEN_PDF_PREVIEWS_SLUG . '&gopp_rpp=1_1_0_0.2';
		$dummy_screen->id = 'blah';
		GS_Only_PDF_Preview::current_screen( $dummy_screen );
		$this->assertTrue( false !== stripos( $_SERVER['REQUEST_URI'], 'gopp_rpp' ) );

		if ( null !== $old_request_uri ) {
			$_SERVER['REQUEST_URI'] = $old_request_uri;
		}
	}

	/**
	 * Test media row actions.
	 */
	function test_media_row_actions() {
		$out = wp_set_current_user( 1 ); // Need manage_options cap.

		$actions = array();
		$post = new stdClass;
		$post->ID = 1234;
		$post->post_mime_type = 'blah';
		$detached = null;

		$out = GS_Only_PDF_Preview::media_row_actions( $actions, $post, $detached );
		$this->assertEmpty( $out );

		$post->post_mime_type = 'application/pdf';

		$out = GS_Only_PDF_Preview::media_row_actions( $actions, $post, $detached );
		if ( true !== self::$have_gs ) {
			$this->assertEmpty( $out );
		} else {
			$this->assertNotEmpty( $out );
			$this->assertTrue( isset( $out['gopp_regen_pdf_preview'] ) );
			$this->assertTrue( false !== stripos( $out['gopp_regen_pdf_preview'], '1234' ) );
		}

		$out = wp_set_current_user( 0 );
	}

	/**
	 * Test row action ajax callback.
	 */
	function test_gopp_media_row_action() {
		if ( ! defined( 'GOPP_TESTING' ) ) define( 'GOPP_TESTING', true );

		$out = GS_Only_PDF_Preview::gopp_media_row_action();
		$this->assertTrue( false !== stripos( $out, 'allowed' ) );

		$out = wp_set_current_user( 1 ); // Need manage_options cap.

		do_action( 'admin_init' );
		$this->assertSame( 10, has_filter( 'wp_image_editors', array( 'GS_Only_PDF_Preview', 'wp_image_editors' ) ) );
		GOPP_Image_Editor_GS::clear();

		$out = GS_Only_PDF_Preview::gopp_media_row_action();
		$this->assertTrue( false !== stripos( $out, 'invalid nonce' ) );

		// Bad id.
		$id = '0';
		$_POST = array();
		$_POST['id'] = $id;
		$_POST['nonce'] = wp_create_nonce( 'gopp_media_row_action_' . $id );
		$_REQUEST = $_POST;
		$this->assertTrue( 1 === wp_verify_nonce( $_POST['nonce'], 'gopp_media_row_action_' . $id ) );
		$out = GS_Only_PDF_Preview::gopp_media_row_action();
		$this->assertTrue( false !== stripos( $out, 'invalid id' ) );

		// Non-existent id.
		$id = '999999';
		$_POST['id'] = $id;
		$_POST['nonce'] = wp_create_nonce( 'gopp_media_row_action_' . $id );
		$_REQUEST = $_POST;
		$this->assertTrue( 1 === wp_verify_nonce( $_POST['nonce'], 'gopp_media_row_action_' . $id ) );
		$out = GS_Only_PDF_Preview::gopp_media_row_action();
		$this->assertTrue( false !== stripos( $out, 'invalid id' ) );

		// Success.
		$test_file = dirname( __FILE__ ) . '/images/test_alpha.pdf';
		$id = $this->factory->attachment->create_upload_object( $test_file );
		$this->assertNotEmpty( $id );

		$_POST['id'] = (string) $id;
		$_POST['nonce'] = wp_create_nonce( 'gopp_media_row_action_' . $id );
		$_REQUEST = $_POST;
		$this->assertTrue( 1 === wp_verify_nonce( $_POST['nonce'], 'gopp_media_row_action_' . $id ) );
		$out = GS_Only_PDF_Preview::gopp_media_row_action();
		if ( true !== self::$have_gs ) {
			$this->assertTrue( false !== stripos( $out, 'error' ) );
		} else {
			$this->assertTrue( false !== stripos( $out, 'success' ) );
		}

		// Fail.
		$file = get_attached_file( $id );
		unlink( $file );
		$out = GS_Only_PDF_Preview::gopp_media_row_action();
		$this->assertTrue( false !== stripos( $out, 'failed' ) );

		// Non-PDF.
		$test_file = dirname( __FILE__ ) . '/images/test-image.jpg';
		$id = $this->factory->attachment->create_upload_object( $test_file );
		$this->assertNotEmpty( $id );

		$_POST['id'] = (string) $id;
		$_POST['nonce'] = wp_create_nonce( 'gopp_media_row_action_' . $id );
		$_REQUEST = $_POST;
		$this->assertTrue( 1 === wp_verify_nonce( $_POST['nonce'], 'gopp_media_row_action_' . $id ) );
		$out = GS_Only_PDF_Preview::gopp_media_row_action();
		$this->assertTrue( false !== stripos( $out, 'invalid id' ) );

		$out = wp_set_current_user( 0 );
	}

	/**
	 * Test progress polling ajax callback.
	 */
	function test_gopp_poll_regen_pdf_previews() {
		if ( ! defined( 'GOPP_TESTING' ) ) define( 'GOPP_TESTING', true );

		$out = GS_Only_PDF_Preview::gopp_poll_regen_pdf_previews();
		$this->assertSame( '{"msg":""}', $out );

		$out = wp_set_current_user( 1 ); // Need manage_options cap.

		// No nonce.
		add_filter( 'wp_doing_ajax', '__return_true' );
		self::clear_func_args();
		try {
			GS_Only_PDF_Preview::gopp_poll_regen_pdf_previews();
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$this->assertSame( 1, count( self::$func_args['wp_die'] ) );
		$args = self::$func_args['wp_die'][0];
		$this->assertSame( 403, $args['args']['response'] );
		remove_filter( 'wp_doing_ajax', '__return_true' );

		// No transient.
		$cnt = 1;
		$_POST['cnt'] = (string) $cnt;
		$_POST['poll_nonce'] = wp_create_nonce( 'gopp_poll_regen_pdf_previews_' . $cnt );
		$_REQUEST = $_POST;
		$out = GS_Only_PDF_Preview::gopp_poll_regen_pdf_previews();
		$this->assertSame( '{"msg":""}', $out );

		// Transient bad check_cnt.
		set_transient( 'gopp_plugin_poll_rpp', array( 2, 1 ) );
		$out = GS_Only_PDF_Preview::gopp_poll_regen_pdf_previews();
		$this->assertSame( '{"msg":""}', $out );

		// Success.
		set_transient( 'gopp_plugin_poll_rpp', array( 1, 0 ) );
		$out = GS_Only_PDF_Preview::gopp_poll_regen_pdf_previews();
		$this->assertSame( '{"msg":"100% (1)"}', $out );
	}

	/**
	 * Test media_send_to_editor.
	 */
	function test_media_send_to_editor() {
		$html = 'asdf';
		$id = 1;
		$attachment = array();

		$output = GS_Only_PDF_Preview::media_send_to_editor( $html, $id, $attachment );
		$this->assertSame( $html, $output );

		$attachment['image-size'] = 'thumbnail';
		$output = GS_Only_PDF_Preview::media_send_to_editor( $html, $id, $attachment );
		$this->assertSame( $html, $output );

		$html = '<img>';
		$attachment['post_title'] = 'Title';
		$output = GS_Only_PDF_Preview::media_send_to_editor( $html, $id, $attachment );
		$this->assertSame( $html, $output );

		$html = 'blah';
		$test_file = dirname( __FILE__ ) . '/images/test_alpha.pdf';
		$id = $this->factory->attachment->create_upload_object( $test_file );
		$attachment['url'] = wp_get_attachment_url( $id );

		$attachment['image-size'] = 'thumbnail';
		$output = GS_Only_PDF_Preview::media_send_to_editor( $html, $id, $attachment );
		$this->assertTrue( false !== strpos( $output, '<a href="' . $attachment['url'] . '"' ) );
		$meta = get_metadata( 'post', $id, '_wp_attachment_metadata' );
		if ( true !== self::$have_gs ) {
			$this->assertEmpty( $meta );
		} else {
			$this->assertNotEmpty( $meta );
			$this->assertNotEmpty( $meta[0]['sizes']['thumbnail']['file'] );
			$this->assertTrue( 1 === preg_match( '/<img srcset="" src="[^"]+' . preg_quote( $meta[0]['sizes']['thumbnail']['file'] ) . '"/', $output ) );
		}
	}
}

require_once dirname( __FILE__ ) . '/test-gopp-image-editor-gs.php';
