/**************************************************************
	Field names

	The names decide where values are stored - they must never
	change. The names after loading, adding a row, switching a
	conditional field, moving and removing a row are compared
	with stored snapshots (created from the original renaming
	implementation's results).
 **************************************************************/

const answer = fixture => request => ({
	body: request.get('action') === 'mpcf_get_repeater_row' ? fixture.rowHTML : fixture.condHTML
});

async function runScenario(ctx, options) {
	const fixture = ctx.buildPage(options),
		  result  = await ctx.openPage({ body: fixture.body, ajax: answer(fixture) }),
		  page    = result.page,
		  states  = {};

	states.load = await ctx.dumpFields(page);

	await page.click('.mpcf-repeater-add-row >> nth=0');
	await page.waitForTimeout(300);
	states.addRow = await ctx.dumpFields(page);

	const select = '.mpcf-repeater-row:nth-child(2) .mpcf-conditional-choice select >> nth=0';
	await page.selectOption(select, 'b');
	await page.selectOption(select, 'a');
	await page.waitForTimeout(300);
	states.conditional = await ctx.dumpFields(page);

	await page.click('ol[data-basename="sections"] > li:nth-child(3) > .mpcf-repeater-row-controls .mpcf-repeater-row-move-up');
	states.move = await ctx.dumpFields(page);

	await page.click('ol[data-basename="sections"] > li:nth-child(1) > .mpcf-repeater-row-controls .mpcf-repeater-row-remove');
	states.remove = await ctx.dumpFields(page);

	ctx.noErrors(result);
	await page.close();
	return states;
}


module.exports = {
	'names after loading, adding, switching, moving and removing match the snapshots': async ctx => {
		const states = await runScenario(ctx, { rows: 4 });
		for (const [name, value] of Object.entries(states))
			ctx.snapshot('names-' + name, value);
	},

	'options printed once per page (option store) give the same names': async ctx => {
		const inline = await runScenario(ctx, { rows: 4 }),
			  stored = await runScenario(ctx, { rows: 4, store: true });

		for (const state of Object.keys(inline))
			ctx.equal(stored[state], inline[state], `names with option store, state "${state}"`);
	},

	'conditional panels placed before their dropdowns give the same names': async ctx => {
		const fixture = ctx.buildPage({ rows: 3, panelsFirst: true }),
			  result  = await ctx.openPage({ body: fixture.body }),
			  normal  = await ctx.openPage({ body: ctx.buildPage({ rows: 3 }).body });

		const sorted = async page => (await ctx.dumpFields(page)).split('\n').sort().join('\n');
		ctx.equal(await sorted(result.page), await sorted(normal.page), 'names (in any order)');
		ctx.noErrors(result);

		await result.page.close();
		await normal.page.close();
	}
};
