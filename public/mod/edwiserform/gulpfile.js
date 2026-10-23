const notify = require("gulp-notify");
const gulp = require('gulp');
const exec = require('gulp-exec');
const minify = require('gulp-minify');
const babel = require('gulp-babel');

const moodlepath = '/var/www/html/m37dev/';
const PRODUCTION = process.argv.includes('-production');

var minifyOptions = {
    ext: {
        min: '.js',
    }
};
minifyOptions.mangle = PRODUCTION;
minifyOptions.compress = PRODUCTION;
minifyOptions.noSource = PRODUCTION;
if (PRODUCTION == false) {
    minifyOptions.preserveComments = 'all';
}
gulp.task('script', function() {
    return gulp.src('amd/src/*.js')
    .pipe(babel({
        presets: [["@babel/preset-env"]]
    }))
    .pipe(minify(minifyOptions))
    .pipe(gulp.dest('amd/build'));
});

gulp.task('purge', function() {
    return gulp.src(moodlepath + 'admin/cli')
    .pipe(exec('php ' + moodlepath + 'admin/cli/purge_caches.php'))
    .pipe(notify('Purged all caches.'))
});

gulp.task('watch', function(done) {
    gulp.watch('amd/src/*.js', gulp.series('script', 'purge'));
    notify({message: 'Watching files'});
    done();
});

gulp.task('default', gulp.series(
    'script',
    'watch',
    'purge'
));
