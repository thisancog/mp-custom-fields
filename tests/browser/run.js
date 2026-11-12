/**************************************************************
	Browser tests

	node tests/browser/run.js                      run all tests
	node tests/browser/run.js --update-snapshots   store the current results as reference

	Requires Playwright's Chromium (npx playwright install chromium)
	or set CHROME_PATH to a local Chrome / Chromium.
 **************************************************************/

const fs             = require('fs'),
	  path           = require('path'),
	  { chromium }   = require('playwright'),
	  { openPage, dumpFields } = require('./harness'),
	  { buildPage }  = require('./fixtures');

const SNAPSHOTS        = path.join(__dirname, 'snapshots'),
	  updateSnapshots  = process.argv.includes('--update-snapshots');


class TestFailure extends Error {}

function createContext(browser) {
	const ctx = {
		browser, openPage: options => openPage(browser, options), dumpFields, buildPage,

		assert(condition, message) {
			if (!condition) throw new TestFailure(message);
		},

		equal(actual, expected, message) {
			if (actual !== expected)
				throw new TestFailure(`${message}\n      expected: ${JSON.stringify(expected)}\n      actual:   ${JSON.stringify(actual)}`);
		},

		noErrors(result, message = 'no errors in the browser') {
			if (result.errors.length)
				throw new TestFailure(`${message}, but got:\n      ${result.errors.join('\n      ')}`);
		},

	//	compares with tests/browser/snapshots/<name>.txt (created on first run or with --update-snapshots)
		snapshot(name, value) {
			const file = path.join(SNAPSHOTS, name + '.txt');

			if (updateSnapshots || !fs.existsSync(file)) {
				fs.mkdirSync(SNAPSHOTS, { recursive: true });
				fs.writeFileSync(file, value);
				return;
			}

			const expected = fs.readFileSync(file, 'utf8');
			if (expected === value) return;

			const a = expected.split('\n'), b = value.split('\n'),
				  i = a.findIndex((line, n) => line !== b[n]);
			throw new TestFailure(`differs from snapshot "${name}" at line ${i + 1}:\n      expected: ${a[i]}\n      actual:   ${b[i]}`);
		}
	};

	return ctx;
}


(async () => {
	const files   = fs.readdirSync(path.join(__dirname, 'tests')).filter(f => f.endsWith('.test.js')).sort(),
		  browser = await chromium.launch(process.env.CHROME_PATH ? { executablePath: process.env.CHROME_PATH } : {}),
		  ctx     = createContext(browser);

	let passed = 0, failed = 0;

	for (const file of files) {
		const tests = require(path.join(__dirname, 'tests', file));
		console.log(file);

		for (const [name, test] of Object.entries(tests)) {
			try {
				await test(ctx);
				passed++;
				console.log(`  ✓ ${name}`);
			} catch (error) {
				failed++;
				console.log(`  ✗ ${name}\n      ${error instanceof TestFailure ? error.message : error.stack}`);
			}
		}
	}

	await browser.close();
	console.log(`\n${passed} passed, ${failed} failed`);
	process.exit(failed ? 1 : 0);
})().catch(error => {
	console.error(error);
	process.exit(1);
});
