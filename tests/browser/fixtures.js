/**************************************************************
	Test pages

	Builds admin pages with the markup the plugin's PHP renders:
	a repeater "sections" whose rows contain text, radio and
	checkbox fields, a conditional field (with a nested
	repeater), a conditional panels field and a nested
	repeater. Fields inside conditional fields only get their
	own name from the server, as in the real plugin.
 **************************************************************/

const crypto = require('crypto');

const escapeHtml = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#x27;');
const toID       = name => name.replace(/\]\[/g, '-').replace(/\[/g, '-').replace(/\]/g, '');

const ROW_CONTROLS = '<div class="mpcf-repeater-row-controls"><div class="mpcf-repeater-row-moveupdown">'
	+ '<div class="mpcf-repeater-row-move-up dashicons-before dashicons-arrow-up" role="button" tabindex="0" title="Move up" aria-label="Move up"></div>'
	+ '<div class="mpcf-repeater-row-move dashicons-before dashicons-move" role="button" tabindex="0" title="Move" aria-label="Move"></div>'
	+ '<div class="mpcf-repeater-row-move-down dashicons-before dashicons-arrow-down" role="button" tabindex="0" title="Move down" aria-label="Move down"></div>'
	+ '</div><div class="mpcf-repeater-row-remove dashicons-before dashicons-remove" role="button" tabindex="0" title="Remove row" aria-label="Remove row"></div></div>';


