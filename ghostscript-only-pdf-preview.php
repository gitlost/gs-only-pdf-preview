<?php
/**
 * Plugin Name: GhostScript Only PDF Preview
 * Plugin URI: https://github.com/gitlost/ghostscript-only-pdf-preview
 * Description: Uses GhostScript directly to generate PDF previews.
 * Version: 0.9.0
 * Author: gitlost
 * Author URI: https://profiles.wordpress.org/gitlost
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ghostscript-only-pdf-preview
 * Domain Path: /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'GOPP_PLUGIN_VERSION' ) ) {
	// These need to be synced with "readme.txt".
	define( 'GOPP_PLUGIN_VERSION', '0.9.0' ); // Sync also "package.json" and "language/ghostscript-only-pdf-preview.pot".
	define( 'GOPP_PLUGIN_WP_AT_LEAST_VERSION', '4.7.0' );
	define( 'GOPP_PLUGIN_WP_UP_TO_VERSION', '4.7.0' );
}

load_plugin_textdomain( 'ghostscript-only-pdf-preview', false, basename( dirname( __FILE__ ) ) . '/languages' );

register_activation_hook( __FILE__, 'gopp_plugin_activation_hook' );
add_action( 'admin_init', 'gopp_plugin_admin_init' );
if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
	add_action( 'admin_menu', 'gopp_plugin_admin_menu' );
	add_action( 'admin_enqueue_scripts', 'gopp_plugin_admin_enqueue_scripts' );
	add_action( 'media_row_actions', 'gopp_plugin_media_row_actions', 100, 3 ); // Add after most others due to spinner.
} else {
	add_action( 'wp_ajax_gopp_media_row_action', 'gopp_plugin_gopp_media_row_action' );
}

/**
 * Called on plugin activation.
 */
