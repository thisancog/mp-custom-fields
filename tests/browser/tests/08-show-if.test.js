/**************************************************************
	Fields shown depending on other fields (show_if)

	The markup below is what inc/gui.php prints for fields and
	panels with show_if (data-show-if, already normalised).
 **************************************************************/

const { escapeHtml, ROW_CONTROLS } = require('../fixtures');

const showIf = condition => ` data-show-if="${escapeHtml(JSON.stringify(condition))}"`;
const cond   = (field, operator, value) => ({ field, operator, value });

const text = (own, name, value = '', condition = null, extra = '') =>
	`<div class="mpcf-text-input mpcf-field-option" id="mpcf-field-${own}"${condition ? showIf(condition) : ''}><div class="mpcf-field">`
	+ `<input type="text" name="${name}" id="${name.replace(/\W+/g, '-')}" value="${value}"${extra}></div></div>`;

const select = (own, name, options, selected, condition = null) =>
	`<div class="mpcf-select-input mpcf-field-option" id="mpcf-field-${own}"${condition ? showIf(condition) : ''}><div class="mpcf-field"><select name="${name}">`
	+ options.map(o => `<option value="${o}"${o === selected ? ' selected' : ''}>${o}</option>`).join('') + '</select></div></div>';

const truefalse = (own, name, on) =>
	`<div class="mpcf-truefalse-input mpcf-field-option" id="mpcf-field-${own}"><div class="mpcf-field">`
	+ `<input type="radio" name="${name}" value="true"${on ? ' checked' : ''}><input type="radio" name="${name}" value="false"${on ? '' : ' checked'}></div></div>`;

const row = (i, type) => `<li class="mpcf-repeater-row">`
	+ select('type', `items[${i}][type]`, ['image', 'text'], type)
	+ text('caption', `items[${i}][caption]`, `caption ${i}`, cond('type', '=', 'image'))
	+ text('note', `items[${i}][note]`, '', cond('../mode', '=', 'advanced'))
	+ `${ROW_CONTROLS}</li>`;

const BODY = `<form id="post"><div class="postbox" id="box"><div class="mpcf-parent"><div class="mpcf-panels">
	<input type="hidden" class="activetab" value="0"><input type="hidden" name="mpcf-interface-ready" class="mpcf-interface-ready" value="0">
	<ul class="mpcf-panels-menu">
		<li class="mpcf-panel-item" data-index="0"><span class="mpcf-panel-title">Basics</span></li>
		<li class="mpcf-panel-item" data-index="1"${showIf(cond('mode', '=', 'advanced'))}><span class="mpcf-panel-title">Advanced</span></li>
	</ul>
	<div class="mpcf-panels-tabs">
		<div class="mpcf-panel" data-index="0">
			${select('mode', 'mode', ['simple', 'advanced'], 'advanced')}
			${text('url', 'url')}
			${select('target', 'target', ['_self', '_blank'], '_blank', cond('url', 'not_empty'))}
			${truefalse('gallery', 'gallery', false)}
			${text('columns', 'columns', '3', cond('gallery', 'checked'), ' required')}
			${text('wide', 'wide', '', { any: [ cond('columns', '>', 2), cond('url', 'contains', 'wide') ] })}
			${text('broken', 'broken', '', cond('nonexistent', '=', 'x'))}
			<div class="mpcf-repeater-input mpcf-field-option" id="mpcf-field-items" data-uniqid="r1"><div class="mpcf-field">
				<ol class="mpcf-repeater-wrapper" data-basename="items" data-fields="[]" data-maxrows="0" data-uniqid="r1">${row(0, 'image')}${row(1, 'text')}</ol>
				<div class="mpcf-loading-container" data-uniqid="r1"></div><input type="button" class="mpcf-repeater-add-row" data-uniqid="r1" value="add"></div></div>
		</div>
		<div class="mpcf-panel" data-index="1"${showIf(cond('mode', '=', 'advanced'))}>${text('extra', 'extra', 'kept')}</div>
	</div></div></div></div></form>`;

const visible = (page, selector) => page.$eval(selector, e => !e.closest('.mpcf-hidden-by-condition'));
const field   = own => `#mpcf-field-${own}`;
const ROW     = n => `ol[data-basename="items"] > li:nth-child(${n})`;
const settle  = page => page.waitForTimeout(100);

