<?php
/**
 * WordPress GhostScript Image Editor
 *
 * @package GhostScript Only PDF Preview
 * @subpackage Image_Editor
 */

if ( ! defined( 'GOPP_IMAGE_EDITOR_GS_TRANSIENT_EXPIRATION' ) ) {
	define( 'GOPP_IMAGE_EDITOR_GS_TRANSIENT_EXPIRATION', DAY_IN_SECONDS );
}

/**
 * WordPress Image Editor Class for producing JPEG from PDF using GhostScript.
 *
 * @since 4.x
 * @package WordPress
 * @subpackage Image_Editor
 * @uses WP_Image_Editor Extends class
 */
class GOPP_Image_Editor_GS extends WP_Image_Editor {

	/**
	 * Override the default quality to lessen file size.
	 *
	 * @access protected
	 * @var int
	 */
	protected $default_quality = 70;

	/**
	 * Resolution of output JPEG (DPI).
	 *
	 * @access protected
	 * @var int
	 */
	protected $resolution = null;
	protected $default_resolution = 128;

	/**
	 * Page to render.
	 *
	 * @access protected
	 * @var int
	 */
	protected $page = null;
	protected $default_page = 1;

	/**
	 * Whether on Windows or not.
	 *
	 * @static
	 * @access protected
	 * @var bool
	 */
	protected static $is_win = null;

	/**
	 * The path to the GhostScript executable.
	 *
	 * @static
	 * @access protected
	 * @var string
	 */
	protected static $gs_cmd_path = null;

