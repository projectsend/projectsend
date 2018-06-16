module.exports = function(grunt) {

	grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        assets_dir: 'assets/',
        src_dir: '<%= assets_dir %>src/',
        dest_dir: '<%= assets_dir %>dist/',
        dependencies_bower_dir: 'bower_components/',
        dependencies_composer_dir: 'vendor/',
        dependencies_npm_dir: 'node_modules/',
		concat: {
			options: {
				separator: '\n\n',
			},
			projectsend: {
				src: [
					'<%= src_dir %>js/jquery.functions.js',
					'<%= src_dir %>js/jquery.psendmodal.js',
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
				files: { '<%= dest_dir %>js/projectsend.min.js' : '<%= dest_dir %>js/projectsend.js' }
			},
			compatibility: {
				files: { '<%= dest_dir %>js/ie8compatibility.min.js' : '<%= dest_dir %>js/ie8compatibility.js' }
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
            assets_dir: {
                constant    : 'ASSETS_DIR',
                value       : '<%= assets_dir %>',
                file        : './includes/site.options.php'
            },
            assets_compiled_dir: {
                constant    : 'ASSETS_COMPILED_DIR',
                value       : '<%= dest_dir %>',
                file        : './includes/site.options.php'
            },
            dependencies_bower_dir: {
                constant    : 'BOWER_DEPENDENCIES_DIR',
                value       : '<%= dependencies_bower_dir %>',
                file        : './includes/site.options.php'
            },
            dependencies_composer_dir: {
                constant    : 'COMPOSER_DEPENDENCIES_DIR',
                value       : '<%= dependencies_composer_dir %>',
                file        : './includes/site.options.php'
            },
            dependencies_npm_dir: {
                constant    : 'NPM_DEPENDENCIES_DIR',
                value       : '<%= dependencies_npm_dir %>',
                file        : './includes/site.options.php'
            },
        },
        compress: {
			dist: {
				options: {
					archive: '<%= pkg.name %>-<%= pkg.version %>.zip'
				},
				files: [
					{
                        expand: true,
                        cwd: './',
						src: './*'
					}
				]
			}
		}
	});

	grunt.loadNpmTasks('grunt-contrib-compress');
	grunt.loadNpmTasks('grunt-contrib-concat');
	grunt.loadNpmTasks('grunt-contrib-copy');
    grunt.loadNpmTasks('grunt-contrib-sass');
    grunt.loadNpmTasks('grunt-php-set-constant');
	grunt.loadNpmTasks('grunt-contrib-uglify');

    grunt.registerTask('default', ['concat', 'uglify', 'sass']);
    grunt.registerTask('php_constants', ['setPHPConstant']);
	grunt.registerTask('prepare_dist', ['compress']);
}
