/**************************************************************
	Individual fields and panels
 **************************************************************/

const PANELS = (content, menu = '<li class="mpcf-panel-item" data-index="0"><span class="mpcf-panel-title">Main</span></li>') =>
	`<form id="post"><div class="postbox" id="box"><div class="mpcf-parent"><div class="mpcf-panels"><input type="hidden" class="activetab" value="0">
	<input type="hidden" name="mpcf-interface-ready" class="mpcf-interface-ready" value="0">
	<ul class="mpcf-panels-menu">${menu}</ul><div class="mpcf-panels-tabs"><div class="mpcf-panel" data-index="0">${content}</div></div></div></div></div></form>`;

const COLOR = name => `<div class="mpcf-color-input mpcf-field-option"><div class="mpcf-field"><input type="text" name="${name}" value="#fff"></div></div>`;


module.exports = {
	'select options with quotes or commas in their values do not stop the interface': async ctx => {
		const select = `<div class="mpcf-select-input mpcf-field-option"><div class="mpcf-field"><select name="sel" data-json-safe-order="[&quot;d&quot;e&quot;,&quot;b,c&quot;,&quot;a&quot;]">`
					 + `<option value="a">A</option><option value="b,c">B,C</option><option value='d"e'>D"E</option></select></div></div>`,
			  result = await ctx.openPage({ body: PANELS(select) });

		ctx.assert(await result.page.evaluate(() => typeof mpcf !== 'undefined'), 'interface built');
		ctx.equal(await result.page.evaluate(() => [...document.querySelectorAll('select[name=sel] option')].map(o => o.value).join(' | ')), 'b,c | d"e | a', 'option order');
		ctx.noErrors(result);
		await result.page.close();
	},

	'color pickers are created once, not on their own "Clear" buttons': async ctx => {
		const rowsHtml = `<li class="mpcf-repeater-row">${COLOR('rep[0][c]')}<div class="mpcf-repeater-row-controls"></div></li>`,
			  repeater = `<div class="mpcf-repeater-input mpcf-field-option" data-uniqid="r1"><div class="mpcf-field"><ol class="mpcf-repeater-wrapper" data-basename="rep" data-fields="[]" data-maxrows="0" data-uniqid="r1">${rowsHtml}</ol>`
					   + `<div class="mpcf-loading-container" data-uniqid="r1"></div><input type="button" class="mpcf-repeater-add-row" data-uniqid="r1" value="add"></div></div>`,
			  result   = await ctx.openPage({ body: PANELS(COLOR('col') + repeater) });

		ctx.equal(await result.page.evaluate(() => document.querySelectorAll('.wp-color-picker').length), 2, 'color pickers');
		ctx.equal(await result.page.evaluate(() => document.querySelectorAll('input[type=button].wp-color-picker').length), 0, 'color pickers on "Clear" buttons');
		ctx.noErrors(result);
		await result.page.close();
	},

	'an invalid field in a module box does not cause errors': async ctx => {
		const body = `<form id="post"><div class="postbox" id="modbox"><div class="mpcf-modules"><input type="hidden" name="mpcf-interface-ready" class="mpcf-interface-ready" value="0"><div class="mpcf-modules-inner">
			<section class="mpcf-module" data-basename="modules[0]"><header class="mpcf-module-header"><div class="mpcf-module-controls">
			<button type="button" class="mpcf-btn-remove-module"></button><button type="button" class="mpcf-btn-move-module-up"></button><button type="button" class="mpcf-btn-move-module-down"></button>
			<input type="hidden" class="mpcf-module-type" name="modules[0][module_type]" value="t"></div></header>
			<main class="mpcf-module-inner"><div class="mpcf-text-input mpcf-field-option"><div class="mpcf-field"><input type="text" name="modules[0][req]" required></div></div></main></section></div>
			<div class="mpcf-modules-controls"><div class="mpcf-modules-controls-inner"><select data-modules="{}" data-basename="modules"><option value="t">T</option></select>
			<input type="button" class="mpcf-modules-add-module"><input type="hidden" class="activetab" value=""></div></div></div></div></form>`;

		const result = await ctx.openPage({ body });
		await result.page.evaluate(() => document.querySelector('input[name="modules[0][req]"]').checkValidity());
		ctx.noErrors(result);
		await result.page.close();
	},

	'panel menu titles get the same width on wide screens, their own on small screens': async ctx => {
		const titles = ['Hero', 'Text block', 'A considerably longer panel title that exceeds the limit', 'SEO'],
			  menu   = titles.map((t, i) => `<li class="mpcf-panel-item" data-index="${i}"><span class="mpcf-panel-title">${t}</span></li>`).join('');

		for (const [width, expectSame] of [[1400, true], [900, false]]) {
			const result = await ctx.openPage({ body: PANELS('', menu), viewport: { width, height: 800 } }),
				  widths = await result.page.evaluate(() => [...document.querySelectorAll('.mpcf-panel-title')].map(t => parseInt(t.style.minWidth)));

			ctx.assert(widths.every(w => w > 0 && w <= 250), `widths between 1 and 250 px at ${width}px: ${widths}`);
			ctx.equal(new Set(widths).size === 1, expectSame, `all titles equally wide at ${width}px (${widths})`);
			ctx.noErrors(result);
			await result.page.close();
		}
	}
};