function gopp_plugin_activation_hook() {

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

	if ( ! class_exists( 'GOPP_Image_Editor_GS' ) ) {
		if ( ! class_exists( 'WP_Image_Editor' ) ) {
			require ABSPATH . WPINC . '/class-wp-image-editor.php';
		}
		require dirname( __FILE__ ) . '/includes/class-gopp-image-editor-gs.php';
	}

	// Clear out any old transients.
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
 * Called on 'admin_init' action.
 */
function gopp_plugin_admin_init() {
	add_filter( 'wp_image_editors', 'gopp_plugin_wp_image_editors' );
	$admin_notices_action = is_network_admin() ? 'network_admin_notices' : ( is_user_admin() ? 'user_admin_notices' : 'admin_notices' );
	add_action( $admin_notices_action, 'gopp_plugin_admin_notices' );
}

/**
 * Called on 'wp_image_editors' action.
 * Adds GhostScript `GOPP_Image_Editor_GS` class to head of image editors list.
 */
function gopp_plugin_wp_image_editors( $image_editors ) {
	if ( ! in_array( 'GOPP_Image_Editor_GS', $image_editors, true ) ) {
		// Double-check that exec() is available and we're not in safe_mode.
		if ( function_exists( 'exec' ) && ! ini_get( 'safe_mode' ) ) {
			if ( ! class_exists( 'GOPP_Image_Editor_GS' ) ) {
				require dirname( __FILE__ ) . '/includes/class-gopp-image-editor-gs.php';
			}
			array_unshift( $image_editors, 'GOPP_Image_Editor_GS' );
		}
	}
	error_log( "gopp_plugin_wp_image_editors: image_editors=" . print_r( $image_editors, true ) );
	return $image_editors;
}

/**
 * Called on 'network_admin_notices', 'user_admin_notices' or 'admin_notices' action.
 * Output any messages.
 */
function gopp_plugin_admin_notices() {

	$admin_notices = get_transient( 'gopp_plugin_admin_notices' );
	if ( false !== $admin_notices ) {
		delete_transient( 'gopp_plugin_admin_notices' );
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

define( 'GOPP_REGEN_PDF_PREVIEWS_SLUG', 'gopp-regen-pdf-previews' );

global $gopp_plugin_hook_suffix;
$gopp_plugin_hook_suffix = null;

global $gopp_plugin_cap;
$gopp_plugin_cap = 'manage_options';

/**
 * Called on 'admin_menu' action.
 */
function gopp_plugin_admin_menu() {
	global $gopp_plugin_hook_suffix, $gopp_plugin_cap;
	$gopp_plugin_hook_suffix = add_management_page( __( 'Regenerate PDF Previews', 'ghostscript-only-pdf-preview' ), __( 'Regen. PDF Previews', 'ghostscript-only-pdf-preview' ), $gopp_plugin_cap, GOPP_REGEN_PDF_PREVIEWS_SLUG, 'gopp_plugin_regen_pdf_previews' );
	if ( $gopp_plugin_hook_suffix ) {
		add_action( 'load-' . $gopp_plugin_hook_suffix, 'gopp_plugin_load_regen_pdf_previews' );
	}
}

/**
 * Called on 'load-tools_page_GOPP_REGEN_PDF_PREVIEWS_SLUG' action.
 */
function gopp_plugin_load_regen_pdf_previews() {
	global $gopp_plugin_cap;
	if ( ! current_user_can( $gopp_plugin_cap ) ) {
		wp_die( __( 'Sorry, you are not allowed to access this page.', 'ghostscript-only-pdf-preview' ) );
	}

	if ( ! empty( $_REQUEST[GOPP_REGEN_PDF_PREVIEWS_SLUG] ) ) {

		check_admin_referer( GOPP_REGEN_PDF_PREVIEWS_SLUG );

		$redirect = admin_url( 'tools.php?page=' . GOPP_REGEN_PDF_PREVIEWS_SLUG );
		$admin_notices = array();

		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_mime_type = %s", 'attachment', 'application/pdf' ) );
		if ( ! $ids ) {
			$admin_notices[] = array( 'error', __( 'No PDFs found', 'ghostscript-only-pdf-preview' ) );
		} else {
			$max_execution_time = count( $ids ) * 10;
			if ( $max_execution_time > ini_get( 'max_execution_time' ) ) {
				@ set_time_limit( $max_execution_time );
			}
			$num_updates = $num_fails = 0;
			foreach ( $ids as $id ) {
				$file = get_attached_file( $id );
				if ( false === $file ) {
					$num_fails++;
					error_log( "gopp_plugin_load_regen_pdf_previews: fail get_attached_file id=$id" );
				} else {
					$meta = wp_generate_attachment_metadata( $id, $file );
					if ( ! $meta ) {
						$num_fails++;
						error_log( "gopp_plugin_load_regen_pdf_previews: fail wp_generate_attachment_metadata id=$id, file=$file" );
						$editor = wp_get_image_editor( $file );
						if ( is_wp_error( $editor ) ) { // No support for this type of file
							error_log( "gopp_plugin_load_regen_pdf_previews: wp_error wp_get_image_editor editor=" . print_r( $editor, true ) );
						} else {
							$uploaded = $editor->save( $file, 'image/jpeg' );
							unset( $editor );
							if ( is_wp_error( $uploaded ) ) {
								error_log( "gopp_plugin_load_regen_pdf_previews: wp_error save uploaded=" . print_r( $uploaded, true ) );
							} else {
								$editor = wp_get_image_editor( $uploaded['path'] );
								unset( $uploaded['path'] );
								if ( is_wp_error( $editor ) ) {
									error_log( "gopp_plugin_load_regen_pdf_previews: wp_error wp_get_image_editor 2 uploaded['path']={$uploaded['path']}, editor=" . print_r( $editor, true ) );
								}
							}
						}
					} else {
						// wp_update_attachment_metadata() returns false if nothing to update so check first.
						$old_value = get_metadata( 'post', $id, '_wp_attachment_metadata' );
						if ( $old_value && is_array( $old_value ) && 1 === count( $old_value ) && $old_value[0] === $meta ) {
							$num_updates++;
						} else {
							if ( false === wp_update_attachment_metadata( $id, $meta ) ) {
								error_log( "gopp_plugin_load_regen_pdf_previews: fail wp_update_attachment_metadata id=$id, file=$file, $meta=" . print_r( $meta, true ) );
								$num_fails++;
							} else {
								$num_updates++;
							}
						}
					}
				}
			}
			if ( $num_updates ) {
				/* translators: %s: formatted number of PDF previews regenerated. */
				$admin_notices[] = array( 'updated', sprintf(
					_n( '%s PDF preview regenerated.', '%s PDF previews regenerated.', $num_updates, 'ghostscript-only-pdf-preview' ), number_format_i18n( $num_updates )
				) );
			} else {
				$admin_notices[] = array( 'updated', __( 'Nothing updated!', 'ghostscript-only-pdf-preview' ) );
			}
			if ( $num_fails ) {
				/* translators: %s: formatted number of PDF previews that failed to regenerate. */
				$admin_notices[] = array( 'warning', sprintf(
					_n( '%s PDF preview not regenerated.', '%s PDF previews not regenerated.', $num_fails, 'ghostscript-only-pdf-preview' ), number_format_i18n( $num_fails )
				) );
			}
		}

		if ( $admin_notices ) {
			set_transient( 'gopp_plugin_admin_notices', $admin_notices, 5 * MINUTE_IN_SECONDS );
		}

		wp_redirect( esc_url_raw( $redirect ) );
		if ( defined( 'GOPP_TESTING' ) && GOPP_TESTING ) { // Allow for testing.
			wp_die( $redirect, 'wp_redirect', $admin_notices );
		}
		exit;
	}
}

/**
 * Callback for regenerate PDF previews (admin tools menu).
 */
function gopp_plugin_regen_pdf_previews() {
	global $gopp_plugin_cap;
	if ( ! current_user_can( $gopp_plugin_cap ) ) {
		wp_die( __( 'Sorry, you are not allowed to access this page.', 'ghostscript-only-pdf-preview' ) );
	}
	global $wpdb;
	$num_pdfs = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_mime_type = %s", 'attachment', 'application/pdf' ) );
	?>
<div id="gopp_regen_pdf_previews" class="wrap">
	<h1><?php _e( "GhostScript Only PDF Preview - Regenerate PDF Previews", 'ghostscript-only-pdf-preview' ); ?></h1>
	<?php
	if ( ! $num_pdfs ) {
		?>
		<p>
			<?php _e( 'This tool is for regenerating the thumbnail previews of PDFs, but no PDFs have been uploaded, so it has nothing to do.', 'ghostscript-only-pdf-preview' ); ?>
		</p>
		<?php
	} else {
		// Test setting max execution time.
		$max_execution_msg = '';
		$max_execution_time = $num_pdfs * 10; // Allow 10 seconds per PDF.
		if ( $max_execution_time > ini_get( 'max_execution_time' ) && ! @ set_time_limit( $max_execution_time ) ) {
			$max_execution_msg = __( '<strong>Warning: cannot set max execution time!</strong> The maximum time allowed for a PHP script to run on your system could not be set, so you may experience the White Screen Of Death (WSOD) on trying this.', 'ghostscript-only-pdf-preview' );
		}
		?>
		<form class="gopp_regen_pdf_previews_form" method="GET">
			<input type="hidden" name="page" value="<?php echo GOPP_REGEN_PDF_PREVIEWS_SLUG; ?>" />
			<?php wp_nonce_field( GOPP_REGEN_PDF_PREVIEWS_SLUG ) ?>
			<p class="gopp_regen_pdf_previews_form_hide">
				<?php _e( 'This tool regenerates the thumbnail previews of PDFs uploaded to your system.', 'ghostscript-only-pdf-preview' ); ?>
				<?php echo sprintf( _n( '%s PDF has been found.', '%s PDFs have been found.', $num_pdfs, 'ghostscript-only-pdf-preview' ), number_format_i18n( $num_pdfs ) ); ?>
			</p>
			<input id="<?php echo GOPP_REGEN_PDF_PREVIEWS_SLUG; ?>" class="button" name="<?php echo GOPP_REGEN_PDF_PREVIEWS_SLUG; ?>" value="<?php echo esc_attr( __( 'Regenerate PDF Previews', 'ghostscript-only-pdf-preview' ) ); ?>" type="submit" />
			<?php if ( $num_pdfs > 10 ) : ?>
			<p>
				<?php _e( 'Regenerating PDF previews can take a long time.', 'ghostscript-only-pdf-preview' ); ?>
			</p>
			<?php endif; ?>
			<?php if ( $max_execution_msg ) : ?>
			<p>
				<?php echo $max_execution_msg; ?>
			</p>
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
 */
function gopp_plugin_admin_enqueue_scripts( $hook_suffix ) {
	global $gopp_plugin_hook_suffix;
	if ( $gopp_plugin_hook_suffix === $hook_suffix ) {
		add_action( 'admin_print_footer_scripts', 'gopp_plugin_admin_print_footer_scripts' );
	} elseif ( 'upload.php' === $hook_suffix ) {
		add_action( 'admin_print_footer_scripts', 'gopp_plugin_admin_print_footer_scripts_upload' );
	}
}

/**
 * Called on 'admin_print_footer_scripts' action.
 */
function gopp_plugin_admin_print_footer_scripts() {
	$please_wait_msg = '<div class="notice notice-warning inline"><p>' . __( 'Please wait...', 'ghostscript-only-pdf-preview' ) . '<span class="spinner is-active" style="float:none;margin-top:0;"></span></p></div>';
	?>
<script type="text/javascript">
/*jslint ass: true, nomen: true, plusplus: true, regexp: true, vars: true, white: true, indent: 4 */
/*global jQuery */
( function ( $ ) {
	'use strict';
	// jQuery ready.
	$( function () {
		var $wrap = $( '#gopp_regen_pdf_previews' ), $msgs = $( '.notice, .updated', $wrap ), $form = $( '.gopp_regen_pdf_previews_form', $wrap );
		if ( $wrap.length ) {
			$( 'input[type="submit"]', $form ).click( function ( e ) {
				var $this = $( this ), $msg = $( <?php echo json_encode( $please_wait_msg ); ?> );
				$this.hide();
				$( '.gopp_regen_pdf_previews_form_hide', $wrap ).hide();
				$msgs.hide();
				$( 'h1', $wrap ).first().after( $msg );
			} );
		}
	} );
} )( jQuery );
</script>
	<?php
}

/**
 * Called on 'admin_print_footer_scripts' action.
 */
function gopp_plugin_admin_print_footer_scripts_upload() {
	?>
<script type="text/javascript">
/*jslint ass: true, nomen: true, plusplus: true, regexp: true, vars: true, white: true, indent: 4 */
/*global jQuery, ajaxurl, gopp_plugin */
var gopp_plugin = gopp_plugin || {}; // Our namespace.
( function ( $ ) {
	'use strict';

	gopp_plugin.media_row_action = function ( e, id, nonce ) {
		var $target = $( e.target ), $row_action_div = $target.parents( '.row-actions' ).first(), $spinner = $target.next();
		$( '.gopp_response', $row_action_div.parent() ).remove();
		$spinner.addClass( 'is-active' );
		$.post( ajaxurl, {
				action: 'gopp_media_row_action',
				id: id,
				nonce: nonce
			}, function( response ) {
				$spinner.removeClass( 'is-active' );
				if ( response ) {
					if ( response.error ) {
						$( '<div class="notice error gopp_response"><p>' + response.error + '</p></div>' ).insertAfter( $row_action_div );
					} else if ( response.msg ) {
						$( '<div class="notice updated gopp_response"><p>' + response.msg + '</p></div>' ).insertAfter( $row_action_div );
					}
					if ( response.img ) {
						$( '.has-media-icon .media-icon', $row_action_div.parent() ).html( response.img );
					}
				}
			}, 'json'
		);

		return false;
	};

} )( jQuery );
</script>
	<?php
}

/**
 * Called on 'media_row_actions' filter.
 */
function gopp_plugin_media_row_actions( $actions, $post, $detached ) {
	global $gopp_plugin_cap;
	if ( 'application/pdf' === $post->post_mime_type && current_user_can( $gopp_plugin_cap ) ) {
		$actions['gopp_regen_pdf_preview'] = sprintf(
			'<a href="#the-list" onclick="return gopp_plugin.media_row_action( event, %d, %s );" class="hide-if-no-js aria-button-if-js" aria-label="%s">%s</a><span class="spinner" style="float:none;margin-top:0;"></span>',
			$post->ID,
			esc_attr( json_encode( wp_create_nonce( 'gopp_media_row_action_' . $post->ID ) ) ),
			/* translators: %s: attachment title */
			esc_attr( sprintf( __( 'Regenerate the PDF preview for &#8220;%s&#8221;', 'ghostscript-only-pdf-preview' ), _draft_or_post_title() ) ),
			esc_attr( __( 'Regenerate preview', 'ghostscript-only-pdf-preview' ) )
		);
	}
	return $actions;
}

/**
 * Ajax callback for media row action.
 */
function gopp_plugin_gopp_media_row_action() {
	$ret = array( 'msg' => '', 'error' => false, 'img' => '' );

	global $gopp_plugin_cap;
	if ( ! current_user_can( $gopp_plugin_cap ) ) {
		$ret['error'] = __( 'Sorry, you are not allowed to access this page.', 'ghostscript-only-pdf-preview' );
	} else {
		$id = isset( $_POST['id'] ) && is_string( $_POST['id'] ) ? intval( $_POST['id'] ) : '';

		if ( ! check_ajax_referer( 'gopp_media_row_action_' . $id, 'nonce', false /*die*/ ) ) {
			$ret['error'] = __( 'Invalid nonce.', 'ghostscript-only-pdf-preview' );
		} else {
			if ( ! $id ) {
				$ret['error'] = __( 'Invalid ID.', 'ghostscript-only-pdf-preview' );
			} else {
				$file = get_attached_file( $id );
				if ( false === $file ) {
					$ret['error'] = __( 'Invalid ID.', 'ghostscript-only-pdf-preview' );
				} else {
					$meta = wp_generate_attachment_metadata( $id, $file );
					if ( ! $meta ) {
						$ret['error'] = __( 'Failed to generate the PDF preview.', 'ghostscript-only-pdf-preview' );
					} else {
						// wp_update_attachment_metadata() returns false if nothing to update so check first.
						$old_value = get_metadata( 'post', $id, '_wp_attachment_metadata' );
						if ( $old_value && is_array( $old_value ) && 1 === count( $old_value ) && $old_value[0] === $meta ) {
							$ret['msg'] = __( 'Successfully regenerated the PDF preview. You will probably need to refresh your browser to see the updated thumbnail.', 'ghostscript-only-pdf-preview' );
						} else {
							if ( false === wp_update_attachment_metadata( $id, $meta ) ) {
								$ret['error'] = __( 'Failed to generate the PDF preview.', 'ghostscript-only-pdf-preview' );
							} else {
								$ret['msg'] = __( 'Successfully regenerated the PDF preview. You will probably need to refresh your browser to see the updated thumbnail.', 'ghostscript-only-pdf-preview' );
							}
						}
						if ( ! $ret['error'] ) {
							$ret['img'] = wp_get_attachment_image( $id, array( 60, 60 ), true, array( 'alt' => '' ) );
						}
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
