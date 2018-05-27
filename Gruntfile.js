module.exports = function(grunt) {

	grunt.initConfig({
		pkg: grunt.file.readJSON('package.json'),
		uglify: {
			my_target: {
				files: {
					'assets/compiled/dependencies.min.js': [
						'node_modules/jquery/dist/jquery.min.js',
						'node_modules/bootstrap/dist/js/bootstrap.min.js'
					]
				}
			}
		}
	});

	grunt.loadNpmTasks('grunt-contrib-uglify');

	grunt.registerTask('default', ["uglify"]);
}
