/*********************************************************************
	MP Custom Fields - build tasks

	gulp build     check the syntax of all PHP and JS files, run all tests
	gulp test      run the PHP and browser tests only
	gulp commit    bump the version, commit and push

	Requirements: Node.js, PHP on the command line, and once:
	npm install && npx playwright install chromium
 *********************************************************************/

var gulp			= require('gulp'),
	childProcess	= require('child_process'),
	fs 				= require('fs'),
	path			= require('path');


//	runs a command, shows its output and fails the task if it fails
function run(command, args) {
	return new Promise(function(resolve, reject) {
		var child = childProcess.spawn(command, args, { stdio: 'inherit', cwd: __dirname });

		child.on('error', function(error) {
			reject(new Error('Could not run "' + command + '": ' + error.message));
		});

		child.on('close', function(code) {
			if (code === 0) resolve();
			else reject(new Error('"' + command + ' ' + args.join(' ') + '" failed (exit code ' + code + ').'));
		});
	});
}

//	all files with the given extension, without node_modules
function findFiles(dir, extension) {
	return fs.readdirSync(dir, { withFileTypes: true }).reduce(function(files, entry) {
		var file = path.join(dir, entry.name);

		if (entry.isDirectory() && entry.name !== 'node_modules' && entry.name[0] !== '.')
			return files.concat(findFiles(file, extension));

		if (entry.isFile() && entry.name.endsWith(extension))
			files.push(file);

		return files;
	}, []);
}

//	checks files one by one with a command, e.g. php -l, and reports all failing files
function checkSyntax(label, files, command, args) {
	var failed = files.filter(function(file) {
		var result = childProcess.spawnSync(command, args.concat([file]), { encoding: 'utf8' });

		if (result.error) throw new Error('Could not run "' + command + '": ' + result.error.message);
		if (result.status === 0) return false;

		console.error((result.stdout + result.stderr).trim());
		return true;
	});

	if (failed.length)
		throw new Error(label + ': ' + failed.length + ' of ' + files.length + ' files contain errors.');

	console.log(label + ': ' + files.length + ' files OK');
}



/*********************************************************************
	Syntax checks
 *********************************************************************/

gulp.task('php', function(done) {
	checkSyntax('PHP syntax', findFiles(__dirname, '.php'), 'php', ['-l']);
	done();
});

gulp.task('js', function(done) {
	checkSyntax('JS syntax', findFiles(path.join(__dirname, 'inc/js'), '.js'), process.execPath, ['--check']);
	done();
});



/*********************************************************************
	Tests (see tests/)
 *********************************************************************/

gulp.task('test:php', function() {
	return run('php', ['tests/php/run.php']);
});

gulp.task('test:browser', function() {
	return run(process.execPath, ['tests/browser/run.js']);
});

gulp.task('test', gulp.series('test:php', 'test:browser'));



/*********************************************************************
	Bundled tasks
 *********************************************************************/

gulp.task('build', gulp.series(gulp.parallel('php', 'js'), 'test'));
gulp.task('default', gulp.series('build'));



/*********************************************************************
	Version bump and git push

	The plugins for these tasks are only loaded when they are used.
 *********************************************************************/

let commitMessage, versioningPattern;

gulp.task('gitVersioning', function() {
	var bump    = require('gulp-bump'),
		prompt  = require('gulp-prompt'),
		replace = require('gulp-string-replace'),
		semver  = require('semver');

	return gulp.src('./package.json', { buffer: false })
			   .pipe(prompt.prompt([
			   		{
			   			type:    'list',
			   			name:    'version',
			   			message: 'What kind of changes were made?',
			   			choices: [ 'major', 'minor', 'patch' ]
			   		},
			   		{
			   			type:    'input',
			   			name:    'commit',
			   			message: 'Please enter a commit message.'
			   		}], function(res) {
			   			versioningPattern = res.version;
			   			commitMessage     = res.commit;

			   			let oldVersion = JSON.parse(fs.readFileSync('./package.json', 'utf8')).version,
			   				newVersion = semver.inc(oldVersion, versioningPattern);

			   			gulp.src('./package.json')
			   				.pipe(bump({ version: newVersion, quiet: true }))
				   			.pipe(gulp.dest('./'))
				   			.pipe(gulp.src('./mp-custom-fields.php'))		// the plugin header
				   			.pipe(replace(/Version: \d+\.?(\d+\.?)?(\d+\.?)?/g, 'Version: ' + newVersion, { logs: { enabled: false }}))
				   			.pipe(gulp.dest('./'));
			   	}));
});

gulp.task('gitCommit', function() {
	var git = require('gulp-git');

	return gulp.src('./')
			   .pipe(git.add({ quiet: true }))
			   .pipe(git.commit(commitMessage));
});

gulp.task('gitPush', function(cb) {
	var git    = require('gulp-git'),
		branch = childProcess.execSync('git rev-parse --abbrev-ref HEAD', { cwd: __dirname, encoding: 'utf8' }).trim();

	git.push('origin', branch, { quiet: true }, function(error) {
		if (error) throw error;
	});

	cb();
});

//	the build has to succeed before anything is committed
gulp.task('commit', gulp.series('build', 'gitVersioning', 'gitCommit', 'gitPush'));
