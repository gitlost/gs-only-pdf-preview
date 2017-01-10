[![Build Status](https://travis-ci.org/gitlost/ghostscript-only-pdf-preview.png?branch=master)](https://travis-ci.org/gitlost/ghostscript-only-pdf-preview)
[![codecov.io](http://codecov.io/github/gitlost/ghostscript-only-pdf-preview/coverage.svg?branch=master)](http://codecov.io/github/gitlost/ghostscript-only-pdf-preview?branch=master)
# GhostScript Only PDF Preview #
**Contributors:** [gitlost](https://profiles.wordpress.org/gitlost)  
**Tags:** GhostScript, PDF, PDF Preview, GhostScript Only  
**Requires at least:** 4.7.0  
**Tested up to:** 4.7.0  
**Stable tag:** 0.9.0  
**License:** GPLv2 or later  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

Uses GhostScript directly to generate PDF previews.

## Description ##

The plugin pre-empts the standard WordPress 4.7.0 PDF preview process (which uses the PHP extension `Imagick`) to call GhostScript directly to produce the preview.

This means that only GhostScript is required on the server. Neither the PHP module `Imagick` nor the server package `ImageMagick` is needed or used (though it's fine if they're installed anyway, and if they are they'll be used by WP (unless you override it) to produce the intermediate sizes of the preview).

### Background ###

The plugin was prompted by the demonstration `WP_Image_Editor_Imagick_External` class uploaded to the WP Trac ticket [#39262 Fall back to ImageMagick command line when the pecl imagic is not available on the server](https://core.trac.wordpress.org/ticket/39262) by [Hristo Pandjarov](https://profiles.wordpress.org/hristo-sg), and by the wish to solve the WP Trac ticket [#39216 PDFs with non-opaque alpha channels can result in previews with black backgrounds.](https://core.trac.wordpress.org/ticket/39216), which particularly affects PDFs with CMYK color spaces (common in the printing world).

The plugin by-passes (as far as PDF previews are concerned) #39216, and also by-passes the related issue [#39331 unsharpMaskImage in Imagick's thumbnail_image is not compatible with CMYK jpegs.](https://core.trac.wordpress.org/ticket/39331), as the preview jpegs produced directly by GhostScript always use sRGB color spaces.

### Limitations ###

The plugin requires the [PHP function `exec`](http://php.net/manual/en/function.exec.php) to be enabled on your system. So if the [PHP ini setting `disable_functions`](http://php.net/manual/en/ini.core.php#ini.disable-functions) includes `exec`, the plugin won't work. Neither will it work if the (somewhat outdated) [`suhosin` security extension](https://suhosin.org/stories/index.html) is installed and `exec` is [blacklisted](https://suhosin.org/stories/configuration.html#suhosin-executor-func-blacklist).

Also, the plugin is incompatible with the [PHP ini setting `safe_mode`](http://php.net/manual/en/ini.sect.safe-mode.php#ini.safe-mode), an old (and misnamed) setting that was deprecated in PHP 5.3.0 and removed in PHP 5.4.0.

### Security ###

The plugin uses the PHP function `exec` to call GhostScript as a shell command. This has security implications as uncareful use with user supplied data (eg the upload file name or the file itself) could introduce an attack vector.

I believe these concerns are addressed here through screening of the file and its name and escaping of arguments. This belief is backed by a bounty of fifteen hundred thousand intergalactic credits to anyone who spots a security issue. Please disclose responsibly.

### Performance ###

Unsurprisingly it's faster. Crude benchmarking (see the [script `perf_vs_imagick.php`](https://github.com/gitlost/ghostscript-only-pdf-preview/blob/master/perf/perf_vs_imagick.php)) suggest it's at least 40% faster. However the production of the preview is only a part of the overhead of uploading a PDF (and doesn't include producing the intermediate thumbnail sizes for instance) so any speed-up will probably not be that noticeable.

### Tool ###

A primitive administration tool to regenerate (or generate, if they previously didn't have a preview) the previews of all PDFs uploaded to the system is included. Note that if you have a lot of PDFs you may experience the White Screen Of Death (WSOD) if the tool exceeds the [maximum execution time](http://php.net/manual/en/info.configuration.php#ini.max-execution-time) allowed. Note also that as the filenames of the previews don't (normally) change, you will probably have to refresh your browser (to clear the cache) to see the updated thumbnails.

As a workaround for the possible WSOD issue above, and as a facility in itself, a "Regenerate preview" row action is added to PDF entries in the list mode of the Media Library, so that you can regenerate the previews of individual PDFs.

### And ###

A google-cheating schoolboy French translation is supplied.

The plugin runs on WP 4.7.0, and requires GhostScript to be installed on the server. The plugin should run on PHP 5.2.17 to 7.1, and on both Unix and Windows systems.

The project is on [github](https://github.com/gitlost/ghostscript-only-pdf-preview).

## Installation ##

Install the plugin in the standard way via the 'Plugins' menu in WordPress and then activate.

To install GhostScript, see [How to install Ghostscript](https://ghostscript.com/doc/9.20/Install.htm) on the official GhostScript site. For Ubuntu users, there's a package:

	sudo apt-get install ghostscript

## Frequently Asked Questions ##

### What filters are available? ###

Four plugin-specific filters are available:

* `gopp_editor_set_resolution` sets the resolution of the PDF preview.
* `gopp_editor_set_page` sets the page to render for the PDF preview.
* `gopp_image_have_gs` short-circuits the test (via the shell) to see if GhostScript is installed on your system.
* `gopp_image_gs_cmd_path` short-circuits the determination of the path of the GhostScript executable on your system.

The `gopp_editor_set_resolution` filter is an analogue of the standard `wp_editor_set_quality` filter, and allows one to override the default resolution ("128x128") used for the PDF preview by returning a string formatted "widthxheight". For instance, in your theme's "functions.php":

	function mytheme_gopp_editor_set_resolution( $resolution, $filename ) {
		return '100x100';
	}
	add_filter( 'gopp_editor_set_resolution', 'mytheme_gopp_editor_set_resolution', 10, 2 );

Similarly the `gopp_editor_set_page` filter allows one to override the default of rendering the first page:

	function mytheme_gopp_editor_set_page( $page, $filename ) {
		return 2; // Render the second page instead.
	}
	add_filter( 'gopp_editor_set_page', 'mytheme_gopp_editor_set_page', 10, 2 );

The `gopp_image_have_gs` filter can be used to improve performance (saves a test shell command) if you know the GhostScript installation on your server works:

	add_filter( 'gopp_image_have_gs', '__return_true', 10, 0 );

The `gopp_image_gs_cmd_path` filter is necessary if your GhostScript installation is in a non-standard location and the plugin fails to determine where it is (if this happens you'll get a **Warning: no GhostScript!** notice on activation):

	function mytheme_gopp_image_gs_cmd_path( $gs_cmd_path, $is_win ) {
		return $is_win ? 'D:\\My GhostScript Location\\bin\\gswin32c.exe' : '/my ghostscript location/gs';
	}
	add_filter( 'gopp_image_gs_cmd_path', 'mytheme_gopp_image_gs_cmd_path', 10, 2 );

The filter can also be used just for performance reasons (especially on Windows systems to save searching the registry and directories).

## Screenshots ##

### 1. Before: upload of various PDFs with alpha channels and/or CMYK color spaces resulting in broken previews. ###
![Before: upload of various PDFs with alpha channels and/or CMYK color spaces resulting in broken previews.](https://github.com/gitlost/ghostscript-only-pdf-preview/raw/master/assets/screenshot-1.png)

### 2. After: upload of the same PDFs resulting in a result. ###
![After: upload of the same PDFs resulting in a result.](https://github.com/gitlost/ghostscript-only-pdf-preview/raw/master/assets/screenshot-2.png)

### 3. Regenerate PDF Previews administration tool. ###
![Regenerate PDF Previews administration tool.](https://github.com/gitlost/ghostscript-only-pdf-preview/raw/master/assets/screenshot-3.png)

### 4. Regenerate preview row action in list mode of Media Library. ###
![Regenerate preview row action in list mode of Media Library.](https://github.com/gitlost/ghostscript-only-pdf-preview/raw/master/assets/screenshot-4.png)


## Changelog ##

### 0.9.0 (8 Jan 2017) ###
* Initial github version.

## Upgrade Notice ##

### 0.9.0 ###
Improved PDF preview experience.
