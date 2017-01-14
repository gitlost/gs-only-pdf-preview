<?php
/**
 * Plugin Name: GhostScript Only PDF Preview
 * Plugin URI: https://github.com/gitlost/ghostscript-only-pdf-preview
 * Description: Uses GhostScript directly to generate PDF previews.
 * Version: 1.0.0
 * Author: gitlost
 * Author URI: https://profiles.wordpress.org/gitlost
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ghostscript-only-pdf-preview
 * Domain Path: /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

// These need to be synced with "readme.txt".
define( 'GOPP_PLUGIN_VERSION', '1.0.0' ); // Sync also "package.json" and "language/ghostscript-only-pdf-preview.pot".
define( 'GOPP_PLUGIN_WP_AT_LEAST_VERSION', '4.7.0' );
define( 'GOPP_PLUGIN_WP_UP_TO_VERSION', '4.7.1' );

define( 'GOPP_REGEN_PDF_PREVIEWS_SLUG', 'gopp-regen-pdf-previews' );

/**
 * Plugin as pseudo-namespace static class.
 */
class GhostScript_Only_PDF_Preview {

	static $hook_suffix = null; // As returned by `add_management_page`.
	static $cap = 'manage_options'; // What capability is needed to regenerate PDF previews.
	static $per_pdf_secs = 20; // Seconds to allow for regenerating the preview of each PDF in the "Regen. PDF Previews" administraion tool.
	static $min_time_limit = 300; // Minimum seconds to set max execution time to on doing regeneration.
	static $poll_interval = 2; // How often to poll for progress info in seconds.
	static $timing_dec_places = 1; // Number of decimal places to show on time (in seconds) taken.

	/**
	 * Called on 'init' action.
	 * Entry point.
	 */
	static function init() {
		load_plugin_textdomain( 'ghostscript-only-pdf-preview', false, basename( dirname( __FILE__ ) ) . '/languages' );

		// Add always in case of front-end use.
		add_filter( 'wp_image_editors', array( __CLASS__, 'wp_image_editors' ) );

		if ( is_admin() ) {
			register_activation_hook( __FILE__, array( __CLASS__, 'activation_hook' ) );
			if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
				add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
				add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
				add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ) );

				add_filter( 'bulk_actions-upload', array( __CLASS__, 'bulk_actions_upload' ) );
				add_filter( 'handle_bulk_actions-upload', array( __CLASS__, 'handle_bulk_actions_upload' ), 10, 3 );
				add_filter( 'removable_query_args', array( __CLASS__, 'removable_query_args' ) );