module.exports = {
	'conditions are evaluated when the interface is built': async ctx => {
		const result = await ctx.openPage({ body: BODY }),
			  page   = result.page;

		ctx.equal(await visible(page, field('target')),  false, 'target hidden: url is empty');
		ctx.equal(await visible(page, field('columns')), false, 'columns hidden: gallery is not checked');
		ctx.equal(await visible(page, field('wide')),    true,  'wide shown: columns (3) > 2, even though columns is hidden');
		await page.close();
	},

	'fields follow changes of other fields': async ctx => {
		const result = await ctx.openPage({ body: BODY }),
			  page   = result.page;

		await page.fill('#url', 'https://example.com');
		await settle(page);
		ctx.equal(await visible(page, field('target')), true, 'target shown after typing a URL');

		await page.fill('#url', '');
		await settle(page);
		ctx.equal(await visible(page, field('target')), false, 'target hidden again');

		await page.check('input[name="gallery"][value="true"]');
		await settle(page);
		ctx.equal(await visible(page, field('columns')), true, 'columns shown when gallery is checked');
		ctx.equal(result.errors.filter(e => !e.includes('nonexistent')).join('\n'), '', 'no other errors');
		await page.close();
	},

	'any: one condition is enough': async ctx => {
		const result = await ctx.openPage({ body: BODY }),
			  page   = result.page;

		ctx.equal(await visible(page, field('wide')), true, 'columns (3) > 2');
		await page.check('input[name="gallery"][value="true"]');
		await page.fill('#columns', '1');
		await settle(page);
		ctx.equal(await visible(page, field('wide')), false, 'columns 1, url without "wide"');
		await page.fill('#url', 'https://wide.example');
		await settle(page);
		ctx.equal(await visible(page, field('wide')), true, 'url contains "wide"');
		await page.close();
	},

	'conditions in repeater rows refer to their own row, ../ to the field next to the repeater': async ctx => {
		const result = await ctx.openPage({ body: BODY }),
			  page   = result.page;

		ctx.equal(await visible(page, `${ROW(1)} ${field('caption')}`), true,  'row 1 (image): caption shown');
		ctx.equal(await visible(page, `${ROW(2)} ${field('caption')}`), false, 'row 2 (text): caption hidden');
		ctx.equal(await visible(page, `${ROW(1)} ${field('note')}`),    true,  'note shown: mode is advanced');

		await page.click(`${ROW(1)} > .mpcf-repeater-row-controls .mpcf-repeater-row-move-down`);
		ctx.equal(await visible(page, `${ROW(1)} ${field('caption')}`), false, 'after moving: the text row is first, caption hidden');
		ctx.equal(await visible(page, `${ROW(2)} ${field('caption')}`), true,  'after moving: the image row is second, caption shown');

		await page.selectOption(`${ROW(1)} select`, 'image');
		await settle(page);
		ctx.equal(await visible(page, `${ROW(1)} ${field('caption')}`), true, 'changing the row type shows its caption');

		await page.selectOption('select[name="mode"]', 'simple');
		await settle(page);
		ctx.equal(await page.$$eval(`${field('note')}.mpcf-hidden-by-condition`, e => e.length), 2, 'notes of all rows hidden');
		await page.close();
	},

	'panels: hidden with their menu item, the first visible panel is opened instead': async ctx => {
		const result = await ctx.openPage({ body: BODY }),
			  page   = result.page,
			  active = () => page.$eval('.mpcf-panel.active-panel', e => e.dataset.index);

		await page.click('.mpcf-panel-item[data-index="1"]');
		ctx.equal(await active(), '1', 'advanced panel open');

	//	e.g. changed by a script: the dropdown is on the other panel
		await page.$eval('select[name="mode"]', s => { s.value = 'simple'; s.dispatchEvent(new Event('change', { bubbles: true })); });
		await settle(page);
		ctx.equal(await visible(page, '.mpcf-panel-item[data-index="1"]'), false, 'menu item hidden');
		ctx.equal(await active(), '0', 'first panel opened');
		await page.close();
	},

	'hidden fields are still submitted and not required': async ctx => {
		const result = await ctx.openPage({ body: BODY }),
			  page   = result.page;

		await page.selectOption('select[name="mode"]', 'simple');
		await settle(page);

		const data = await page.evaluate(() => Object.fromEntries(new FormData(document.getElementById('post'))));
		ctx.equal(data.extra, 'kept', 'field of the hidden panel submitted');
		ctx.equal(data['items[1][caption]'], 'caption 1', 'hidden caption submitted');
		ctx.equal(data.target, '_blank', 'hidden select submitted');
		ctx.equal(await page.$eval('#columns', e => e.required), false, 'hidden required field is not required');

		await page.check('input[name="gallery"][value="true"]');
		await settle(page);
		ctx.equal(await page.$eval('#columns', e => e.required), true, 'required again when shown');
		await page.close();
	},

	'a condition on a missing field is reported and hides nothing': async ctx => {
		const result = await ctx.openPage({ body: BODY });
		ctx.equal(await visible(result.page, field('broken')), true, 'field stays visible');
		ctx.assert(result.errors.some(e => e.includes('[MPCF]') && e.includes('nonexistent')), 'reported in the console');
		await result.page.close();
	},

	'pages without show_if are unchanged': async ctx => {
		const result = await ctx.openPage({ body: ctx.buildPage({ rows: 2 }).body });
		ctx.equal(await result.page.$$eval('.mpcf-hidden-by-condition', e => e.length), 0, 'nothing hidden');
		ctx.noErrors(result);
		await result.page.close();
	}
};
