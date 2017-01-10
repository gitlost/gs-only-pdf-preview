<?php

global $wp_version;
error_log( "wp_version=$wp_version" );

/**
 * Test the WP_Image_Editor_GS class
 * @group image
 * @group media
 * @group wp-image-editor-gs
 */
//require_once( dirname( __FILE__ ) . '/base.php' );

//class Tests_GOPP_Image_Editor_GS extends WP_Image_UnitTestCase {
class Tests_GOPP_Image_Editor_GS extends WP_UnitTestCase {

	//public $editor_engine = 'WP_Image_Editor_GS';

	public function setUp() {
		//require_once( ABSPATH . WPINC . '/class-wp-image-editor-gs.php' );
		require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
		require_once dirname( dirname( __FILE__ ) ) . '/includes/class-gopp-image-editor-gs.php';

		parent::setUp();
	}

	public function tearDown() {
		$this->remove_added_uploads();

		parent::tearDown();
	}

	/**
	 * Test test().
	 */
	public function test_test() {
		GOPP_Image_Editor_GS::clear();
		$output = GOPP_Image_Editor_GS::test();
		$this->assertTrue( $output );

		// Succeed shortcircuit.
		add_filter( 'gopp_image_have_gs', array( $this, 'filter_gopp_image_have_gs_true' ) );
		GOPP_Image_Editor_GS::clear();
		$output = GOPP_Image_Editor_GS::test();
		$this->assertTrue( $output );
		remove_filter( 'gopp_image_have_gs', array( $this, 'filter_gopp_image_have_gs_true' ) );

		// Fail shortcircuit.
		add_filter( 'gopp_image_have_gs', array( $this, 'filter_gopp_image_have_gs_false' ) );
		GOPP_Image_Editor_GS::clear();
		$output = GOPP_Image_Editor_GS::test();
		$this->assertFalse( $output );
		remove_filter( 'gopp_image_have_gs', array( $this, 'filter_gopp_image_have_gs_false' ) );

		// Succeed transient.
		GOPP_Image_Editor_GS::clear();
		set_transient( 'gopp_image_have_gs', 1, 100 );
		$output = GOPP_Image_Editor_GS::test();
		$this->assertTrue( $output );

		// Fail exec return.
		add_filter( 'gopp_image_gs_cmd_path', array( $this, 'filter_gopp_image_gs_cmd_path_nonexistent' ) );
		GOPP_Image_Editor_GS::clear();
		$output = GOPP_Image_Editor_GS::test();
		$this->assertFalse( $output );
		remove_filter( 'gopp_image_gs_cmd_path', array( $this, 'filter_gopp_image_gs_cmd_path_nonexistent' ) );

		// Fail exec output.
		add_filter( 'gopp_image_gs_cmd_path', array( $this, 'filter_gopp_image_gs_cmd_path_not_gs' ) );
		GOPP_Image_Editor_GS::clear();
		$output = GOPP_Image_Editor_GS::test();
		$this->assertFalse( $output );
		remove_filter( 'gopp_image_gs_cmd_path', array( $this, 'filter_gopp_image_gs_cmd_path_not_gs' ) );

		// Unsupported methods.
		GOPP_Image_Editor_GS::clear();
		$output = GOPP_Image_Editor_GS::test( array( 'methods' => array( 'resize' ) ) );
		$this->assertFalse( $output );
	}

	function filter_gopp_image_have_gs_true( $gs_cmd_path ) {
		return true;
	}

	function filter_gopp_image_have_gs_false( $gs_cmd_path ) {
		return false;
	}

	function filter_gopp_image_gs_cmd_path_nonexistent( $gs_cmd_path ) {
		return 'gs_nonexistent';
	}

	function filter_gopp_image_gs_cmd_path_not_gs( $gs_cmd_path ) {
		return 'echo';
	}

