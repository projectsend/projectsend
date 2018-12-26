module.exports = function(grunt) {

	grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        src_dir: './assets/',
        dest_dir: './public/assets/',
        options_file: './src/includes/options.get.php',
        config_file: './src/config/config.php',
        copy: {
            jquery: {
                expand: false,
                src: './node_modules/jquery/dist/jquery.min.js',
                dest: '<%= dest_dir %>/lib/jquery.min.js'
            },
            jquery_migrate: {
                expand: false,
                src: './node_modules/jquery-migrate/dist/jquery-migrate.min.js',
                dest: '<%= dest_dir %>/lib/jquery-migrate.min.js'
            },
            bootstrap: {
                expand: false,
                src: './node_modules/bootstrap/dist/js/bootstrap.min.js',
                dest: '<%= dest_dir %>/lib/bootstrap.min.js'
            },
        },
		concat: {
			options: {
				separator: '\n\n',
			},
			dependencies_js: {
				src: [
                    'node_modules/axios/dist/axios.min.js',
                    'node_modules/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js',
                    'node_modules/bootstrap-toggle/js/bootstrap-toggle.min.js',
                    'node_modules/chosen-js/chosen.jquery.min.js',
                    'node_modules/footable/dist/footable.all.min.js',
                    'node_modules/chart.js/dist/Chart.bundle.min.js',
                    'node_modules/js-cookie/src/js.cookie.js',
                    'node_modules/node-jen/jen.js',
                    'node_modules/@yaireo/tagify/dist/jQuery.tagify.min.js',
                    'node_modules/sprintf-js/dist/sprintf.min.js'
				],
				dest: '<%= dest_dir %>js/dependencies.js',
			},
			dependencies_css: {
				src: [
                    'node_modules/bootstrap/dist/css/bootstrap.min.css',
                    'node_modules/bootstrap/dist/css/bootstrap-theme.min.css',
                    'node_modules/bootstrap-datepicker/dist/css/bootstrap-datepicker3.min.css',
                    'node_modules/bootstrap-toggle/css/bootstrap-toggle.min.css',
                    'node_modules/chosen-js/chosen.min.css',
                    'node_modules/@yaireo/tagify/dist/tagify.css'
				],
				dest: '<%= dest_dir %>css/dependencies.css',
			},
			projectsend: {
				src: [
					'<%= src_dir %>js/jquery.functions.js',
					'<%= src_dir %>js/bulk.actions.js',
					'<%= src_dir %>js/jquery.psendmodal.js',
                    '<%= src_dir %>js/dashboard.widgets.js',
                    '<%= src_dir %>js/jquery.validations.js',
					'<%= src_dir %>js/main.js',
				],
				dest: '<%= dest_dir %>js/projectsend.js',
			},
			compatibility: {
				src: [
					'<%= src_dir %>js/html5shiv.min.js',
					'<%= src_dir %>js/respond.min.js',
				],
				dest: '<%= dest_dir %>js/ie8compatibility.js',
			}
		},
		uglify: {
			projectsend: {
                options: {
                    unused: false,
                },
				files: { '<%= dest_dir %>js/projectsend.min.js' : '<%= dest_dir %>js/projectsend.js' }
			},
			compatibility: {
				files: { '<%= dest_dir %>js/ie8compatibility.min.js' : '<%= dest_dir %>js/ie8compatibility.js' }
			},
			dependencies_js: {
				files: { '<%= dest_dir %>js/dependencies.min.js' : '<%= dest_dir %>js/dependencies.js' }
			},
			dependencies_css: {
				files: { '<%= dest_dir %>css/dependencies.min.css' : '<%= dest_dir %>css/dependencies.css' }
			}
		},
		sass: {
			dist: {
				options: {
                    style: 'compressed'
				},
				files: { '<%= dest_dir %>css/main.min.css' : '<%= src_dir %>css/main.scss' }
			}
		},
        setPHPConstant: {
            version: {
                constant    : 'CURRENT_VERSION',
                value       : '<%= pkg.version %>',
                file        : '<%= config_file %>'
            },
            debug: {
                constant    : 'DEBUG',
                value       : 'false',
                file        : '<%= config_file %>'
            },
            is_dev: {
                constant    : 'IS_DEV',
                value       : 'false',
                file        : '<%= config_file %>'
            },
        },
        compress: {
			dist: {
				options: {
                    archive: '<%= pkg.name %>-<%= pkg.version %>.zip'
                    // This is not implemented yet
                    // Don't forget to add an ignore to the custom config file config/config.php
				},
				files: [
					{
                        expand: true,
                        cwd: './',
						src: './*'
					}
				]
			}
        },
        shell: {
            command: ["npm update", "bower update", "composer update"].join('&&')
        }
	});

	grunt.loadNpmTasks('grunt-contrib-compress');
	grunt.loadNpmTasks('grunt-contrib-concat');
	grunt.loadNpmTasks('grunt-contrib-copy');
    grunt.loadNpmTasks('grunt-contrib-sass');
    grunt.loadNpmTasks('grunt-php-set-constant');
    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-shell');

    grunt.registerTask('default', ['copy', 'concat', 'uglify', 'sass']);
	grunt.registerTask('prepare_dist', ['compress', 'setPHPConstant']);
	grunt.registerTask('dependencies_update', ['shell']);
}
