module.exports = function(grunt) {

	grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        assets_dir: 'assets/',
        src_dir: '<%= assets_dir %>src/',
        dest_dir: '<%= assets_dir %>dist/',
        options_file: './app/includes/options.get.php',
        config_file: './app/config/config.php',
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
					'<%= src_dir %>js/bulk.actions.js',
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

    grunt.registerTask('default', ['concat', 'uglify', 'sass']);
	grunt.registerTask('prepare_dist', ['compress', 'setPHPConstant']);
	grunt.registerTask('dependencies_update', ['shell']);
}