				add_action( 'current_screen', array( __CLASS__, 'current_screen' ) );
				add_action( 'media_row_actions', array( __CLASS__, 'media_row_actions' ), 100, 3 ); // Add after (most) others due to spinner.
			}
			add_action( 'wp_ajax_gopp_media_row_action', array( __CLASS__, 'gopp_media_row_action' ) );
			add_action( 'wp_ajax_gopp_poll_regen_pdf_previews', array( __CLASS__,  'gopp_poll_regen_pdf_previews' ) );
		}
	}

	/**
	 * Called on 'wp_image_editors' action.
	 * Adds GhostScript `GOPP_Image_Editor_GS` class to head of image editors list.
	 */
	static function wp_image_editors( $image_editors ) {
		if ( ! in_array( 'GOPP_Image_Editor_GS', $image_editors, true ) ) {
			// Quick double-check that exec() is available and we're not in safe_mode.
			if ( function_exists( 'exec' ) && ! ini_get( 'safe_mode' ) ) {
				self::load_gopp_image_editor_gs();
				array_unshift( $image_editors, 'GOPP_Image_Editor_GS' );
			}
		}
		return $image_editors;
	}

	/**
	 * Called on plugin activation.
	 * Checks that `exec` available and "safe_mode" not set. Checks WP versions. Checks GhostScript available.
	 */
	static function activation_hook() {

		// Disabling conditions.

		$plugin_basename = plugin_basename( __FILE__ );

		// Must have exec() available (ie hasn't been disabled by the PHP_INI_SYSTEM directive "disable_functions").
		if ( ! function_exists( 'exec' ) ) {
			deactivate_plugins( $plugin_basename );
			if ( in_array( 'exec', array_map( 'trim', explode( ',', strtolower( ini_get( 'disable_functions' ) ) ) ), true ) ) {
				$msg = sprintf(
					/* translators: %s: url to admin plugins page. */
					__( 'The plugin "GhostScript Only PDF Preview" cannot be activated as it relies on the PHP function <code>exec</code>, which has been disabled on your system via the <code>php.ini</code> directive <code>disable_functions</code>. <a href="%s">Return to Plugins page.</a>', 'ghostscript-only-pdf-preview' ),
					esc_url( self_admin_url( 'plugins.php' ) )
				);
			} else {
				$msg = sprintf(
					/* translators: %s: url to admin plugins page. */
					__( 'The plugin "GhostScript Only PDF Preview" cannot be activated as it relies on the PHP function <code>exec</code> being enabled on your system. <a href="%s">Return to Plugins page.</a>', 'ghostscript-only-pdf-preview' ),
					esc_url( self_admin_url( 'plugins.php' ) )
				);
			}
			wp_die( $msg );
		}

		// If the (somewhat out-dated) suhosin extension is loaded, then need to check its function blacklist also, as it doesn't reflect in function_exists().
		// Theoretically should first check whether the function whitelist exists and whether exec() is not in it, as it overrides the blacklist, but it's unlikely to be used.
		if ( extension_loaded( 'suhosin' ) && in_array( 'exec', array_map( 'trim', explode( ',', strtolower( ini_get( 'suhosin.executor.func.blacklist' ) ) ) ), true ) ) {
			deactivate_plugins( $plugin_basename );
			$msg = sprintf(
				/* translators: %s: url to admin plugins page. */
				__( 'The plugin "GhostScript Only PDF Preview" cannot be activated as it relies on the PHP function <code>exec</code>, which has been disabled on your system via the suhosin extension directive <code>suhosin.executor.func.blacklist</code>. <a href="%s">Return to Plugins page.</a>', 'ghostscript-only-pdf-preview' ),
				esc_url( self_admin_url( 'plugins.php' ) )
			);
			wp_die( $msg );
		}

		// Not compatible with safe_mode, which was deprecated in PHP 5.3.0 & removed in 5.4.0. See http://php.net/manual/en/features.safe-mode.php
		// In particular safe_mode puts the exec() command string through escapeshellcmd(), which ironically makes individual elements put through escapeshellarg() (as we do) unsafe.
		if ( ini_get( 'safe_mode' ) ) {
			deactivate_plugins( $plugin_basename );
			wp_die( sprintf(
				/* translators: %s: url to admin plugins page. */
				__( 'The plugin "GhostScript Only PDF Preview" cannot be activated as it is incompatible with the <code>php.ini</code> directive <code>safe_mode</code>, enabled on your system. <a href="%s">Return to Plugins page.</a>', 'ghostscript-only-pdf-preview' ),
				esc_url( self_admin_url( 'plugins.php' ) )
			) );
		}

		global $wp_version;
		$stripped_wp_version = substr( $wp_version, 0, strspn( $wp_version, '0123456789.' ) ); // Remove any trailing date stuff.
		if ( preg_match( '/^[0-9]+\.[0-9]+$/', $stripped_wp_version ) ) {
			$stripped_wp_version .= '.0'; // Make WP version x.y.z compat.
		}

		// Must be on WP 4.7 at least.
		if ( version_compare( $stripped_wp_version, GOPP_PLUGIN_WP_AT_LEAST_VERSION, '<' ) ) {
			deactivate_plugins( $plugin_basename );
			wp_die( sprintf(
				/* translators: %1$s: lowest compatible WordPress version; %2$s: user's current WordPress version; %3$s: url to admin plugins page. */
				__( 'The plugin "GhostScript Only PDF Preview" cannot be activated as it requires WordPress %1$s to work and you have WordPress %2$s. <a href="%3$s">Return to Plugins page.</a>', 'ghostscript-only-pdf-preview' ),
				GOPP_PLUGIN_WP_AT_LEAST_VERSION, $wp_version, esc_url( self_admin_url( 'plugins.php' ) )
			) );
		}

		// Warnings.

		$admin_notices = array();

		// Check tested version.
		if ( version_compare( $stripped_wp_version, GOPP_PLUGIN_WP_UP_TO_VERSION, '>' ) ) {
			$admin_notices[] = array( 'warning', sprintf(
				/* translators: %1$s: highest WordPress version tested; %2$s: user's current WordPress version. */
				__( '<strong>Warning: untested!</strong> The plugin "GhostScript Only PDF Preview" has only been tested on WordPress versions up to %1$s. You have WordPress %2$s.', 'ghostscript-only-pdf-preview' ),
				GOPP_PLUGIN_WP_UP_TO_VERSION, $wp_version
			) );
		}


		// Clear out any old transients.
		self::load_gopp_image_editor_gs();
		GOPP_Image_Editor_GS::clear();

		// Check if GhostScript available.
		if ( ! GOPP_Image_Editor_GS::test( array() ) ) {
			$admin_notices[] = array( 'warning', __( '<strong>Warning: no GhostScript!</strong> The plugin "GhostScript Only PDF Preview" cannot determine the server location of your GhostScript executable.', 'ghostscript-only-pdf-preview' ) );
		}

		if ( $admin_notices ) {
			set_transient( 'gopp_plugin_admin_notices', $admin_notices, 5 * MINUTE_IN_SECONDS );
		}
	}

	/**
	 * Helper to load GOPP_Image_Editor_GS class.
	 */
	static function load_gopp_image_editor_gs() {
		if ( ! class_exists( 'GOPP_Image_Editor_GS' ) ) {
			if ( ! class_exists( 'WP_Image_Editor' ) ) {
				require ABSPATH . WPINC . '/class-wp-image-editor.php';
			}
			require dirname( __FILE__ ) . '/includes/class-gopp-image-editor-gs.php';
		}
	}

	/**
	 * Called on 'admin_init' action.
	 * Adds the admin notices action.
	 */
	static function admin_init() {
		$admin_notices_action = is_network_admin() ? 'network_admin_notices' : ( is_user_admin() ? 'user_admin_notices' : 'admin_notices' );
		add_action( $admin_notices_action, array( __CLASS__, 'admin_notices' ) );
	}

	/**
	 * Called on 'network_admin_notices', 'user_admin_notices' or 'admin_notices' action.
	 * Outputs any messages in transient or query arg.
	 */
	static function admin_notices() {

		$admin_notices = get_transient( 'gopp_plugin_admin_notices' );
		if ( false !== $admin_notices ) {
			delete_transient( 'gopp_plugin_admin_notices' );
		}
		if ( ! is_array( $admin_notices ) ) {
			$admin_notices = array();
		}

		$current_screen = get_current_screen();
		$current_screen_id = $current_screen ? $current_screen->id : null;
		$is_tool = $current_screen_id === self::$hook_suffix;
		if ( ( $is_tool || 'upload' === $current_screen_id ) && ! empty( $_REQUEST['gopp_rpp'] ) && is_string( $_REQUEST['gopp_rpp'] ) ) {
			if ( preg_match( '/^([0-9]+)_([0-9]+)_([0-9]+)_([0-9.]+)+$/', $_REQUEST['gopp_rpp'], $matches ) ) {
				$cnt = intval( $matches[1] );
				$num_updates = intval( $matches[2] );
				$num_fails = intval( $matches[3] );
				$time = floatval( $matches[4] );
				if ( $cnt < 0 || $num_updates < 0 || $num_fails < 0 || $cnt < $num_updates + $num_fails || $time < 0 ) {
					$admin_notices[] = array( 'error', __( 'Invalid arguments!', 'ghostscript-only-pdf-preview' ) );
				} else {
					if ( 0 === $cnt ) {
						// Note the standard WP behaviour is to be silent on doing bulk action with nothing selected.
						if ( $is_tool ) {
							$admin_notices[] = array( 'error', __( 'No PDFs found!', 'ghostscript-only-pdf-preview' ) );
						}
					} else {
						if ( $num_updates ) {
							$admin_notices[] = array( 'updated', sprintf(
								/* translators: %1$s: formatted number of PDF previews regenerated; %2$s: formatted number of seconds to one decimal place it took. */
								_n( '%1$s PDF preview regenerated in %2$s seconds.', '%1$s PDF previews regenerated in %2$s seconds.', $num_updates, 'ghostscript-only-pdf-preview' ),
									number_format_i18n( $num_updates ), number_format_i18n( $time, self::$timing_dec_places )
							) );
							if ( $cnt > $num_updates + $num_fails ) {
								$admin_notices[] = array( 'warning', sprintf(
									/* translators: %s: formatted number of non-PDFs ignored. */
									_n( '%s non-PDF ignored.', '%s non-PDFs ignored.', $cnt, 'ghostscript-only-pdf-preview' ), number_format_i18n( $cnt - ( $num_updates + $num_fails ) )
								) );
							}
						} else {
							if ( $num_fails ) {
								$admin_notices[] = array( 'warning', __( 'Nothing updated!', 'ghostscript-only-pdf-preview' ) );
							} else {
								$admin_notices[] = array( 'warning', sprintf(
									/* translators: %s: formatted number of non-PDFs ignored. */
									_n( 'Nothing updateable! %s non-PDF ignored.', 'Nothing updateable! %s non-PDFs ignored.', $cnt, 'ghostscript-only-pdf-preview' ), number_format_i18n( $cnt )
								) );
							}
						}
						if ( $num_fails ) {
							$admin_notices[] = array( 'error', sprintf(
								/* translators: %s: formatted number of PDF previews that failed to regenerate. */
								_n( '%s PDF preview not regenerated.', '%s PDF previews not regenerated.', $num_fails, 'ghostscript-only-pdf-preview' ), number_format_i18n( $num_fails )
							) );
						}
					}
				}
				if ( $is_tool ) {
					$admin_notices[] = array( 'info', __( 'You can go again below if you want.', 'ghostscript-only-pdf-preview' ) );
				}
			}
		}

		if ( $admin_notices ) {

			foreach ( $admin_notices as $admin_notice ) {
				list( $type, $notice ) = $admin_notice;
				if ( 'error' === $type ) {
					?>
						<div class="notice error is-dismissible">
							<p><?php echo $notice; ?></p>
						</div>
					<?php
				} elseif ( 'updated' === $type ) {
					?>
						<div class="notice updated is-dismissible">
							<p><?php echo $notice; ?></p>
						</div>
					<?php
				} else {
					?>
						<div class="notice notice-<?php echo $type; ?> is-dismissible">
							<p><?php echo $notice; ?></p>
						</div>
					<?php
				}
			}
		}
	}

	/**
	 * Called on 'admin_menu' action.
	 * Adds the "Regen. PDF Previews" administration tool.
	 */
	static function admin_menu() {
		self::$hook_suffix = add_management_page(
			__( 'Regenerate PDF Previews', 'ghostscript-only-pdf-preview' ), __( 'Regen. PDF Previews', 'ghostscript-only-pdf-preview' ),
			self::$cap, GOPP_REGEN_PDF_PREVIEWS_SLUG, array( __CLASS__, 'regen_pdf_previews' )
		);
		if ( self::$hook_suffix ) {
			add_action( 'load-' . self::$hook_suffix, array( __CLASS__, 'load_regen_pdf_previews' ) );
		}
	}

	/**
	 * Called on 'load-tools_page_GOPP_REGEN_PDF_PREVIEWS_SLUG' action.
	 * Does the work of the "Regen. PDF Previews" administration tool. Just loops through all uploaded PDFs.
	 */
	static function load_regen_pdf_previews() {
		if ( ! current_user_can( self::$cap ) ) {
			wp_die( __( 'Sorry, you are not allowed to access this page.', 'ghostscript-only-pdf-preview' ) );
		}

		if ( ! empty( $_REQUEST[GOPP_REGEN_PDF_PREVIEWS_SLUG] ) ) {

			check_admin_referer( GOPP_REGEN_PDF_PREVIEWS_SLUG );

			global $wpdb;
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_mime_type = %s", 'attachment', 'application/pdf' ) );

			list( $cnt, $num_updates, $num_fails, $time ) = self::do_regen_pdf_previews( $ids );

			$redirect = add_query_arg( 'gopp_rpp', "{$cnt}_{$num_updates}_{$num_fails}_{$time}", admin_url( 'tools.php?page=' . GOPP_REGEN_PDF_PREVIEWS_SLUG ) );

			wp_redirect( esc_url_raw( $redirect ) );
			if ( defined( 'GOPP_TESTING' ) && GOPP_TESTING ) { // Allow for testing.
				wp_die( $redirect, 'wp_redirect', array( $cnt, $num_updates, $num_fails, $time ) );
			}
			exit;
		}
	}

	/**
	 * Does the actual PDF preview regenerate.
	 */
	static function do_regen_pdf_previews( $ids, $check_mime_type = false, $do_transient = true ) {

		$cnt = $num_updates = $num_fails = $time = 0;
		if ( $ids ) {
			$time = microtime( true );
			$cnt = count( $ids );
			self::set_time_limit( max( $cnt * self::$per_pdf_secs, self::$min_time_limit ) );
			if ( $do_transient ) {
				delete_transient( 'gopp_plugin_poll_rpp' );
				$last_sub_time = -1;
			}
			foreach ( $ids as $idx => $id ) {
				if ( $check_mime_type && 'application/pdf' !== get_post_mime_type( $id ) ) {
					continue;
				}
				$file = get_attached_file( $id );
				if ( false === $file || '' === $file ) {
					$num_fails++;
				} else {
					$meta = wp_generate_attachment_metadata( $id, $file );
					if ( ! $meta ) {
						$num_fails++;
					} else {
						// wp_update_attachment_metadata() returns false if nothing to update so check first.
						$old_value = get_metadata( 'post', $id, '_wp_attachment_metadata' );
						if ( ( $old_value && is_array( $old_value ) && 1 === count( $old_value ) && $old_value[0] === $meta ) || false !== wp_update_attachment_metadata( $id, $meta ) ) {
							$num_updates++;
						} else {
							$num_fails++;
						}
					}
				}
				if ( $do_transient && ( $sub_time = round( microtime( true ) - $time ) ) && 0 === $sub_time % self::$poll_interval && $sub_time > $last_sub_time ) {
					set_transient( 'gopp_plugin_poll_rpp', array( $cnt, $idx ), self::$poll_interval + 2 ); // Allow some comms time.
					$last_sub_time = $sub_time;
				}
			}
			if ( $do_transient ) {
				delete_transient( 'gopp_plugin_poll_rpp' );
			}
			$time = round( microtime( true ) - $time, self::$timing_dec_places );
		}
		return array( $cnt, $num_updates, $num_fails, $time );
	}

	/**
	 * Helper to set the max_execution_time.
	 */
	static function set_time_limit( $time_limit ) {
		$max_execution_time = ini_get( 'max_execution_time' );
		if ( $max_execution_time && $time_limit > $max_execution_time ) {
			return @ set_time_limit( $time_limit );
		}
		return null;
	}

	/**
	 * Callback for regenerate PDF previews (admin tools menu).
	 * Outputs the front page of the "Regen. PDF Previews" administration tool. Basically a single button with some guff.
	 */
	static function regen_pdf_previews() {
		if ( ! current_user_can( self::$cap ) ) {
			wp_die( __( 'Sorry, you are not allowed to access this page.', 'ghostscript-only-pdf-preview' ) );
		}
		global $wpdb;
		$cnt = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_mime_type = %s", 'attachment', 'application/pdf' ) );
		?>
	<div id="gopp_regen_pdf_previews" class="wrap">
		<h1><?php _e( "GhostScript Only PDF Preview - Regenerate PDF Previews", 'ghostscript-only-pdf-preview' ); ?></h1>
		<?php
		if ( ! $cnt ) {
			?>
			<p>
				<?php _e( 'This tool is for regenerating the thumbnail previews of PDFs, but no PDFs have been uploaded, so it has nothing to do.', 'ghostscript-only-pdf-preview' ); ?>
			</p>
			<?php
		} else {
			// Test setting max execution time.
			$max_execution_msg = '';
			if ( false === self::set_time_limit( max( $cnt * self::$per_pdf_secs, self::$min_time_limit ) ) ) {
				$max_execution_msg = __( '<strong>Warning: cannot set max execution time!</strong> The maximum time allowed for a PHP script to run on your system could not be set, so you may experience the White Screen Of Death (WSOD) on trying this.', 'ghostscript-only-pdf-preview' );
			}
			?>
			<form class="gopp_regen_pdf_previews_form" method="GET">
				<input type="hidden" name="page" value="<?php echo GOPP_REGEN_PDF_PREVIEWS_SLUG; ?>" />
				<?php wp_nonce_field( GOPP_REGEN_PDF_PREVIEWS_SLUG ) ?>
				<input type="hidden" id="poll_cnt" name="poll_cnt" value="<?php echo esc_attr( $cnt ); ?>" />
				<input type="hidden" id="poll_nonce" name="poll_nonce" value="<?php echo esc_attr( wp_create_nonce( 'gopp_poll_regen_pdf_previews_' . $cnt, 'poll_nonce' ) ); ?>" />
				<p class="gopp_regen_pdf_previews_form_hide">
					<?php _e( 'Regenerate the thumbnail previews of PDFs uploaded to your system.', 'ghostscript-only-pdf-preview' ); ?>
				</p>
				<p class="gopp_regen_pdf_previews_form_hide">
					<?php
						/* translators: %s: formatted number of PDFs found. */
						echo sprintf( _n( '<strong>%s</strong> PDF has been found.', '<strong>%s</strong> PDFs have been found.', $cnt, 'ghostscript-only-pdf-preview' ), number_format_i18n( $cnt ) );
					?>
				</p>
				<input id="<?php echo GOPP_REGEN_PDF_PREVIEWS_SLUG; ?>" class="button" name="<?php echo GOPP_REGEN_PDF_PREVIEWS_SLUG; ?>" value="<?php echo esc_attr( __( 'Regenerate PDF Previews', 'ghostscript-only-pdf-preview' ) ); ?>" type="submit" />
				<?php if ( $cnt > 10 ) : ?>
				<p>
					<?php echo sprintf(
							/* translators: %s: formatted number (greater than 10) of PDFs found. */
							__( 'Regenerating %s PDF previews can take a long time.', 'ghostscript-only-pdf-preview' ), number_format_i18n( $cnt )
						); ?>
				</p>
				<?php endif; ?>
				<?php if ( $max_execution_msg ) : ?>
				<p>
					<?php echo $max_execution_msg; ?>
				</p>
				<?php endif; ?>
				<p>
					<?php
						/* translators: %s: url to the Media Library page in list mode. */
						echo sprintf( __( 'Note that you can also regenerate PDF previews in batches or individually using the bulk action "Regenerate PDF Previews" (or the row action "Regenerate Preview") available in the <a href="%s">list mode of the Media Library</a>.', 'ghostscript-only-pdf-preview' ), admin_url( 'upload.php?mode=list' ) );
					?>
				</p>
				<?php if ( defined( 'GOPP_PLUGIN_DEBUG' ) && GOPP_PLUGIN_DEBUG ) : ?>
					<?php require_once dirname( __FILE__ ) . '/includes/debug-gopp-image-editor-gs.php'; ?>
					<?php DEBUG_GOPP_Image_Editor_GS::dump(); ?>
				<?php endif; ?>
			</form>
			<?php
		}

		?>
	</div>
		<?php
	}

	/**
	 * Called on 'admin_enqueue_scripts' action.
	 * Outputs some javascript on "Regen. PDFs Previews" page or Media Library page.
	 */
	static function admin_enqueue_scripts( $hook_suffix ) {
		if ( self::$hook_suffix === $hook_suffix || 'upload.php' === $hook_suffix ) {
			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
			$is_regen_pdf_preview = self::$hook_suffix === $hook_suffix;
			$is_upload = 'upload.php' === $hook_suffix;

			wp_enqueue_script( 'ghostscript-only-pdf-preview', plugins_url( "js/ghostscript-only-pdf-preview{$suffix}.js", __FILE__ ), array( 'jquery', 'jquery-migrate' ), GOPP_PLUGIN_VERSION );

			// Our parameters.
			$params = array(
				'val' => array( // Gets around stringification of direct localize elements.
					'is_debug' => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG && defined( 'GOPP_PLUGIN_DEBUG' ) && GOPP_PLUGIN_DEBUG,
					'is_regen_pdf_preview' => $is_regen_pdf_preview,
					'is_upload' => $is_upload,
				),
			);
			if ( $is_regen_pdf_preview ) {
				$params['please_wait_msg'] = '<div class="notice notice-warning inline"><p>' . __( 'Please wait...', 'ghostscript-only-pdf-preview' )
											. '<span id="gopp_progress"></span>' . self::spinner( -2, true ) . '</p></div>';
				$params['val']['poll_interval'] = self::$poll_interval;
			} elseif ( $is_upload ) {
				$params['no_items_selected_msg'] = '<span class="gopp_none" style="display:inline-block;margin-top:6px">' . __( 'No items selected!', 'ghostscript-only-pdf-preview' ) . '</span>';
				$params['action_not_available'] = __( 'Regenerate Preview ajax action not available!', 'ghostscript-only-pdf-preview' );
				$params['spinner'] = self::spinner( 5, true );
				$params['val']['min_time_limit'] = self::$min_time_limit;
			}
			$params = apply_filters( 'gopp_plugin_params', $params );
			wp_localize_script( 'ghostscript-only-pdf-preview', 'gopp_plugin_params', $params );
		}
	}

	/**
	 * Return spinner HTML, catering for IE perculiarities.
	 */
	static function spinner( $margin_top = 0, $is_active = false ) {
		$is_active = $is_active ? ' is-active' : '';
		if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) && (
			false !== strpos( $_SERVER['HTTP_USER_AGENT'], 'Edge' ) || false !== strpos( $_SERVER['HTTP_USER_AGENT'], 'MSIE' ) || false !== strpos( $_SERVER['HTTP_USER_AGENT'], 'Trident' )
		) ) {
			// See http://stackoverflow.com/a/1904931/664741
			$ret = '<img class="spinner' . $is_active . '" src="' . admin_url( 'images/spinner.gif' ) . '" width="20" height="20" style="float:none;margin-top:' . $margin_top . 'px;" />';
		} else {
			$ret = '<span class="spinner' . $is_active . '" style="float:none;margin-top:' . $margin_top . 'px;"></span>';
		}
		return $ret;
	}

	/**
	 * Called on 'bulk_actions-upload' filter.
	 * Adds "Regenerate PDF Previews" to the bulk action dropdown in the list mode of the Media Library.
	 */
	static function bulk_actions_upload( $actions ) {
		if ( current_user_can( self::$cap ) ) {
			$actions['gopp_regen_pdf_previews'] = __( 'Regenerate PDF Previews', 'ghostscript-only-pdf-preview' );
		}
		return $actions;
	}

	/**
	 * Called on 'handle_bulk_actions-upload' filter.
	 * Does the work of the "Regenerate PDF Previews" bulk action.
	 */
	static function handle_bulk_actions_upload( $location, $doaction, $post_ids ) {

		if ( 'gopp_regen_pdf_previews' === $doaction && current_user_can( self::$cap ) ) {
			// Note nonce has already been checked in "wp-admin/upload.php".
			// But $post_ids hasn't (and triggers a PHP Warning if nothing selected in "wp-admin/upload.php:168" as of WP 4.7.1).
			$ids = $post_ids && is_array( $post_ids ) ? array_map( 'intval', $post_ids ) : array();

			list( $cnt, $num_updates, $num_fails, $time ) = self::do_regen_pdf_previews( $ids, true /*check_mime_type*/, false /*do_transient*/ );

			$location = add_query_arg( 'gopp_rpp', "{$cnt}_{$num_updates}_{$num_fails}_{$time}", $location );
		}
		return $location;
	}

	/**
	 * Called on 'removable_query_args' filter.
	 * Signals that our query arg should be removed from the URL.
	 */
	static function removable_query_args( $removable_query_args ) {
		$removable_query_args[] = 'gopp_rpp';
		return $removable_query_args;
	}

	/**
	 * Called on 'current_screen' action.
	 * Removes our query arg from the URL.
	 */
	static function current_screen( $current_screen ) {
		if ( self::$hook_suffix === $current_screen->id || 'upload' === $current_screen->id ) {
			$_SERVER['REQUEST_URI'] = remove_query_arg( array( 'gopp_rpp' ), $_SERVER['REQUEST_URI'] );
		}
	}

	/**
	 * Called on 'media_row_actions' filter.
	 * Adds the "Regenerate Preview" link to PDF rows in the list mode of the Media Library.
	 */
	static function media_row_actions( $actions, $post, $detached ) {
		if ( 'application/pdf' === $post->post_mime_type && current_user_can( self::$cap ) ) {
			self::load_gopp_image_editor_gs();
			if ( GOPP_Image_Editor_GS::test( array( 'path' => get_attached_file( $post->ID ) ) ) ) {
				$actions['gopp_regen_pdf_preview'] = sprintf(
					'<a href="#the-list" onclick="return gopp_plugin.media_row_action( event, %d, %s );" class="hide-if-no-js aria-button-if-js" aria-label="%s">%s</a>' . self::spinner( -3 ),
					$post->ID,
					esc_attr( json_encode( wp_create_nonce( 'gopp_media_row_action_' . $post->ID ) ) ),
					/* translators: %s: attachment title */
					esc_attr( sprintf( __( 'Regenerate the PDF preview for &#8220;%s&#8221;', 'ghostscript-only-pdf-preview' ), _draft_or_post_title() ) ),
					esc_attr( __( 'Regenerate&nbsp;Preview', 'ghostscript-only-pdf-preview' ) )
				);
			}
		}
		return $actions;
	}

	/**
	 * Ajax callback for media row action.
	 * Does the work of the "Regenerate Preview" row action.
	 */
	static function gopp_media_row_action() {
		$ret = array( 'msg' => '', 'error' => false, 'img' => '' );

		if ( ! current_user_can( self::$cap ) ) {
			$ret['error'] = __( 'Sorry, you are not allowed to access this page.', 'ghostscript-only-pdf-preview' );
		} else {
			$id = isset( $_POST['id'] ) && is_string( $_POST['id'] ) ? intval( $_POST['id'] ) : '';

			if ( ! check_ajax_referer( 'gopp_media_row_action_' . $id, 'nonce', false /*die*/ ) ) {
				$ret['error'] = __( 'Invalid nonce.', 'ghostscript-only-pdf-preview' );
			} else {
				if ( ! $id || 'application/pdf' !== get_post_mime_type( $id ) ) {
					$ret['error'] = __( 'Invalid ID.', 'ghostscript-only-pdf-preview' );
				} else {

					list( $cnt, $num_updates, $num_fails, $time ) = self::do_regen_pdf_previews( array( $id ), false /*check_mime_type*/, false /*do_transient*/ );

					if ( $num_fails || 1 !== $num_updates ) {
						$ret['error'] = __( 'Failed to generate the PDF preview.', 'ghostscript-only-pdf-preview' );
					} else {
						$ret['img'] = wp_get_attachment_image( $id, array( 60, 60 ), true, array( 'alt' => '' ) );
						if ( preg_match( '/^(<img.*?src="[^"]+\.jpg)(" .*\/>)$/', $ret['img'], $matches ) ) {
							$ret['img'] = $matches[1] . '?gopp=' . rand() . $matches[2];
							$ret['msg'] = __( 'Successfully regenerated the PDF preview. It\'s best to refresh your browser to see the updated thumbnail correctly.', 'ghostscript-only-pdf-preview' );
						} else {
							$ret['msg'] = __( 'Successfully regenerated the PDF preview. You will need to refresh your browser to see the updated thumbnail.', 'ghostscript-only-pdf-preview' );
						}
					}
				}
			}
		}

		if ( defined( 'GOPP_TESTING' ) && GOPP_TESTING ) { // Allow for testing.
			return json_encode( $ret );
		}
		wp_send_json( $ret );
	}

	/**
	 * Ajax callback for progess polling when using the "Regen. PDF Previews" administration tool. 
	 */
	static function gopp_poll_regen_pdf_previews() {
		$ret = array( 'msg' => '' );

		if ( current_user_can( self::$cap ) ) {
			$cnt = isset( $_REQUEST['cnt'] ) && is_string( $_REQUEST['cnt'] ) ? intval( $_REQUEST['cnt'] ) : 0;

			check_ajax_referer( 'gopp_poll_regen_pdf_previews_' . $cnt, 'poll_nonce' );

			if ( $cnt && ( $transient = get_transient( 'gopp_plugin_poll_rpp' ) ) ) {
				list( $check_cnt, $idx ) = $transient;
				if ( $check_cnt === $cnt ) {
					$idx++;
					/* translators: %1$d: percentage of PDF previews completed; %2$d: completed count. */
					$ret['msg'] = sprintf( __( '%d%% (%d)', 'ghostscript-only-pdf-preview' ), round( $idx * 100 / $cnt ), $idx );
				}
			}
		}

		if ( defined( 'GOPP_TESTING' ) && GOPP_TESTING ) { // Allow for testing.
			return json_encode( $ret );
		}
		wp_send_json( $ret );
	}
}

add_action( 'init', array( 'GhostScript_Only_PDF_Preview', 'init' ) );
