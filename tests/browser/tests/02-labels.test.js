/**************************************************************
	Labels

	Every label with a "for" attribute must point to an element
	of its own field.
 **************************************************************/

module.exports = {
	'labels point to an element of their own field': async ctx => {
		const result = await ctx.openPage({ body: ctx.buildPage({ rows: 3 }).body });

		const broken = await result.page.evaluate(() => [...document.querySelectorAll('.mpcf-field-option label[for]')]
			.filter(label => !label.control || label.control.closest('.mpcf-field-option') !== label.closest('.mpcf-field-option'))
			.map(label => 'for=' + label.htmlFor));

		ctx.equal(broken.join(', '), '', 'labels without a target');
		ctx.noErrors(result);
		await result.page.close();
	},

	'checkbox labels in rows are connected (they used to get for="undefined")': async ctx => {
		const result = await ctx.openPage({ body: ctx.buildPage({ rows: 2 }).body });

		const states = await result.page.evaluate(() => [...document.querySelectorAll('.mpcf-truefalse-input label')]
			.map(label => label.control && label.control.type === 'checkbox'));

		ctx.assert(states.length > 0 && states.every(Boolean), 'all checkbox labels connected to their checkbox');
		await result.page.close();
	}
};
