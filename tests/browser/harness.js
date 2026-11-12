/**************************************************************
	Browser test harness

	Opens test pages in Chromium (Playwright) with the plugin's
	real admin.js and admin.css. WordPress itself is replaced by
	small stand-ins (window.wp, jQuery plugins), AJAX requests
	are answered by the test.
 **************************************************************/

const fs   = require('fs'),
	  path = require('path');

const PLUGIN = path.resolve(__dirname, '../..');

function findJQuery() {
	const candidates = [ process.env.JQUERY_PATH, '/usr/share/javascript/jquery/jquery.min.js' ];
	try { candidates.unshift(require.resolve('jquery/dist/jquery.min.js')); } catch (e) {}

	const found = candidates.find(file => file && fs.existsSync(file));
	if (!found) throw new Error('jQuery not found - run "npm install" or set JQUERY_PATH.');
	return found;
}

const FILES = {
	'/admin.js':  process.env.MPCF_ADMIN_JS || path.join(PLUGIN, 'inc/js/admin.js'),		// MPCF_ADMIN_JS: test another version
	'/admin.css': path.join(PLUGIN, 'inc/admin.css'),
	'/jquery.js': findJQuery()
};

//	stand-ins for WordPress: color picker (adds a "Clear" button like the real one),
//	editor (records calls), svgPainter
const WP_STUBS = `
	var ajaxurl = '/ajax', localizedmpcf = { nonce: 'test-nonce' };
	window.testLog = { pickers: 0, editor: [] };

	jQuery.fn.wpColorPicker = function() { return this.each(function() {
		testLog.pickers++;
		if (this.classList.contains('wp-color-picker')) return;
		this.classList.add('wp-color-picker');
		var clear = document.createElement('input'); clear.type = 'button'; clear.className = 'button wp-picker-clear'; this.after(clear);
	}); };
	jQuery.fn.sortable = function() { return this; };
	jQuery.fn.disableSelection = function() { return this; };

	var wp = {
		svgPainter: { setColors: function() {}, paintElement: function() {} },
		editor: {
			getContent: function(id) { var t = document.getElementById(id); return t ? t.value : undefined; },
			autop:      function(s) { return /^<p>/.test(s) ? s : '<p>' + s + '</p>'; },
			initialize: function(id) { var t = document.getElementById(id); testLog.editor.push('init ' + t.name + ' = ' + t.value); var m = document.createElement('div'); m.className = 'fake-mce'; m.dataset.for = id; t.after(m); },
			remove:     function(id) { var t = document.getElementById(id); testLog.editor.push('remove ' + t.name); var m = document.querySelector('.fake-mce[data-for="' + id + '"]'); if (m) m.remove(); }
		}
	};`;

//	the icon font is not available: give the row controls a size
const TEST_CSS = `.mpcf-repeater-row-controls { opacity: 1; }
	.mpcf-repeater-row-controls [role=button] { display: block; width: 20px; height: 20px; background: #ccc; margin: 1px; }
	body { margin: 20px; width: 900px; }`;


//	Opens a page. options: body (HTML), ajax (function(request) -> { status, body } | Promise),
//	viewport, bodyEnd (HTML after the form, e.g. inline scripts)
async function openPage(browser, { body, ajax = null, viewport = { width: 1000, height: 800 }, bodyEnd = '' }) {
	const page   = await browser.newPage({ viewport }),
		  result = { page, errors: [], dialogs: [], requests: [], submitted: false };

	page.on('pageerror', e => result.errors.push(e.message));
	page.on('console',   m => { if (m.type() === 'error' && !m.text().startsWith('Failed to load resource')) result.errors.push(m.text()); });
	page.on('dialog',    d => { result.dialogs.push(d.message()); d.dismiss(); });

	const html = `<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="/admin.css"><style>${TEST_CSS}</style>`
			   + `<script src="/jquery.js"></script><script>${WP_STUBS}</script><script src="/admin.js"></script></head>`
			   + `<body>${body}${bodyEnd}</body></html>`;

	await page.route('**/*', async route => {
		const url = new URL(route.request().url());

		if (url.pathname === '/index.html')	return route.fulfill({ body: html, contentType: 'text/html' });
		if (url.pathname === '/submitted') {
			result.submitted = true;
			return route.fulfill({ body: 'saved', contentType: 'text/html' });
		}
		if (url.pathname === '/ajax') {
			const request = new URLSearchParams(route.request().postData() || url.search);
			result.requests.push(Object.fromEntries(request));
			const answer = ajax ? await ajax(request) : { status: 500, body: '' };
			return route.fulfill({ status: answer.status || 200, body: answer.body || '' });
		}
		if (FILES[url.pathname]) {
			const type = url.pathname.endsWith('.css') ? 'text/css' : 'application/javascript';
			return route.fulfill({ body: fs.readFileSync(FILES[url.pathname]), contentType: type });
		}
		return route.abort();
	});

	await page.goto('http://mpcf.test/index.html');
	await page.waitForFunction(() => typeof mpcf !== 'undefined' || document.readyState === 'complete');
	await page.waitForTimeout(200);
	return result;
}


//	all names, IDs, own names (data-name) and panel base names of the fields, in document order
async function dumpFields(page) {
	return page.evaluate(() => [...document.querySelectorAll('.mpcf-panels [name], .mpcf-panels [id], .mpcf-panels [data-name], .mpcf-panel[data-basename]')]
		.filter(e => e.type !== 'button' && e.tagName !== 'SCRIPT' && e.tagName !== 'LABEL' && !e.classList.contains('mpcf-interface-ready'))
		.map(e => `${e.tagName} name=${e.getAttribute('name')} id=${e.getAttribute('id')} data-name=${e.getAttribute('data-name')}`
				+ (e.classList.contains('mpcf-panel') ? ` basename=${e.dataset.basename}` : ''))
		.join('\n'));
}


module.exports = { openPage, dumpFields, PLUGIN };