	/**
	 * Test test_load().
	 */
	public function test_load() {
		// Success.
		$image_editor = new GOPP_Image_Editor_GS( dirname( __FILE__ ) . '/images/wordpress-gsoc-flyer.pdf' );
		$output = $image_editor->load();
		$this->assertTrue( $output );

		// Not PDF.
		$image_editor = new GOPP_Image_Editor_GS( dirname( __FILE__ ) . '/images/test-image.jpg' );
		$output = $image_editor->load();
		$this->assertInstanceOf( 'WP_Error', $output );

		// Bad resolution.
		add_filter( 'gopp_editor_set_resolution', array( $this, 'filter_gopp_editor_set_resolution' ) );
		$this->resolution = 'x100';
		$image_editor = new GOPP_Image_Editor_GS( dirname( __FILE__ ) . '/images/wordpress-gsoc-flyer.pdf' );
		$output = $image_editor->load();
		$this->assertInstanceOf( 'WP_Error', $output );
		remove_filter( 'gopp_editor_set_resolution', array( $this, 'filter_gopp_editor_set_resolution' ) );

		// Bad page.
		add_filter( 'gopp_editor_set_page', array( $this, 'filter_gopp_editor_set_page' ) );
		$this->page = -2;
		$image_editor = new GOPP_Image_Editor_GS( dirname( __FILE__ ) . '/images/wordpress-gsoc-flyer.pdf' );
		$output = $image_editor->load();
		$this->assertInstanceOf( 'WP_Error', $output );
		remove_filter( 'gopp_editor_set_page', array( $this, 'filter_gopp_editor_set_page' ) );
	}

	protected $resolution = '100x100';

	function filter_gopp_editor_set_resolution( $resolution ) {
		return $this->resolution;
	}

	protected $page = 2;

	function filter_gopp_editor_set_page( $page ) {
		return $this->page;
	}

	/**
	 * Test test_save().
	 */
	public function test_save() {
		// Fail destination file.
		$image_editor = new GOPP_Image_Editor_GS( dirname( __FILE__ ) . '/images/wordpress-gsoc-flyer.pdf' );
		$output = $image_editor->load();
		$this->assertTrue( $output );
		$output = $image_editor->save( 'non_existing_dir/donk.jpg' );
		$this->assertInstanceOf( 'WP_Error', $output );

		// Fail mime_type.
		$image_editor = new GOPP_Image_Editor_GS( dirname( __FILE__ ) . '/images/wordpress-gsoc-flyer.pdf' );
		$output = $image_editor->load();
		$this->assertTrue( $output );
		$output = $image_editor->save( '/tmp/test_save-gs-fail.jpg', 'application/pdf' );
		$this->assertInstanceOf( 'WP_Error', $output );

		// Success filename given.
		$test_filename = '/tmp/test_save-gs.jpg';
		$image_editor = new GOPP_Image_Editor_GS( dirname( __FILE__ ) . '/images/wordpress-gsoc-flyer.pdf' );
		$output = $image_editor->load();
		$this->assertTrue( $output );
		$output = $image_editor->save( $test_filename, 'image/jpeg' );
		$this->assertNotEmpty( $output );
		$this->assertSame( $test_filename, $output['path'] );
		$this->assertTrue( is_file( $output['path'] ) );
		$this->assertNotEmpty( $output['file'] );
		$this->assertNotEmpty( $output['width'] );
		$this->assertNotEmpty( $output['height'] );
		$this->assertSame( 'image/jpeg', $output['mime-type'] );
		unlink( $test_filename );

		// Success no filename given.
		$test_filename = null;
		$image_editor = new GOPP_Image_Editor_GS( dirname( __FILE__ ) . '/images/wordpress-gsoc-flyer.pdf' );
		$output = $image_editor->load();
		$this->assertTrue( $output );
		$output = $image_editor->save( $test_filename, 'image/jpeg' );
		$this->assertNotEmpty( $output );
		$this->assertNotEmpty( $output['path'] );
		$this->assertTrue( is_file( $output['path'] ) );
		$this->assertNotEmpty( $output['file'] );
		$this->assertNotEmpty( $output['width'] );
		$this->assertNotEmpty( $output['height'] );
		$this->assertSame( 'image/jpeg', $output['mime-type'] );
		unlink( $output['path'] );
	}

	/**
	 * Test gs_valid().
	 * @dataProvider data_gs_valid
	 */
	public function test_gs_valid( $path, $no_read_check, $expected ) {
		Test_GOPP_Image_Editor_GS::clear();
		$output = Test_GOPP_Image_Editor_GS::public_gs_valid( $path, $no_read_check );
		$this->assertSame( $expected, $output );
	}

	public function data_gs_valid() {
		return array(
			array( 'non_existent', false, 'File doesn&#8217;t exist?' ), // Non-existent.
			array( 'non_existent', true, true ), // Non-existent.
			array( 'http://external', false, 'Loading from URL not supported.' ), // Non-local.
			array( '@args_file', false, 'Unsupported file name.' ), // GhostScript argument file.
			array( 'space filename', false, 'Unsupported file name.' ), // File name containing space.
			array( 'quote\'filename', false, 'Unsupported file name.' ), // File name containing single quote.
			array( 'double_quote"filename', false, 'Unsupported file name.' ), // File name containing double quote.
			array( dirname( __FILE__ ) . '/images/test-image.jpg', false, 'File is not a PDF.' ), // Not a PDF.
			array( dirname( __FILE__ ) . '/images/test-image.jpg', true, true ), // Not a PDF.
			array( dirname( __FILE__ ) . '/images/wordpress-gsoc-flyer.pdf', false, true ), // Success.
		);
	}

