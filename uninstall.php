<?php
/**
 * Uninstall - just deletes transients used.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) || wp_basename( dirname( __FILE__ ) ) !== wp_basename( dirname( WP_UNINSTALL_PLUGIN ) ) ) exit;

delete_transient( 'gopp_plugin_admin_notices' );
delete_transient( 'gopp_plugin_poll_rpp' );

if ( ! class_exists( 'GOPP_Image_Editor_GS' ) ) {
	if ( ! class_exists( 'WP_Image_Editor' ) ) {
		require ABSPATH . WPINC . '/class-wp-image-editor.php';
	}
	require dirname( __FILE__ ) . '/includes/class-gopp-image-editor-gs.php';
}
GOPP_Image_Editor_GS::clear();
