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
        copy: {
            sync: {
                files: sites_files
            }
        }
    });

    grunt.loadNpmTasks('grunt-contrib-copy');
    grunt.registerTask('default', ['copy:sync']);
};