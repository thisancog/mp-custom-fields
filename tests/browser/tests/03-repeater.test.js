/**************************************************************
	Repeater rows: moving (buttons, drag and drop, keyboard),
	removing, nested repeaters
 **************************************************************/

const ROWS   = 'ol[data-basename="sections"] > li',
	  row    = i => `${ROWS}:nth-child(${i})`,
	  handle = i => `${row(i)} > .mpcf-repeater-row-controls .mpcf-repeater-row-move`;

//	marks the rows with their original position and returns the order
async function labelRows(page) {
	await page.evaluate(sel => document.querySelectorAll(sel).forEach((li, i) => li.querySelector('input[type=text]').value = 'row' + i), ROWS);
}

const order = page => page.evaluate(sel => [...document.querySelectorAll(sel)].map(li => li.querySelector('input[type=text]').value).join(','), ROWS);

//	drags with the mouse until the rows are in the expected order
async function dragUntil(page, handleSelector, rowsSelector, expected) {
	await page.locator(handleSelector).scrollIntoViewIfNeeded();
	const box = await page.locator(handleSelector).boundingBox(),
		  x   = box.x + box.width / 2;

	await page.mouse.move(x, box.y + box.height / 2);
	await page.mouse.down();

	for (let y = box.y; y < box.y + 2000; y += 10) {
		await page.mouse.move(x, y);
		await page.waitForTimeout(20);

		const current = await page.evaluate(sel => [...document.querySelectorAll(sel)].map(li => li.querySelector('input[type=text]').value).join(','), rowsSelector);
		if (current === expected) break;
	}

	await page.mouse.up();
	await page.waitForTimeout(100);
}


module.exports = {
	'dragging a row gives the same result as the move buttons': async ctx => {
		const body    = ctx.buildPage({ rows: 4 }).body,
			  dragged = await ctx.openPage({ body }),
			  clicked = await ctx.openPage({ body });

		await labelRows(dragged.page);
		await dragUntil(dragged.page, handle(1), ROWS, 'row1,row2,row0,row3');

		await labelRows(clicked.page);
		await clicked.page.click(`${row(1)} > .mpcf-repeater-row-controls .mpcf-repeater-row-move-down`);
		await clicked.page.click(`${row(2)} > .mpcf-repeater-row-controls .mpcf-repeater-row-move-down`);

		ctx.equal(await order(dragged.page), 'row1,row2,row0,row3', 'order after dragging');
		ctx.equal(await ctx.dumpFields(dragged.page), await ctx.dumpFields(clicked.page), 'field names after dragging and after clicking');
		ctx.noErrors(dragged);

		await dragged.page.close();
		await clicked.page.close();
	},

	'rows can be moved and removed with the keyboard': async ctx => {
		const result = await ctx.openPage({ body: ctx.buildPage({ rows: 4 }).body }),
			  page   = result.page;

		await labelRows(page);
		await page.focus(handle(2));
		await page.keyboard.press('ArrowUp');
		ctx.equal(await order(page), 'row1,row0,row2,row3', 'order after ArrowUp');
		ctx.assert(await page.evaluate(() => document.activeElement.matches('.mpcf-repeater-row-move')), 'focus stays on the move handle');

		await page.keyboard.press('ArrowDown');
		await page.keyboard.press('ArrowDown');
		ctx.equal(await order(page), 'row0,row2,row1,row3', 'order after ArrowDown twice');

		await page.focus(`${row(1)} > .mpcf-repeater-row-controls .mpcf-repeater-row-remove`);
		await page.keyboard.press('Enter');
		ctx.equal(await order(page), 'row2,row1,row3', 'order after Enter on "remove"');

		const names = await page.evaluate(sel => [...document.querySelectorAll(sel)].map(li => li.querySelector('input[type=text]').name).join(','), ROWS);
		ctx.equal(names, 'sections[0][heading],sections[1][heading],sections[2][heading]', 'names renumbered');
		ctx.noErrors(result);
		await page.close();
	},

	'nested repeaters: moving and removing inner rows leaves the outer rows alone': async ctx => {
		const result = await ctx.openPage({ body: ctx.buildPage({ rows: 3 }).body }),
			  page   = result.page,
			  inner  = `${row(1)} ol[data-basename="sub"] > li`;

		await labelRows(page);
		await page.evaluate(sel => document.querySelectorAll(sel).forEach((li, i) => li.querySelector('input[type=text]').value = 'inner' + i), inner);

		await dragUntil(page, `${inner}:nth-child(1) > .mpcf-repeater-row-controls .mpcf-repeater-row-move`, inner, 'inner1,inner0');
		const innerOrder = await page.evaluate(sel => [...document.querySelectorAll(sel)].map(li => li.querySelector('input[type=text]').value + '=' + li.querySelector('input[type=text]').name).join(' '), inner);
		ctx.equal(innerOrder, 'inner1=sections[0][sub][0][x] inner0=sections[0][sub][1][x]', 'inner rows after dragging');

		await page.$eval(`${inner}:nth-child(1) > .mpcf-repeater-row-controls .mpcf-repeater-row-remove`, e => e.click());
		ctx.equal(await page.locator(inner).count(), 1, 'inner rows after removing one');
		ctx.equal(await order(page), 'row0,row1,row2', 'outer rows unchanged');
		ctx.noErrors(result);
		await page.close();
	},

	'removing a row with a conditional panel keeps the panel menu intact': async ctx => {
		const result = await ctx.openPage({ body: ctx.buildPage({ rows: 3 }).body }),
			  page   = result.page;

		await page.$eval(`${row(2)} > .mpcf-repeater-row-controls .mpcf-repeater-row-remove`, e => e.click());
		await page.evaluate(() => window.dispatchEvent(new Event('resize')));

		const state = await page.evaluate(() => {
			const set = mpcf.panelSwitchers.sets.box;
			return set.menuItems.every(item => item.classList.contains('mpcf-panel-item')) + ' ' + set.menuItems.length + '/' + set.panels.length;
		});
		ctx.equal(state, 'true 3/3', 'menu items are menu items, 3 of 3');
		ctx.noErrors(result);
		await page.close();
	},

	'radio buttons keep their selection when rows are moved (they used to lose it)': async ctx => {
		const result = await ctx.openPage({ body: ctx.buildPage({ rows: 3 }).body }),
			  page   = result.page,
			  groups = () => page.evaluate(() => {
				  const names = new Set([...document.querySelectorAll('input[type=radio]')].map(r => r.name));
				  return [...names].filter(name => !document.querySelector(`input[type=radio][name="${name}"]:checked`)).join(', ');
			  });

		await page.click(`${ROWS}:nth-child(1) > .mpcf-repeater-row-controls .mpcf-repeater-row-move-down`);
		await page.click(`${ROWS}:nth-child(3) > .mpcf-repeater-row-controls .mpcf-repeater-row-move-up`);
		ctx.equal(await groups(), '', 'radio groups without a selection');
		ctx.noErrors(result);
		await page.close();
	}
};
