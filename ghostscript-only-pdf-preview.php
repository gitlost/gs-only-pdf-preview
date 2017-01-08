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
	// Make WP version x.y.z compat.
	if ( preg_match( '/^[0-9]+\.[0-9]+$/', $stripped_wp_version ) ) {
		$stripped_wp_version .= '.0';
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