	/**
	 * Test gs_cmd_nix().
	 */
	public function test_gs_cmd_nix() {
		$is_win = 0 === strncasecmp( 'WIN', PHP_OS, 3 );
		if ( $is_win ) {
			$this->markTestSkipped( 'Unix only test.' );
		}

		GOPP_Image_Editor_GS::clear();
		$output = GOPP_Image_Editor_GS::test();
		$this->assertTrue( $output );
	}

	/**
	 * Test gs_cmd_win() on *nix.
	 */
	public function test_gs_cmd_win_on_nix() {
		$is_win = 0 === strncasecmp( 'WIN', PHP_OS, 3 );
		if ( $is_win ) {
			$this->markTestSkipped( 'Unix only test.' );
		}

		Test_GOPP_Image_Editor_GS::clear();
		Test_GOPP_Image_Editor_GS::public_set_is_win( true );

		$image_editor = new Test_GOPP_Image_Editor_GS( null );
		$args = $image_editor->public_get_gs_args( 'dummy.pdf' );

		Test_GOPP_Image_Editor_GS::clear();
		Test_GOPP_Image_Editor_GS::public_set_is_win( true );
		$output = Test_GOPP_Image_Editor_GS::public_gs_cmd( $args );
		$this->assertTrue( 0 === strpos( $output, '"gswin64c.exe"' ) );

		// Transient exists and is executable.
		$expected = exec( 'which gs' ); // Need something executable.
		Test_GOPP_Image_Editor_GS::clear();
		Test_GOPP_Image_Editor_GS::public_set_is_win( true );
		set_transient( 'gopp_image_gs_cmd_win', $expected, 100 );
		$output = Test_GOPP_Image_Editor_GS::public_gs_cmd( $args );
		$this->assertTrue( 0 === strpos( $output, '"' . $expected . '"' ) );

		// Transient exists and isn't executable.
		Test_GOPP_Image_Editor_GS::clear();
		Test_GOPP_Image_Editor_GS::public_set_is_win( true );
		set_transient( 'gopp_image_gs_cmd_win', 'gs_nonexistent', 100 );
		$output = Test_GOPP_Image_Editor_GS::public_gs_cmd( $args );
		$this->assertTrue( 0 === strpos( $output, '"gswin64c.exe"' ) );

		// Short circuit.
		add_filter( 'gopp_image_gs_cmd_path', array( $this, 'filter_gopp_image_gs_cmd_path_win' ) );
		Test_GOPP_Image_Editor_GS::clear();
		Test_GOPP_Image_Editor_GS::public_set_is_win( true );
		$output = Test_GOPP_Image_Editor_GS::public_gs_cmd( $args );
		$this->assertTrue( 0 === strpos( $output, '"spaced dir\\prog.exe"' ) );
		remove_filter( 'gopp_image_gs_cmd_path', array( $this, 'filter_gopp_image_gs_cmd_path_win' ) );

		// Uses fake entries in tests directory.
		$old_server = $_SERVER;
		$dirname = dirname( __FILE__ );
		$_SERVER['ProgramW6432'] = $dirname . '/Program Files';
		$_SERVER['ProgramFiles'] = $dirname . '/Program Files';
		$_SERVER['ProgramFiles(x86)'] = $dirname . '/Program Files (x86)';
		$expected = $_SERVER['ProgramW6432'] . '\\gs\\gs9.20\\bin\\gswin64c.exe';
		Test_GOPP_Image_Editor_GS::clear();
		Test_GOPP_Image_Editor_GS::public_set_is_win( true );
		$output = Test_GOPP_Image_Editor_GS::public_gs_cmd( $args );
		$this->assertTrue( 0 === strpos( $output, '"' . $expected . '"' ) );
		$_SERVER = $old_server;

		// Uses REG bash shell fake in tests directory.
		$old_path = getenv( 'PATH' );
		putenv( 'PATH=' . $dirname . ':' . $old_path );
		$expected = '\\gs\\gs9.20\\bin\\gswin64c.exe"';
		Test_GOPP_Image_Editor_GS::clear();
		Test_GOPP_Image_Editor_GS::public_set_is_win( true );
		$output = Test_GOPP_Image_Editor_GS::public_gs_cmd( $args );
		putenv( 'PATH=' . $old_path );
		$this->assertTrue( false !== strpos( $output, $expected ) );

		Test_GOPP_Image_Editor_GS::clear();
	}