function buildPage({ rows = 4, store = false, panelsFirst = false, readyInput = true } = {}) {
	let uid       = 0,
		storeData = {},
		panels    = [];

	const optionsAttr = obj => {
		const json = JSON.stringify(obj);
		if (!store) return `data-options="${escapeHtml(json)}"`;

		const key = crypto.createHash('md5').update(json).digest('hex');
		storeData[key] = json;
		return `data-options-ref="${key}"`;
	};

	const text = (name, own, label = true) =>
		`<div class="mpcf-text-input mpcf-field-option" id="mpcf-field-${own}">`
		+ (label ? `<div class="mpcf-title"><label for="${toID(name)}">${own}</label></div>` : '')
		+ `<div class="mpcf-field"><input type="text" name="${name}" id="${toID(name)}" data-own-name="${own}" value="v"></div></div>`;

	const radio = (name, own) =>
		`<div class="mpcf-radio-input mpcf-field-option"><div class="mpcf-title"><label for="${toID(name)}">${own}</label></div><div class="mpcf-field">`
		+ ['l', 'r'].map(v => `<div class="mpcf-radio-option"><input type="radio" name="${name}" id="${toID(name)}-${v}" value="${v}"${v === 'l' ? ' checked' : ''}><label for="${toID(name)}-${v}">${v}</label></div>`).join('')
		+ '</div></div>';

	const checkbox = (name, own) =>
		`<div class="mpcf-truefalse-input mpcf-field-option"><div class="mpcf-field"><input type="checkbox" name="${name}" id="${toID(name)}" value="1"><label for="${toID(name)}">${own}</label></div></div>`;

	const repeater = (base, own, rowBuilders) => {
		const id     = 'u' + (++uid),
			  fields = escapeHtml(JSON.stringify([{ name: 'label', type: 'text' }])),
			  rowsHtml = rowBuilders.map((build, i) => `<li class="mpcf-repeater-row">${build(`${base}[${i}]`, i)}${ROW_CONTROLS}</li>`).join('');

		return `<div class="mpcf-repeater-input mpcf-field-option" id="mpcf-field-${own}" data-uniqid="${id}"><div class="mpcf-title"><label for="${toID(base)}">${own}</label></div><div class="mpcf-field">`
			+ `<ol class="mpcf-repeater-wrapper" data-basename="${own}" data-fields="${fields}" data-maxrows="0" data-uniqid="${id}">${rowsHtml}</ol>`
			+ `<div class="mpcf-loading-container" data-uniqid="${id}"></div><div class="mpcf-repeater-controls" data-uniqid="${id}">`
			+ `<input type="button" class="mpcf-repeater-add-row mpcf-button" value="Add" data-uniqid="${id}">`
			+ `<input type="hidden" class="mpcf-repeater-empty" data-name="${base}[]" data-own-name="${own}" data-uniqid="${id}" value=""></div></div></div>`;
	};

	const conditional = (base, own) => {
		const options = { a: { fields: [{ name: 'caption', type: 'text' }] }, b: { fields: [] } };
	//	the server gives fields inside conditional fields only their own name
		const inner   = text('caption', 'caption') + repeater('items', 'items', [0, 1].map(() => b => text(`${b}[label]`, 'label')));

		return `<div class="mpcf-conditional-input mpcf-field-option" id="mpcf-field-${own}"><div class="mpcf-field"><div class="mpcf-conditional-container" data-basename="${own}"><div class="mpcf-conditional-choice">`
			+ `<select name="${base}[type]" id="${toID(base)}-type" data-own-name="${own}" data-basename="${own}" ${optionsAttr(options)}><option value="-1">--</option><option value="a" selected>a</option><option value="b">b</option></select></div>`
			+ `<div class="mpcf-conditional-wrapper">${inner}</div><div class="mpcf-loading-container"></div></div></div></div>`;
	};

	const conditionalPanel = (base, own, i) => {
		const panelID = 'P' + i,
			  options = { x: { panel: { name: 'X', fields: [{ name: 'note', type: 'text' }] } } };

		panels.push([panelID, text(`${base}[note]`, 'note') + radio(`${base}[side]`, 'side')]);

		return `<div class="mpcf-conditionalpanels-input mpcf-field-option" data-panel-id="${panelID}"><div class="mpcf-field"><div class="mpcf-conditionalpanels-container" data-basename="${own}"><div class="mpcf-conditional-choice">`
			+ `<select name="${base}[type]" id="${toID(base)}-type" data-own-name="type" data-basename="${own}" ${optionsAttr(options)} data-values="${escapeHtml(JSON.stringify({ type: 'x' }))}"><option value="-1">--</option><option value="x" selected>x</option></select></div></div></div></div>`;
	};

	const row = (base, i) => text(`${base}[heading]`, 'heading') + radio(`${base}[align]`, 'align') + checkbox(`${base}[flag]`, 'flag')
		+ conditional(`${base}[layout]`, 'layout') + conditionalPanel(`${base}[extra]`, 'extra', i)
		+ repeater(`${base}[sub]`, 'sub', [0, 1].map(() => b => text(`${b}[x]`, 'x') + checkbox(`${b}[y]`, 'y')));

	let main = text('title', 'title') + repeater('sections', 'sections', Array.from({ length: rows }, () => row));
	main += Object.entries(storeData).map(([key, json]) => `<script type="application/json" class="mpcf-options-store" id="mpcf-options-${key}">${json}</script>`).join('');

	let mainTab   = `<div class="mpcf-panel" data-index="0" data-panel-id="main" data-basename="">${main}</div>`,
		panelTabs = panels.map(([id, content], k) => `<div class="mpcf-panel mpcf-conditionalpanel" data-index="${k + 1}" data-panel-id="${id}" data-basename="sections">${content}</div>`).join(''),
		menu      = '<li class="mpcf-panel-item" data-index="0" data-panel-id="main"><span class="mpcf-panel-title">Main</span></li>'
				  + panels.map(([id], k) => `<li class="mpcf-panel-item" data-index="${k + 1}" data-panel-id="${id}"><span class="mpcf-panel-title">X${k + 1}</span></li>`).join('');

	const tabs = panelsFirst ? panelTabs + mainTab : mainTab + panelTabs;

	const body = `<form id="post"><div class="postbox" id="box"><div class="mpcf-panels">`
		+ `<input type="hidden" name="mpcf-activetab-box" class="activetab" value="0">`
		+ (readyInput ? `<input type="hidden" name="mpcf-interface-ready" class="mpcf-interface-ready" value="0">` : '')
		+ `<ul class="mpcf-panels-menu">${menu}</ul><div class="mpcf-panels-tabs">${tabs}</div></div></div></form>`;

	return {
		body,
	//	what the server returns for a new row of "sections" and for option "a"/"b" of a conditional field
		rowHTML:  text('heading', 'heading') + radio('align', 'align') + conditional('layout', 'layout') + ROW_CONTROLS,
		condHTML: text('caption', 'caption') + radio('pos', 'pos')
	};
}


module.exports = { buildPage, escapeHtml, ROW_CONTROLS };