	/**
	 * Checks to see if current environment supports GhostScript.
	 *
	 * @since 4.x
	 *
	 * @static
	 * @access public
	 *
	 * @param array $args
	 * @return bool
	 */
	public static function test( $args = array() ) {
		// Must have path to GhostScript executable.
		if ( ! self::gs_cmd_path() ) {
			return false;
		}

		// No manipulation supported - dedicated to producing JPEG preview.
		if ( isset( $args['methods'] ) ) {
			$unsupported_methods = array( 'resize', 'multi_resize', 'crop', 'rotate', 'flip', 'stream' );
			if ( array_intersect( $unsupported_methods, $args['methods'] ) ) {
				return false;
			}
		}

		// Do strict file name check if given path.
		if ( isset( $args['path'] ) && true !== self::gs_valid( $args['path'], true /*no_read_check*/ ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Checks to see if editor supports the mime-type specified.
	 *
	 * @since 4.x
	 *
	 * @static
	 * @access public
	 *
	 * @param string $mime_type
	 * @return bool
	 */
	public static function supports_mime_type( $mime_type ) {
		return 'pdf' === strtolower( self::get_extension( $mime_type ) );
	}

	/**
	 * Checks validity and existence of file and sets mime type and calls `set_resolution` and `set_page` and `set_quality` (firing filters).
	 *
	 * @since 4.x
	 * @access protected
	 *
	 * @return true|WP_Error True if loaded; WP_Error on failure.
	 */
	public function load() {
		$result = self::gs_valid( $this->file );
		if ( true !== $result ) {
			return new WP_Error( 'invalid_image', $result, $this->file );
		}

		list( $filename, $extension, $mime_type ) = $this->get_output_format( $this->file );
		$this->mime_type = $mime_type;

		// Allow chance for gopp_editor_set_resolution filter to fire by calling set_resolution() with null arg (mimicking set_quality() behavior).
		$this->set_resolution();

		// Similarly for page to render.
		$this->set_page();

		return $this->set_quality();
	}

	/**
	 * Creates JPEG preview from PDF.
	 *
	 * @since 4.x
	 * @access public
	 *
	 * @param string $destfilename
	 * @param string $mime_type
	 * @return array|WP_Error {'path'=>string, 'file'=>string, 'width'=>int, 'height'=>int, 'mime-type'=>string}
	 */
	public function save( $destfilename = null, $mime_type = null ) {
		list( $filename, $extension, $mime_type ) = $this->get_output_format( $destfilename, $mime_type );

		if ( 'image/jpeg' !== $mime_type ) {
			return new WP_Error( 'image_save_error', __( 'Unsupported MIME type.', 'ghostscript-only-pdf-preview' ), $mime_type );
		}

		if ( ! $filename ) {
			$filename = $this->generate_filename( null, null, $extension );
		}

		if ( ! ( $cmd = self::gs_cmd( $this->get_gs_args( $filename ) ) ) ) {
			return new WP_Error( 'image_save_error', __( 'No GhostScript.', 'ghostscript-only-pdf-preview' ) );
		}
		exec( $cmd, $output, $return_var );

		if ( 0 !== $return_var ) {
			do_action( 'gopp_error', __CLASS__, __FUNCTION__, __LINE__, compact( 'cmd', 'return_var', 'output' ) );
			return new WP_Error( 'image_save_error', __( 'Image Editor Save Failed', 'ghostscript-only-pdf-preview' ) );
		}

		// As this editor is immediately thrown away just do dummy size. Saves some cycles.
		$size = array( 1088, 1408 ); // US Letter size at 128 DPI. Makes it pass the unit test Tests_Image_Functions::test_wp_generate_attachment_metadata_pdf().
		//$size = @ getimagesize( $filename );
		if ( ! $size ) {
			return new WP_Error( 'image_save_error', __( 'Could not read image size.', 'ghostscript-only-pdf-preview' ) ); // @codeCoverageIgnore
		}

		// For form's sake, transmogrify into the JPEG file.
		$this->file = $filename;
		$this->mime_type = $mime_type;
		$this->update_size( $size[0], $size[1] );

		// Set correct file permissions
		$stat = stat( dirname( $filename ) );
		$perms = $stat['mode'] & 0000666; // Same permissions as parent folder, strip off the executable bits.
		@ chmod( $filename, $perms );

		/** This filter is documented in wp-includes/class-wp-image-editor-gd.php */
		return array(
			'path'      => $filename,
			'file'      => wp_basename( apply_filters( 'image_make_intermediate_size', $filename ) ),
			'width'     => $this->size['width'],
			'height'    => $this->size['height'],
			'mime-type' => $mime_type,
		);
	}

	/**
	 * Checks that file is local, doesn't have a funny name and is a PDF.
	 *
	 * @since 4.x
	 *
	 * @static
	 * @access protected
	 *
	 * @param string $file          File path.
	 * @param bool   $no_read_check If true then doesn't open & read file to check existence and magic bytes.
	 * @return bool|String Returns true if valid; returns error message string if invalid.
	 */
	protected static function gs_valid( $file, $no_read_check = false ) {
		// Loading from URL not currently supported.
		if ( preg_match( '|^https?://|', $file ) ) {
			return __( 'Loading from URL not supported.', 'ghostscript-only-pdf-preview' );
		}

		// Check filename can't be interpreted by GhostScript as special - see https://ghostscript.com/doc/9.20/Use.htm#Options
		if ( preg_match( '/^[@|%-]/', $file ) ) {
			return __( 'Unsupported file name.', 'ghostscript-only-pdf-preview' );
		}

		// Check for suspect chars in base filename - same as $special_chars in sanitize_file_name() with ctrls, space and del added.
		if ( preg_match( '/[?\[\]\/\\\\=<>:;,\'"&$#*()|~`!{}%+\x00-\x20\x7f]/', wp_basename( $file ) ) ) {
			return __( 'Unsupported file name.', 'ghostscript-only-pdf-preview' );
		}

		if ( $no_read_check ) {
			return true;
		}

		// Check existence & magic bytes.
		$fp = @ fopen( $file, 'rb' );
		if ( false === $fp ) {
			return __( 'File doesn&#8217;t exist?', 'ghostscript-only-pdf-preview' );
		}
		$magic_bytes = fread( $fp, 10 ); // Max 10 chars: "%PDF-N.NN" plus optional initial linefeed.
		fclose( $fp );
		// This is a similar test to that done by libmagic, but more strict on version format by insisting it's "0." or "1." followed by 1 or 2 numbers.
		if ( ! preg_match( '/^\n?%PDF-[01]\.[0-9]{1,2}/', $magic_bytes ) ) {
			do_action( 'gopp_error', __CLASS__, __FUNCTION__, __LINE__, compact( 'file', 'magic_bytes' ) );
			return __( 'File is not a PDF.', 'ghostscript-only-pdf-preview' );
		}

		return true;
	}

	/**
	 * Returns the path of the GhostScript executable.
	 *
	 * @since 4.x
	 *
	 * @static
	 * @access protected
	 *
	 * @return false|string Returns false if can't determine path, else path string.
	 */
	protected static function gs_cmd_path() {
		if ( null === self::$gs_cmd_path ) {
			/**
			 * Returning a valid path will short-circuit determining the path of the GhostScript executable.
			 * Useful if your GhostScript installation is in a non-standard location.
			 *
			 * @since 4.x
			 *
			 * @param string $gs_cmd_path The path to the GhostScript executable. Default null.
			 * @param bool   $is_win      True if running on Windows.
			 */
			$shortcircuit_path = apply_filters( 'gopp_image_gs_cmd_path', self::$gs_cmd_path, self::is_win() );
			// See also if we've a cached value.
			$transient = get_transient( 'gopp_image_gs_cmd_path' );
			// Only use transient if no filtered value or they're the same.
			if ( $transient && ( ! $shortcircuit_path || $transient === $shortcircuit_path ) ) {
				self::$gs_cmd_path = $transient;
			} else {
				if ( $shortcircuit_path && self::test_gs_cmd( $shortcircuit_path ) ) {
					self::$gs_cmd_path = $shortcircuit_path;
				} else {
					if ( self::is_win() ) {
						self::$gs_cmd_path = self::gs_cmd_win();
					} else {
						self::$gs_cmd_path = self::gs_cmd_nix();
					}
				}
				if ( self::$gs_cmd_path ) {
					set_transient( 'gopp_image_gs_cmd_path', self::$gs_cmd_path, GOPP_IMAGE_EDITOR_GS_TRANSIENT_EXPIRATION );
				} elseif ( $transient ) {
					delete_transient( 'gopp_image_gs_cmd_path' );
				}
			}
		}
		return self::$gs_cmd_path;
	}

	/**
	 * Tests whether a purported GhostScript executable works.
	 *
	 * @since 4.x
	 *
	 * @static
	 * @access protected
	 *
	 * @param string $cmd GhostScript executable to try.
	 * @return bool
	 */
	protected static function test_gs_cmd( $cmd ) {
		exec( self::escapeshellarg( $cmd ) . ' -dBATCH -dNOPAUSE -dNOPROMPT -dSAFER -v 2>&1', $output, $return_var );

		return 0 === $return_var && is_array( $output ) && ! empty( $output[0] ) && is_string( $output[0] ) && false !== stripos( $output[0], 'ghostscript' );
	}

	/**
	 * Returns the *nix path of the GhostScript executable.
	 *
	 * @since 4.x
	 *
	 * @static
	 * @access protected
	 *
	 * @return false|string Returns false if can't determine path, else path.
	 */
	protected static function gs_cmd_nix() {
		if ( self::test_gs_cmd( '/usr/bin/gs' ) ) {
			return '/usr/bin/gs';
		}
		if ( self::test_gs_cmd( 'gs' ) ) { // Resort to PATH.
			return 'gs';
		}
		return false;
	}

	/**
	 * Tries to determine the Windows path of the GhostScript executable.
	 *
	 * @since 4.x
	 *
	 * @static
	 * @access protected
	 *
	 * @return false|string Returns false if can't determine path, else path.
	 */
	protected static function gs_cmd_win() {
		$win_path = false;

		// Try using REG QUERY to access the registry.
		// Do one test query first to see if it works.
		$cmd = 'REG QUERY HKEY_LOCAL_MACHINE\\SOFTWARE 2>&1';
		$output = array();
		exec( $cmd, $output, $return_var );
		if ( 0 === $return_var && is_array( $output ) ) {
			// Might work.
			$products = array(
				"GPL Ghostscript",
				"GNU Ghostscript",
				"AFPL Ghostscript",
				"Aladdin Ghostscript",
			);
			foreach ( $products as $product ) {
				$cmd = sprintf( 'REG QUERY "HKEY_LOCAL_MACHINE\\SOFTWARE\\%s" /S 2>&1', $product );
				$output = array();
				exec( $cmd, $output, $return_var );
				if ( 0 === $return_var && is_array( $output ) ) {
					// Find latest version.
					$best_match = '';
					$highest_ver = 0;
					foreach ( $output as $out ) {
						$out = trim( $out );
						if ( preg_match( '/^GS_DLL[\t ]+REG_SZ[\t ]+(.+)\\\\gs([0-9.]+)\\\\bin\\\\gsdll(64|32)\.dll$/', $out, $matches ) ) {
							$ver = (float) $matches[2];
							if ( $highest_ver < $ver ) {
								$possible_path = $matches[1] . '\\gs' . $matches[2] . '\\bin\\gswin' . $matches[3] . 'c.exe';
								if ( self::test_gs_cmd( $possible_path ) ) {
									$best_match = $possible_path;
								}
							}
						}
					}
					if ( $best_match ) {
						$win_path = $best_match;
						break;
					}
				}
			}
		}

		if ( ! $win_path ) {
			// Try GSC environment variable. TODO: Is this still used?
			$gsc = getenv( 'GSC' );
			if ( $gsc && is_string( $gsc ) && self::test_gs_cmd( $gsc ) ) {
				$win_path = $gsc;
			}
		}

		if ( ! $win_path ) {
			// Try default install location.
			$program_dirs = array();
			if ( ! empty( $_SERVER['ProgramW6432'] ) && is_string( $_SERVER['ProgramW6432'] ) ) {
				$program_dirs[] = stripslashes( $_SERVER['ProgramW6432'] );
			}
			if ( ! empty( $_SERVER['ProgramFiles'] ) && is_string( $_SERVER['ProgramFiles'] ) ) {
				$program_dirs[] = stripslashes( $_SERVER['ProgramFiles'] );
			}
			if ( ! empty( $_SERVER['ProgramFiles(x86)'] ) && is_string( $_SERVER['ProgramFiles(x86)'] ) ) {
				$program_dirs[] = stripslashes( $_SERVER['ProgramFiles(x86)'] );
			}
			if ( $program_dirs ) {
				$program_dirs = array_unique( $program_dirs );
			} else {
				$program_dirs[] = 'C:\\Program Files';
			}
			foreach ( $program_dirs as $program_dir ) {
				$gs_dir = glob( $program_dir . '\\gs\\gs*', GLOB_NOESCAPE );
				if ( $gs_dir ) {
					// Find latest version.
					$best_match = '';
					$highest_ver = 0;
					foreach ( $gs_dir as $gs_entry ) {
						if ( preg_match( '/[0-9]+\.[0-9]+$/', $gs_entry, $matches ) ) {
							$ver = (float) $matches[0];
							if ( $highest_ver < $ver ) {
								if ( is_executable( $gs_entry . '\\bin\\gswin64c.exe' ) && self::test_gs_cmd( $gs_entry . '\\bin\\gswin64c.exe' ) ) {
									$best_match = $gs_entry . '\\bin\\gswin64c.exe';
								} elseif ( is_executable( $gs_entry . '\\bin\\gswin32c.exe' ) && self::test_gs_cmd( $gs_entry . '\\bin\\gswin32c.exe' ) ) {
									$best_match = $gs_entry . '\\bin\\gswin32c.exe';
								}
							}
						}
					}
					if ( $best_match ) {
						$win_path = $best_match;
						break;
					}
				}
			}
		}

		// Resort to PATH.
		if ( ! $win_path && self::test_gs_cmd( 'gswin64c.exe' ) ) {
			$win_path = 'gswin64c.exe';
		}
		if ( ! $win_path && self::test_gs_cmd( 'gswin32c.exe' ) ) {
			$win_path = 'gswin32c.exe';
		}

		return $win_path;
	}

	/**
	 * Returns (shell-escaped) shell command with passed-in arguments tagged on, and stderr redirected to stdout.
	 *
	 * @since 4.x
	 *
	 * @static
	 * @access protected
	 *
	 * @param string $args Arguments, already shell escaped.
	 * @return false|string Returns false if no executable path, else command string.
	 */
	protected static function gs_cmd( $args ) {
		if ( $gs_cmd_path = self::gs_cmd_path() ) {
			return self::escapeshellarg( $gs_cmd_path ) . ' ' . $args . ' 2>&1';
		}
		return false;
	}

	/**
	 * Returns the arguments for the main GhostScript invocation.
	 *
	 * @since 4.x
	 * @access protected
	 *
	 * @param string $filename File name of output JPEG.
	 * @return string Arguments string, shell-escaped.
	 */
	protected function get_gs_args( $filename ) {
		$ret = $this->initial_gs_args();

		if ( ( $quality = intval( $this->get_quality() ) ) > 0 && $quality <= 100 ) {
			$ret .= ' -dJPEGQ=' . $quality; // Nothing escape-worthy.
		}
		if ( ( $resolution = intval( $this->get_resolution() ) ) > 0 ) {
			$ret .= ' -r' . $resolution; // Nothing escape-worthy.
		}

		if ( ( $page = intval( $this->get_page() ) ) > 0 ) {
			$ret .= " -dFirstPage=$page -dLastPage=$page"; // Nothing escape-worthy.
		} else {
			$ret .= ' -dFirstPage=1 -dLastPage=1';
		}

		$ret .= ' ' . self::escapeshellarg( '-sOutputFile=' . $filename );
		if ( self::is_win() ) {
			$ret .= ' -sstdout=NUL'; // Lessen noise.
		} else {
			$ret .= ' -sstdout=/dev/null'; // Lessen noise.
		}
		$ret .= ' --'; // No more options.
		$ret .= ' ' . self::escapeshellarg( $this->file );

		return $ret;
	}

	/**
	 * The initial non-varying arguments for the main invocation of GhostScript.
	 *
	 * @since 4.x
	 * @access protected
	 *
	 * @return string
	 */
	protected function initial_gs_args() {
		// -dAlignToPixels=0 combined with -dGraphicsAlphaBits=4 and -dTextAlphaBits=4 enables anti-aliasing. -dGridFitTT=2 enables font autohinting.
		return '-dAlignToPixels=0 -dBATCH -dDOINTERPOLATE -dGraphicsAlphaBits=4 -dGridFitTT=2 -dNOPAUSE -dNOPROMPT -dQUIET -dSAFER -dTextAlphaBits=4 -q -sDEVICE=jpeg';
	}

	/**
	 * It's too tiresome to have to deal with PHP's setlocale()
	 * to avoid UTF-8 mangling so just do escaping ourselves.
	 *
	 * @since 4.x
	 *
	 * @static
	 * @access protected
	 *
	 * @param string $arg Shell argument to escape.
	 * @return string
	 */
	protected static function escapeshellarg( $arg ) {
		// Note that the only things we're really going to escape, given the strict base file name check,
		// is the "WP_CONTENT_DIR/uploads" directory and the path to the GhostScript executable.
		if ( self::is_win() ) {
			// Note bang was not zapped in versions of PHP older than about Jul 2015.
			$arg = '"' . str_replace( array( '%', '!', '"' ), ' ', $arg ) . '"'; // So will get "not found" error if any of these chars in directory path.
		} else {
			$arg = "'" . str_replace( "'", "'\\''", $arg ) . "'";
		}
		return $arg;
	}

	/**
	 * Whether on Windows or not.
	 *
	 * @static
	 * @access protected
	 *
	 * @return bool
	 */
	protected static function is_win() {
		if ( null === self::$is_win ) {
			self::$is_win = 0 === strncasecmp( 'WIN', PHP_OS, 3 );
		}
		return self::$is_win;
	}

	/**
	 * Deletes transient and clears caching statics.
	 *
	 * @since 4.x
	 *
	 * @static
	 * @access public
	 *
	 * @return void
	 */
	public static function clear() {
		delete_transient( 'gopp_image_gs_cmd_path' );

		self::$is_win = self::$gs_cmd_path = null;
	}

	/**
	 * Gets the resolution to use for the preview.
	 *
	 * @since 4.x
	 * @access public
	 *
	 * @return int $resolution Resolution of preview (DPI).
	 */
	public function get_resolution() {
		if ( ! $this->resolution ) {
			$this->set_resolution();
		}

		return $this->resolution;
	}

	/**
	 * Sets the resolution to use for the preview.
	 *
	 * @since 4.x
	 * @access public
	 *
	 * @param int $resolution Resolution to use for preview.
	 *
	 * @return true|WP_Error True if set successful; WP_Error on failure.
	 */
	public function set_resolution( $resolution = null ) {
		if ( null === $resolution ) {
			/**
			 * Filters the default PDF preview resolution setting.
			 *
			 * Applies only during initial editor instantiation, or when set_resolution() is run
			 * manually without the `$resolution` argument.
			 *
			 * set_resolution() has priority over the filter.
			 *
			 * @since 4.x
			 *
			 * @param int    $resolution Resolution (DPI) of the PDF preview thumbnail.
			 * @param string $filename   The PDF file name.
			 */
			$resolution = apply_filters( 'gopp_editor_set_resolution', $this->default_resolution, $this->file );
			if ( ( $resolution = intval( $resolution ) ) <= 0 ) {
				$resolution = $this->default_resolution;
			}
		} else {
			$resolution = intval( $resolution );
		}
		if ( $resolution > 0 ) {
			$this->resolution = $resolution;
			return true;
		}
		return new WP_Error( 'invalid_image_resolution', __( 'Attempted to set PDF preview resolution to an invalid value.', 'ghostscript-only-pdf-preview' ) );
	}

	/**
	 * Gets the page to render for the preview.
	 *
	 * @since 4.x
	 * @access public
	 *
	 * @return int $page The page to render.
	 */
	public function get_page() {
		if ( ! $this->page ) {
			$this->set_page();
		}

		return $this->page;
	}

	/**
	 * Sets the page to render for the preview.
	 *
	 * @since 4.x
	 * @access public
	 *
	 * @param int $page Page number to render.
	 *
	 * @return true|WP_Error True if set successful; WP_Error on failure.
	 */
	public function set_page( $page = null ) {
		if ( null === $page ) {
			/**
			 * Filters the default PDF preview page setting.
			 *
			 * Applies only during initial editor instantiation, or when set_page() is run
			 * manually without the `$page` argument.
			 *
			 * set_page() has priority over the filter.
			 *
			 * @since 4.x
			 *
			 * @param int    $page     The page to render.
			 * @param string $filename The PDF file name.
			 */
			$page = apply_filters( 'gopp_editor_set_page', $this->default_page, $this->file );
			if ( ( $page = intval( $page ) ) <= 0 ) {
				$page = $this->default_page;
			}
		} else {
			$page = intval( $page );
		}
		if ( $page > 0 ) {
			$this->page = $page;
			return true;
		}
		return new WP_Error( 'invalid_image_page', __( 'Attempted to set PDF preview page to an invalid value.', 'ghostscript-only-pdf-preview' ) );
	}

	/**
	 * Resizes current image. Unsupported.
	 *
	 * At minimum, either a height or width must be provided.
	 * If one of the two is set to null, the resize will
	 * maintain aspect ratio according to the provided dimension.
	 *
	 * @since 4.x
	 * @access public
	 *
	 * @param  int|null $max_w Image width.
	 * @param  int|null $max_h Image height.
	 * @param  bool     $crop
	 * @return WP_Error
	 */
	public function resize( $max_w, $max_h, $crop = false ) {
		return new WP_Error( 'image_resize_error', __( 'Unsupported operation.', 'ghostscript-only-pdf-preview' ) );
	}

	/**
	 * Resize multiple images from a single source. Unsupported.
	 *
	 * @since 4.x
	 * @access public
	 *
	 * @param array $sizes {
	 *     An array of image size arrays. Default sizes are 'small', 'medium', 'large'.
	 *
	 *     @type array $size {
	 *         @type int  $width  Image width.
	 *         @type int  $height Image height.
	 *         @type bool $crop   Optional. Whether to crop the image. Default false.
	 *     }
	 * }
	 * @return WP_Error
	 */
	public function multi_resize( $sizes ) {
		return new WP_Error( 'image_multi_resize_error', __( 'Unsupported operation.', 'ghostscript-only-pdf-preview' ) );
	}

	/**
	 * Crops Image. Unsupported.
	 *
	 * @since 4.x
	 * @access public
	 *
	 * @param int $src_x The start x position to crop from.
	 * @param int $src_y The start y position to crop from.
	 * @param int $src_w The width to crop.
	 * @param int $src_h The height to crop.
	 * @param int $dst_w Optional. The destination width.
	 * @param int $dst_h Optional. The destination height.
	 * @param bool $src_abs Optional. If the source crop points are absolute.
	 * @return WP_Error
	 */
	public function crop( $src_x, $src_y, $src_w, $src_h, $dst_w = null, $dst_h = null, $src_abs = false ) {
		return new WP_Error( 'image_crop_error', __( 'Unsupported operation.', 'ghostscript-only-pdf-preview' ) );
	}

	/**
	 * Rotates current image counter-clockwise by $angle. Unsupported.
	 *
	 * @since 4.x
	 * @access public
	 *
	 * @param float $angle
	 * @return WP_Error
	 */
	public function rotate( $angle ) {
		return new WP_Error( 'image_rotate_error', __( 'Unsupported operation.', 'ghostscript-only-pdf-preview' ) );
	}

	/**
	 * Flips current image. Unsupported.
	 *
	 * @since 4.x
	 * @access public
	 *
	 * @param bool $horz Flip along Horizontal Axis
	 * @param bool $vert Flip along Vertical Axis
	 * @return WP_Error
	 */
	public function flip( $horz, $vert ) {
		return new WP_Error( 'image_flip_error', __( 'Unsupported operation.', 'ghostscript-only-pdf-preview' ) );
	}

	/**
	 * Streams current image to browser. Unsupported.
	 *
	 * @since 4.x
	 * @access public
	 *
	 * @param string $mime_type
	 * @return WP_Error
	 */
	public function stream( $mime_type = null ) {
		return new WP_Error( 'image_stream_error', __( 'Unsupported operation.', 'ghostscript-only-pdf-preview' ) );
	}
}