	public function filter_gopp_image_gs_cmd_path_win( $gs_cmd_path ) {
		return 'spaced dir\\prog.exe';
	}

	/**
	 * Test escapeshellarg().
	 * @dataProvider data_escapeshellarg
	 */
	public function test_escapeshellarg( $input, $expected_nix, $expected_win ) {
		$is_win = 0 === strncasecmp( 'WIN', PHP_OS, 3 );

		Test_GOPP_Image_Editor_GS::clear();

		Test_GOPP_Image_Editor_GS::public_set_is_win( false );
		$output = Test_GOPP_Image_Editor_GS::public_escapeshellarg( $input );
		$this->assertSame( $expected_nix, $output );
		if ( ! $is_win ) {
			$this->assertSame( escapeshellarg( $input ), $output );
		}

		Test_GOPP_Image_Editor_GS::public_set_is_win( true );
		$output = Test_GOPP_Image_Editor_GS::public_escapeshellarg( $input );
		$this->assertSame( $expected_win, $output );
		if ( $is_win ) {
			$this->assertSame( escapeshellarg( $input ), $output );
		}

		Test_GOPP_Image_Editor_GS::clear();
	}

	public function data_escapeshellarg() {
		return array(
			array( 'quote\'filename', '\'quote\'\\\'\'filename\'', '"quote\'filename"' ),
			array( 'double_quote"filename', '\'double_quote"filename\'', '"double_quote filename"' ),
			array( 'space filename', '\'space filename\'', '"space filename"' ),
			array( 'percent%filename', '\'percent%filename\'', '"percent filename"' ),
			array( 'bang!filename', '\'bang!filename\'', '"bang filename"' ),
			array( '\'" %!"\'', '\'\'\\\'\'" %!"\'\\\'\'\'', '"\'     \'"' ),
		);
	}

	/*
	 * Test resolution.
	 */
	public function test_resolution() {
		$gs = new GOPP_Image_Editor_GS( null );
		$this->assertSame( '128x128', $gs->get_resolution() );

		$resolution = '999x99';
		$this->assertTrue( $gs->set_resolution( $resolution ) );
		$this->assertSame( $resolution, $gs->get_resolution() );
		$resolution = '72x72';
		$this->assertTrue( $gs->set_resolution( $resolution ) );
		$this->assertSame( $resolution, $gs->get_resolution() );
		$resolution = '1x1'; // Min.
		$this->assertTrue( $gs->set_resolution( $resolution ) );
		$this->assertSame( $resolution, $gs->get_resolution() );
		$resolution = '99999x99999'; // Max.
		$this->assertTrue( $gs->set_resolution( $resolution ) );
		$this->assertSame( $resolution, $gs->get_resolution() );

		$this->assertInstanceOf( 'WP_Error', $gs->set_resolution( '0x0' ) );
		$this->assertSame( $resolution, $gs->get_resolution() );
		$this->assertInstanceOf( 'WP_Error', $gs->set_resolution( '128x0' ) );
		$this->assertSame( $resolution, $gs->get_resolution() );
		$this->assertInstanceOf( 'WP_Error', $gs->set_resolution( '0x128' ) );
		$this->assertSame( $resolution, $gs->get_resolution() );
		$this->assertInstanceOf( 'WP_Error', $gs->set_resolution( '100000x1000' ) );
		$this->assertSame( $resolution, $gs->get_resolution() );
		$this->assertInstanceOf( 'WP_Error', $gs->set_resolution( '100x' ) );
		$this->assertSame( $resolution, $gs->get_resolution() );
		$this->assertInstanceOf( 'WP_Error', $gs->set_resolution( 'x100' ) );
		$this->assertSame( $resolution, $gs->get_resolution() );
		$this->assertInstanceOf( 'WP_Error', $gs->set_resolution( '100xa' ) );
		$this->assertSame( $resolution, $gs->get_resolution() );

		add_filter( 'gopp_editor_set_resolution', array( $this, 'filter_gopp_editor_set_resolution' ) );
		$resolution = $this->resolution = '100x100';
		$this->assertTrue( $gs->set_resolution() );
		$this->assertSame( $this->resolution, $gs->get_resolution() );
		$this->resolution = 'x100';
		$this->assertInstanceOf( 'WP_Error', $gs->set_resolution() );
		$this->assertSame( $resolution, $gs->get_resolution() );
		remove_filter( 'gopp_editor_set_resolution', array( $this, 'filter_gopp_editor_set_resolution' ) );

		$gs->set_resolution( '128x128' );
		$this->assertSame( '128x128', $gs->get_resolution() );
	}

