module.exports = function( grunt ) { //The wrapper function

	require( 'load-grunt-tasks' )( grunt );

	// Project configuration & task configuration
	grunt.initConfig( {
		pkg: grunt.file.readJSON( 'package.json' ),

		wp_readme_to_markdown: {
			convert:{
				files: {
					'README.md': 'readme.txt'
				},
				options: {
					'screenshot_url': 'https://github.com/gitlost/{plugin}/raw/master/assets/{screenshot}.png', //'https://ps.w.org/{plugin}/assets/{screenshot}.png',
					'post_convert': function ( readme ) {
						return '[![Build Status](https://travis-ci.org/gitlost/ghostscript-only-pdf-preview.png?branch=master)](https://travis-ci.org/gitlost/ghostscript-only-pdf-preview)\n'
							+ '[![codecov.io](http://codecov.io/github/gitlost/ghostscript-only-pdf-preview/coverage.svg?branch=master)](http://codecov.io/github/gitlost/ghostscript-only-pdf-preview?branch=master)\n'
							+ readme;
					}
				}
			}
		},

		makepot: {
			target: {
				options: {
					cwd: '',                          // Directory of files to internationalize.
					domainPath: '/languages',         // Where to save the POT file.
					exclude: [ 'tests/' ],            // List of files or directories to ignore.
					include: [],                      // List of files or directories to include.
					mainFile: 'ghostscript-only-pdf-preview.php',   // Main project file.
					potComments: '',                  // The copyright at the beginning of the POT file.
					potFilename: '',                  // Name of the POT file.
					potHeaders: {
						poedit: true,                 // Includes common Poedit headers.
						'x-poedit-keywordslist': true // Include a list of all possible gettext functions.
					},                                // Headers to add to the generated POT file.
					processPot: null,                 // A callback function for manipulating the POT file.
					type: 'wp-plugin',                // Type of project (wp-plugin or wp-theme).
					updateTimestamp: false,           // Whether the POT-Creation-Date should be updated without other changes.
					updatePoFiles: false              // Whether to update PO files in the same directory as the POT file.
				}
			}
		},

		po2mo: {
			files: {
				src: 'languages/*.po',
				expand: true,
			},
		},

		compress: {
			main: {
				options: {
					archive: 'dist/<%= pkg.name %>-<%= pkg.version %>.zip',
					mode: 'zip'
				},
				files: [
					{
						src: [
							'../ghostscript-only-pdf-preview/readme.txt',
							'../ghostscript-only-pdf-preview/ghostscript-only-pdf-preview.php',
							'../ghostscript-only-pdf-preview/uninstall.php',
							'../ghostscript-only-pdf-preview/includes/class-gopp-image-editor-gs.php',
							'../ghostscript-only-pdf-preview/languages/ghostscript-only-pdf-preview.pot',
							'../ghostscript-only-pdf-preview/languages/ghostscript-only-pdf-preview-fr_FR.mo',
							'../ghostscript-only-pdf-preview/languages/ghostscript-only-pdf-preview-fr_FR.po'
						]
					}
				]
			}
		},

		phpunit: {
			classes: {
				dir: 'tests/'
			},
			options: {
				bin: 'WP_TESTS_DIR=/var/www/wordpress-develop/tests/phpunit phpunit',
				configuration: 'phpunit.xml'
			}
		},

	} );

	// Default task(s), executed when you run 'grunt'
	grunt.registerTask( 'default', [ 'wp_readme_to_markdown', 'makepot', 'compress' ] );

	// Creating a custom task
	grunt.registerTask( 'test', [ 'phpunit' ] );
};
