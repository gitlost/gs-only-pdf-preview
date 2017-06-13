/* global jQuery, wp, ajaxurl, module, test, equal, strictEqual, media_responses, gopp_fixtures */

jQuery( function( $ ) {
	'use strict';

	module( 'GS Only PDF Preview' );

	test( 'upload_patch', function() {
		var result;

		result = gopp_plugin.upload_patch();

		ok( result, 'upload patch success' );

		// Reset.
		gopp_fixtures.load_sync( '#media-templates-container', 'fixtures/generated-media-templates.html' );
	} );

	test( 'post_patch', function() {
		var result, prev_media_editor_send_attachment = wp.media.editor.send.attachment;

		result = gopp_plugin.post_patch();

		ok( result, 'post patch success' );
		notEqual( wp.media.editor.send.attachment, prev_media_editor_send_attachment, 'post patch differs' );

		// Reset.
		gopp_fixtures.load_sync( '#media-templates-container', 'fixtures/generated-media-templates.html' );
		wp.media.editor.send.attachment = prev_media_editor_send_attachment;
	} );

	test( 'patch_39630', function() {
		var result;

		result = gopp_plugin.patch_39630();

		ok( result, 'patch 39630 success' );

		// Reset.
		gopp_fixtures.load_sync( '#media-templates-container', 'fixtures/generated-media-templates.html' );
	} );

	test( 'no post_patch select', function() {
		var options, frame, $previews, $preview, $attachment_details, $thumbnail, $alt_text;

		options = {
			frame: 'post',
			state: 'insert',
			title: wp.media.view.l10n.addMedia,
			multiple: true,
			uploader: false
		};

		frame = wp.media.editor.open( 'content', options );
		frame.content.mode( 'browse' );

		$previews = $( '.attachments-browser .attachments .attachment .attachment-preview', frame.$el );
		strictEqual( $previews.length, media_responses.length, 'attachment previews found' );

		$preview = $previews.eq( 0 );

		$preview.click(); // Select.

		$attachment_details = $( '.attachment-details', frame.$el );
		strictEqual( $attachment_details.length, 1, 'Attachment Details found' );

		$thumbnail = $( '.thumbnail img', $attachment_details );
		strictEqual( $thumbnail.length, 1, 'thumbnail found' );
		ok( $thumbnail.hasClass( 'icon' ), 'icon thumbnail' );

		$alt_text = $( 'label[data-setting="alt"]', $attachment_details );
		strictEqual( $alt_text.length, 0, 'no alt' );

		// Reset.
		$preview.click(); // Unselect.
		frame.close();
		frame.detach();
	} );

	test( 'post_patch select', function() {
		var prev_media_editor_send_attachment = wp.media.editor.send.attachment;
		var options, frame, $previews, $preview, $attachment_details, $attachment_display_settings, $thumbnail, $alt_text, $align, $size_options, $document_link_only;

		ok( gopp_plugin.post_patch(), 'post patch success' );

		options = {
			frame: 'post',
			state: 'insert',
			title: wp.media.view.l10n.addMedia,
			multiple: true,
			uploader: false
		};

		frame = wp.media.editor.open( 'content', options );
		frame.content.mode( 'browse' );

		$previews = $( '.attachments-browser .attachments .attachment .attachment-preview', frame.$el );
		strictEqual( $previews.length, media_responses.length, 'attachment previews found' );

		// First.

		$preview = $previews.eq( 0 );

		$preview.click(); // Select.

		$attachment_details = $( '.attachment-details', frame.$el );
		strictEqual( $attachment_details.length, 1, 'Attachment Details found' );

		$thumbnail = $( '.thumbnail img', $attachment_details );
		strictEqual( $thumbnail.length, 1, 'thumbnail found' );
		/*
		notOk( $thumbnail.hasClass( 'icon' ), 'not icon thumbnail' );
		strictEqual( $thumbnail.prop( 'src' ), media_responses[0].sizes.thumbnail.url, 'pdf thumbnail' );

		$alt_text = $( 'label[data-setting="alt"]', $attachment_details );
		strictEqual( $alt_text.length, 1, 'alt found' );

		$attachment_display_settings = $( '.attachment-display-settings', frame.$el );
		strictEqual( $attachment_display_settings.length, 1, 'Attachment Display Settings found' );

		$align = $( 'select.alignment', $attachment_display_settings );
		strictEqual( $align.length, 1, 'align select found' );

		$size_options = $( 'select.size option', $attachment_display_settings );
		notEqual( $size_options.length, 0, 'size options found' );

		$document_link_only = $size_options.eq( 0 );
		strictEqual( $document_link_only.val(), '', 'Document Link Only size option value' );
		strictEqual( $document_link_only.html().trim(), gopp_plugin_params.document_link_only, 'Document Link Only size option html' );

		$preview.click(); // Unselect.

		// Second.

		$preview = $previews.eq( 1 );

		$preview.click(); // Select.

		$attachment_details = $( '.attachment-details', frame.$el );
		strictEqual( $attachment_details.length, 1, 'Attachment Details found' );

		$thumbnail = $( '.thumbnail img', $attachment_details );
		strictEqual( $thumbnail.length, 1, '2nd thumbnail found' );
		notOk( $thumbnail.hasClass( 'icon' ), 'not icon thumbnail' );
		strictEqual( $thumbnail.prop( 'src' ), media_responses[1].sizes.full.url, 'jpg thumbnail' );
		*/

		// Reset.
		gopp_fixtures.load_sync( '#media-templates-container', 'fixtures/generated-media-templates.html' );
		wp.media.editor.send.attachment = prev_media_editor_send_attachment;
		$preview.click(); // Unselect.
		frame.close();
		frame.detach();
	} );

	test( 'post_patch send_attachment', function() {
		var prev_media_editor_send_attachment = wp.media.editor.send.attachment;
		var options, frame, $previews, $preview, $attachment_details, $attachment_display_settings, $alt_text, $align, $link_to, $size, $insert_button;

		ok( gopp_plugin.post_patch(), 'post patch success' );

		options = {
			frame: 'post',
			state: 'insert',
			title: wp.media.view.l10n.addMedia,
			multiple: true,
			uploader: false
		};

		frame = wp.media.editor.open( 'content', options );
		frame.content.mode( 'browse' );

		$previews = $( '.attachments-browser .attachments .attachment .attachment-preview', frame.$el );
		strictEqual( $previews.length, media_responses.length, 'attachment previews found' );

		$preview = $previews.eq( 0 );

		$preview.click(); // Select.

		$attachment_details = $( '.attachment-details', frame.$el );
		strictEqual( $attachment_details.length, 1, 'Attachment Details found' );

		$alt_text = $( 'label[data-setting="alt"]', $attachment_details );
		strictEqual( $alt_text.length, 1, 'alt found' );
		$alt_text.val( 'alt text' );
		$alt_text.change();

		$attachment_display_settings = $( '.attachment-display-settings', frame.$el );
		strictEqual( $attachment_display_settings.length, 1, 'Attachment Display Settings found' );

		$align = $( 'select.alignment', $attachment_display_settings );
		strictEqual( $align.length, 1, 'align select found' );
		$( 'option[value="left"]', $align ).prop( 'selected', true );
		$align.change();

		$link_to = $( 'select.link-to', $attachment_display_settings );
		strictEqual( $link_to.length, 1, 'link to select found' );
		$( 'option[value="file"]', $link_to ).prop( 'selected', true );
		$link_to.change();

		$size = $( 'select.size', $attachment_display_settings );
		strictEqual( $size.length, 1, 'size select found' );
		$( 'option[value="thumbnail"]', $size ).prop( 'selected', true );
		$size.change();

		$insert_button = $( 'button.media-button-insert', frame.$el );
		strictEqual( $size.length, 1, 'insert button found' );

		$insert_button.click();
		notEqual( gopp_fixtures.last_post_data, null, 'attachment sent data' );
		strictEqual( gopp_fixtures.last_post_data.action, 'send-attachment-to-editor', 'attachment sent action' );
		strictEqual( gopp_fixtures.last_post_data.attachment.alt, 'alt text', 'attachment sent alt text' );
		strictEqual( gopp_fixtures.last_post_data.attachment.align, 'left', 'attachment sent align left' );
		strictEqual( gopp_fixtures.last_post_data.attachment.link_to, 'file', 'attachment sent link to file' );
		strictEqual( gopp_fixtures.last_post_data.attachment['image-size'], 'thumbnail', 'attachment sent image size thumbnail' );
		strictEqual( gopp_fixtures.last_post_data.attachment.post_title, media_responses[0].title, 'attachment sent title' );
		strictEqual( gopp_fixtures.last_post_data.attachment.url, media_responses[0].url, 'attachment sent url' );

		// Reset.
		gopp_fixtures.load_sync( '#media-templates-container', 'fixtures/generated-media-templates.html' );
		wp.media.editor.send.attachment = prev_media_editor_send_attachment;
		$preview.click(); // Unselect.
		frame.close();
		frame.detach();
	} );

	test( 'post_patch image-details', function() {
		var prev_media_editor_send_attachment = wp.media.editor.send.attachment;
		var metadata, frame, $image_details, $img, $image_actions;

		ok( gopp_plugin.post_patch(), 'post patch success' );

		// PDF - no image actions.

		metadata = {
			attachment_id: media_responses[0].id,
			caption: '',
			customHeight: media_responses[0].sizes.thumbnail.height,
			customWidth: media_responses[0].sizes.thumbnail.width,
			align: 'left',
			extraClasses: '',
			link: false,
			linkUrl: media_responses[0].url,
			linkClassName: '',
			linkTargetBlank: false,
			linkRel: '',
			size: 'thumbnail',
			title: '',
			url: media_responses[0].sizes.thumbnail.url
		};

		frame = wp.media({
			frame: 'image',
			state: 'image-details',
			metadata: metadata
		} );

		wp.media.events.trigger( 'editor:frame-create', { frame: frame } );

		frame.open();

		$image_details = $( '.image-details', frame.$el );
		strictEqual( $image_details.length, 1, 'Image Details found' );

		$img = $( '.image img', $image_details );
		strictEqual( $img.length, 1, 'pdf image found' );

		$image_actions = $( '.image .actions', $image_details );
		strictEqual( $image_actions.length, 0, 'no actions' );

		frame.close();
		frame.detach();

		// JPEG - have image action(s).

		metadata = {
			attachment_id: media_responses[1].id,
			caption: '',
			customHeight: media_responses[1].sizes.full.height,
			customWidth: media_responses[1].sizes.full.width,
			align: 'left',
			extraClasses: '',
			link: false,
			linkUrl: media_responses[1].url,
			linkClassName: '',
			linkTargetBlank: false,
			linkRel: '',
			size: 'full',
			title: '',
			url: media_responses[1].sizes.full.url
		};

		frame = wp.media({
			frame: 'image',
			state: 'image-details',
			metadata: metadata
		} );

		wp.media.events.trigger( 'editor:frame-create', { frame: frame } );

		frame.open();

		$image_details = $( '.image-details', frame.$el );
		strictEqual( $image_details.length, 1, 'Image Details found' );

		$img = $( '.image img', $image_details );
		strictEqual( $img.length, 1, 'jpeg image found' );

		$image_actions = $( '.image .actions', $image_details );
		notEqual( $image_actions.length, 0, 'actions found' );

		frame.close();
		frame.detach();

		// Reset.
		gopp_fixtures.load_sync( '#media-templates-container', 'fixtures/generated-media-templates.html' );
		wp.media.editor.send.attachment = prev_media_editor_send_attachment;
	} );

	test( 'upload bulk action', function() {
		var $container = gopp_fixtures.load_sync( $( '<div id="media-list-table-container"></div>' ), 'fixtures/generated-media-list-table.html' ).appendTo( document.body );
		var $actions, $action, $selects, $select, $msg, $checkboxes, $checkbox;

		gopp_plugin.upload();

		$actions = $( '#doaction, #doaction2', $container );
		strictEqual( $actions.length, 2, 'doactions found' );

		$action = $actions.eq( 0 );

		$selects = $( 'select[name^="action"]', $container );
		strictEqual( $selects.length, 2, 'selects found' );

		$select = $selects.eq( 0 );
		$( 'option[value="gopp_regen_pdf_previews"]', $select ).prop( 'selected', true );
		$select.change();

		// Nothing selected.

		$action.click();
		$msg = $action.next();
		notEqual( gopp_plugin_params.no_items_selected_msg.indexOf( $msg.html() ), -1, 'no items selected' );
		notOk( $msg.hasClass( 'spinner' ) );

		$checkboxes = $( '#the-list input[name="media[]"]', $container );
		strictEqual( $checkboxes.length, media_responses.length, 'checkboxes found' );

		// Select first.

		$checkbox = $checkboxes.eq( 0 );
		$checkbox.prop( 'checked', true );
		$checkbox.change();

		$action.click();
		$msg = $action.next();
		ok( $msg.hasClass( 'spinner' ), 'spinner' );
		strictEqual( $msg.prop( 'outerHTML' ).indexOf( 'selected' ), -1, 'not no items selected' );

		// Reset.
		$container.remove();
	} );

	test( 'media_row_action', function() {
		var $container = gopp_fixtures.load_sync( $( '<div id="media-list-table-container"></div>' ), 'fixtures/generated-media-list-table.html' ).appendTo( document.body );
		var e = {}, test = {};
		var $row_action_parent, $row_action_div, $target, $spinner;

		$row_action_parent = $( '#post-1', $container );
		$row_action_div = $( '.row-actions', $row_action_parent );
		$target = $( '.gopp_regen_pdf_preview a', $row_action_div );
		strictEqual( $target.length, 1, 'target found' );

		$spinner = $target.next();
		strictEqual( $spinner.length, 1, 'spinner found' );
		notOk( $spinner.hasClass( 'is-active' ), 'spinner not active' );
		strictEqual( $( '.gopp_response', $row_action_parent ).length, 0, 'no message' );

		e.target = $target[0];

		// Ajax fail.

		gopp_plugin.media_row_action( e, 1, 'nonce', test );
		notEqual( test.post_ret, null, 'post return' );

		stop();
		test.post_ret.fail( function( jqXHR, textStatus, errorThrown ) {
			strictEqual( jqXHR.status, 404, 'not found' );
			notOk( $spinner.hasClass( 'is-active' ), 'spinner not active' );
			// This can/will execute out of sequence so checking 'gopp_response' messages is problematic.
			start();
		} ).done( function () {
			notOk( true, 'ajax fail' );
			start();
		} );

		// Succeed with mock ajax.

		$.ajaxTransport( 'json', function ( options, originalOptions, jqXHR ) {
			var id;
			if ( originalOptions.data && 'gopp_media_row_action' === originalOptions.data.action ) {
				id = originalOptions.data.id;
				return {
					send: function( headers, completeCallback ) {
						// If have spinner, should be active.
						ok( 0 === $spinner.length || $spinner.hasClass( 'is-active' ), 'spinner active' );
						var ret = { msg: '', error: false, img: '' }, status = 200, statusText = 'success';
						if ( 1 === id || 2 === id ) {
							ret.msg = 'Success.';
							ret.img = '<img src="' + media_responses[ id - 1 ].url + '">';
						} else {
							ret.error = 'Fail.';
						}
						completeCallback( status, statusText, { json: ret } );
					},
					abort: function() {
						notOk( true, 'no abort' );
					}
				};
			}
		} );

		gopp_plugin.media_row_action( e, 1, 'nonce', test );
		notEqual( test.post_ret, null, 'post return' );

		stop();
		test.post_ret.done( function( data, textStatus, jqXHR ) {
			strictEqual( jqXHR.status, 200, 'success' );
			notEqual( $row_action_div.next().html().indexOf( 'Success' ), -1, 'Success message' );
			strictEqual( $( '.gopp_response', $row_action_parent ).length, 1, 'one message' );
			notEqual( $( '.has-media-icon .media-icon', $row_action_parent ).html().indexOf( media_responses[0].url ), -1, 'image set' );
			notOk( $spinner.hasClass( 'is-active' ), 'spinner not active' );
			start();
		} ).fail( function () {
			notOk( true, 'ajax done response success' );
			start();
		} );

		// Non-PDF fail.

		$row_action_parent = $( '#post-3', $container );
		$row_action_div = $( '.row-actions', $row_action_parent );
		$target = $( '.view a', $row_action_div ); // No gopp_regen_pdf_preview so use view.
		strictEqual( $target.length, 1, 'target found' );

		$spinner = $target.next();
		strictEqual( $spinner.length, 0, 'no spinner' );
		strictEqual( $( '.gopp_response', $row_action_parent ).length, 0, 'no message' );

		e.target = $target[0];

		gopp_plugin.media_row_action( e, 3, 'nonce', test );
		notEqual( test.post_ret, null, 'post return' );

		stop();
		test.post_ret.done( function( data, textStatus, jqXHR ) {
			strictEqual( jqXHR.status, 200, 'success' );
			notEqual( $row_action_div.next().html().indexOf( 'Fail' ), -1, 'Fail message' );
			strictEqual( $( '.gopp_response', $row_action_parent ).length, 1, 'one message' );
			strictEqual( $( '.has-media-icon .media-icon', $row_action_parent ).html().indexOf( media_responses[0].url ), -1, 'image not set' );
			start();
		} ).fail( function () {
			notOk( true, 'ajax done response fail' );
			start();
		} );

		// Reset.
		$container.remove();
	} );

	test( 'regen_pdf_preview', function() {
		var $container = gopp_fixtures.load_sync( $( '<div id="regen_pdf_preview-container"></div>' ), 'fixtures/generated-regen_pdf_preview.html' ).appendTo( document.body );
		var $wrap, $form, $submit, $msg, $poll_nonce;

		$wrap = $( '#gopp_regen_pdf_previews', $container );
		$form = $( '.gopp_regen_pdf_previews_form', $wrap );

		$submit = $( 'input[type="submit"]', $form );
		strictEqual( $submit.length, 1, 'submit found' );
		ok( $submit.is( ':visible', 'submit visible' ) );

		$poll_nonce = $( '#poll_nonce', $form );
		strictEqual( $poll_nonce.length, 1, 'poll_nonce found' );

		// Prevent form submission.
		$submit.click( function ( e ) {
			e.preventDefault();
		} );

		gopp_plugin.regen_pdf_preview();

		$submit.click();

		ok( $submit.is( ':hidden' ), 'submit hidden' );
		ok( $( '.gopp_regen_pdf_previews_form_hide', $wrap ).is( ':hidden' ), 'form hidden' );
		$msg = $( '.notice-warning', $wrap );
		strictEqual( $msg.length, 1, 'have notice' );
		notEqual( $msg.html().indexOf( 'Please wait' ), -1, 'is Please wait' );

		// Reset.
		$container.remove();
	} );

} );