	/**
	 * Test unsupported methods.
	 */
	public function test_unsupported_methods() {
		$gs = new GOPP_Image_Editor_GS( null );
		$this->assertInstanceOf( 'WP_Error', $gs->resize( 100, 100 ) );
		$this->assertInstanceOf( 'WP_Error', $gs->multi_resize( array( 'small' => array( 'width' => 100, 'height' => 100, ) ) ) );
		$this->assertInstanceOf( 'WP_Error', $gs->crop( 1, 1, 50, 50 ) );
		$this->assertInstanceOf( 'WP_Error', $gs->rotate( 0.5 ) );
		$this->assertInstanceOf( 'WP_Error', $gs->flip( true, false ) );
		$this->assertInstanceOf( 'WP_Error', $gs->stream() );
	}

	/*
	 * Test page.
	 */
	public function test_page() {
		$gs = new GOPP_Image_Editor_GS( null );
		$this->assertSame( 1, $gs->get_page() );

		$page = 3;
		$this->assertTrue( $gs->set_page( $page ) );
		$this->assertSame( $page, $gs->get_page() );

		$this->assertInstanceOf( 'WP_Error', $gs->set_page( 0 ) );
		$this->assertSame( $page, $gs->get_page() );
		$this->assertInstanceOf( 'WP_Error', $gs->set_page( -1 ) );
		$this->assertSame( $page, $gs->get_page() );
		$this->assertInstanceOf( 'WP_Error', $gs->set_page( '' ) );
		$this->assertSame( $page, $gs->get_page() );

		add_filter( 'gopp_editor_set_page', array( $this, 'filter_gopp_editor_set_page' ) );
		$page = $this->page = 21;
		$this->assertTrue( $gs->set_page() );
		$this->assertSame( $this->page, $gs->get_page() );
		$this->page = 'asdf';
		$this->assertInstanceOf( 'WP_Error', $gs->set_page() );
		$this->assertSame( $page, $gs->get_page() );
		remove_filter( 'gopp_editor_set_page', array( $this, 'filter_gopp_editor_set_page' ) );

		$gs->set_page( 1 );
		$this->assertSame( 1, $gs->get_page() );
	}

	/**
	 * Check support for compatible mime types.
	 */
	public function test_supports_mime_type() {
		$image_editor = new GOPP_Image_Editor_GS( null );

		$this->assertTrue( $image_editor->supports_mime_type( 'application/pdf' ), 'Does not support application/pdf' );
		$this->assertFalse( $image_editor->supports_mime_type( 'image/jpeg' ) );
		$this->assertFalse( $image_editor->supports_mime_type( 'image/png' ) );
		$this->assertFalse( $image_editor->supports_mime_type( 'image/gif' ) );
	}

	/**
	 * ticket 39216
	 */
	public function test_alpha_pdf_preview() {
		do_action( 'admin_init' );
		$this->assertSame( 10, has_filter( 'wp_image_editors', 'gopp_plugin_wp_image_editors' ) );

		$test_file = dirname( __FILE__ ) . '/images/test_alpha.pdf';
		$attachment_id = $this->factory->attachment->create_upload_object( $test_file );
		$this->assertNotEmpty( $attachment_id );

		$attached_file = get_attached_file( $attachment_id );
		$this->assertNotEmpty( $attached_file );

		$check_file = str_replace( '.pdf', '.jpg', $attached_file );

		$gd_image = imagecreatefromjpeg( $check_file );
		$output = dechex( imagecolorat( $gd_image, 100, 100 ) );
		imagedestroy( $gd_image );
		$this->assertSame( 'ffffff', $output );
	}
}

// Expose protected vars/methods.
require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
require_once dirname( dirname( __FILE__ ) ) . '/includes/class-gopp-image-editor-gs.php';
class Test_GOPP_Image_Editor_GS extends GOPP_Image_Editor_GS {
	public static function public_set_is_win( $is_win ) { self::$is_win = $is_win; }
	public static function public_gs_valid( $file, $no_read_check = false ) { return parent::gs_valid( $file, $no_read_check ); }
	public static function public_gs_cmd( $args ) { return parent::gs_cmd( $args ); }
	public static function public_gs_cmd_win() { return parent::gs_cmd_win(); }
	public function public_get_gs_args( $filename ) { return parent::get_gs_args( $filename ); }
	public static function public_escapeshellarg( $arg ) { return parent::escapeshellarg( $arg ); }
}
