/**************************************************************
	Warning about unsaved changes

	Leaving the page with changed custom fields shows the
	browser's "Leave site?" dialog: the beforeunload event is
	cancelled. Browsers only show it after the user has
	interacted with the page, so every test clicks first.
 **************************************************************/

const ROW   = n => `.mpcf-repeater-wrapper[data-basename="sections"] > .mpcf-repeater-row:nth-child(${n})`;
const FIELD = 'input[type="text"] >> nth=0';

//	whether leaving the page now would ask first: the page's beforeunload handler
//	cancels the event (headless Chrome does not show the dialog itself)
async function asksBeforeLeaving(result) {
	const asked = await result.page.evaluate(() => {
		const e = new Event('beforeunload', { cancelable: true });
		window.dispatchEvent(e);
		return e.defaultPrevented;
	});
	await result.page.close();
	return asked;
}

const open = async (ctx, options = {}) => {
	const result = await ctx.openPage({ body: ctx.buildPage({ rows: 2 }).body, ...options });
	await result.page.click('body', { position: { x: 1, y: 1 } });		// user activation
	return result;
};

module.exports = {
	'no warning without changes': async ctx => {
		const result = await open(ctx);
		ctx.assert(!await asksBeforeLeaving(result), 'no dialog');
		ctx.noErrors(result);
	},

	'warning after typing into a field': async ctx => {
		const result = await open(ctx);
		await result.page.fill(FIELD, 'changed');
		ctx.assert(await asksBeforeLeaving(result), 'dialog shown');
	},

	'no warning when a change is undone': async ctx => {
		const result = await open(ctx),
			  value  = await result.page.inputValue(FIELD);

		await result.page.fill(FIELD, 'changed');
		await result.page.fill(FIELD, value);
		ctx.assert(!await asksBeforeLeaving(result), 'no dialog');
	},

	'moving a row counts as a change, moving it back does not': async ctx => {
		let result = await open(ctx);
		await result.page.click(`${ROW(1)} > .mpcf-repeater-row-controls .mpcf-repeater-row-move-down`);
		ctx.assert(await asksBeforeLeaving(result), 'dialog after moving');

		result = await open(ctx);
		await result.page.click(`${ROW(1)} > .mpcf-repeater-row-controls .mpcf-repeater-row-move-down`);
		await result.page.click(`${ROW(2)} > .mpcf-repeater-row-controls .mpcf-repeater-row-move-up`);
		ctx.assert(!await asksBeforeLeaving(result), 'no dialog after moving back');
	},

	'switching tabs is no change': async ctx => {
		const result = await open(ctx);
		await result.page.evaluate(() => { const tab = document.querySelector('.activetab'); tab.value = '1'; });
		ctx.assert(!await asksBeforeLeaving(result), 'no dialog');
	},

	'no warning when saving': async ctx => {
		const result = await open(ctx);
		await result.page.fill(FIELD, 'changed');
		await result.page.evaluate(() => { const form = document.getElementById('post'); form.setAttribute('action', '/submitted'); form.requestSubmit(); });
		await result.page.waitForTimeout(300);

		ctx.assert(result.submitted, 'form submitted');
		ctx.equal(result.dialogs.length, 0, 'no dialog while saving');
		await result.page.close();
	},

	'the filter switches the warning off': async ctx => {
		const result = await open(ctx, { bodyEnd: '<script>localizedmpcf.warnUnsaved = "0";</script>' });
		await result.page.fill(FIELD, 'changed');
		ctx.assert(!await asksBeforeLeaving(result), 'no dialog');
	},

	'typing into an editor in text mode counts as a change': async ctx => {
		const { escapeHtml } = require('../fixtures');
		const settings = escapeHtml(JSON.stringify({ wpautop: true, tinymce: {}, quicktags: true }));
		const body = ctx.buildPage({ rows: 1 }).body.replace('<div class="mpcf-panels-tabs">', '<div class="mpcf-panels-tabs">'
			+ `<div class="mpcf-editor-inner" data-settings="${settings}" data-is-registered="0"><textarea class="wp-editor-area" name="intro" id="introeditor">Text</textarea></div>`);
		ctx.assert(body.includes('introeditor'), 'editor in the fixture');

		const result = await ctx.openPage({ body });
		await result.page.click('body', { position: { x: 1, y: 1 } });
		await result.page.fill('#introeditor', 'Changed');
		ctx.assert(await asksBeforeLeaving(result), 'dialog shown');
	},

	'a changed visual editor counts as a change': async ctx => {
		const result = await open(ctx);
		await result.page.evaluate(() => {
			const inner = document.createElement('div');
			inner.className = 'mpcf-editor-inner';
			inner.dataset.isRegistered = '1';
			inner.innerHTML = '<textarea class="wp-editor-area" id="fakemce"></textarea>';
			document.querySelector('.mpcf-panels').appendChild(inner);
			window.tinymce = { get: id => id === 'fakemce' ? { isDirty: () => true, setDirty: () => {} } : null };
		});
		ctx.assert(await asksBeforeLeaving(result), 'dialog shown');
	}
};
