module.exports = function(grunt) {

	grunt.initConfig({
		pkg: grunt.file.readJSON('package.json'),
		uglify: {
			my_target: {
				files: {
					'assets/js/dependencies.min.js': [
						'node_modules/jquery/dist/jquery.min.js',
						'node_modules/bootstrap/dist/js/bootstrap.min.js'
					]
				}
			}
		}
	});

	grunt.loadNpmTasks('grunt-contrib-uglify');

}
