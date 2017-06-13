<?php

$basename = basename( __FILE__ );
$dirname = dirname( __FILE__ );
$dirdirname = dirname( $dirname );

$develop_dirname = '/var/www/wordpress-develop';

$wp_dirname = file_exists( 'src/wp-config.php' ) && file_exists( 'src/wp-load.php' ) ? 'src' : ( $develop_dirname . '/src' );
$wp_tests_dir = getenv( 'WP_TESTS_DIR' );
$tests_dirname = $wp_tests_dir ? $wp_tests_dir : ( $develop_dirname . '/tests/phpunit' );

gjf_log( "(===begin " . $basename );

gjf_log( "gjf: dirdirname=$dirdirname, wp_dirname=$wp_dirname, tests_dirname=$tests_dirname" );

// Hack to work on multi-site.
$_SERVER['HTTP_HOST'] = '192.168.1.64'; // Needs to match DOMAIN_CURRENT_SITE in wp-config.php
$_SERVER['REQUEST_URI'] = preg_replace( '/^\/var\/www(\/[^\/]+\/).+$/', '$1', $dirdirname ); // Needs to match PATH_CURRENT_SITE in wp-config.php

$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';

// Override to keep nonces constant.
function wp_nonce_tick() {
	$time = strtotime( '2017-03-22 00:00:00' );
	$nonce_life = DAY_IN_SECONDS;

	return ceil( $time / ( $nonce_life / 2 ) );
}
gjf_log( "gjf: require " . $wp_dirname . '/wp-load.php' );
if ( ! file_exists( $wp_dirname . '/wp-load.php' ) ) {
	gjf_log( "gjf: ! file_exists " . $wp_dirname . '/wp-load.php' );
	gjf_log( "gjf: scandir=" . print_r( scandir( $wp_dirname ), true ) );
}
require $wp_dirname . '/wp-load.php';
gjf_log( "gjf: require " . ABSPATH . 'wp-admin/includes/image.php' );
require ABSPATH . 'wp-admin/includes/image.php';
gjf_log( "gjf: require " . $tests_dirname . '/includes/factory.php' );
require $tests_dirname . '/includes/factory.php';

function gjf_log( $msg ) {
	error_log( $msg );
}

function gjf_replace_urls( $str ) {
	static $url_replaces = null;

	if ( null === $url_replaces ) {
		$url_replaces = array(
			home_url( '/' ) => 'http://example.com/',
			set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ) => 'http://example.com/',
			admin_url( '/', 'relative' ) => '/wp-admin/',
		);
	}

	return str_replace( array_keys( $url_replaces ), array_values( $url_replaces ), $str );
}

function gjf_normalize_fixture( $response, $id, $file ) {
	$response['id'] = $id;
	$response['url'] = gjf_replace_urls( $response['url'] );
	$response['link'] = gjf_replace_urls( $response['link'] );
	$orig_name = $response['name'];
	$response['name'] = wp_basename( $file, '.' . pathinfo( $file, PATHINFO_EXTENSION ) ) . '-' . $id;
	$response['link'] = str_replace( $orig_name, $response['name'], $response['link'] );
	$time = strtotime( '2017-04-01 00:00:00' );
	$time -= $id; // So order by DATE DESC returns them in ID ASC order.
	$response['date'] = $response['modified'] = $time;
	$response['icon'] = gjf_replace_urls( $response['icon'] );
	$response['dateFormatted'] = date( 'F j, Y', $time );

	if ( ! empty( $response['sizes'] ) ) {
		foreach ( $response['sizes'] as $key => $size ) {
			if ( isset( $response['sizes'][ $key ]['url'] ) ) {
				$response['sizes'][ $key ]['url'] = gjf_replace_urls( $response['sizes'][ $key ]['url'] );
			}
		}
	}
	return $response;
}

function gjf_put_contents( $output_file, $new_contents ) {
	gjf_log( "gjf_put_contents: output_file=$output_file" );
	$old_contents = file_exists( $output_file ) ? file_get_contents( $output_file ) : '';
	if ( $new_contents === $old_contents ) {
		gjf_log( "gjf_put_contents: no change output_file=$output_file" );
	} else {
		if ( false === file_put_contents( $output_file, $new_contents ) ) {
			gjf_log( "gjf_put_contents: ERROR: Failed to write output_file=$output_file" );
			exit( 1 );
		}
		gjf_log( "gjf_put_contents: wrote output_file=$output_file" );
	}
}

gjf_log( "gjf: wp_enqueue_media" );
wp_enqueue_media();

// Generate generated-media.js

gjf_log( "gjf: wp_print_scripts" );
ob_start();

wp_print_scripts();

$print_scripts = ob_get_clean();

$vars = array(
	'pluploadL10n',
	'_wpPluploadSettings',
	//'mejsL10n',
	//'_wpmejsSettings',
	'_wpMediaViewsL10n',
);

$var_re = '/^var (' . implode( '|', $vars ) . ') /';
$var_lines = array();
$lines = explode( "\n", $print_scripts );

