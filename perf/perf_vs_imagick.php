<?php

ini_set( 'ignore_repeated_errors', true );

$basename = basename( __FILE__ );
$dirname = dirname( __FILE__ );
$dirdirname = dirname( $dirname );

error_log( "(===begin " . $basename );

// Hack to work on multi-site.
$_SERVER['HTTP_HOST'] = '192.168.1.64';
$_SERVER['REQUEST_URI'] = '/wpfm-test/wp-admin/plugins.php';

require '../../../wp-load.php';

require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
require_once ABSPATH . WPINC . '/class-wp-image-editor-imagick.php';
require $dirdirname . '/includes/class-gopp-image-editor-gs.php';

$loop_num = 10;

$pdfs = array(
	$dirname . '/data/wordpress-gsoc-flyer.pdf',
	$dirname . '/data/test_alpha.pdf',
	$dirname . '/data/test_cmyk.pdf',
	$dirname . '/data/test_cmyk_alpha.pdf',
	$dirname . '/data/Contents-CR-51-2.pdf',
	$dirname . '/data/contents-hs-issue-41.pdf',
);
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

error_log( "loop_num=$loop_num, cnt_pdfs=$cnt_pdfs" );
error_log( "     g=" . sprintf( '%.10f', $tots_g ) );
error_log( "     i=" . sprintf( '%.10f', $tots_i ) );
error_log( "   g:i=" . sprintf( '%.10f', $tots_i ? $tots_g / $tots_i : 0 ) );
error_log( "   i:g=" . sprintf( '%.10f', $tots_g ? $tots_i / $tots_g : 0 ) );
error_log( " avg g=" . sprintf( '%.10f', ( $loop_num * $cnt_pdfs ) ? $tots_g / ( $loop_num * $cnt_pdfs ) : 0 ) ); 
error_log( " avg i=" . sprintf( '%.10f', ( $loop_num * $cnt_pdfs ) ? $tots_i / ( $loop_num * $cnt_pdfs ) : 0 ) ); 

error_log( ")===end " . $basename );
