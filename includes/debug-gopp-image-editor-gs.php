<?php
/**
 * Debug class. Protected stuff and various helpers.
 */

require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
require_once dirname( __FILE__ ) . '/class-gopp-image-editor-gs.php';

class DEBUG_GOPP_Image_Editor_GS extends GOPP_Image_Editor_GS {
	static function get_test_output() {
		if ( self::is_exec_disabled() ) {
			return array( -1, array( __( 'Exec disabled!', 'gs-only-pdf-preview' ) ) );
		}
		if ( $cmd = self::gs_cmd( '-dBATCH -dNOPAUSE -dNOPROMPT -dSAFER -v' ) ) {
			exec( $cmd, $output, $return_var );
			return array( $return_var, $output );
		}
		return array( -1, array( __( 'Ghostscript command not found!', 'gs-only-pdf-preview' ) ) );
	}

	static function is_win() { return self::$is_win; }
	static function gs_cmd_path() { return self::$gs_cmd_path; }

	static function transient_gopp_image_gs_cmd_path() { return get_transient( 'gopp_image_gs_cmd_path' ); }

	static function filter_gopp_image_gs_cmd_path() { return has_filter( 'gopp_image_gs_cmd_path' ); }
	static function apply_filters_gopp_image_gs_cmd_path() { return apply_filters( 'gopp_image_gs_cmd_path', self::$gs_cmd_path, self::is_win() ); }

	static function is_exec_disabled() {
		$ini_get = ini_get( 'disable_functions' );
		if ( $ini_get && in_array( 'exec', array_map( 'trim', explode( ',', strtolower( $ini_get ) ) ), true ) ) {
			return true;
		}
		if ( extension_loaded( 'suhosin' ) || extension_loaded( 'suhosin7' ) || ( version_compare( PHP_VERSION, '5.3', '>=' ) && ini_get( 'enable_dl' ) && @ dl( 'suhosin' ) ) ) {
			$ini_get = ini_get( 'suhosin.executor.func.whitelist' );
			if ( $ini_get && in_array( 'exec', array_map( 'trim', explode( ',', strtolower( $ini_get ) ) ), true ) ) {
				return false;
			}
			$ini_get = ini_get( 'suhosin.executor.func.blacklist' );
			if ( $ini_get && in_array( 'exec', array_map( 'trim', explode( ',', strtolower( $ini_get ) ) ), true ) ) {
				return true;
			}
		}
		return false;
	}

	static function dump() {
		$args = array( 'mime_type' => 'application/pdf' );
		list( $return_var, $output ) = self::get_test_output();
		?>
			<hr />
			<h2>Debug Info</h2>
			<table border="1" cellpadding="5" cellspacing="0" bgcolor="white">
				<tr><td valign="top">Return var</td><td><strong><?php echo $return_var; ?></strong></td></tr>
				<tr><td valign="top">Output</td><td><strong><?php echo implode( '<br />', $output ); ?></strong></td></tr>
				<tr><td>gs_cmd_path</td><td><strong><?php echo self::gs_cmd_path(); ?></strong> </td></tr>
				<tr><td>test</td><td><strong><?php echo self::test( $args ) ? 'true' : 'false'; ?></strong> </td></tr>
				<tr><td>is_win</td><td><strong><?php echo self::is_win() ? 'true' : 'false'; ?></strong> </td></tr>
				<tr><td>Transient <em>gopp_image_gs_cmd_path</em></td><td><strong><?php echo self::transient_gopp_image_gs_cmd_path(); ?></strong> </td></tr>
				<tr><td>Has filter <em>gopp_image_gs_cmd_path</em></td><td><strong><?php echo self::filter_gopp_image_gs_cmd_path() ? 'true' : 'false'; ?></strong> </td></tr>
				<tr><td>Apply filters <em>gopp_image_gs_cmd_path</em></td><td><strong><?php echo self::apply_filters_gopp_image_gs_cmd_path(); ?></strong> </td></tr>
			</table>
		<?php
	}
}