foreach ( $lines as $line ) {
	$line = trim( $line );
	if ( preg_match( $var_re, $line ) ) {
		$line = str_replace( '\/', '/', $line ); // De-backslash slashes.
		$var_lines[] = '';
		$var_lines[] = gjf_replace_urls( $line );
	}
}

$upload_dir = wp_get_upload_dir();

$folder = $upload_dir['path'] . '/{minimal-us-letter,test_alpha,test-image}*.{jpg,pdf}';
foreach ( glob( $folder, GLOB_BRACE ) as $file ) {
	unlink( $file );
}

$factory = new WP_UnitTest_Factory;

$responses = $attachments = array();

$test_file = $dirdirname . '/tests/images/minimal-us-letter.pdf';
$attachment_id = $factory->attachment->create_upload_object( $test_file );
$attachment = get_post( $attachment_id );
$attachments[] = $attachment;
$response = wp_prepare_attachment_for_js( $attachment );
$responses[] = gjf_normalize_fixture( $response, 1, $test_file );

$test_file = $dirdirname . '/tests/images/test-image.jpg';
$attachment_id = $factory->attachment->create_upload_object( $test_file );
$attachment = get_post( $attachment_id );
$attachments[] = $attachment;
$response = wp_prepare_attachment_for_js( $attachment );
$responses[] = gjf_normalize_fixture( $response, 2, $test_file );

$test_file = $dirdirname . '/tests/images/test_alpha.pdf';
$attachment_id = $factory->attachment->create_upload_object( $test_file );
$attachment = get_post( $attachment_id );
$attachments[] = $attachment;
$response = wp_prepare_attachment_for_js( $attachment );
$responses[] = gjf_normalize_fixture( $response, 3, $test_file );

$var_lines[] = '';
$var_lines[] = 'var media_responses = ' . json_encode( $responses, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . ';';

$header = array(
	'/**',
	' * Generated by "' . $basename . '".',
	' */'
);

$new_contents = implode( "\n", array_merge( $header, $var_lines ) );

gjf_put_contents( $dirdirname . '/tests/qunit/fixtures/generated-media.js', $new_contents );

// Generate generated-media-templates.html

gjf_log( "gjf: wp_print_media_templates" );
ob_start();

wp_print_media_templates();

$new_contents = ob_get_clean();

gjf_put_contents( $dirdirname . '/tests/qunit/fixtures/generated-media-templates.html', $new_contents );

// Generate generated-media-list-table.html

gjf_log( "gjf: generated-media-list-table.html" );
require ABSPATH . 'wp-admin/includes/comment.php';
require ABSPATH . 'wp-admin/includes/post.php';
require ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require ABSPATH . 'wp-admin/includes/screen.php';
require ABSPATH . 'wp-admin/includes/template.php';
if ( ! class_exists( 'WP_List_Table' ) ) {
	require ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}
require ABSPATH . 'wp-admin/includes/list-table.php';

if ( ! defined( 'GOPP_PLUGIN_VERSION' ) ) {
	require $dirdirname . '/gs-only-pdf-preview.php';
}

global $hook_suffix;
// Need edit post privs.
wp_set_current_user( 1 );

$wp_list_table = _get_list_table( 'WP_Media_List_Table' );

function gjf_posts_pre_query( $posts, $wp_query ) {
	global $attachments, $responses;
	$posts = array();
	foreach ( $attachments as $i => $attachment ) {
		$post = $attachment;
		$post->ID = $responses[ $i ]['id'];
		$post->post_name = preg_replace( '/-[0-9]+$/', '', $post->post_name );
		$post->guid = gjf_replace_urls( $post->guid );
		$posts[] = $post;
	}
	$wp_query->found_posts = count( $posts );
	return $posts;
}
add_filter( 'posts_pre_query', 'gjf_posts_pre_query', 10, 2 );

$wp_list_table->prepare_items();

remove_filter( 'posts_pre_query', 'gjf_posts_pre_query', 10 );

ob_start();

$wp_list_table->views();

add_filter( 'bulk_actions-', array( 'GS_Only_PDF_Preview', 'bulk_actions_upload' ) );
add_filter( 'media_row_actions', array( 'GS_Only_PDF_Preview', 'media_row_actions' ), 100, 3 ); // Add after (most) others due to spinner.

$wp_list_table->display();

remove_filter( 'media_row_actions', array( 'GS_Only_PDF_Preview', 'media_row_actions' ), 100 );
remove_filter( 'bulk_actions-', array( 'GS_Only_PDF_Preview', 'bulk_actions_upload' ) );

$new_content = gjf_replace_urls( ob_get_clean() );

gjf_put_contents( $dirdirname . '/tests/qunit/fixtures/generated-media-list-table.html', $new_content );

// Generate

function gjf_query( $query ) {
	global $attachments;
	$query = 'SELECT ' . count( $attachments );
	return $query;
}
add_filter( 'query', 'gjf_query' );

ob_start();

GS_Only_PDF_Preview::regen_pdf_previews();

$new_content = gjf_replace_urls( ob_get_clean() );

remove_filter( 'query', 'gjf_query' );

gjf_put_contents( $dirdirname . '/tests/qunit/fixtures/generated-regen_pdf_preview.html', $new_content );

gjf_log( ")===end " . $basename );
