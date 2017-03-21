/**
 * Fixtures for GS Only PDF Preview.
 */

var gopp_plugin_params = {
	val: {
		is_debug: true,
		is_regen_pdf_preview: false,
		is_upload: false,
		is_post: false,
		current_user_can_cap: true,
		poll_interval: 0,
		min_time_limit: 300
	},
	please_wait_msg: '<div class="notice notice-warning inline"><p>Please wait...<span id="gopp_progress"></span><span class="spinner" style="float:none;margin-top:-2px;"></span></p></div>',
	no_items_selected_msg: '<span class="gopp_none" style="display:inline-block;margin-top:6px">No items selected!</span>',
	action_not_available: 'Regenerate Preview ajax action not available!',
	spinner: '<span class="spinner is-active" style="float:none;margin-top:5px;"></span>',
	document_link_only: 'Document Link Only'
};

var gopp_fixtures = gopp_fixtures || {}; // Our namespace.

window.imageEdit = window.imageEdit || {}; // Dummy image editor.

var ajaxurl = ajaxurl || window._wpUtilSettings.ajax.url; // Dummy ajaxurl.

( function ( $ ) {
	'use strict';

	// Synchronous load.
	gopp_fixtures.load_sync = function( sel, url ) {
		var $el = $( sel );
		$.ajax( {
			url: url,
			success: function( html ) {
				$el.html( html );
			},
			async: false
		} );
		return $el;
	};

	// Create template container and load templates.
	gopp_fixtures.load_sync( $( '<div id="media-templates-container"></div>' ), 'fixtures/generated-media-templates.html' ).appendTo( document.body );

	// Fake attachment get and post.
	gopp_fixtures.wp_ajax_send = null;
	gopp_fixtures.wp_ajax_post = null;
	gopp_fixtures.last_post_data = null;
	if ( wp && wp.ajax ) {
		if ( wp.ajax.send ) {
			gopp_fixtures.wp_ajax_send = wp.ajax.send;
			wp.ajax.send = function ( action, options ) {
				if ( media_responses ) {
					// Hacked version of wp.ajax.send in "wp-includes/js/wp-utils.js".
					if ( _.isObject( action ) ) {
						options = action;
					} else {
						options = options || {};
						options.data = _.extend( options.data || {}, { action: action });
					}
					if ( options.data && ( 'query-attachments' === options.data.action || 'get-attachment' === options.data.action ) ) {
						var data = 'get-attachment' === options.data.action ? media_responses[ options.data.id - 1 ] : media_responses;
						return $.Deferred( function( deferred ) {
							if ( options.success ) {
								deferred.done( options.success );
							}
							if ( options.error ) {
								deferred.fail( options.error );
							}
							delete options.success;
							delete options.error;

							deferred.resolveWith( options.context, [ data ] );
						} ).promise();
					}
				}
				return gopp_fixtures.wp_ajax_send.apply( this, arguments );
			};
		}
		if ( wp.ajax.post ) {
			gopp_fixtures.wp_ajax_post = wp.ajax.post;
			wp.ajax.post = function( action, data ) {
				// Keep copy of data so it can be checked.
				gopp_fixtures.last_post_data = _.isObject( action ) ? action : _.extend( data || {}, { action: action } );
				return gopp_fixtures.wp_ajax_post.apply( this, arguments );
			};
		}
	}

	// Non-caching template.
	gopp_fixtures.wp_template = null;
	if ( wp && wp.template ) {
		gopp_fixtures.wp_template = wp.template;
		wp.template = function ( id ) {
			// Hacked version of wp.template in "wp-includes/js/wp-utils.js".
			var options = {
					evaluate:    /<#([\s\S]+?)#>/g,
					interpolate: /\{\{\{([\s\S]+?)\}\}\}/g,
					escape:      /\{\{([^\}]+?)\}\}(?!\})/g,
					variable:    'data'
				};

			return function ( data ) {
				return _.template( $( '#tmpl-' + id ).html(),  options )( data );
			};
		};
	}

	// Restore original WP funcs.
	gopp_fixtures.reset = function () {
		if ( gopp_fixtures.wp_ajax_send ) {
			wp.ajax.send = gopp_fixtures.wp_ajax_send;
		}
		if ( gopp_fixtures.wp_template ) {
			wp.template = gopp_fixtures.wp_template;
		}
	};

} )( jQuery );
