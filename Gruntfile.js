module.exports = function(grunt) {

    var localconfig = grunt.file.readJSON('localconfig.json'),
        sites_files = (function(sites){
            var paths = [],
                dest = null;

            for (var i = 0, j = sites.length; i < j; i++)
            {
                dest = sites[i] + '/modules/doofinder/';

                paths.push({expand: true, src: '*.php', dest: dest});
                paths.push({expand: true, src: '*.tpl', dest: dest});
                paths.push({expand: true, src: 'logo.*', dest: dest});

                paths.push({expand: true, src: 'css/**', dest: dest});
                paths.push({expand: true, src: 'img/**', dest: dest});
                paths.push({expand: true, src: 'translations/**', dest: dest});
	   }

            return paths;
        })(localconfig.sites);

    grunt.initConfig({
        packageconfig: grunt.file.readJSON('package.json'),

        copy: {
            sync: {
                files: sites_files
            },
            release: {
                files: [
                    {expand: true, src: '*.php', dest: 'release/doofinder'},
                    {expand: true, src: '*.tpl', dest: 'release/doofinder'},
                    {expand: true, src: '*.md', dest: 'release/doofinder'},
                    {expand: true, src: 'logo.*', dest: 'release/doofinder'},
                    {expand: true, src: 'css/**', dest: 'release/doofinder'},
                    {expand: true, src: 'js/**', dest: 'release/doofinder'},
                    {expand: true, src: 'translations/**', dest: 'release/doofinder'},
        		    {expand: true, src: 'override/**', dest: 'release/doofinder'},
        		    {expand: true, src: 'lib/**', dest: 'release/doofinder'},
                    {expand: true, src: 'views/**', dest: 'release/doofinder'}
                ]
            },
            release_no_full: {
                files: [
                    {expand: true, src: '*.php', dest: 'release/doofinder'},
                    {expand: true, src: '*.tpl', dest: 'release/doofinder'},
                    {expand: true, src: '*.md', dest: 'release/doofinder'},
                    {expand: true, src: 'logo.*', dest: 'release/doofinder'},
                    {expand: true, src: 'css/**', dest: 'release/doofinder'},
                    {expand: true, src: 'js/**', dest: 'release/doofinder'},
                    {expand: true, src: 'translations/**', dest: 'release/doofinder'},
                    {expand: true, src: 'lib/**', dest: 'release/doofinder'},
                    {expand: true, src: 'views/**', dest: 'release/doofinder'}
                ]
            },
            latest_to_version: {
                files: [
                    {src: 'dist/doofinder-p1.5-latest.zip', dest: 'dist/doofinder-p1.5-<%= packageconfig.version %>.zip'}
                ]
            }
        },
        compress: {
            release: {
                options: {
                    archive: 'dist/doofinder-p1.5-latest.zip'
                },
                files: [
                    {expand: true, cwd: 'release', src: '**/*'}
                ]
            },
            release_no_full: {
                options: {
                    archive: 'dist/doofinder-p1.5-latest-no-full.zip'
                },
                files: [
                    {expand: true, cwd: 'release', src: '**/*'}
                ]
            }
        },
        clean: {
            options: {
                force: true
            },
            release: ['release'],
            dist: ['dist']
        },
        version: {
            release: {
                options: {
                    prefix: '\\s+const VERSION = "'
                },
                src: ['doofinder.php']
            }
        },
        watch: {
            dev: {
                files: ['**/*', '!**/node_modules/**'],
                tasks: ['copy:sync']
            }
        }
    });

    grunt.loadNpmTasks('grunt-contrib-copy');
    grunt.loadNpmTasks('grunt-contrib-compress');
    grunt.loadNpmTasks('grunt-contrib-clean');
    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.loadNpmTasks('grunt-version');

    grunt.registerTask('default', ['copy:sync', 'watch:dev']);
    grunt.registerTask('release', ['version:release', 'copy:release', 'compress:release', 'copy:latest_to_version', 'clean:release']);
    grunt.registerTask('release_no_full', ['version:release', 'copy:release_no_full', 'compress:release_no_full', 'clean:release']);
};
