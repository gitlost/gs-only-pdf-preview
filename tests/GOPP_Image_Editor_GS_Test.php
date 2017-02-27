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

	static $is_win;
	static $have_gs;

	static function wpSetUpBeforeClass() {
		//require_once( ABSPATH . WPINC . '/class-wp-image-editor-gs.php' );
		require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
		require_once dirname( dirname( __FILE__ ) ) . '/includes/class-gopp-image-editor-gs.php';

		self::$is_win = 0 === strncasecmp( 'WIN', PHP_OS, 3 );
		if ( ! self::$is_win ) {
			exec( 'which gs', $output, $return_var );
			self::$have_gs = 0 === $return_var;
		}
	}

	static function wpTearDownAfterClass() {
	}

	public function setUp() {
		parent::setUp();

		if ( ! isset( $_SERVER['SCRIPT_NAME'] ) ) { // Suppress internal phpunit PHP Warning bug.
			$_SERVER['SCRIPT_NAME'] = __FILE__;
		}
	}

	public function tearDown() {
		$this->remove_added_uploads();

		parent::tearDown();
	}

	/**
	 * Test test().
	 */
	public function test_test() {
		$args = array( 'mime_type' => 'application/pdf' );

		GOPP_Image_Editor_GS::clear();
		$output = GOPP_Image_Editor_GS::test( $args );
		if ( true !== self::$have_gs ) {
			$this->assertFalse( $output );
		} else {
			$this->assertTrue( $output );
		}

		// No mime type given.
		GOPP_Image_Editor_GS::clear();
		$output = GOPP_Image_Editor_GS::test();
		$this->assertFalse( $output );

		// Non-existent short circuit ignored.
		add_filter( 'gopp_image_gs_cmd_path', array( $this, 'filter_gopp_image_gs_cmd_path_nonexistent' ) );
		GOPP_Image_Editor_GS::clear();
		$output = GOPP_Image_Editor_GS::test( $args );
		if ( true !== self::$have_gs ) {
			$this->assertFalse( $output );
		} else {
			$this->assertTrue( $output );
		}
		remove_filter( 'gopp_image_gs_cmd_path', array( $this, 'filter_gopp_image_gs_cmd_path_nonexistent' ) );

		// Bad gs short circuit ignored.
		add_filter( 'gopp_image_gs_cmd_path', array( $this, 'filter_gopp_image_gs_cmd_path_not_gs' ) );
		GOPP_Image_Editor_GS::clear();
		$output = GOPP_Image_Editor_GS::test( $args );
		if ( true !== self::$have_gs ) {
			$this->assertFalse( $output );
		} else {
			$this->assertTrue( $output );
		}
		remove_filter( 'gopp_image_gs_cmd_path', array( $this, 'filter_gopp_image_gs_cmd_path_not_gs' ) );

		// gs_cmd_path() fail.
		Test_GOPP_Image_Editor_GS::clear();
		Test_GOPP_Image_Editor_GS::public_set_gs_cmd_path( false );
		$output = GOPP_Image_Editor_GS::test( $args );
		$this->assertFalse( $output );

		// Unsupported methods.
		GOPP_Image_Editor_GS::clear();
		$output = GOPP_Image_Editor_GS::test( array_merge( $args, array( 'methods' => array( 'resize' ) ) ) );
		$this->assertFalse( $output );

		// Bad path.
		GOPP_Image_Editor_GS::clear();
		$output = GOPP_Image_Editor_GS::test( array_merge( $args, array( 'path' => '@file' ) ) );
		$this->assertFalse( $output );
	}

	function filter_gopp_image_gs_cmd_path_nonexistent( $gs_cmd_path ) {
		return 'gs_nonexistent';
	}

	function filter_gopp_image_gs_cmd_path_not_gs( $gs_cmd_path ) {
		return 'echo';
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
	 * Test test_load().
	 */
	public function test_load() {
		// Success.
		$image_editor = new GOPP_Image_Editor_GS( dirname( __FILE__ ) . '/images/minimal-us-letter.pdf' );
		$output = $image_editor->load();
		$this->assertTrue( $output );
		unset( $image_editor );

		// Not PDF.
		$image_editor = new GOPP_Image_Editor_GS( dirname( __FILE__ ) . '/images/test-image.jpg' );
		$output = $image_editor->load();
		$this->assertInstanceOf( 'WP_Error', $output );
		unset( $image_editor );

		// Bad default resolution.
		$image_editor = new Test_GOPP_Image_Editor_GS( dirname( __FILE__ ) . '/images/minimal-us-letter.pdf' );
		$image_editor->public_set_default_resolution( -1 );
		$output = $image_editor->load();
		$this->assertInstanceOf( 'WP_Error', $output );
		unset( $image_editor );

		// Bad default page.
		$image_editor = new Test_GOPP_Image_Editor_GS( dirname( __FILE__ ) . '/images/minimal-us-letter.pdf' );
		$image_editor->public_set_default_page( -1 );
		$output = $image_editor->load();
		$this->assertInstanceOf( 'WP_Error', $output );
		unset( $image_editor );

		// Bad default quality.
		$image_editor = new Test_GOPP_Image_Editor_GS( dirname( __FILE__ ) . '/images/minimal-us-letter.pdf' );
		$image_editor->public_set_default_quality( -1 );
		$output = $image_editor->load();
		$this->assertInstanceOf( 'WP_Error', $output );
		unset( $image_editor );
	}

	/**
	 * Test test_save().
	 */
	public function test_save() {
		// Fail destination file.
		$image_editor = new GOPP_Image_Editor_GS( dirname( __FILE__ ) . '/images/minimal-us-letter.pdf' );
		$output = $image_editor->load();
		$this->assertTrue( $output );
		$output = $image_editor->save( 'non_existing_dir/donk.jpg' );
		$this->assertInstanceOf( 'WP_Error', $output );

		// Fail mime_type.
		$image_editor = new GOPP_Image_Editor_GS( dirname( __FILE__ ) . '/images/minimal-us-letter.pdf' );
		$output = $image_editor->load();
		$this->assertTrue( $output );
		$output = $image_editor->save( '/tmp/test_save-gs-fail.jpg', 'application/pdf' );
		$this->assertInstanceOf( 'WP_Error', $output );

		// Fail cmd path.
		$test_filename = '/tmp/test_save-gs.jpg';
		$image_editor = new Test_GOPP_Image_Editor_GS( dirname( __FILE__ ) . '/images/minimal-us-letter.pdf' );
		$output = $image_editor->load();
		$this->assertTrue( $output );
		$gs_cmd_path = Test_GOPP_Image_Editor_GS::public_gs_cmd_path();
		Test_GOPP_Image_Editor_GS::public_set_gs_cmd_path( false );
		$output = $image_editor->save( $test_filename, 'image/jpeg' );
		$this->assertInstanceOf( 'WP_Error', $output );
		Test_GOPP_Image_Editor_GS::public_set_gs_cmd_path( $gs_cmd_path );

		// Success filename given.
		$test_filename = '/tmp/test_save-gs.jpg';
		$image_editor = new GOPP_Image_Editor_GS( dirname( __FILE__ ) . '/images/minimal-us-letter.pdf' );
		$output = $image_editor->load();
		$this->assertTrue( $output );
		$output = $image_editor->save( $test_filename, 'image/jpeg' );

		if ( true !== self::$have_gs ) {
			$this->assertTrue( is_wp_error( $output ) );
		} else {
			$this->assertNotEmpty( $output );
			$this->assertTrue( is_array( $output ) );
			$this->assertSame( $test_filename, $output['path'] );
			$this->assertTrue( is_file( $output['path'] ) );
			$this->assertNotEmpty( $output['file'] );
			$this->assertNotEmpty( $output['width'] );
			$this->assertNotEmpty( $output['height'] );
			$this->assertSame( 'image/jpeg', $output['mime-type'] );
			unlink( $test_filename );
		}

		// Success existing jpeg.
		$test_filename = '/tmp/test_save-gs.jpg';
		$image_editor = new GOPP_Image_Editor_GS( dirname( __FILE__ ) . '/images/minimal-us-letter.pdf' );
		$output = $image_editor->load();
		$this->assertTrue( $output );
		file_put_contents( $test_filename, 'asdf' );
		$output = $image_editor->save( $test_filename, 'image/jpeg' );

		if ( true !== self::$have_gs ) {
			$this->assertTrue( is_wp_error( $output ) );
		} else {
			$this->assertNotEmpty( $output );
			$this->assertTrue( is_array( $output ) );
			$this->assertNotEquals( $test_filename, $output['path'] );
			$this->assertSame( 'asdf', file_get_contents( $test_filename ) );
			$this->assertTrue( is_file( $output['path'] ) );
			$this->assertNotEmpty( $output['file'] );
			$this->assertNotEmpty( $output['width'] );
			$this->assertNotEmpty( $output['height'] );
			$this->assertSame( 'image/jpeg', $output['mime-type'] );
			unlink( $output['path'] );
		}
		unlink( $test_filename );

		// Fail no filename given.
		$test_filename = null;
		$image_editor = new GOPP_Image_Editor_GS( dirname( __FILE__ ) . '/images/minimal-us-letter.pdf' );
		$output = $image_editor->load();
		$this->assertTrue( $output );
		$output = $image_editor->save( $test_filename, 'image/jpeg' );
		$this->assertNotEmpty( $output );
		$this->assertInstanceOf( 'WP_Error', $output );

		// Success filename with no directory given.
		$test_filename = 'blah';
		$image_editor = new GOPP_Image_Editor_GS( dirname( __FILE__ ) . '/images/minimal-us-letter.pdf' );
		$output = $image_editor->load();
		$this->assertTrue( $output );
		$output = $image_editor->save( $test_filename, 'image/jpeg' );
		if ( true !== self::$have_gs ) {
			$this->assertTrue( is_wp_error( $output ) );
		} else {
			$this->assertTrue( is_file( $output['path'] ) );
			$this->assertNotEmpty( $output['file'] );
			$this->assertNotEmpty( $output['width'] );
			$this->assertNotEmpty( $output['height'] );
			$this->assertSame( 'image/jpeg', $output['mime-type'] );
			unlink( $output['path'] );
		}
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
			array( '@args_file', true, 'Unsupported file name.' ), // Ghostscript argument file.
			array( 'space filename', true, 'Unsupported file name.' ), // File name containing space.
			array( 'quote\'filename', true, 'Unsupported file name.' ), // File name containing single quote.
			array( 'double_quote"filename', true, 'Unsupported file name.' ), // File name containing double quote.
			array( 'percent_file%name', true, 'Unsupported file name.' ), // File name containing percent.
			array( 'plus+file+name', true, true ), // Success: allow filnames with pluses for BC with common older uploads.
			array( 'bang_!filename', true, 'Unsupported file name.' ), // File name containing exclaimation mark.
			array( dirname( __FILE__ ) . '/images/test-image.jpg', false, 'File is not a PDF.' ), // Not a PDF.
			array( dirname( __FILE__ ) . '/images/test-image.jpg', true, true ), // Not a PDF.
			array( dirname( __FILE__ ) . '/images/test-bad.pdf', false, 'File is not a PDF.' ), // Bad PDF.
			array( dirname( __FILE__ ) . '/images/minimal-us-letter.pdf', false, true ), // Success.
		);
	}

	/**
	 * Test gs_cmd_path().
	 */
	public function test_gs_cmd_path() {
		if ( self::$is_win ) {
			$this->markTestSkipped( 'Unix only test.' );
		}

		// Transient exists.
		Test_GOPP_Image_Editor_GS::clear();
		set_transient( 'gopp_image_gs_cmd_path', 'gs_nonexistent', 100 );
		$output = Test_GOPP_Image_Editor_GS::public_gs_cmd_path();
		$this->assertSame( 'gs_nonexistent', $output );

		// Short circuit with differing transient.
		Test_GOPP_Image_Editor_GS::clear();
		set_transient( 'gopp_image_gs_cmd_path', 'gs_nonexistent', 100 );
		add_filter( 'gopp_image_gs_cmd_path', array( $this, 'filter_gopp_image_gs_cmd_path' ) );
		$output = Test_GOPP_Image_Editor_GS::public_gs_cmd_path();
		$this->assertTrue( false !== strpos( $output, 'gs_sh' ) );
		remove_filter( 'gopp_image_gs_cmd_path', array( $this, 'filter_gopp_image_gs_cmd_path' ) );
		$this->assertSame( get_transient( 'gopp_image_gs_cmd_path' ), $output );

		// Short circuit no transient.
		Test_GOPP_Image_Editor_GS::clear();
		add_filter( 'gopp_image_gs_cmd_path', array( $this, 'filter_gopp_image_gs_cmd_path' ) );
		$output = Test_GOPP_Image_Editor_GS::public_gs_cmd_path();
		$this->assertTrue( false !== strpos( $output, 'gs_sh' ) );
		remove_filter( 'gopp_image_gs_cmd_path', array( $this, 'filter_gopp_image_gs_cmd_path' ) );
		$this->assertSame( get_transient( 'gopp_image_gs_cmd_path' ), $output );

		// Bad short circuit with differing bad transient.
		Test_GOPP_Image_Editor_GS::clear();
		Test_GOPP_Image_Editor_GS::public_set_is_win( true ); // So doesn't use valid unix path.
		set_transient( 'gopp_image_gs_cmd_path', 'gs_nonexistent', 100 );
		add_filter( 'gopp_image_gs_cmd_path', array( $this, 'filter_gopp_image_gs_cmd_path_bad' ) );
		$output = Test_GOPP_Image_Editor_GS::public_gs_cmd_path();
		$this->assertFalse( false !== strpos( $output, 'gs_nonexistent' ) );
		remove_filter( 'gopp_image_gs_cmd_path', array( $this, 'filter_gopp_image_gs_cmd_path_bad' ) );
		$this->assertSame( get_transient( 'gopp_image_gs_cmd_path' ), $output );

		Test_GOPP_Image_Editor_GS::clear();
	}

	public function filter_gopp_image_gs_cmd_path( $gs_cmd_path ) {
		return dirname( __FILE__ ) . '/fake/gs_sh';
	}

	public function filter_gopp_image_gs_cmd_path_bad( $gs_cmd_path ) {
		return 'another_gs_nonexistent';
	}

	/**
	 * Test test_gs_cmd_path().
	 */
	public function test_test_gs_cmd_path() {
	}

	/**
	 * Test gs_cmd_nix().
	 */
	public function test_gs_cmd_nix() {
		if ( self::$is_win ) {
			$this->markTestSkipped( 'Unix only test.' );
		}

		$args = array( 'mime_type' => 'application/pdf' );

		GOPP_Image_Editor_GS::clear();
		$output = GOPP_Image_Editor_GS::test( $args );
		if ( true !== self::$have_gs ) {
			$this->assertFalse( $output );
		} else {
			$this->assertTrue( $output );
		}
	}

	/**
	 * Test gs_cmd_win() on *nix.
	 */
	public function test_gs_cmd_win_on_nix() {
		if ( self::$is_win ) {
			$this->markTestSkipped( 'Unix only test.' );
		}

		$old_server = $_SERVER;
		$_SERVER = array();

		Test_GOPP_Image_Editor_GS::clear();
		Test_GOPP_Image_Editor_GS::public_set_is_win( true );

		$image_editor = new Test_GOPP_Image_Editor_GS( null );
		$args = $image_editor->public_get_gs_args( 'dummy.pdf' );

		Test_GOPP_Image_Editor_GS::clear();
		Test_GOPP_Image_Editor_GS::public_set_is_win( true );
		$output = Test_GOPP_Image_Editor_GS::public_gs_cmd( $args );
		$this->assertFalse( $output );

		$dirname = dirname( __FILE__ );

		// Uses REG bash shell fake in tests directory.
		$old_path = getenv( 'PATH' );
		putenv( 'PATH=' . $dirname . '/fake:' . $old_path );
		$expected = '\\gs\\gs9.20\\bin\\gswin64c.exe"';
		Test_GOPP_Image_Editor_GS::clear();
		Test_GOPP_Image_Editor_GS::public_set_is_win( true );
		$output = Test_GOPP_Image_Editor_GS::public_gs_cmd( $args );
		putenv( 'PATH=' . $old_path );
		$this->assertTrue( false !== strpos( $output, $expected ) );

		// GSC
		$gsc = getenv( 'GSC' );
		$expected = 'gs';
		putenv( 'GSC=' . $expected );
		Test_GOPP_Image_Editor_GS::clear();
		Test_GOPP_Image_Editor_GS::public_set_is_win( true );
		$output = Test_GOPP_Image_Editor_GS::public_gs_cmd( $args );
		putenv( 'GSC=' . $gsc );
		if ( true !== self::$have_gs ) {
			$this->assertEmpty( $output );
		} else {
			$this->assertTrue( false !== strpos( $output, $expected ) );
		}

		// Uses fake entries in tests directory.
		$_SERVER['ProgramW6432'] = $dirname . '/fake/Program Files';
		$_SERVER['ProgramFiles'] = $dirname . '/fake/Program Files';
		$_SERVER['ProgramFiles(x86)'] = $dirname . '/fake/Program Files (x86)';
		$expected = $_SERVER['ProgramW6432'] . '\\gs\\gs9.20\\bin\\gswin64c.exe';
		Test_GOPP_Image_Editor_GS::clear();
		Test_GOPP_Image_Editor_GS::public_set_is_win( true );
		$output = Test_GOPP_Image_Editor_GS::public_gs_cmd( $args );
		$this->assertTrue( false !== strpos( $output, $expected ) );
		$_SERVER = array();

		// Default PATH.
		$old_path = getenv( 'PATH' );
		putenv( 'PATH=' . $dirname . '/fake/win64_path:' . $old_path );
		Test_GOPP_Image_Editor_GS::clear();
		Test_GOPP_Image_Editor_GS::public_set_is_win( true );
		$output = Test_GOPP_Image_Editor_GS::public_gs_cmd( $args );
		$this->assertTrue( false !== strpos( $output, 'gswin64c.exe' ) );
		putenv( 'PATH=' . $old_path );
		putenv( 'PATH=' . $dirname . '/fake/win32_path:' . $old_path );
		Test_GOPP_Image_Editor_GS::clear();
		Test_GOPP_Image_Editor_GS::public_set_is_win( true );
		$output = Test_GOPP_Image_Editor_GS::public_gs_cmd( $args );
		$this->assertTrue( false !== strpos( $output, 'gswin32c.exe' ) );
		putenv( 'PATH=' . $old_path );

		$_SERVER = $old_server;

		Test_GOPP_Image_Editor_GS::clear();
	}

	public function filter_gopp_image_gs_cmd_path_win( $gs_cmd_path ) {
		return dirname( __FILE__ ) . '/fake/\\spaced dir\\prog.exe';
	}

	/**
	 * Test gs_cmd().
	 */
	public function test_gs_cmd() {
		$args = '';

		$output = Test_GOPP_Image_Editor_GS::public_gs_cmd( $args );
		if ( true !== self::$have_gs ) {
			$this->assertFalse( $output );
		} else {
			$this->assertTrue( '' === $output || 0 === strpos( $output, self::$is_win ? '"' : "'" ) );
		}
	}

	/**
	 * Test get_gs_args().
	 */
	public function test_get_gs_args() {
		Test_GOPP_Image_Editor_GS::clear();

		$filename = 'in.pdf';

		$gs = new Test_GOPP_Image_Editor_GS( $filename );

		$out = $gs->public_get_gs_args( $filename );
		$this->assertTrue( false !== strpos( $out, '-dJPEGQ=70' ) );
		$this->assertTrue( false !== strpos( $out, '-r128' ) );
		$this->assertTrue( false !== strpos( $out, '-dFirstPage=1' ) );
		$this->assertTrue( false !== strpos( $out, '-sOutputFile=' . $filename ) );

		$gs->public_set_quality( 101 );
		$gs->public_set_resolution( -1 );
		$gs->public_set_page( 0 );
		$out = $gs->public_get_gs_args( $filename );
		$this->assertFalse( false !== strpos( $out, '-dJPEGQ' ) );
		$this->assertFalse( false !== strpos( $out, '-r' ) );
		$this->assertTrue( false !== strpos( $out, '-dFirstPage=1' ) );
	}

	/**
	 * Test initial_gs_args().
	 */
	public function test_initial_gs_args() {
	}

	/**
	 * Test escapeshellarg().
	 * @dataProvider data_escapeshellarg
	 */
	public function test_escapeshellarg( $input, $expected_nix, $expected_win ) {
		Test_GOPP_Image_Editor_GS::clear();

		Test_GOPP_Image_Editor_GS::public_set_is_win( false );
		$output = Test_GOPP_Image_Editor_GS::public_escapeshellarg( $input );
		$this->assertSame( $expected_nix, $output );
		if ( ! self::$is_win ) {
			$this->assertSame( escapeshellarg( $input ), $output );
		}

		Test_GOPP_Image_Editor_GS::public_set_is_win( true );
		$output = Test_GOPP_Image_Editor_GS::public_escapeshellarg( $input );
		$this->assertSame( $expected_win, $output );
		if ( self::$is_win ) {
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

	/**
	 * Test is_win().
	 */
	public function test_is_win() {
		$this->assertSame( self::$is_win, Test_GOPP_Image_Editor_GS::public_is_win() );
	}

	/**
	 * Test clear().
	 */
	public function test_clear() {
	}

	/*
	 * Test resolution.
	 */
	public function test_resolution() {
		$gs = new Test_GOPP_Image_Editor_GS( null );
		$this->assertSame( 128, $gs->get_resolution() );

		$resolution = 999;
		$this->assertTrue( $gs->set_resolution( $resolution ) );
		$this->assertSame( $resolution, $gs->get_resolution() );
		$resolution = 1; // Min.
		$this->assertTrue( $gs->set_resolution( $resolution ) );
		$this->assertSame( $resolution, $gs->get_resolution() );

		$this->assertInstanceOf( 'WP_Error', $gs->set_resolution( 0 ) );
		$this->assertSame( $resolution, $gs->get_resolution() );
		$this->assertInstanceOf( 'WP_Error', $gs->set_resolution( -3 ) );
		$this->assertSame( $resolution, $gs->get_resolution() );

		add_filter( 'gopp_editor_set_resolution', array( $this, 'filter_gopp_editor_set_resolution' ) );
		$this->resolution = 100;
		$this->assertTrue( $gs->set_resolution() );
		$this->assertSame( $this->resolution, $gs->get_resolution() );
		$this->resolution = -4;
		$this->assertTrue( $gs->set_resolution() );
		$this->assertNotSame( $this->resolution, $gs->get_resolution() );
		$this->assertSame( 128, $gs->get_resolution() );
		remove_filter( 'gopp_editor_set_resolution', array( $this, 'filter_gopp_editor_set_resolution' ) );

		$gs->public_set_resolution( ';rm *' );
		$args = $gs->public_get_gs_args( 'wow' );
		$this->assertFalse( false !== stripos( $args, 'rm' ) );

		$gs->set_resolution( 128 );
		$this->assertSame( 128, $gs->get_resolution() );

		$gs->public_set_default_resolution( -1 );
		$result = $gs->set_resolution();
		$this->assertTrue( is_wp_error( $result ) );

		$gs->public_set_default_page( -1 );
		$result = $gs->set_page();
		$this->assertTrue( is_wp_error( $result ) );

		$gs->public_set_default_quality( -1 );
		$result = $gs->set_quality();
		$this->assertTrue( is_wp_error( $result ) );
	}

	protected $resolution = 100;

	function filter_gopp_editor_set_resolution( $resolution ) {
		return $this->resolution;
	}

	/*
	 * Test page.
	 */
	public function test_page() {
		$gs = new Test_GOPP_Image_Editor_GS( null );
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
		$this->page = 21;
		$this->assertTrue( $gs->set_page() );
		$this->assertSame( $this->page, $gs->get_page() );
		$this->page = 'asdf';
		$this->assertTrue( $gs->set_page() );
		$this->assertSame( 1, $gs->get_page() );
		remove_filter( 'gopp_editor_set_page', array( $this, 'filter_gopp_editor_set_page' ) );

		$gs->public_set_page( ';echo /etc/passwd' );
		$args = $gs->public_get_gs_args( 'wow' );
		$this->assertFalse( false !== stripos( $args, 'passwd' ) );
		$this->assertTrue( false !== stripos( $args, 'firstpage=1' ) );

		$gs->set_page( 1 );
		$this->assertSame( 1, $gs->get_page() );
	}

	protected $page = 2;

	function filter_gopp_editor_set_page( $page ) {
		return $this->page;
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

	public function test_debug() {
		require_once dirname( dirname( __FILE__ ) ) . '/includes/debug-gopp-image-editor-gs.php';

		DEBUG_GOPP_Image_Editor_GS::clear();

		ob_start();
		DEBUG_GOPP_Image_Editor_GS::dump();
		$out = ob_get_clean();
		if ( true !== self::$have_gs ) {
			$this->assertFalse( false !== stripos( $out, 'return var</td><td><strong>0' ) );
			$this->assertSame( 1, preg_match( '/output<\/td><td><strong>[^<]+not found/i', $out ) );
			$this->assertSame( 1, preg_match( '/gs_cmd_path<\/td><td><strong><\/strong/i', $out ) );
			$this->assertTrue( false !== stripos( $out, 'test</td><td><strong>false' ) );
		} else {
			$this->assertTrue( false !== stripos( $out, 'return var</td><td><strong>0' ) );
			$this->assertSame( 1, preg_match( '/output<\/td><td><strong>[^<]+ghostscript/i', $out ) );
			$this->assertSame( 0, preg_match( '/output<\/td><td><strong>[^<]+not found/i', $out ) );
			if ( self::$is_win ) {
				$this->assertSame( 1, preg_match( '/gs_cmd_path<\/td><td><strong>[^<]+gswin[^<]+</i', $out ) );
			} else {
				$this->assertSame( 1, preg_match( '/gs_cmd_path<\/td><td><strong>[^<]*gs</i', $out ) );
			}
			$this->assertTrue( false !== stripos( $out, 'test</td><td><strong>true' ) );
		}
		$this->assertTrue( false !== stripos( $out, 'is_win</td><td><strong>' . ( self::$is_win ? 'true' : 'false' ) ) );
	}

	/**
	 * ticket 39216
	 */
	public function test_alpha_pdf_preview() {
		do_action( 'admin_init' );
		$this->assertSame( 10, has_filter( 'wp_image_editors', array( 'GS_Only_PDF_Preview', 'wp_image_editors' ) ) );

		$test_file = dirname( __FILE__ ) . '/images/test_alpha.pdf';
		$attachment_id = $this->factory->attachment->create_upload_object( $test_file );
		$this->assertNotEmpty( $attachment_id );

		$attached_file = get_attached_file( $attachment_id );
		$this->assertNotEmpty( $attached_file );

		$metadata = get_metadata( 'post', $attachment_id, '_wp_attachment_metadata' );

		if ( true !== self::$have_gs ) {
			$this->assertEmpty( $metadata );
		} else {
			$this->assertNotEmpty( $metadata );
			$this->assertNotEmpty( $metadata[0] );
			$this->assertNotEmpty( $metadata[0]['sizes'] );
			$this->assertNotEmpty( $metadata[0]['sizes']['full'] );
			$this->assertNotEmpty( $metadata[0]['sizes']['full']['file'] );
			$check_file = dirname( $attached_file ) . '/' . $metadata[0]['sizes']['full']['file'];

			$gd_image = imagecreatefromjpeg( $check_file );
			$output = dechex( imagecolorat( $gd_image, 100, 100 ) );
			imagedestroy( $gd_image );
			$this->assertSame( 'ffffff', $output );
		}
	}
}

require_once dirname( __FILE__ ) . '/test-gopp-image-editor-gs.php';
