/**
 * Javascript for GhostScript Only PDF Preview WP plugin.
 */
/*jslint ass: true, nomen: true, plusplus: true, regexp: true, vars: true, white: true, indent: 4 */
/*global jQuery, wp, commonL10n, gopp_plugin_params, console */
/*exported gopp_plugin */

var gopp_plugin = gopp_plugin || {}; // Our namespace.

( function ( $ ) {
	'use strict';

	/**
	 * UI feedback for Regenerate PDF Previews administration tool.
	 */
	gopp_plugin.regen_pdf_preview = function () {
		var $wrap = $( '#gopp_regen_pdf_previews' ), $form = $( '.gopp_regen_pdf_previews_form', $wrap );
		if ( $wrap.length ) {
			$( 'input[type="submit"]', $form ).click( function ( e ) {
				var $this = $( this ), $msgs = $( '.notice, .updated', $wrap ), $msg = $( gopp_plugin_params.please_wait_msg ), $progress, poll_func,
					cnt = parseInt( $( '#poll_cnt', $form ).val(), 10 ), poll_nonce = $( '#poll_nonce', $form ).val();

				$this.hide();
				$( '.gopp_regen_pdf_previews_form_hide', $wrap ).hide();
				$msgs.hide();
				$( 'h1', $wrap ).first().after( $msg );
				$progress = $( '#gopp_progress', $wrap );

				poll_func = function () {
					$.post( ajaxurl, {
							action: 'gopp_poll_regen_pdf_previews',
							cnt: cnt,
							poll_nonce: poll_nonce
						}, function( data ) {
							if ( data && data.msg ) {
								$progress.html( data.msg );
							}
							setTimeout( poll_func, gopp_plugin_params.val.poll_interval * 1000 );
						}, 'json'
					);
				};

				if ( $.browser && $.browser.safari ) {
					// Safari apparently suspends rendering in a submit handler, so hack around it. See http://stackoverflow.com/a/1164177/664741
					// Still no spinner or polling, but better than nothing.
					e.preventDefault();
					$( '.spinner', $msg ).removeClass( 'is-active' ); // Hide as it doesn't spin.
					$this.unbind( 'click' );
					setTimeout( function() { $this.click(); }, 0 );
				} else {
					setTimeout( poll_func, gopp_plugin_params.val.poll_interval * 1000 );
				}
			} );
		}
	};

	/**
	 * Regenerate Preview row action.
	 */
	gopp_plugin.media_row_action = function ( e, id, nonce ) {
		var $target = $( e.target ), $row_action_div = $target.parents( '.row-actions' ).first(), $spinner = $target.next();
		$( '.gopp_response', $row_action_div.parent() ).remove();
		$spinner.addClass( 'is-active' );
		$.post( {
			url: ajaxurl,
			data: {
				action: 'gopp_media_row_action',
				id: id,
				nonce: nonce
			},
			dataType: 'json',
			error: function( jqXHR, textStatus, errorThrown ) {
				var err_msg;
				$spinner.removeClass( 'is-active' );
				err_msg = '<div class="notice error gopp_response"><p>' + gopp_plugin_params.action_not_available + ' (' + errorThrown + ')</p></div>';
				$row_action_div.after( $( err_msg ) );
			},
			success: function( data ) {
				$spinner.removeClass( 'is-active' );
				$( '.gopp_response', $row_action_div.parent() ).remove();
				if ( data ) {
					if ( data.error ) {
						$row_action_div.after( $( '<div class="notice error gopp_response"><p>' + data.error + '</p></div>' ) );
					} else if ( data.msg ) {
						$row_action_div.after( $( '<div class="notice updated gopp_response"><p>' + data.msg + '</p></div>' ) );
					}
					if ( data.img ) {
						$( '.has-media-icon .media-icon', $row_action_div.parent() ).html( data.img );
					}
				} else {
					$row_action_div.after( $( '<div class="notice error gopp_response"><p>' + gopp_plugin_params.action_not_available + '</p></div>' ) );
				}
			},
			timeout: gopp_plugin_params.val.min_time_limit * 1000
		} );

		return false;
	};

	/**
	 * UI feedback for Regenerate PDF Previews bulk action.
	 */
	gopp_plugin.upload = function () {
		$( '#doaction, #doaction2' ).click( function ( e ) {
			$( 'select[name^="action"]' ).each( function () {
				var $target, checkeds;
				if ( 'gopp_regen_pdf_previews' === $( this ).val() ) {
					$target = $( e.target );
					// See if anything selected.
					checkeds = $.makeArray( $( '#the-list input[name="media[]"]:checked' ).map( function () {
						return this.value;
					} ) );
					if ( ! checkeds.length ) {
						e.preventDefault();
						$( '.gopp_none', $target.parent() ).remove();
						$( gopp_plugin_params.no_items_selected_msg ).insertAfter( $target ).fadeOut( 1000, function() { $( this ).remove(); } );
					} else {
						$( '.spinner', $target.parent() ).remove();
						$( gopp_plugin_params.spinner ).insertAfter( $target ); // Like above, as in submit, doesn't work for Safari.
					}
				}
			} );
		} );
	};

	// jQuery ready.
	$( function () {
		if ( gopp_plugin_params && gopp_plugin_params.val ) {
			if ( gopp_plugin_params.val.is_regen_pdf_preview ) {
				gopp_plugin.regen_pdf_preview();
			} else if ( gopp_plugin_params.val.is_upload ) {
				gopp_plugin.upload();
			}
		}
	} );
} )( jQuery );
