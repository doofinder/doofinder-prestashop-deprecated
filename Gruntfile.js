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
                    {expand: true, src: 'translations/**', dest: 'release/doofinder'}
                ]
            },
            latest_to_version: {
                files: [
                    {src: 'doofinder-p1.5-latest.zip', dest: 'doofinder-p1.5-<%= packageconfig.version %>.zip'}
                ]
            }
        },
        compress: {
            release: {
                options: {
                    archive: 'doofinder-p1.5-latest.zip'
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
            release: ['release']
        }
    });

    grunt.loadNpmTasks('grunt-contrib-copy');
    grunt.loadNpmTasks('grunt-contrib-compress');
    grunt.loadNpmTasks('grunt-contrib-clean');
    grunt.loadNpmTasks('grunt-version');

    grunt.registerTask('default', ['copy:sync']);
    grunt.registerTask('release', ['copy:release', 'compress:release', 'copy:latest_to_version', 'clean:release']);
};