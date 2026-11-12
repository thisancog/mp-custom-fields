/**************************************************************
	Saving only complete forms

	The hidden input .mpcf-interface-ready may only be 1 (and
	the form only be submitted) when all fields are loaded.
 **************************************************************/

const CONDITIONAL = '.mpcf-repeater-row:nth-child(1) .mpcf-conditional-choice select >> nth=0';

const marker     = page => page.evaluate(() => document.querySelector('.mpcf-interface-ready').value);
const trySubmit  = page => page.evaluate(() => { const form = document.getElementById('post'); form.setAttribute('action', '/submitted'); form.requestSubmit(); });

module.exports = {
	'saving is allowed once the interface is built': async ctx => {
		const result = await ctx.openPage({ body: ctx.buildPage({ rows: 2 }).body });
		ctx.equal(await marker(result.page), '1', 'marker after building');
		ctx.noErrors(result);
		await result.page.close();
	},

	'saving is blocked while fields are loading': async ctx => {
		const result = await ctx.openPage({
			body: ctx.buildPage({ rows: 2 }).body,
			ajax: () => new Promise(resolve => setTimeout(() => resolve({ body: '<div class="mpcf-field-option"><input name="caption"></div>' }), 1000))
		});

		await result.page.selectOption(CONDITIONAL, 'b');
		await result.page.waitForTimeout(150);
		ctx.equal(await marker(result.page), '0', 'marker while loading');

		await trySubmit(result.page);
		await result.page.waitForTimeout(150);
		ctx.assert(!result.submitted, 'form not submitted while loading');
		ctx.equal(result.dialogs.length, 1, 'user is told to wait');

		await result.page.waitForTimeout(1100);
		ctx.equal(await marker(result.page), '1', 'marker after loading');
		ctx.noErrors(result);
		await result.page.close();
	},

	'requests carry the nonce': async ctx => {
		const result = await ctx.openPage({ body: ctx.buildPage({ rows: 2 }).body, ajax: () => ({ body: '' }) });
		await result.page.selectOption(CONDITIONAL, 'b');
		await result.page.waitForTimeout(200);

		ctx.equal(result.requests[0] && result.requests[0].nonce, 'test-nonce', 'nonce of the request');
		await result.page.close();
	},

	'a failed request is reported and does not block saving': async ctx => {
		const result = await ctx.openPage({ body: ctx.buildPage({ rows: 2 }).body, ajax: () => ({ status: 403, body: 'Not allowed' }) });
		await result.page.selectOption(CONDITIONAL, 'b');
		await result.page.waitForTimeout(300);

		ctx.equal(await marker(result.page), '1', 'marker after the failed request');
		ctx.assert(result.errors.some(e => e.includes('[MPCF]') && e.includes('403')), 'error reported in the console');
		await result.page.close();
	},

	'saving stays blocked if the interface could not be built': async ctx => {
		const body   = ctx.buildPage({ rows: 2 }).body.replace('<ol class="mpcf-repeater-wrapper" data-basename="sections"', '<ol class="broken" data-basename="sections"'),
			  result = await ctx.openPage({ body });

		ctx.equal(await marker(result.page), '0', 'marker after the build failed');
		await trySubmit(result.page);
		await result.page.waitForTimeout(150);
		ctx.assert(!result.submitted, 'form not submitted');
		ctx.assert(result.errors.some(e => e.includes('could not be built completely')), 'error reported in the console');
		await result.page.close();
	},

	'submitting while the page is still loading is blocked': async ctx => {
		const result = await ctx.openPage({
			body:    ctx.buildPage({ rows: 2 }).body.replace('<form id="post">', '<form id="post" action="/submitted">'),
			bodyEnd: '<script>document.getElementById("post").requestSubmit();</script>'
		});

		ctx.assert(!result.submitted, 'form not submitted during page load');
		ctx.equal(await marker(result.page), '1', 'marker after loading');
		await result.page.close();
	}
};
