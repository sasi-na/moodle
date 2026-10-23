// Gulp
var gulp = require('gulp');

// Sass/CSS stuff
var sass = require('gulp-sass');
var concat = require('gulp-concat');
var prefix = require('gulp-autoprefixer');
var minifycss = require('gulp-minify-css');
var shell  = require('gulp-shell');
var del = require('del');

// JS stuff
const minify = require('gulp-minify');
var babel = require("gulp-babel");

// Compile all your Sass.
gulp.task('sass', function (){
    return gulp.src(['./scss/main.scss'])
    .pipe(sass({
        includePaths: ['./sass'],
        outputStyle: 'expanded'
    }))
    .pipe(prefix(
        "last 1 version", "> 1%", "ie 8", "ie 7"
        ))
    .pipe(minifycss())
    .pipe(concat('styles.min.css'))
    .pipe(gulp.dest('./style'));
});

gulp.task('compress', function() {
    return gulp.src('./amd/src/*.js')
    .pipe(babel())
    .pipe(minify({
        ext:{
           min:'.min.js'
        },
        noSource: true,
        ignoreFiles: []
    }))
    .pipe(gulp.dest('./amd/build'))
});

gulp.task('purge', shell.task('php '+__dirname+'/../../admin/cli/purge_caches.php'));

gulp.task('clean', function() {
    return del(['amd/build/*']);
});

gulp.task('watch', function(done) {
    gulp.watch('./amd/src/*.js', gulp.series('compress', 'purge'));
    gulp.watch('./scss/*.scss', gulp.series('sass', 'purge'));
    gulp.watch('./templates/**/*', gulp.series('purge'));
    done();
});

gulp.task('default', gulp.series('clean', 'watch', 'compress', 'sass', 'purge'));
