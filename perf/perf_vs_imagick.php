<?php
/**
 * Compare performance of GOPP_Image_editor_GS with WP_Image_Editor_Imagick.
 * Run from the GS Only PDF Preview plugin directory or your theme's directory.
 */

ini_set( 'ignore_repeated_errors', true );

$basename = basename( __FILE__ );
$dirname = dirname( __FILE__ );
$dirdirname = dirname( $dirname );

error_log( "(===begin " . $basename );

// Hack to work on multi-site.
$_SERVER['HTTP_HOST'] = '192.168.1.64'; // Needs to match DOMAIN_CURRENT_SITE in wp-config.php
$_SERVER['REQUEST_URI'] = preg_replace( '/^\/var\/www(\/[^\/]+\/).+$/', '$1', $dirdirname ); // Needs to match PATH_CURRENT_SITE in wp-config.php

require '../../../wp-load.php';

require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
require_once ABSPATH . WPINC . '/class-wp-image-editor-imagick.php';
require $dirdirname . '/includes/class-gopp-image-editor-gs.php';

$loop_num = 20; // Warning it's slow!
$pdfs_begin = 0;
$pdfs_end = 1; // Choose to include problematic data or not.

$pdfs = array(
	$dirname . '/data/wordpress-gsoc-flyer.pdf', // sRGB color space, no alpha issues.
	$dirname . '/data/test_alpha.pdf', // Non-opaque alpha channel.
	$dirname . '/data/test_cmyk.pdf', // CMYK color space.
	$dirname . '/data/test_cmyk_alpha.pdf', // Non-opaque alpha channel and CMYK color space.
	$dirname . '/data/Contents-CR-51-2.pdf', // CMYK color space.
	$dirname . '/data/contents-hs-issue-41.pdf', // Non-opaque alpha channel.
);
array_slice( $pdfs, $pdfs_begin, $pdfs_end );
$cnt_pdfs = count( $pdfs );

$tots_g = 0;
$tots_i = 0;

$gs = $imagick = null;

for ( $i = 0; $i < $loop_num; $i++ ) {
	foreach ( $pdfs as $pdf ) {
		$tots_g += -microtime( true );
		$gs = new GOPP_Image_Editor_GS( $pdf );
		$gs->load();
		$gs->save( $pdf, 'image/jpeg' );
		$tots_g += microtime( true );
		unset( $gs );

		$tots_i += -microtime( true );
		$imagick = new WP_Image_Editor_Imagick( $pdf );
		$imagick->load();
		$imagick->save( $pdf, 'image/jpeg' );
		$tots_i += microtime( true );
		unset( $imagick );
	}
}

error_log( "loop_num=$loop_num, cnt_pdfs=$cnt_pdfs, pdfs=" . implode( ',', $pdfs ) );
error_log( "     g=" . sprintf( '%.10f', $tots_g ) );
error_log( "     i=" . sprintf( '%.10f', $tots_i ) );
error_log( "   g:i=" . sprintf( '%.10f', $tots_i ? $tots_g / $tots_i : 0 ) );
error_log( "   i:g=" . sprintf( '%.10f', $tots_g ? $tots_i / $tots_g : 0 ) );
error_log( " avg g=" . sprintf( '%.10f', ( $loop_num * $cnt_pdfs ) ? $tots_g / ( $loop_num * $cnt_pdfs ) : 0 ) ); 
error_log( " avg i=" . sprintf( '%.10f', ( $loop_num * $cnt_pdfs ) ? $tots_i / ( $loop_num * $cnt_pdfs ) : 0 ) ); 

error_log( ")===end " . $basename );
