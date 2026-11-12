/**************************************************************
	Editors: initialised when visible, re-initialised after moves

	window.wp.editor is a stand-in which records its calls in
	testLog.editor (see harness.js).
 **************************************************************/

const { escapeHtml, ROW_CONTROLS } = require('../fixtures');

const SETTINGS = escapeHtml(JSON.stringify({ wpautop: true, tinymce: { wpautop: true }, quicktags: true, mediaButtons: true }));

//	markup of the editor field as rendered by inc/modules/mpcf-editor.php
const editor = (name, id, content) =>
	`<div class="mpcf-editor-input mpcf-field-option"><div class="mpcf-title"><label for="${id}">Text</label></div><div class="mpcf-field">`
	+ `<div class="mpcf-editor-inner" data-settings="${SETTINGS}" data-is-registered="0">`
	+ `<div id="wp-${id}-wrap" class="wp-core-ui wp-editor-wrap html-active"><div id="wp-${id}-editor-container" class="wp-editor-container">`
	+ `<textarea class="wp-editor-area mpcf-input-editor" rows="5" name="${name}" id="${id}">${escapeHtml(content)}</textarea></div></div>`
	+ `<style>#${id}_ifr { min-height: 200px; }</style></div><div class="mpcf-description">Help</div></div></div>`;

const rows = [0, 1].map(i => `<li class="mpcf-repeater-row">${editor(`rep[${i}][text]`, `reptext${i}`, `Row ${i} text`)}${ROW_CONTROLS}</li>`).join('');

const BODY = `<form id="post"><div class="postbox" id="box"><div class="mpcf-parent"><div class="mpcf-panels">
	<input type="hidden" class="activetab" value="0"><input type="hidden" name="mpcf-interface-ready" class="mpcf-interface-ready" value="0">
	<ul class="mpcf-panels-menu"><li class="mpcf-panel-item" data-index="0"><span class="mpcf-panel-title">One</span></li><li class="mpcf-panel-item" data-index="1"><span class="mpcf-panel-title">Two</span></li></ul>
	<div class="mpcf-panels-tabs">
		<div class="mpcf-panel" data-index="0">${editor('intro', 'introeditor', 'Visible intro')}
			<div class="mpcf-repeater-input mpcf-field-option" data-uniqid="r1"><div class="mpcf-field"><ol class="mpcf-repeater-wrapper" data-basename="rep" data-fields="[]" data-maxrows="0" data-uniqid="r1">${rows}</ol>
			<div class="mpcf-loading-container" data-uniqid="r1"></div><input type="button" class="mpcf-repeater-add-row" data-uniqid="r1" value="add"></div></div>
		</div>
		<div class="mpcf-panel" data-index="1">${editor('hidden', 'hiddeneditor', 'Hidden text')}</div>
	</div></div></div></div></form>`;

const takeLog = page => page.evaluate(() => testLog.editor.splice(0).join('\n'));


module.exports = {
	'only visible editors are initialised, others when their panel is opened': async ctx => {
		const result = await ctx.openPage({ body: BODY }),
			  page   = result.page;

		await page.waitForTimeout(200);
		ctx.equal(await takeLog(page), 'init intro = <p>Visible intro</p>\ninit rep[0][text] = <p>Row 0 text</p>\ninit rep[1][text] = <p>Row 1 text</p>', 'initialised after loading');
		ctx.equal(await page.evaluate(() => document.querySelector('textarea[name="hidden"]').value), 'Hidden text', 'editor in the closed panel untouched');
		ctx.equal(await page.evaluate(() => document.querySelector('.mpcf-interface-ready').value), '1', 'saving allowed before all editors are initialised');

		await page.click('.mpcf-panel-item[data-index="1"]');
		await page.waitForTimeout(200);
		ctx.equal(await takeLog(page), 'init hidden = <p>Hidden text</p>', 'initialised when its panel is opened');

		ctx.noErrors(result);
		await page.close();
	},

	'moving a row removes its editor and initialises it again': async ctx => {
		const result = await ctx.openPage({ body: BODY }),
			  page   = result.page;

		await page.waitForTimeout(200);
		await takeLog(page);

		await page.focus('ol[data-basename="rep"] > li:nth-child(1) > .mpcf-repeater-row-controls .mpcf-repeater-row-move');
		await page.keyboard.press('ArrowDown');
		await page.waitForTimeout(200);

		ctx.equal(await takeLog(page), 'remove rep[0][text]\ninit rep[1][text] = <p>Row 0 text</p>', 'editor calls when moving');
		ctx.assert(await page.evaluate(() => [...document.querySelectorAll('.mpcf-editor-inner')].every(inner => inner.querySelector('style').textContent.includes('#' + inner.querySelector('textarea').id + '_ifr'))), 'height style follows the new ID');
		ctx.assert(await page.evaluate(() => [...document.querySelectorAll('.mpcf-editor-input .mpcf-field')].every(field => field.lastElementChild.classList.contains('mpcf-description'))), 'description stays below the editor');

		ctx.noErrors(result);
		await page.close();
	}
};
