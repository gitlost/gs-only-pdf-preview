/**
 * Javascript for GS Only PDF Preview WP plugin.
 */
/*jslint ass: true, nomen: true, plusplus: true, regexp: true, vars: true, white: true, indent: 4 */
/*global jQuery, _, wp, ajaxurl, gopp_plugin_params */
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

	/**
	 * Patches templates and wp.media.editor.send.attachment to allow PDF thumbnail insertion (see #39618).
	 * Also addresses #39630 by using either thumbnail or medium sized thumbnails in Media Library, favouring thumbnail size.
	 */
	gopp_plugin.post = function () {
		var $tmpl_attachment_details, $tmpl_attachment_display_settings, $tmpl_image_details, html_before,
			html_attachment_details,
			attachment_details_re = /(<# } else if \( 'image' === data\.type && data\.sizes \) { #>\s+<img src="{{ data\.size\.url }}" draggable="false" alt="" \/>)(\s+<# } else { #>)/,
			attachment_details_with = '$1\n<# } else if ( data.sizes && data.sizes.thumbnail ) { #>\n<img src="{{ data.sizes.thumbnail.url }}" draggable="false" alt="" />\n$2',
			html_attachment_display_settings,
			attachment_display_settings_re = /<option value="post">\n\s+<# if \( data\.model\.canEmbed \) { #>\n[^\n]+\n[^\n]+\n\s+<\/option>/,
			attachment_display_settings_with = '<# if ( \'application\' !== data.type ) { #>\n$&\n<# } #>\n',
			attachment_display_settings2_re = /(<# if \( data\.userSettings \) { #>\s+data-user-setting="imgsize"\s+<# } #>>)(\s+<#)/,
			attachment_display_settings2_with = '$1\n<# if ( \'application\' === data.type ) { #>\n<option value="">\n' + gopp_plugin_params.document_link_only + '\n</option>\n<# } #>\n$2',
			html_image_details,
			image_details_re = /(<# if \( data\.attachment && window\.imageEdit)( \) { #>)/,
			image_details_with = '$1 && \'image\' === data.type $2';

		if ( wp.media && wp.media.editor && wp.media.editor.send && 'function' === typeof( wp.media.editor.send.attachment ) ) {
			$tmpl_attachment_details = $( '#tmpl-attachment-details' );
			$tmpl_attachment_display_settings = $( '#tmpl-attachment-display-settings' );
			$tmpl_image_details = $( '#tmpl-image-details' );
			if ( $tmpl_attachment_details.length && $tmpl_attachment_display_settings.length && $tmpl_image_details.length ) {

				// All or nothing - if any replacements fail, bail.

				// Uses PDF thumbnail in Attachment Details (instead of icon).
				html_before = $tmpl_attachment_details.html();
				html_attachment_details = html_before.replace( attachment_details_re, attachment_details_with );
				if ( html_before === html_attachment_details ) {
					return false;
				}

				// #39618 Removes "Attachment" option from Link To select of Attachment Details.
				html_before = $tmpl_attachment_display_settings.html();
				html_attachment_display_settings = html_before.replace( attachment_display_settings_re, attachment_display_settings_with );
				if ( html_before === html_attachment_display_settings ) {
					return false;
				}
				// #39618 Adds "Document Link Only" option to Size select of Attachment Details.
				html_before = html_attachment_display_settings;
				html_attachment_display_settings = html_before.replace( attachment_display_settings2_re, attachment_display_settings2_with );
				if ( html_before === html_attachment_display_settings ) {
					return false;
				}

				// #39618 Don't allow editing/replacement of image if it's a PDF thumbnail in Image Details edit.
				html_before = $tmpl_image_details.html();
				html_image_details = html_before.replace( image_details_re, image_details_with );
				if ( html_before === html_image_details ) {
					return false;
				}

				// #39630 Use either thumbnail or medium sized thumbnails in Media Library.
				if ( false === gopp_plugin.patch_39630() ) {
					return false;
				}

				$tmpl_attachment_details.html( html_attachment_details );
				$tmpl_attachment_display_settings.html( html_attachment_display_settings );
				$tmpl_image_details.html( html_image_details );

				// Replace with version to set image options for PDFs.
				wp.media.editor.send.attachment = gopp_plugin.media_editor_send_attachment;

				return true;
			}
		}
		return false;
	};

	/**
	 * Version of wp.media.editor.send.attachment() ("wp-includes/js/media-editor.js") hacked to set image options for PDFs.
	 */
	gopp_plugin.media_editor_send_attachment = function( props, attachment ) {
		var caption = attachment.caption,
			options, html;

		// If captions are disabled, clear the caption.
		if ( ! wp.media.view.settings.captions ) {
			delete attachment.caption;
		}

		props = wp.media.string.props( props, attachment );

		options = {
			id:           attachment.id,
			post_content: attachment.description,
			post_excerpt: caption
		};

		if ( props.linkUrl ) {
			options.url = props.linkUrl;
		}

		if ( 'image' === attachment.type ) {
			html = wp.media.string.image( props );

			_.each({
				align: 'align',
				size:  'image-size',
				alt:   'image_alt'
			}, function( option, prop ) {
				if ( props[ prop ] )
					options[ option ] = props[ prop ];
			});
		} else if ( 'video' === attachment.type ) {
			html = wp.media.string.video( props, attachment );
		} else if ( 'audio' === attachment.type ) {
			html = wp.media.string.audio( props, attachment );
		} else {
			html = wp.media.string.link( props );
			options.post_title = props.title;
			// Begin hack.
			if ( 'application' === attachment.type && 'pdf' === attachment.subtype ) {
				_.each({
					align: 'align',
					size:  'image-size'
				}, function( option, prop ) {
					if ( props[ prop ] )
						options[ option ] = props[ prop ];
				});
			}
			// End hack.
		}

		return wp.media.post( 'send-attachment-to-editor', {
			nonce:      wp.media.view.settings.nonce.sendToEditor,
			attachment: options,
			html:       html,
			post_id:    wp.media.view.settings.post.id
		});
	};

	/**
	 * Patches 'tmpl-attachment in "wp-includes/media-templates.php" to use either thumbnail or medium sized thumbnails in Media Library, favouring thumbnail size.
	 */
	gopp_plugin.patch_39630 = function () {
		var $tmpl_attachment = $( '#tmpl-attachment' ), html_before, html_attachment,
			attachment_re = /(<# } else if \( data\.sizes && )data\.sizes\.medium( \) { #>\s+<img src="{{ )data\.sizes\.medium(\.url }}" class="thumbnail" draggable="false" alt="" \/>\s+<# } else { #>)/,
			attachment_with = '$1( data.sizes.thumbnail || data.sizes.medium )$2( data.sizes.thumbnail || data.sizes.medium )$3';

		if ( $tmpl_attachment.length ) {

			html_before = $tmpl_attachment.html();
			html_attachment = html_before.replace( attachment_re, attachment_with );
			if ( html_before === html_attachment ) {
				return false;
			}
			$tmpl_attachment.html( html_attachment );
			return true;
		}
		return false;
	};


	// jQuery ready.
	$( function () {
		if ( gopp_plugin_params && gopp_plugin_params.val ) {
			if ( gopp_plugin_params.val.is_regen_pdf_preview ) {
				gopp_plugin.regen_pdf_preview();
			} else if ( gopp_plugin_params.val.is_upload ) {
				gopp_plugin.upload();
				gopp_plugin.patch_39630();
			} else if ( gopp_plugin_params.val.is_post ) {
				gopp_plugin.post();
			}
		}
	} );
} )( jQuery );
