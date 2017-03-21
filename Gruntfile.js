module.exports = function( grunt ) { //The wrapper function

	require( 'load-grunt-tasks' )( grunt );
	var shell = require( 'shelljs' );

	// Project configuration & task configuration
	grunt.initConfig( {
		pkg: grunt.file.readJSON( 'package.json' ),

		// The uglify task and its configurations
		uglify: {
			options: {
				banner: '/*! <%= pkg.name %> <%= pkg.version %> <%= grunt.template.today("yyyy-mm-dd") %> */\n'
			},
			build: {
				files: [ {
					expand: true,     // Enable dynamic expansion.
					src: [ 'js/*.js', '!js/*.min.js' ], // Actual pattern(s) to match.
					ext: '.min.js'   // Dest filepaths will have this extension.
				} ]
			}
		},

		// The jshint task and its configurations
		jshint: {
			all: [ 'js/*.js', '!js/*.min.js' ]
		},

		wp_readme_to_markdown: {
			convert:{
				files: {
					'README.md': 'readme.txt'
				},
				options: {
					'screenshot_url': 'https://github.com/gitlost/{plugin}/raw/master/assets/{screenshot}.png', //'https://ps.w.org/{plugin}/assets/{screenshot}.png',
					'post_convert': function ( readme ) {
						return '[![Build Status](https://travis-ci.org/gitlost/gs-only-pdf-preview.png?branch=master)](https://travis-ci.org/gitlost/gs-only-pdf-preview)\n'
							+ '[![codecov.io](http://codecov.io/github/gitlost/gs-only-pdf-preview/coverage.svg?branch=master)](http://codecov.io/github/gitlost/gs-only-pdf-preview?branch=master)\n'
							+ '[![WordPress plugin](https://img.shields.io/wordpress/plugin/v/gs-only-pdf-preview.svg)](https://wordpress.org/plugins/gs-only-pdf-preview/)\n'
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
					mainFile: 'gs-only-pdf-preview.php',   // Main project file.
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
							'../gs-only-pdf-preview/readme.txt',
							'../gs-only-pdf-preview/gs-only-pdf-preview.php',
							'../gs-only-pdf-preview/uninstall.php',
							'../gs-only-pdf-preview/includes/class-gopp-image-editor-gs.php',
							'../gs-only-pdf-preview/includes/debug-gopp-image-editor-gs.php',
							'../gs-only-pdf-preview/js/gs-only-pdf-preview.js',
							'../gs-only-pdf-preview/js/gs-only-pdf-preview.min.js',
							'../gs-only-pdf-preview/languages/gs-only-pdf-preview.pot',
							'../gs-only-pdf-preview/languages/gs-only-pdf-preview-fr_FR.mo',
							'../gs-only-pdf-preview/languages/gs-only-pdf-preview-fr_FR.po'
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

		qunit: {
			all: [ 'tests/qunit/index.html' ]
		},

		clean: {
			js: [ 'js/*.min.js' ]
		}

	} );

	// Default task(s), executed when you run 'grunt'
	grunt.registerTask( 'default', [ 'uglify', 'wp_readme_to_markdown', 'makepot', 'compress' ] );

	// Creating a custom task
	grunt.registerTask( 'test', [ 'jshint', 'phpunit', 'qunit' ] );

	grunt.registerTask( 'generate_fixtures', function () {
		shell.exec( 'php tools/gen_js_fixtures.php' );
	} );

	grunt.registerTask( 'test_qunit', [ 'jshint', 'qunit' ] );
};
