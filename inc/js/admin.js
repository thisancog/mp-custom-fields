$ = jQuery;

var loadingElements = [],
	panelSwitchers = [],
	painterColors = {
		icons: {
			base:    '#444',
			current: '#fff',
			focus:   '#007CBA'
		}
	};

var vpWidth = function() { return Math.max(document.documentElement.clientWidth, window.innerWidth || 0); }

window.addEventListener('load', function() {
	panelSwitch();
	resizePanelMenus();
	goToInvalids();
	checkHTML5Support();
	registerColorPicker();
	repeaterField();
	conditionalField();
	conditionalPanelsField();
	gridField();
	addQTranslateX();
	dragDropLists();
	paintImageButtonGroup();
});

window.addEventListener('resize', function() {
	resizePanelMenus();
});


/**************************************************************
	Panel switch
**************************************************************/

var panelSwitch = function() {
	var panelSets = document.querySelectorAll('.mpcf-panels'),
		painter   = wp.svgPainter;

	wp.svgPainter.setColors(painterColors);

	[].forEach.call(panelSets, function(panelSet) {
		 panelSwitchers.push(new PanelSwitcher(panelSet));
	});
}

class PanelSwitcher {
	constructor(set) {
		this.set       = set;
		this.menuItems = [].slice.call(this.set.querySelectorAll('.mpcf-panels-menu li'));
		this.panels    = [].slice.call(this.set.querySelectorAll('.mpcf-panels-tabs .mpcf-panel'));
		this.activeTab = set.querySelector('.activetab');

		this.registerEvents();
		this.activatePreactivatedPanel();
	}

	registerEvents() {
		this.menuItems.forEach(item => item.addEventListener('click', this.navigateToPanel.bind(this)));
	}

	activatePreactivatedPanel() {
		this.activatePanel(this.activeTab.value || 0);
	}

	navigateToPanel(e) {
		var target = e.target.classList.contains('mpcf-panel-item')
				   ? e.target
				   : e.target.closest('.mpcf-panel-item');

		this.activatePanel(target.dataset.index);
	}

	activatePanel(panel) {
		this.panels.forEach(item => item.classList.toggle('active-panel', item.dataset.index == panel));

		this.menuItems.forEach(function(item) {
			var icon      = item.querySelector('.mpcf-panel-icon'),
				paintMode = item.dataset.index == panel ? 'focus' : 'base';

			item.classList.toggle('active', item.dataset.index == panel);
			if (icon) wp.svgPainter.paintElement($(icon), paintMode);
		});

		this.set.classList.toggle('last-item-selected', panel == this.menuItems.length - 1);
		this.activeTab.setAttribute('value', panel);
	}

	registerMenuItem(item) {
		this.menuItems.push(item);
		this.menuItems = Array.from(new Set(this.menuItems));
		item.addEventListener('click', this.navigateToPanel.bind(this));
	}

	registerPanel(panel) {
		this.panels.push(panel);
		this.panels = Array.from(new Set(this.panels));
	}

	removePanel(panel, menuItem) {
		this.panels = this.panels.filter(otherPanel => otherPanel !== panel);
		this.menuItems = this.panels.filter(otherItem => otherItem !== menuItem);

		if (panel.classList.contains('active-panel'))
			this.activatePanel(this.panels[0].dataset.index);
		
		panel.parentElement.removeChild(panel);
		menuItem.parentElement.removeChild(menuItem);
	}
}



/**************************************************************
	Resize panel menus
 **************************************************************/

var resizePanelMenus = function() {
	var menuItems = document.querySelectorAll('.mpcf-panel-item'),
		minWidth = 0,
		maxWidth = 250,
		threshold = 1130;

	if (vpWidth() >= threshold) {
		[].forEach.call(menuItems, function(item) {
			var title = item.querySelector('.mpcf-panel-title');

			title.style.position = 'fixed';
			title.style.fontWeight = 'bold';
			minWidth = Math.max(minWidth, title.scrollWidth);
			title.style.position = '';
			title.style.fontWeight = '';
		});
	}

	if (minWidth >= maxWidth) minWidth = 0;
	document.styleSheets[0].insertRule('.mpcf-panel-title { min-width: ' + minWidth + 'px; }');
};


/**************************************************************
	Register asynchronously loaded elements
 **************************************************************/

