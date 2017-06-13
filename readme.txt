=== GS Only PDF Preview ===
Contributors: gitlost
Tags: Ghostscript, PDF, PDF Preview, Ghostscript Only
Requires at least: 4.7.0
Tested up to: 4.8.0
Stable tag: 1.0.7
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Uses Ghostscript directly to generate PDF previews.

== Description ==

The plugin pre-empts the standard WordPress 4.7/4.8 PDF preview production process (which uses the PHP extension [`Imagick`](http://php.net/manual/en/book.imagick.php)) to call [Ghostscript](https://ghostscript.com/) directly to produce the preview.

This means that only Ghostscript is required on the server. Neither the PHP module `Imagick` nor the server package [`ImageMagick`](https://www.imagemagick.org/script/index.php) is needed or used (though it's fine if they're installed anyway, and if they are they'll be used by WP (unless you override it) to produce the intermediate sizes of the preview).

= Background =

The plugin was prompted by the `WP_Image_Editor_Imagick_External` demonstration class uploaded to the WP Trac ticket [#39262 Fall back to ImageMagick command line when the pecl imagic is not available on the server](https://core.trac.wordpress.org/ticket/39262) by [Hristo Pandjarov](https://profiles.wordpress.org/hristo-sg), and by the wish to solve the WP Trac ticket [#39216 PDFs with non-opaque alpha channels can result in previews with black backgrounds.](https://core.trac.wordpress.org/ticket/39216), which particularly affects PDFs with CMYK color spaces (common in the print world).

The plugin by-passes (as far as PDF previews are concerned) #39216, and also by-passes the related issue [#39331 unsharpMaskImage in Imagick's thumbnail_image is not compatible with CMYK JPEGs.](https://core.trac.wordpress.org/ticket/39331), as the preview JPEGs produced directly by Ghostscript use sRGB color spaces.

= Limitations =

The plugin requires the PHP function [`exec`](http://php.net/manual/en/function.exec.php) to be enabled on your system. So if the PHP ini setting [`disable_functions`](http://php.net/manual/en/ini.core.php#ini.disable-functions) includes `exec`, the plugin won't work. Neither will it work if the [`suhosin` security extension](https://suhosin.org/stories/index.html) is installed and `exec` is either not [whitelisted](https://suhosin.org/stories/configuration.html#suhosin-executor-func-whitelist) or is [blacklisted](https://suhosin.org/stories/configuration.html#suhosin-executor-func-blacklist).

Also, the plugin is incompatible with the PHP ini setting [`safe_mode`](http://php.net/manual/en/ini.sect.safe-mode.php#ini.safe-mode), an old (and misnamed) setting that was deprecated in PHP 5.3.0 and removed in PHP 5.4.0.

= Security =

The plugin uses the PHP function `exec` to call Ghostscript as a shell command. This has security implications as uncareful use with user supplied data (eg the upload file name or the file itself) could introduce an attack vector.

I believe these concerns are addressed here through screening of the file and its name and escaping of arguments. This belief is backed by a bounty of fifteen hundred thousand intergalactic credits to anyone who spots a security issue. Please disclose responsibly.

= Performance =

Unsurprisingly it's faster. Crude benchmarking (see the script [`perf_vs_imagick.php`](https://github.com/gitlost/gs-only-pdf-preview/blob/master/perf/perf_vs_imagick.php)) suggests it's around 35-40% faster. However the production of the preview is only a part of the overhead of uploading a PDF (and doesn't include producing the intermediate thumbnail sizes for instance) so any speed-up may not be that noticeable.

= Size =

On JPEG thumbnail size it appears to be comparable (though it depends on the PDF), maybe a bit larger on average. To mitigate this the default JPEG quality for the PDF preview has been lowered to 70 (from 82), which results in some extra "ringing" (speckles around letters) but the previews tested remain very readable. Note that this only affects the "full" PDF thumbnail - the intermediate-sized thumbnails as produced by `Imagick` or `GD` and any other non-PDF images remain at the standard JPEG quality of 82. You can use the WP filter [`wp_editor_set_quality`](https://developer.wordpress.org/reference/hooks/wp_editor_set_quality/) to override this, for instance to restore the quality to 82 add to your theme's "functions.php":

	function mytheme_wp_editor_set_quality( $quality, $mime_type ) {
		if ( 'application/pdf' === $mime_type ) {
			$quality = 82;
		}
		return $quality;
	}
	add_filter( 'wp_editor_set_quality', 'mytheme_wp_editor_set_quality', 10, 2 );

= Quality =

Eyeballing based on very limited data, ie anecdotally, the previews seem to be of superior definition with less artifacts (even with the JPEG quality reduced to 70), and more faithful to the original colours.

= Tool =

A basic administration tool to regenerate (or generate, if they previously didn't have a preview) the previews of all PDFs uploaded to the system is included (any previously generated intermediate preview thumbnails will be removed if their dimensions differ). Note that if you have a lot of PDFs you may experience the White Screen Of Death (WSOD) if the tool exceeds the [maximum execution time](http://php.net/manual/en/info.configuration.php#ini.max-execution-time) allowed. Note also that as the file names of the previews don't (normally) change, you will probably have to refresh your browser to see the updated thumbnails.

As workarounds for the possible WSOD issue above, and as facilities in themselves, a "Regenerate PDF Previews" bulk action is added to the list mode of the Media Library, and a "Regenerate Preview" row action is added to each PDF entry in the list. So previews can be regenerated in batches or individually instead.

= Patches =

As a bonus version 1.0.2+ patches WordPress to allow linking to the preview image in "Add Media" when editing a post ([#39618 Insert PDF Thumbnail into Editor](https://core.trac.wordpress.org/ticket/39618)). Also patches [#39630 PDF Thumbnails in Media Library Don't Fall Back to Full Size](https://core.trac.wordpress.org/ticket/39630).

= And =

A google-cheating schoolboy French translation is supplied.

The plugin runs on WP 4.7.0 to 4.8.0, and requires Ghostscript to be installed on the server. The plugin should run on PHP 5.2.17 to 7.1, and on both Unix and Windows servers.

The project is on [github](https://github.com/gitlost/gs-only-pdf-preview).

== Installation ==

Install the plugin in the standard way via the 'Plugins' menu in WordPress and then activate.

To install Ghostscript, see [How to install Ghostscript](https://ghostscript.com/doc/current/Install.htm) on the official Ghostscript site. For Ubuntu users, there's a package:

	sudo apt-get install ghostscript

For Windows, there's an installer available at the [Ghostscript download page](https://ghostscript.com/download/gsdnld.html).

== Frequently Asked Questions ==

= What filters are available? =

Three plugin-specific filters are available:

* `gopp_editor_set_resolution` sets the resolution of the PDF preview.
* `gopp_editor_set_page` sets the page to render for the PDF preview.
* `gopp_image_gs_cmd_path` short-circuits the determination of the path of the Ghostscript executable on your server.

The `gopp_editor_set_resolution` filter is an analogue of the standard [`wp_editor_set_quality`](https://developer.wordpress.org/reference/hooks/wp_editor_set_quality/) filter mentioned above, and allows one to override the default resolution of 128 DPI used for the PDF preview. For instance, in your theme's "functions.php":

	function mytheme_gopp_editor_set_resolution( $resolution, $filename ) {
		return 100;
	}
	add_filter( 'gopp_editor_set_resolution', 'mytheme_gopp_editor_set_resolution', 10, 2 );

Similarly the `gopp_editor_set_page` filter allows one to override the default of rendering the first page:

	function mytheme_gopp_editor_set_page( $page, $filename ) {
		return 2; // Render the second page instead.
	}
	add_filter( 'gopp_editor_set_page', 'mytheme_gopp_editor_set_page', 10, 2 );

The `gopp_image_gs_cmd_path` filter is necessary if your Ghostscript installation is in a non-standard location and the plugin fails to determine where it is (if this happens you'll get a **Warning: no Ghostscript!** notice on activation):

	function mytheme_gopp_image_gs_cmd_path( $gs_cmd_path, $is_win ) {
		return $is_win ? 'D:\\My Ghostscript Location\\bin\\gswin32c.exe' : '/my ghostscript location/gs';
	}
	add_filter( 'gopp_image_gs_cmd_path', 'mytheme_gopp_image_gs_cmd_path', 10, 2 );

The filter can also be used just for performance reasons, especially on Windows servers to save searching the registry and directories.

Note that the value of `gs_cmd_path` is cached as a transient by the plugin for performance reasons, with a lifetime of one day. You can clear it by de-activating and re-activating the plugin, or by manually calling the `clear` method of the Ghostscript Image Editor:

	function mytheme_gopp_init() {
		if ( class_exists( 'GOPP_Image_Editor_GS' ) ) {
			GOPP_Image_Editor_GS::clear();
		}
	}
	add_filter( 'init', 'mytheme_gopp_init' );

or for [WP-CLI](https://wp-cli.org/) users:

	wp transient delete gopp_image_gs_cmd_path

== Screenshots ==

1. Before: upload of various PDFs with alpha channels and/or CMYK color spaces resulting in broken previews.
2. After: upload of the same PDFs resulting in a result.
3. Regenerate PDF Previews administration tool front page.
4. Regenerate PDF Previews administration tool after processing.
5. Regenerate PDF Previews bulk action in list mode of Media Library.
6. Regenerate Preview row action in list mode of Media Library.
7. Link to preview image in "Add Media".

== Changelog ==

= 1.0.7 (13 Jun 2017) =
* Fix regex for patching "Align" select of Attachment Display Settings after core changeset [40640].
* Rejig tests.
* WP 4.8.0 compatible.

= 1.0.6 (16 Apr 2017) =
* For BC so as not to break linked thumbnails, check for PDF marker before deleting on regeneration.

= 1.0.5 (16 Apr 2017) =
* Fix Windows cmd path highest version/best match.
* Set real size not dummy for preview.
* Fix test to be preview name agnostic.
* Remove unnecessary upload_dir calc re old preview thumbnails.
* Insist on mime_type arg in test() to avoid reporting bogus supported implementation.
* Only add actions/filters if have cap.
* Add qunit tests.
* Override get_size() to work if loaded.
* Enable "Alt Text" on Attachment Details.
* WP 4.7.3 compatible.

= 1.0.4 (13 Feb 2017) =
* Remove "+" from banned characters in file name, for BC with older uploads.
* Enable "Align" select of Attachment Display Settings.
* Workaround changing Attachment Page url and revert remove Attachment from Link To for pdfs.

= 1.0.3 (9 Feb 2017) =
* Add dummy srcset on linked preview thumbnail so that wp_make_content_images_responsive() ignores it.
* Remove Attachment from Link To for pdfs.

= 1.0.2 (8 Feb 2017) =
* Don't overwrite existing JPEGs with same name as preview.
* Remove existing preview intermediates when regenerating.
* Patch WP to allow preview image linking in Add Media (#39618).
* Patch WP to use thumbnail or medium sized thumbnails in Media Library (#39630).
* WP 4.7.2 compatible.

= 1.0.1 (20 Jan 2017) =
* Move exec and safe_mode check from wp_image_editors action to GOPP_Image_Editor_GS::test().

= 1.0.0 (15 Jan 2017) =
* Initial release.

= 0.9.0 (8 Jan 2017) =
* Initial github version.

== Upgrade Notice ==

= 1.0.7 =
Tested with WordPress 4.8.0, with compatibility fix for patching "Align" select of Attachment Display Settings.

= 1.0.6 =
Keeps backward-compatibility for linked thumbnails.

= 1.0.5 =
Determines Windows command path better.

= 1.0.4 =
Allows file names containing "+".

= 1.0.3 =
Avoids PHP warning on linked pdf thumbnails.

= 1.0.2 =
Doesn't overwrite existing JPEGs with same name as preview. Removes existing preview thumbnails on regeneration.

= 1.0.1 =
Tweeks.

= 1.0.0 =
Improved PDF preview experience.
