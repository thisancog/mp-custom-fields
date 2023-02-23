(function() {
	$ = jQuery;

	var loadingElements   = [],
		panelSwitchers    = null,
		conditionalFields = null,
		conditionalPanels = null,
		mediaPickers      = null,
		colorSelects      = null,
		painterColors = {
			icons: {
				base:    '#666666',
				current: '#FFFFFF',
				focus:   '#007CBA'
			}
		};

	var vpWidth = function() { return Math.max(document.documentElement.clientWidth, window.innerWidth || 0); }

	var init = function() {
		if (!wp || !('svgPainter' in wp))
			return setTimeout(init, 30);	

		panelSwitchers    = new PanelSwitchers();
		conditionalFields = new ConditionalFields();
		conditionalPanels = new ConditionalPanelsFields();
		mediaPickers      = new MediaPickers();
		colorSelects      = new ColorSelects();

		goToInvalids();
		checkHTML5Support();
		registerColorPicker();
		repeaterField();

		gridField();
		addQTranslateX();
		dragDropLists();
		paintImageButtonGroup();

		conditionalFields.registerNew();
		conditionalPanels.registerNew();
	};



	/**************************************************************
		Register asynchronously loaded elements
	 **************************************************************/

	var registerAsyncElements = function(parent) {
		mediaPickers.registerNew(parent);
		addQTranslateX(parent);
		registerEditors(parent);
		registerColorPicker(parent);
		conditionalFields.registerNew(parent);
		conditionalPanels.registerNew(parent);
		colorSelects.registerNew(parent);
		gridField(parent);
		repeaterField(parent);

		checkHTML5Support(parent);
		focusInvalids(parent);
		paintImageButtonGroup(parent);
	}

	


	/**************************************************************
		Panel switch
	**************************************************************/

	class PanelSwitchers {
		constructor() {
			this.sets = {};
			this.maxWidth  = 250;
			this.threshold = 1130;

			this.registerSets();
		}

		registerSets(parent) {
			parent = parent || document;

			let newSets = [].slice.call(parent.querySelectorAll('.mpcf-panels'));
			newSets = newSets.forEach((newSet => {
				let id        = newSet.closest('.postbox, form').id,
					menuItems = [].slice.call(newSet.querySelectorAll('.mpcf-panels-menu .mpcf-panel-item')),
					panels    = [].slice.call(newSet.querySelectorAll('.mpcf-panels-tabs .mpcf-panel'));

				this.sets[id] = {
					element: newSet,
					activeTab: newSet.querySelector('.activetab'),
					menuItems: [],
					panels:    []
				}

				panels.forEach((panel => this.registerPanel(id, panel)).bind(this));
				menuItems.forEach((item => this.registerMenuItem(id, item)).bind(this));

				this.registerEvents(id);
				this.resizePanelMenus(id);
				this.activatePreactivatedPanel(id);
			}).bind(this));
		}

		registerEvents(id) {
			window.addEventListener('resize', this.resizePanelMenus.bind(this));
		}

		activatePreactivatedPanel(id) {
			this.activatePanel(id, this.sets[id].activeTab.value || 0);
		}

		navigateToPanel(id, e) {
			var target = e.target.classList.contains('mpcf-panel-item')
					   ? e.target
					   : e.target.closest('.mpcf-panel-item');

			this.activatePanel(id, target.dataset.index);
		}

		activatePanel(id, panel) {
			if (this.sets[id].panels.filter(item => item.dataset.index == panel).length == 0)
				panel = 0;

			this.sets[id].panels.forEach(item => item.classList.toggle('active-panel', item.dataset.index == panel));

			wp.svgPainter.setColors(painterColors);
			this.sets[id].menuItems.forEach(function(item) {
				var icon      = item.querySelector('.mpcf-panel-icon'),
					paintMode = item.dataset.index == panel ? 'focus' : 'base';

				item.classList.toggle('active', item.dataset.index == panel);
				if (icon) wp.svgPainter.paintElement($(icon), paintMode);
			});

			this.sets[id].element.classList.toggle('last-item-selected', panel == this.sets[id].menuItems.length - 1);
			this.sets[id].activeTab.setAttribute('value', panel);
		}

		registerMenuItem(id, item) {
			this.sets[id].menuItems.push(item);
			this.sets[id].menuItems = Array.from(new Set(this.sets[id].menuItems));
			item.addEventListener('click', (e => this.navigateToPanel(id, e)).bind(this));
		}

		registerPanel(id, panel) {
			this.sets[id].panels.push(panel);
			this.sets[id].panels = Array.from(new Set(this.sets[id].panels));
		}

		removePanel(id, panel, menuItem) {
			this.sets[id].panels    = this.sets[id].panels.filter(otherPanel => otherPanel !== panel);
			this.sets[id].menuItems = this.sets[id].panels.filter(otherItem  => otherItem  !== menuItem);

			if (panel.classList.contains('active-panel'))
				this.activatePanel(id, this.sets[id].panels[0].dataset.index);
			
			panel.parentElement.removeChild(panel);
			menuItem.parentElement.removeChild(menuItem);
		}

		resizePanelMenus() {
			let isSmallScreen = vpWidth() < this.threshold;

			for (let id in this.sets) {
				let set      = this.sets[id],
					minWidth = 0;

				set.menuItems.forEach((item => {
					let title = item.querySelector('.mpcf-panel-title');

					title.style.minWidth   = '';
					title.style.position   = 'fixed';
					title.style.fontWeight = 'bold';

					minWidth = isSmallScreen ? Math.ceil(title.clientWidth) : Math.max(minWidth, Math.ceil(title.clientWidth));
					title.style.position   = '';
					title.style.fontWeight = '';

					if (isSmallScreen) {
						title.style.minWidth = Math.min(minWidth, this.maxWidth) + 'px';
					}
				}).bind(this));

				if (isSmallScreen)
					continue;

				minWidth = Math.min(minWidth, this.maxWidth);
				set.menuItems.forEach(item => item.querySelector('.mpcf-panel-title').style.minWidth = minWidth + 'px');
			}
		}
	}


	/**************************************************************
		Show invalid form element
	 **************************************************************/

	var goToInvalids = function() {
		[].forEach.call(document.querySelectorAll('.mpcf-parent'), function(parent) {
			focusInvalids(parent);
		});
	}

	var focusInvalids = function(elem) {
		[].forEach.call(elem.querySelectorAll('input, select, textarea'), function(input) {
			input.addEventListener('invalid', function(e) {
				var menu = input.closest('.mpcf-panels').querySelector('.mpcf-panels-menu'),
					active = input.closest('.mpcf-panels').querySelector('.mpcf-panels-tabs .active-panel'),
					parent = e.target;

				active.classList.remove('active-panel');
				menu.querySelector('li[data-index="' + active.dataset.index + '"]').classList.remove('active');

				while ((parent = parent.parentElement) && !parent.classList.contains('mpcf-panel'));
				parent.classList.add('active-panel');

				menu.querySelector('li[data-index="' + parent.dataset.index + '"]').classList.add('active');
			});
		});
	}



	/**************************************************************
		Regiser adynamically loaded TinyMCE editors
	 **************************************************************/

	var registerEditors = function(parent) {
		parent = parent || document;

		var fields = [].slice.call(parent.querySelectorAll('.mpcf-editor-input .mpcf-field'));		

		fields.forEach(function(field) {
			var inner           = field.querySelector('.mpcf-editor-inner'),
				editor          = field.querySelector('.mpcf-input-editor'),
				description     = field.querySelector('.mpcf-description'),
				textarea        = editor.cloneNode(),
				id              = editor.id,
				idShort         = id.split('-').pop(),
				oldContent      = wp.editor.getContent(id),
				oldContentAutop = wp.editor.autop(oldContent),
				settings        = JSON.parse(inner.dataset.settings);

			textarea.innerText = oldContentAutop || '';
			wp.editor.remove(idShort);
			editor.parentElement.removeChild(editor);
			field.appendChild(textarea);
			wp.editor.initialize(id, settings);

			if (description)
				field.appendChild(description);
		});
	}


	/**************************************************************
		Check if browser supports HTML5 input types
	 **************************************************************/

	var checkHTML5Support = function(parent = false) {
		parent = parent || document;
		var elems = parent.querySelectorAll('.mpcf-nohtml5');

		[].forEach.call(elems, function(elem) {
			var input = document.createElement('input'),
				type = elem.querySelector('input').getAttribute('type'),
				invalid = elem.dataset.invalidTest;

			input.setAttribute('type', type);
			input.setAttribute('value', invalid);

			if (input.value !== invalid)
				elem.classList.remove('mpcf-nohtml5');
		});
	}



	/**************************************************************
		Repeater field
	 **************************************************************/

	var repeaterField = function(parent = null) {
		parent = parent || document;

		var repeaters = parent.querySelectorAll('.mpcf-repeater-input');
		if (!repeaters.length) return;
				
		[].forEach.call(repeaters, function(repeater) {
			if (repeater.dataset.registered && repeater.dataset.registered == 1) return;

			var set             = repeater.closest('.mpcf-panels'),
				rowsWrapper     = repeater.querySelector(':scope > .mpcf-field > .mpcf-repeater-wrapper'),
				loader          = repeater.querySelector(':scope > .mpcf-field > .mpcf-loading-container'),
				addBtn          = repeater.querySelector(':scope > .mpcf-field > .mpcf-repeater-controls > .mpcf-repeater-add-row'),
				emtpyField      = repeater.querySelector(':scope > .mpcf-field > .mpcf-repeater-controls >.mpcf-repeater-empty'),
				fields          = rowsWrapper.dataset.fields,
				fieldsObj       = JSON.parse(fields),
				maxRows         = parseInt(rowsWrapper.dataset.maxrows),
				rowHTML         = null,
				dragDropHandler = null;

			repeater.dataset.registered = 1;

		//	remove Row
			var removeRow = function(e) {
				var el = e.target;
				el.removeEventListener('click', removeRow);

				removeQTranslateX(el);

				while ((el = el.parentElement) && !el.classList.contains('mpcf-repeater-row'));

				// find and also remove attached panels, if there are conditional panels
				var panelSelects = [].slice.call(el.querySelectorAll('.mpcf-conditionalpanels-input'));
				panelSelects.forEach(function(select) {
					var panelId       = select.dataset.panelId,
						set      = select.closest('.mpcf-panels'),
						panel    = set.querySelector('.mpcf-panel[data-panel-id="' + panelId + '"]'),
						menuItem = set.querySelector('li.mpcf-panel-item[data-panel-id="' + panelId + '"]');

					panelSwitchers.removePanel(select.closest('.postbox').id, panel, menuItem);
				});

				el.parentElement.removeChild(el);

				addBtn.classList.toggle('hide', maxRows !== 0 && maxRows <= rowsWrapper.children.length);
				checkIfEmpty();
				renameDynamicFields(set);
			};


		//	if there are no rows, "activate" the empty field so there is an empty value posted on submit
			var checkIfEmpty = function() {
				rowsWrapper.classList.toggle('empty', rowsWrapper.children.length == 0);

				if (rowsWrapper.children.length == 0) {
					emtpyField.setAttribute('name', emtpyField.dataset.name);
				} else {
					emtpyField.removeAttribute('name');
				}
			};


			// populate repeater
			[].forEach.call(rowsWrapper.querySelectorAll('.mpcf-repeater-row-remove'), function(btn) {
				btn.addEventListener('click', removeRow);
			});

			dragDropHandler = new addDragDrop(rowsWrapper.querySelectorAll('.mpcf-repeater-row'), {
				cbEnd: function() { renameDynamicFields(set); },
				clickElem: '.mpcf-repeater-row-move'
			});

			renameDynamicFields(set);
			checkCheckableElements(rowsWrapper);
			registerAsyncElements(rowsWrapper);
			updateLoadingElements(repeater, true);


			// prefetch blank row

			$.post(ajaxurl, { 'action': 'mpcf_get_repeater_row', 'fields': fields }, function(response) {
				rowHTML = response;
			});


			// add row
			addBtn.addEventListener('click', function() {
				var newRow = document.createElement('li');
				newRow.classList.add('mpcf-repeater-row');
				newRow.innerHTML = rowHTML;
				rowsWrapper.appendChild(newRow);

				let conditionalItems = [].slice.call(newRow.querySelectorAll('.mpcf-conditionalpanels-input'));
				conditionalItems.forEach(item => item.setAttribute('data-panel-id', Math.floor(Math.random() * Math.pow(10,10))));
				newRow.querySelector('.mpcf-repeater-row-remove').addEventListener('click', removeRow);

				addBtn.classList.toggle('hide', maxRows !== 0 && maxRows <= rowsWrapper.children.length);

				dragDropHandler.addElements(newRow);
				checkIfEmpty();
				renameDynamicFields(set);
				registerAsyncElements(newRow);
			});

			checkIfEmpty();
		});
	}



	var generateName = function(elem) {
		var name                 = [elem.dataset.name || elem.name],
			parent               = elem,
			canContainDeepFields = false;

		// traverse parent nodes to find all name attributes to be applied to this input
		while (parent.parentNode) {
			parent = parent.parentNode;
			canContainDeepFields = canContainDeepFields || (parent.classList && parent.classList.contains('mpcf-table-input'));

			if (parent.dataset && parent.dataset.basename)
				name.unshift(parent.dataset.basename);

			if (parent.classList && parent.classList.contains('mpcf-repeater-row')) {
				var i = 0,
					child = parent;

				while ((child = child.previousSibling) != null) {
					if (child.classList && child.classList.contains('mpcf-repeater-row'))
						i++;
				}

				name.unshift(i);
			}
		}


		// generate nested Arrays
		name = name.filter(item => typeof item !== 'undefined' && item !== null);
		name = name.map(function(item, i) {
			var regex     = /(\S+?)\[(\S+?)\](\[(\S+?)\])?/g,
				subfields = [...item.toString().matchAll(regex)];

			if (subfields.length == 0) return item;

			subfields = subfields[0].filter(subfield => subfield && subfield.indexOf('[') == -1);
			return subfields;
		});



	//  flatten Array
		name = name.flat(9999);

	//  remove brackets from strings
		name = name.map(item => item.toString().replace(/[\[\]]/g, ''));

		name = name.filter((item, index, arr) => index === 0 || item !== arr[index - 1] || canContainDeepFields);

	//	join parts and apply format for nested HTML inputs	
		name = name.length == 1 ? name[0] : name[0] + '[' + name.slice(1).join('][') + ']';

		return name;
	}


	var generateID = function(elem) {
		if (elem.tagName && elem.tagName.toLowerCase() == 'label' && 
			elem.previousElementSibling && elem.previousElementSibling.tagName.toLowerCase() == 'input') {
			return generateID(elem.previousElementSibling);
		}

		var id = generateName(elem).replace(/\]\[/g, '-').replace('[', '-').replace(']', '');

		if (elem.tagName.toLowerCase() === 'input' && elem.type === 'radio')
			id += '-' + elem.value;
		return id;
	}

	var renameDynamicFields = function(parent) {
		var rows        = [].slice.call(parent.querySelectorAll('.mpcf-repeater-row, .mpcf-conditional-container, .mpcf-conditionalpanel')),
			validInputs = ['input', 'textarea', 'select', 'fieldset'],
			valids      = validInputs.concat(['button', 'label', 'datalist', 'keygen', 'option']);

		// each row
		rows.forEach(function(row, rowIndex) {
			var fields = [].slice.call(row.querySelectorAll('.mpcf-field-option'));

			// each field
			fields.forEach(function(field, fieldIndex) {
				var inputs = field.querySelectorAll('[name], [id], [for]');

				inputs = [].filter.call(inputs, function(input) {
					return valids.indexOf(input.tagName.toLowerCase()) > -1;
				});

				// each input
				inputs.forEach(function(input, inputIndex) {
					let type   = input.getAttribute('type'),
						parent = input.parentElement,
						newID, newName;

					if (!input.dataset.name) {
						var isLabel = input.getAttribute('for') && input.tagName.toLowerCase() == 'label';
						isLabel = isLabel && (parent.classList.contains('mpcf-title') || parent.classList.contains('mpcf-buttongroup-option') || parent.classList.contains('mpcf-radio-option'));

						if (isLabel) {
							var labelFor = parent.closest('.mpcf-field-option, .mpcf-buttongroup-option, .mpcf-radio-option').querySelector(validInputs.join(','));
							newID = labelFor ? generateID(labelFor) : '';
						} else {
							let name  = input.name || '',
								split = name.split('][').slice(-1)[0];							

							if ((split.match(/\]/g) || []).length > (split.match(/\[/g) || []).length)
								split = split.replace(/\]$/, '');
							input.dataset.name = split; 
						}
					}

					if (!input.getAttribute('for')) {
						newID = generateID(input);
					}

					if (type === 'button' || type === 'submit') return;

					if (input.hasAttribute('id'))	input.setAttribute('id', newID);
					if (input.hasAttribute('for'))	input.setAttribute('for', newID);

					if (input.hasAttribute('name')) {
						newName = generateName(input);
						input.setAttribute('name', newName);
					}

				//	propagate conditional panel name and update panel base name
					if (field.classList.contains('mpcf-conditionalpanels-input')) {
						var panelID = field.dataset.panelId,
							set     = field.closest('.mpcf-panels'),
							panel   = set.querySelector('.mpcf-panel[data-panel-id="' + panelID + '"]');
						
						if (panel) {
							panel.dataset.basename = newName.replace(/(\[type\]$)/g, '');
						}
					}

				});
			});
		});

		parent.classList.toggle('empty', parent.childElementCount === 0);
	}


	// bugfix for Chrome
	var checkCheckableElements = function(parent) {
		var fields = [].slice.call(parent.querySelectorAll('input[type="checkbox"], input[type="radio"]'));
		fields.forEach(field => field.checked = field.getAttribute('checked') !== null);
	}



	/**************************************************************
		Conditional fields
	 **************************************************************/

	class ConditionalFields {
		constructor() {
			this.fields = {};
		}

		registerNew(parent = null) {
			parent = parent || document;

			let newFields = [].slice.call(parent.querySelectorAll('.mpcf-conditional-input:not([data-registered]'));
			if (newFields.length == 0) return;

			newFields.forEach((element => {
				let id       = this.generateID(),
					select   = element.querySelector('.mpcf-conditional-choice select, .mpcf-conditional-choice input[type="checkbox"]'),
					loader   = element.querySelector('.mpcf-loading-container'),
					wrapper  = element.querySelector('.mpcf-conditional-wrapper'),
					parent   = furthestAncestor(wrapper, '.mpcf-conditional-input'),
					baseName = select.dataset.basename,
					options  = JSON.parse(select.dataset.options || '[]'),
					isSingle = select.tagName.toLowerCase() === 'input';

				element.setAttribute('data-registered', 1);
				element.setAttribute('data-id', id);

				select.removeAttribute('data-options');
				select.removeAttribute('data-values');

				renameDynamicFields(parent);
				checkCheckableElements(wrapper);
				registerAsyncElements(wrapper);

				select.addEventListener('change', (() => this.switchContent(id)).bind(this));

				this.fields[id] = {
					element, select, loader, wrapper, parent, baseName, options, isSingle
				};
			}).bind(this));
		}

		switchContent(id) {
			if (!this.fields[id]) return;

			let field = this.fields[id];
			field.wrapper.innerHTML = '';

		//	no option with this value available, i.e. no option selected
			if ((field.isSingle && field.select.checked === false) || typeof field.options[field.select.value] === 'undefined')
				return;

			let request = {
				'action': 'mpcf_get_conditional_fields',
				'fields': JSON.stringify(field.options[field.select.value].fields)
			};

			updateLoadingElements(field.element);
			field.loader.classList.add('mpcf-loading-active');

			$.post(ajaxurl, request, response => {
				removeQTranslateX(field.wrapper);
				field.wrapper.innerHTML = response;
				renameDynamicFields(field.parent);

				field.loader.classList.remove('mpcf-loading-active');
				updateLoadingElements(field.element, true);

				checkCheckableElements(field.wrapper);
				registerAsyncElements(field.wrapper);
			});

		}

		generateID() {
			return Math.floor(Math.random() * Math.pow(10,10));
		}
	}



	/**************************************************************
		Conditional panel fields
	 **************************************************************/

	class ConditionalPanelsFields {
		constructor() {
			this.fields     = {};
			this.fieldClass = 'mpcf-conditionalpanels-input';
		}

		registerNew(parent = null) {
			parent = parent || document;

			let newFields = [].slice.call(parent.querySelectorAll('.' + this.fieldClass + ':not([data-registered])'));
			if (newFields.length == 0) return;

			newFields = newFields.map((field => {
				let id       = field.dataset.panelId || this.generateID(),
					set      = field.closest('.mpcf-panels'),
					menu     = set.querySelector('.mpcf-panels-menu'),
					menuItem = menu.querySelector('.mpcf-panel-item[data-panel-id="' + id + '"]'),
					tabs     = set.querySelector('.mpcf-panels-tabs'),
					tab      = tabs.querySelector('.mpcf-panel[data-panel-id="' + id + '"]'),
					select   = field.querySelector('.mpcf-conditional-choice select, .mpcf-conditional-choice input[type="checkbox"]'),
					options  = JSON.parse(select.dataset.options),
					values   = JSON.parse(select.dataset.values);				

				if (!menuItem) {
					menuItem = document.createElement('li');
					menuItem.classList.add('mpcf-panel-item');
					menuItem.setAttribute('data-panel-id', id);
					menu.appendChild(menuItem);
				}

				if (!tab) {
					tab = document.createElement('div');
					tab.classList.add('mpcf-panel', 'mpcf-conditionalpanel');
					tab.setAttribute('data-panel-id', id);
					tabs.appendChild(tab);
				}

				field.dataset.registered = 1;
				field.setAttribute('data-panel-id', id);
				select.removeAttribute('data-options');
				select.removeAttribute('data-values');

				select.addEventListener('change', (() => { 
					this.switchContent(id);
				}).bind(this));

				renameDynamicFields(set);
				checkCheckableElements(tab);
				registerAsyncElements(tab);

				return {
					id:       id,
					element:  field,
					set:      set,
					menu:     menu,
					menuItem: menuItem,
					tabs:     tabs,
					tab:      tab,
					wrapper:  field.querySelector('.mpcf-conditional-wrapper'),
					select:   select,
					baseName: select.dataset.basename,
					options:  options,
					values:   values,
					isSingle: select.tagName.toLowerCase() === 'input',
				};
			}).bind(this));

			newFields.forEach((newField) => (() => { this.switchContent(id); }).bind(this));
			newFields.forEach((newField => this.fields[newField.id] = newField).bind(this));
		}

		switchContent(id) {
			let field = this.fields[id];

		//	no option with this value available, i.e. no option selected
			if ((field.isSingle && field.select.checked === false) || typeof field.options[field.select.value] === 'undefined') {
				if (field.menuItem) field.menuItem.innerHTML = '';
				if (field.tab)      field.tab.innerHTML      = '';
				return;
			}

			updateLoadingElements(field.element);

			let request = {
				'action': 'mpcf_get_conditional_panels_fields',
				'panel':  JSON.stringify(field.options[field.select.value].panel),
				'values': field.values
			};

			$.post(ajaxurl, request, (response => {
				response = JSON.parse(response);
				if (field.tab) removeQTranslateX(field.tab);

				field.menuItem.outerHTML = response.menu;
				field.tab.outerHTML      = response.tab;

				field.menuItem = field.menu.querySelector('[data-index="-1"]');
				field.tab      = field.tabs.querySelector('[data-index="-1"]');

				field.menuItem.setAttribute('data-panel-id', id);
				field.tab.setAttribute('data-panel-id', id);

				field.menuItem.setAttribute('data-index', this.getElemOrder(field.menuItem));
				field.tab.setAttribute('data-index', this.getElemOrder(field.tab));
				field.tab.setAttribute('data-basename', field.baseName);
				field.tab.classList.add('mpcf-conditionalpanel');

				let boxID = field.element.closest('.postbox').id;

				panelSwitchers.registerMenuItem(boxID, field.menuItem);
				panelSwitchers.registerPanel(boxID, field.tab);
				panelSwitchers.activatePreactivatedPanel(boxID);

				renameDynamicFields(field.set);
				updateLoadingElements(field.element, true);
				checkCheckableElements(field.tab);
				registerAsyncElements(field.tab);
			}).bind(this));
		}

		getElemOrder(elem) {
			let order = 0,
				node  = elem.parentNode.firstChild;

			while (node && node !== elem) {
				if (node !== elem && node.nodeType == Node.ELEMENT_NODE)
					order++;
				node = node.nextElementSibling || node.nextSibling;
			}

			return order;
		}

		generateID() {
			return Math.floor(Math.random() * Math.pow(10,10));
		}
	}






	/**************************************************************
		Grid fields
	 **************************************************************/

	var gridField = function(parent) {
		parent = parent || document;
		var fields = [].slice.call(parent.querySelectorAll('.mpcf-grid-input'));
		if (!fields) return;

		fields.forEach(function(field) {
			var grid      = field.querySelector('.grid-field-grid'),
				cells     = [].slice.call(grid.querySelectorAll('.grid-cell')),
				input     = field.querySelector('.grid-field-input'),
				value     = JSON.parse(input.value),
				isClicked = false;

			var onMouseDown = function(e) {
				if (!e.target.classList.contains('grid-cell')) return;

				isClicked = true;
				value.startrow = value.endrow = e.target.dataset.row;
				value.startcol = value.endcol = e.target.dataset.col;
				paintCells();
			};

			var onMouseMove = function(e) {
				if (!isClicked || !e.target.classList.contains('grid-cell')) return;
				var rows = [value.startrow, value.endrow, e.target.dataset.row],
					cols = [value.startcol, value.endcol, e.target.dataset.col],
					unique = function(value, index, self) {
						return self.indexOf(value) === index;
					};

				rows = rows.map(val => parseInt(val)).filter(unique).sort((a,b) => a-b);
				cols = cols.map(val => parseInt(val)).filter(unique).sort((a,b) => a-b);

				value = {
					startrow: rows[0],
					endrow:   rows[rows.length - 1],
					startcol: cols[0],
					endcol:   cols[cols.length - 1],
				};

				paintCells();
			};

			var onMouseUp = function(e) {
				if (!isClicked) return;
				isClicked = false;
				input.value = JSON.stringify(value);
			};

			var paintCells = function() {
				cells.forEach(function(cell) {
					var toPaint = cell.dataset.row >= value.startrow && cell.dataset.row <= value.endrow &&
								  cell.dataset.col >= value.startcol && cell.dataset.col <= value.endcol;

					cell.classList.toggle('selected', toPaint);
				})
			};

			grid.addEventListener('mousedown', onMouseDown);
			grid.addEventListener('mousemove', onMouseMove);
			grid.addEventListener('mouseup',   onMouseUp);
		});
	}








	/**************************************************************
		Initiate Google Map
	 **************************************************************/

	var initGoogleMap = function() {
		var maps = document.querySelectorAll('.mpcf-map-input');
		if (typeof google === 'undefined') return;

		[].forEach.call(maps, function(row) {
			var container = row.querySelector('.mpcf-map'),
				search = row.querySelector('.mpcf-mapsearch'),
				coordsInput = row.querySelector('.mpcf-mapcoords'),
				markers = [];

			var	oldMarkers = JSON.parse(coordsInput.getAttribute('value') || '[]'),
				center = container.getAttribute('center') ? JSON.parse(container.getAttribute('center')) : [0, 0],
				zoom = container.getAttribute('zoom') ? parseInt(container.getAttribute('zoom')) : 10,
				options = {
					center: { lat: parseInt(center[0]), lng: parseInt(center[1]) },
					zoom: zoom,
					streetViewControl: false
				};

			var	map = new google.maps.Map(container, options),
				searchBox = new google.maps.places.SearchBox(search);

			map.addListener('bounds_changed', function() {
				searchBox.setBounds(map.getBounds());
			});

			
			var updateInput = function() {
				var locations = markers.map(function(marker) {
					return { lat: marker.getPosition().lat(), lng: marker.getPosition().lng() };
				});

				coordsInput.setAttribute('value', JSON.stringify(locations));
			}

			var placeMarker = function(position) {
				var newMarker = new google.maps.Marker({ position: position, map: map, draggable: true, animation: google.maps.Animation.DROP });
				markers.push(newMarker);

				google.maps.event.addListener(newMarker, 'click', function() {
					var content = document.createElement('input'),
						info = new google.maps.InfoWindow({ content: content });

					content.setAttribute('type', 'button');
					content.classList.add('mpcf-button');
					content.setAttribute('value', localizedmpcf.remove);
					
					info.open(map, newMarker);
					info.content.addEventListener('click', function() {
						newMarker.setMap(null);
						markers.splice(markers.indexOf(newMarker), 1);
						updateInput();
					});
				});

				google.maps.event.addListener(newMarker, 'dragend', updateInput);
				return newMarker;
			}

			oldMarkers.forEach(function(position) {
				placeMarker(position);
				map.setCenter(position);
			});

			google.maps.event.addListener(map, 'click', function(e) {
				placeMarker(e.latLng);
				updateInput();
			});

			searchBox.addListener('places_changed', function() {
				var places = searchBox.getPlaces();
				if (places.length == 0) return;

				var bounds = new google.maps.LatLngBounds();
				places.forEach(function(place) {
					if (!place.geometry) return;
					placeMarker(place.geometry.location);

					if (place.geometry.viewport) {
						bounds.union(place.geometry.viewport);
					} else {
						bounds.extend(place.geometry.location);
					}
				});

				map.fitBounds(bounds);
			});
		});
	}

	window.addEventListener('load', initGoogleMap);



	/**************************************************************
		Register color pickers
	 **************************************************************/

	function registerColorPicker() {
		$('.mpcf-color-input').find('input').wpColorPicker();
	}


	/**************************************************************
		Media picker
	 **************************************************************/

	class MediaPickers {
		constructor() {
			this.pickers = {};
			this.registerNew();
		}

		registerNew(parent) {
			parent = parent || document;

			let newElems = [].slice.call(document.querySelectorAll('.mpcf-mediapicker:not([data-registered]), .mpcf-filepicker:not([data-registered]'));
			newElems.forEach((elem => {
				let id           = this.generateID(),
					changeMedia  = elem.querySelector('.mpcf-changemedia'),
					imagePreview = elem.querySelector('.mpcf-imagepreview'),
					videoPreview = elem.querySelector('.mpcf-videopreview'),

					fileName     = elem.querySelector('.filename'),
					fileSize     = elem.querySelector('.filesize'),
					clearMedia   = elem.querySelector('.mpcf-clearmedia, .mpcf-clearfile'),
					changeBtns   = [ changeMedia, imagePreview, videoPreview, fileName].filter(i => i !== null);

				changeBtns.forEach((btn => btn.addEventListener('click', (e => this.changeMedia(id, e)).bind(this))).bind(this));
				clearMedia.addEventListener('click', (e => this.clearMedia(id)).bind(this));

				elem.setAttribute('data-registered', 1);

				this.pickers[id] = {
					element:       elem,
					changeMedia:   changeMedia,
					changeBtns:    changeBtns,
					clearMedia:    clearMedia,

					imagePreview:  imagePreview,
					videoPreview:  videoPreview,

					fileName:      fileName,
					fileSize:      fileSize,
					idField:       elem.querySelector('.mpcf-media-id'),
					multiple:      !!elem.dataset.multiple,
					isMediaPicker: elem.classList.contains('mpcf-mediapicker'),
					isFilePicker:  elem.classList.contains('mpcf-filepicker'),
				};
			}).bind(this));
		}

		changeMedia(id, event) {
			event.preventDefault();

			let picker = this.pickers[id],
				modal  = wp.media({ title: localizedmpcf.chooseMedia, multiple: picker.multiple });

			modal.on('select', () => {
				modal.detach();

				let choice  = modal.state().get('selection').first().toJSON(),
					isImage = choice.mime.indexOf('image') > -1,
					isVideo = choice.mime.indexOf('video') > -1;

				picker.idField.setAttribute('value', choice.id);
				picker.changeMedia.setAttribute('value', localizedmpcf.change);
				picker.clearMedia.classList.remove('hidden');

				if (picker.isFilePicker) {
					picker.fileName.innerText = choice.filename;
					picker.fileSize.innerText = choice.filesizeHumanReadable;
					return;
				}

				picker.imagePreview.setAttribute('src', isImage ? choice.url : '');
				picker.imagePreview.classList.toggle('hidden', !isImage);
				picker.videoPreview.setAttribute('src', isVideo ? choice.url : '');
				picker.videoPreview.classList.toggle('hidden', !isVideo);
			});

			modal.open();
		}

		clearMedia(id) {
			let picker = this.pickers[id];
			picker.idField.setAttribute('value', '');

			picker.changeMedia.setAttribute('value', picker.isFilePicker ? localizedmpcf.addFile : localizedmpcf.addMedia)
			picker.clearMedia.classList.add('hidden');

			if (picker.isFilePicker) {
				picker.fileName.innerText = '';
				picker.fileSize.innerText = '0b';
				return;
			}

			picker.imagePreview.setAttribute('src', '');
			picker.imagePreview.classList.remove('hidden');
			picker.videoPreview.setAttribute('src', '');
			picker.videoPreview.classList.add('hidden');
		}

		generateID() {
			return Math.floor(Math.random() * Math.pow(10,10));
		}
	}




	/**************************************************************
		General helpers
	 **************************************************************/

	/**********
		Drag and drop
	 **********/

	class addDragDrop {
		constructor (target, args = {}) {
			this.elems = [];
			this.dragSource = null;
			this.isDragging = false;
			this.defaults = {
				cbStart: null,
				cbOver: null,
				cbEnter: null,
				cbLeave: null,
				cbDrop: null,
				cbEnd: null,
				clickElem: null
			};

			this.args = Object.assign(this.defaults, args);
			this.addElements(target);
			return this;
		}

		addElements(newElems) {
			if (!newElems || newElems.length === 0) return;
			
			var obj = this;

			if (newElems.length)
				newElems = [].slice.call(newElems);
			else
				newElems = [newElems];

			this.elems = this.elems.concat(newElems);

			newElems.forEach(function(elem) {
				obj.addHandlers(elem);
			});
		}

		addHandlers(elem) {
			elem.addEventListener('mousedown', (e) => this.handleClick(e),     false);
			elem.addEventListener('dragstart', (e) => this.handleDragStart(e), false);
			elem.addEventListener('dragenter', (e) => this.handleDragEnter(e), false);
			elem.addEventListener('dragover',  (e) => this.handleDragOver(e),  false);
			elem.addEventListener('dragleave', (e) => this.handleDragLeave(e), false);
			elem.addEventListener('drop',      (e) => this.handleDragDrop(e),  false);
			elem.addEventListener('dragend',   (e) => this.handleDragEnd(e),   false);
		}

		handleClick(e) {
			var elem = e.target;

			if (this.args.clickElem !== null) {
				if (!elem.matches(this.args.clickElem))
					elem = elem.closest(this.args.clickElem);

				if (elem === null) return;
			}

			while (this.elems.indexOf(elem) === -1 && elem.parentNode !== null)
				elem = elem.parentNode;

			if (elem === null) return;

			elem.draggable = true;
			this.isDragging = true;
		}

		handleDragStart(e) {
			if (!this.isDragging) return;

			var elem = e.target,
				inputs = elem.querySelectorAll('input, textarea');

			this.dragSource = elem;
			e.dataTransfer.effectAllowed = 'move';

			[].forEach.call(inputs, function(input) {
				input.dataset.value = input.value;
			});

			e.dataTransfer.setData('text/html', elem.outerHTML);
			elem.classList.add('dragged');

			if (typeof this.args.cbStart === 'function')
				this.args.cbStart(elem);
		}

		handleDragOver(e) {
			var elem = e.target;

			if (e.preventDefault)
				e.preventDefault();

			elem.classList.add('dragover');
			e.dataTransfer.dropEffect = 'move';

			if (typeof this.args.cbOver === 'function')
				this.args.cbOver(elem);

			return false;
		}

		handleDragEnter(e) {
			var elem = e.target;

			if (typeof this.args.cbEnter === 'function')
				this.args.cbEnter(elem);
		}

		handleDragLeave(e) {
			var elem = e.target;

			elem.classList.remove('dragover');

			if (typeof this.args.cbLeave === 'function')
				this.args.cbLeave(elem);
		}

		handleDragDrop(e) {
			if (!this.isDragging) return;

			var elem = e.target;

			if (e.stopPropagation)
				e.stopPropagation();

			if (this.dragSource !== elem) {
				var dropHtml = e.dataTransfer.getData('text/html'),
					parent = elem.parentNode,
					dropElem;

				parent.removeChild(this.dragSource);
				elem.insertAdjacentHTML('beforebegin', dropHtml);
				dropElem = elem.previousSibling;
				this.addHandlers(dropElem);

				[].forEach.call(dropElem.querySelectorAll('input, textarea'), function(input) {
					input.value = input.dataset.value;
					input.removeAttribute('data-value');
				});


				[].forEach.call(parent.querySelectorAll('meta'), function(meta) {
					parent.removeChild(meta);
				});
			}

			elem.classList.remove('dragover');
			this.isDragging = false;

			if (typeof this.args.cbDrop === 'function')
				this.args.cbDrop(elem);

			return false;
		}

		handleDragEnd(e) {
			var elem = e.target;

			this.isDragging = false;

			this.elems.forEach(function(elem) {
				elem.draggable = false;
				elem.classList.remove('dragover', 'dragged');
			});

			if (typeof this.args.cbEnd === 'function')
				this.args.cbEnd(elem);
		}
	}




	/**************************************************************
		Add qTranslate-X/qTranslate-XT after dynamically loaded fields
	**************************************************************/

	function addQTranslateX(parent = null) {
		if (typeof qTranslateConfig === 'undefined') return;
		
		parent = parent || document;

		var fields = parent.querySelectorAll('.mpcf-multilingual');
		[].forEach.call(fields, function(field) {
			qTranslateConfig.qtx.addContentHooksByClass('mpcf-multilingual');

			var hook = qTranslateConfig.qtx.hasContentHook(field.id);

			if (typeof hook !== 'undefined') return;

			if (field.classList.contains('mpcf-input-editor')) {
				if (typeof qTranslateConfig.qtx.addContentHooksTinyMCE == 'function') {
					qTranslateConfig.qtx.addContentHooksTinyMCE(field);
					return;
				}
			}
			
			qTranslateConfig.qtx.addContentHook(field, false, field.id);
		});
	}

	function removeQTranslateX(parent = null) {
		if (typeof qTranslateConfig === 'undefined') return;
		parent = parent || document;

		var fields = parent.querySelectorAll('.mpcf-multilingual');
		[].forEach.call(fields, function(field) {
			qTranslateConfig.qtx.removeContentHook(field);
		});
	}


	/**************************************************************
		Update loading elements
	**************************************************************/

	var updateLoadingElements = function(elem, toRemove = false) {
		var btns = [].slice.call(document.querySelectorAll('#publish, #submit, .editor-post-publish-button, .editor-post-save-draft, .editor-post-publish-panel__toggle'));
		if (btns.length == 0) return;

		if (!toRemove)	loadingElements.push(elem);
		else 			loadingElements = loadingElements.filter(elem => elem !== elem);

		btns[0].classList.toggle('disabled', loadingElements.length > 0);
	}




	/**************************************************************
		Drag and drop lists
	 **************************************************************/

	function dragDropLists() {
		$('.mpcf-drag-drop-list-container').each(function() {
			var list = this,
				selectionList = $(list).find('.mpcf-drag-drop-list-selection'),
				remainingList = $(list).find('.mpcf-drag-drop-list-remaining'),
				baseName      = list.dataset.basename,
				multiple      = !!+list.dataset.multiple;

			$(list).find('.mpcf-drag-drop-list-sublist').sortable({
				connectWith: '.mpcf-drag-drop-list-sublist',
				placeholder: 'sortable-placeholder'
			}).disableSelection();

			$(list).find('.mpcf-drag-drop-list-sublist').on('sortreceive', function(e) {
				if (multiple) {
					if (e.target.classList.contains('mpcf-drag-drop-list-selection')) {
						$(e.srcElement).parent('li').clone(true).appendTo(remainingList)
					}

					remainingList.find('li').each(function() {
						var title = $(this).find('.title').text();
						if (remainingList.find('li .title:contains("' + title + '")').length > 1)
							this.remove();
					});
				}

				selectionList.find('li').each(function(index) {
					$(this).find('input').attr('name', baseName + '[' + index + ']');
				});

				remainingList.find('input').attr('name', '');
				if (remainingList.find('li').length === 0)
					remainingList.html('');
			});
		});
	}



	/**************************************************************
		Paint Image Button Group
	 **************************************************************/

	var paintImageButtonGroup = function(parent) {
		parent        = parent || document;

		var painter   = wp.svgPainter,
			colors    = JSON.parse(JSON.stringify(painterColors)), // deep copy for WP svgPainter.js
			modules   = parent.querySelectorAll('.mpcf-imagebuttongroup-input');
		if (!modules) return;

		colors.icons.base = '#AAA';
		painter.setColors(colors);

		[].forEach.call(modules, function(module) {
			var options  = document.querySelectorAll('.mpcf-imagebuttongroup-option');

			var changeSelection = function() {
				[].forEach.call(options, function(option) {
					var input = option.querySelector('input'),
						label = option.querySelector('label.mpcf-has-svg-icon'),
						color = input.checked ? 'focus' : 'base';

					if (!label) return;
					painter.setColors(colors);
					painter.paintElement($(label), color);
				});
			};

			[].forEach.call(options, function(option) {
				var input = option.querySelector('input'),
					label = option.querySelector('label.mpcf-has-svg-icon');

				if (label)
					painter.paintElement($(label), input.checked ? 'focus' : 'base');
				input.addEventListener('change', changeSelection);
			});

		});
	}



	/**************************************************************
		Color selects
	 **************************************************************/

	class ColorSelects {
		constructor() {
			this.fields = {};
			this.registerNew();
		}

		registerNew(parent = null) {
			parent = parent || document;

			let fields = [].slice.call(parent.querySelectorAll('.mpcf-colorselect-input:not([data-registered])'));
			if (fields.length == 0) return;

			fields.forEach((element => {
				let id      = this.generateID(),
					select  = element.querySelector('.mpcf-colorselect-select'),
					list    = element.querySelector('.mpcf-colorselect-list'),
					options = [].slice.call(list.querySelectorAll('.mpcf-colorselect-option')),
					field   = element.querySelector('.mpcf-colorselect-hidden');

				element.dataset.registered = 1;

				select.addEventListener('click', (() => this.toggleList(id)).bind(this));
				options.forEach((option => option.addEventListener('click', (e => this.change(id, e)).bind(this))).bind(this));
				document.addEventListener('click', (e => this.hideList(id, e)).bind(this));

				this.fields[id] = {
					element: element,
					select:  select,
					list:    list,
					options: options,
					field:   field
				};
			}).bind(this));
		}

		toggleList(id) {
			this.fields[id].list.classList.toggle('visible');
		}

		hideList(id, e) {
			if (e.target.classList.contains('mpcf-colorselect-input') || e.target.closest('.mpcf-colorselect-input')) return;
			this.fields[id].list.classList.remove('visible');
		}

		change(id, e) {
			let target = e.target.classList.contains('mpcf-colorselect-option') ? e.target : e.target.closest('.mpcf-colorselect-option');

			this.fields[id].field.value = target.dataset.name;
			this.fields[id].list.classList.remove('visible');
			this.fields[id].select.innerHTML = target.innerHTML;
		}

		generateID() {
			return Math.floor(Math.random() * Math.pow(10,10));
		}
	}




	/**************************************************************
		Helper function
	 **************************************************************/

	var furthestAncestor = function(elem, selector) {
		var ancestor = elem;

		while (elem) {
			elem = elem.parentElement;
			if (elem && elem.matches(selector)) ancestor = elem;
		};

		return ancestor;
	};

	var unwrap = function(elem) {
		var parent = elem.parentNode;
		while (elem.firstChild)
			parent.insertBefore(elem.firstChild, elem);
		parent.removeChild(elem);
	}


	init();
})();