var registerAsyncElements = function(parent) {
	registerMediaPicker();
	addQTranslateX(parent);
	registerEditors(parent);
	registerColorPicker(parent);
	conditionalField(parent);
	conditionalPanelsField(parent);
	gridField(parent);
	repeaterField(parent);

	checkHTML5Support(parent);
	focusInvalids(parent);
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

	var repeaters = parent.querySelectorAll('.mpcf-repeater-input'),
		id		  = document.querySelector('#post_ID').value;
			
	[].forEach.call(repeaters, function(repeater) {
		if (repeater.dataset.registered && repeater.dataset.registered == 1) return;

		var set             = repeater.closest('.mpcf-panels'),
			rowsWrapper     = repeater.querySelector('.mpcf-repeater-wrapper'),
			loader          = repeater.querySelector('.mpcf-loading-container'),
			fields          = rowsWrapper.dataset.fields,
			fieldsObj       = JSON.parse(fields),
			values          = rowsWrapper.dataset.values,
			addBtn          = repeater.querySelector('.mpcf-repeater-add-row'),
			emtpyField      = repeater.querySelector('.mpcf-repeater-empty'),
			rowHTML         = null,
			dragDropHandler = null;

		repeater.dataset.registered = 1;

		// make sure that values are always an array
		
		if (!Array.isArray(JSON.parse(values))) {
			values = JSON.stringify([JSON.parse(values)]);
		}

		if (values.length !== -1)
			updateLoadingElements(repeater);


		// populate repeater

		$.post(ajaxurl, { 'action': 'mpcf_get_repeater_row', 'fields': fields, 'values': values, 'id': id }, function(response) {
			rowsWrapper.innerHTML = response;
			[].forEach.call(rowsWrapper.querySelectorAll('.mpcf-repeater-row-remove'), function(btn) {
				btn.addEventListener('click', removeRow);
			});

			dragDropHandler = new addDragDrop(rowsWrapper.querySelectorAll('.mpcf-repeater-row'), {
				cbEnd: function() { renameDynamicFields(set); },
				clickElem: '.mpcf-repeater-row-move'
			});

			renameDynamicFields(set);
			loader.classList.remove('mpcf-loading-active');
			checkCheckableElements(rowsWrapper);
			registerAsyncElements(rowsWrapper);

			updateLoadingElements(repeater, true);
		});


		// prefetch blank row

		$.post(ajaxurl, { 'action': 'mpcf_get_repeater_row', 'fields': fields, 'id': id }, function(response) {
			rowHTML = response;
		});


		// add row
		addBtn.addEventListener('click', function() {
			var newRow = document.createElement('li');
			newRow.classList.add('mpcf-repeater-row');
			newRow.innerHTML = rowHTML;
			rowsWrapper.appendChild(newRow);
			newRow.querySelector('.mpcf-repeater-row-remove').addEventListener('click', removeRow);

			dragDropHandler.addElements(newRow);

			emtpyField.removeAttribute('name');

			renameDynamicFields(set);
			registerAsyncElements(newRow);
		});


		// remove Row

		var removeRow = function(e) {
			var el = e.target;
			el.removeEventListener('click', removeRow);

			removeQTranslateX(el);

			while ((el = el.parentElement) && !el.classList.contains('mpcf-repeater-row'));

			// find and also remove attached panels, if there are conditional panels
			var panelSelects = [].slice.call(el.querySelectorAll('.mpcf-conditionalpanels-input'));
			panelSelects.forEach(function(select) {
				var id       = select.dataset.panelId,
					set      = select.closest('.mpcf-panels'),
					panel    = set.querySelector('.mpcf-panel[data-id="' + id + '"]'),
					menuItem = set.querySelector('li.mpcf-panel-item[data-id="' + id + '"]');

				panelSwitchers.forEach(function(switcher) {
					if (switcher.set !== set) return;

					switcher.removePanel(panel, menuItem);
				});
			
			});

			el.parentElement.removeChild(el);

		//	if there are no rows, "activate" the empty field so there is an empty value posted on submit
			if (rowsWrapper.children.length == 0) {
				emtpyField.setAttribute('name', emtpyField.dataset.name);
			}

			renameDynamicFields(set);
		}
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
						input.dataset.name = input.name; 
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
						panel   = set.querySelector('.mpcf-panel[data-id="' + panelID + '"]');
					
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

var conditionalField = function(parent = null) {
	parent = parent || document;

	var fields = parent.querySelectorAll('.mpcf-conditional-input');
	if (!fields) return;

	[].forEach.call(fields, function(field) {
		if (field.dataset.registered && field.dataset.registered == 1) return;

		var select = field.querySelector('.mpcf-conditional-choice select, .mpcf-conditional-choice input[type="checkbox"]'),
			loader = field.querySelector('.mpcf-loading-container'),
			wrapper = field.querySelector('.mpcf-conditional-wrapper'),
			baseName = select.dataset.basename,
			options = JSON.parse(select.dataset.options),
			values = JSON.parse(select.dataset.values),
			isSingle = select.tagName.toLowerCase() === 'input';

		field.dataset.registered = 1;
		select.removeAttribute('data-options');
		select.removeAttribute('data-values');

		if (values.options)
			values = values.options;

		var switchContent = function(values = false) {
			// no option with this value available, i.e. no option selected
			if ((isSingle && select.checked === false) || typeof options[select.value] === 'undefined') {
				wrapper.innerHTML = '';
				return;
			}

			updateLoadingElements(field);

			var request = {
				'action': 'mpcf_get_conditional_fields',
				'fields': JSON.stringify(options[select.value].fields),
				'values': values
			};

			wrapper.innerHTML = '';
			loader.classList.add('mpcf-loading-active');

			$.post(ajaxurl, request, function(response) {
				var parent = furthestAncestor(wrapper, '.mpcf-conditional-input');
				removeQTranslateX(wrapper);

				wrapper.innerHTML = response;
				renameDynamicFields(parent);

				loader.classList.remove('mpcf-loading-active');
				updateLoadingElements(field, true);
				checkCheckableElements(wrapper);
				registerAsyncElements(wrapper);
			});
		}

		select.addEventListener('change', () => switchContent());

		if (values !== '') {
			delete values.option;
			switchContent(values);
		}

	});
}


/**************************************************************
	Conditional panel fields
 **************************************************************/

var conditionalPanelsField = function(parent = null) {
	parent = parent || document;

	var fields = parent.querySelectorAll('.mpcf-conditionalpanels-input');
	if (!fields) return;

	[].forEach.call(fields, function(field) {
		if (field.dataset.registered && field.dataset.registered == 1) return;

		var set      = field.closest('.mpcf-panels'),
			menu     = set.querySelector('.mpcf-panels-menu'),
			tabs     = set.querySelector('.mpcf-panels-tabs'),
			select   = field.querySelector('.mpcf-conditional-choice select, .mpcf-conditional-choice input[type="checkbox"]'),
			wrapper  = field.querySelector('.mpcf-conditional-wrapper'),			
			baseName = select.dataset.basename,
			options  = JSON.parse(select.dataset.options),
			values   = JSON.parse(select.dataset.values),
			isSingle = select.tagName.toLowerCase() === 'input',
			menuItem = document.createElement('li'),
			tab      = document.createElement('div'),
			id       = Math.floor(Math.random() * Math.pow(10,10));

		field.dataset.registered = 1;
		field.setAttribute('data-panel-id', id);
		select.removeAttribute('data-options');
		select.removeAttribute('data-values');
		
		menuItem.setAttribute('data-id', id);
		menu.appendChild(menuItem);

		tab.setAttribute('data-id', id);
		tabs.appendChild(tab);

		var switchContent = function() {
			// no option with this value available, i.e. no option selected
			if ((isSingle && select.checked === false) || typeof options[select.value] === 'undefined') {
				if (menuItem)	menuItem.innerHTML = '';
				if (tab)		tab.innerHTML = '';
				return;
			}

			updateLoadingElements(field);

			var request = {
				'action': 'mpcf_get_conditional_panels_fields',
				'panel':  JSON.stringify(options[select.value].panel),
				'values': values
			};

			$.post(ajaxurl, request, function(response) {
				response = JSON.parse(response);

				if (tab)
					removeQTranslateX(tab);

				menuItem.outerHTML = response.menu;
				tab.outerHTML      = response.tab;

				menuItem = menu.querySelector('[data-index="-1"]');
				tab      = tabs.querySelector('[data-index=""]');

				menuItem.setAttribute('data-id', id);
				tab.setAttribute('data-id', id);

				menuItem.setAttribute('data-index', getElemOrder(menuItem));
				tab.setAttribute('data-index', getElemOrder(tab));
				tab.setAttribute('data-basename', baseName);
				tab.classList.add('mpcf-conditionalpanel');

				panelSwitchers.forEach(function(switcher) {
					if (switcher.set !== set) return;

					switcher.registerMenuItem(menuItem);
					switcher.registerPanel(tab);
					switcher.activatePreactivatedPanel();
				});

				renameDynamicFields(set);
				updateLoadingElements(field, true);
				checkCheckableElements(tab);
				registerAsyncElements(tab);
			});
		}

		var getElemOrder = function(elem) {
			var order = 0,
				node  = elem.parentNode.firstChild;
			while (node && node !== elem) {
				if (node !== elem && node.nodeType == Node.ELEMENT_NODE)
					order++;
				node = node.nextElementSibling || node.nextSibling;
			}

			return order;
		}

		switchContent();
		select.addEventListener('change', switchContent);
	});
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

$(document).ready(function($) {
	registerMediaPicker();
});

function registerMediaPicker() {
	var change = $('.mpcf-mediapicker .mpcf-imagepreview, .mpcf-mediapicker .mpcf-videopreview, .mpcf-mediapicker .mpcf-changemedia, .mpcf-filepicker .filename'),
		clear = $('.mpcf-clearmedia, .mpcf-clearfile');

	change.unbind('click');
	clear.unbind('click');

	change.click(function(e) { changeMedia(this, e); });
	clear.click(function() { clearimg(this); });
}

function changeMedia(elem, e) {	
	e.preventDefault();

	var parent = $(elem).parents('.mpcf-mediapicker'),
		multiple = parent.data('multiple');

	var image = wp.media({ 
		title: localizedmpcf.chooseMedia,
		multiple: multiple
	}).open()
	.on('select', function(e) {
		var choice = image.state().get('selection').first().toJSON();

		parent.find('.mpcf-media-id').val(choice.id);
		parent.find('.mpcf-changemedia').val(localizedmpcf.change);

		if (parent.is('.mpcf-filepicker')) {
			parent.find('.mpcf-clearfile').removeClass('hidden');
			parent.find('.mpcf-preview-content-file .filename').html(choice.filename);
			parent.find('.mpcf-preview-content-file .filesize').html(choice.filesizeHumanReadable);
			return;
		}
		
		parent.find('.mpcf-clearmedia').removeClass('hidden');

		if (choice.mime.indexOf('image') > -1) {
			parent.find('.mpcf-imagepreview').attr('src', choice.url);
			parent.find('.mpcf-imagepreview').removeClass('hidden');
			parent.find('.mpcf-videopreview').attr('src', '');
			parent.find('.mpcf-videopreview').addClass('hidden');

		} else if (choice.mime.indexOf('video') > -1) {
			parent.find('.mpcf-videopreview').attr('src', choice.url);
			parent.find('.mpcf-videopreview').removeClass('hidden');
			parent.find('.mpcf-imagepreview').attr('src', '');
			parent.find('.mpcf-imagepreview').addClass('hidden');
		}
	});
}

function clearimg(elem) {
	var parent = $(elem).parents('.mpcf-mediapicker'),
		isFilePicker = parent.is('.mpcf-filepicker');

	parent.find('.mpcf-media-id').val('');

	if (isFilePicker) {
		parent.find('.mpcf-changemedia').val(localizedmpcf.addFile);
		parent.find('.mpcf-clearfile').addClass('hidden');
		parent.find('.mpcf-preview-content-file .filename').html('');
		parent.find('.mpcf-preview-content-file .filesize').html('0b');
	} else {
		parent.find('.mpcf-imagepreview').attr('src', '').removeClass('hidden');
		parent.find('.mpcf-videopreview').attr('src', '').addClass('hidden');
		parent.find('.mpcf-changemedia').val(localizedmpcf.addMedia);
		parent.find('.mpcf-clearmedia').addClass('hidden');
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
	Panel switch
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
			baseName = list.dataset.basename,
			multiple = !!+list.dataset.multiple;

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

var paintImageButtonGroup = function() {
	var painter   = wp.svgPainter,
		colors    = painterColors,
		modules   = document.querySelectorAll('.mpcf-imagebuttongroup-input');
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



