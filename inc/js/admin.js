$ = jQuery;

var loadingElements = [];

window.addEventListener('load', function() {
	panelSwitch();
	goToInvalids();
	checkHTML5Support();
	registerColorPicker();
	repeaterField();
	conditionalField();
	addQTranslateX();
	dragDropLists();
});


/**************************************************************
	Panel switch
**************************************************************/

var panelSwitch = function() {
	var panelSets = document.querySelectorAll('.mpcf-panels'),
		painter = wp.svgPainter;

	[].forEach.call(panelSets, function(panelSet) {
		var start = panelSet.querySelector('.activetab').value || 0,
			listItems = panelSet.querySelectorAll('.mpcf-panels-menu li'),
			panels = panelSet.querySelectorAll('.mpcf-panels-tabs .mpcf-panel');

		// apply color to SVG icons
		[].forEach.call(listItems, (item) => painter.paintElement($(item.querySelector('.mpcf-panel-icon')), 'base'));

		// find active panel and give it a class
		[].filter.call(listItems, (item) => item.dataset.index === start).forEach((item) => item.classList.add('active'));
		[].filter.call(panels, (panel) => panel.dataset.index === start).forEach((panel) => panel.classList.add('active-panel'));
		panelSet.classList.toggle('last-item-selected', start == listItems.length - 1);


		[].forEach.call(listItems, function(listItem) {
			listItem.addEventListener('click', function() {
				if (listItem.classList.contains('active')) return;

				var dest = listItem.dataset.index,
					destPanel = [].filter.call(panels, panel => panel.dataset.index === dest)[0];

				// remove active class from current active panel
				[].forEach.call(listItems, (listItem) => {
					listItem.classList.remove('active');
					painter.paintElement($(listItem.querySelector('.mpcf-panel-icon')), 'base');
				});

				[].forEach.call(panels, (panel) => panel.classList.remove('active-panel'));

				// apply new active class
				listItem.classList.add('active');
				destPanel.classList.add('active-panel');
				panelSet.classList.toggle('last-item-selected', dest == listItems.length - 1);

				// apply color to SVG icon of active panel
				painter.paintElement($(listItem.querySelector('.mpcf-panel-icon')), 'focus');

				// update activeTab field
				panelSet.querySelector('.activetab').setAttribute('value', dest);
			});
		});
	});
}


/**************************************************************
	Registre asynchronously loaded elements
 **************************************************************/

var registerAsyncElements = function(parent) {
	registerMediaPicker();
	registerEditors(parent);
	registerColorPicker(parent);
	conditionalField(parent);
	repeaterField(parent);

	checkHTML5Support(parent);
	addQTranslateX(parent);
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

	var fields = parent.querySelectorAll('.mpcf-editor-input .mpcf-field'),
		defaultSettings = wp.editor.getDefaultSettings(),
		options = { 
			tinymce: { 
				wpautop: true,
				plugins : 'charmap colorpicker compat3x directionality fullscreen hr image lists media paste tabfocus textcolor wordpress wpautoresize wpdialogs wpeditimage wpemoji wpgallery wplink wptextpattern wpview', 
				toolbar1: 'formatselect bold italic | bullist numlist | blockquote | alignleft aligncenter alignright | link unlink | wp_more | spellchecker',
			},
			mediaButtons: true,
			quicktags: true 
		};

	[].forEach.call(fields, function(field) {
		var editor = field.querySelector('.mpcf-input-editor'),
			description = field.querySelector('.mpcf-description'),
			textarea = editor.cloneNode(),
			id = editor.id,
			oldContent = wp.editor.getContent(id),
			idShort = id.split('-').pop();

		textarea.innerText = oldContent || '';
		wp.editor.remove(idShort);
		field.appendChild(textarea);
		wp.editor.initialize(id, options);

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
	[].forEach.call(repeaters, function(repeater) {
		if (repeater.dataset.registered && repeater.dataset.registered == 1) return;

		var rowsWrapper = repeater.querySelector('.mpcf-repeater-wrapper'),
			loader = repeater.querySelector('.mpcf-loading-container'),
			fields = rowsWrapper.dataset.fields,
			fieldsObj = JSON.parse(fields),
			values = rowsWrapper.dataset.values,
			addBtn = repeater.querySelector('.mpcf-repeater-add-row'),
			rowHTML = null,
			dragDropHandler = null;

		repeater.dataset.registered = 1;

		// make sure that values are always an array
		
		if (!Array.isArray(JSON.parse(values))) {
			values = JSON.stringify([JSON.parse(values)]);
		}

		if (values.length !== -1)
			updateLoadingElements(repeater);

		// populate repeater

		$.post(ajaxurl, { 'action': 'mpcf_get_repeater_row', 'fields': fields, 'values': values }, function(response) {
			rowsWrapper.innerHTML = response;
			[].forEach.call(rowsWrapper.querySelectorAll('.mpcf-repeater-row-remove'), function(btn) {
				btn.addEventListener('click', removeRow);
			});

			dragDropHandler = new addDragDrop(rowsWrapper.querySelectorAll('.mpcf-repeater-row'), { cbEnd: reorder, clickElem: '.mpcf-repeater-row-move' });

			reorder();
			loader.classList.remove('mpcf-loading-active');
			registerAsyncElements(rowsWrapper);

			updateLoadingElements(repeater, true);
		});

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
			newRow.querySelector('.mpcf-repeater-row-remove').addEventListener('click', removeRow);

			dragDropHandler.addElements(newRow);

			reorder();
			registerAsyncElements(newRow);
		});


		// remove Row

		var removeRow = function(e) {
			var el = e.target;
			el.removeEventListener('click', removeRow);

			removeQTranslateX(el);

			while ((el = el.parentElement) && !el.classList.contains('mpcf-repeater-row'));
			el.parentElement.removeChild(el);

			reorder();
		}


		// reorder rows and assign new indices

		var reorder = function() {
			var rows = rowsWrapper.querySelectorAll('.mpcf-repeater-row');

			[].forEach.call(rows, function(row, rowIndex) {
				var fields = row.querySelectorAll('.mpcf-field-option');

				[].forEach.call(fields, function(field, fieldIndex) {
					var inputs = field.querySelectorAll('[name], [id], [for]'),
						valids = ['input', 'button', 'label', 'textarea', 'select', 'datalist', 'keygen', 'fieldset', 'option'];

					inputs = [].filter.call(inputs, function(input) {
						return valids.indexOf(input.tagName.toLowerCase()) > -1;
					});

					inputs.forEach(function(input) {
						if (!input.dataset.name)
							input.dataset.name = input.name || input.getAttribute('for'); 

						let type    = input.getAttribute('type'),
							newID   = generateID(input),
							newName = generateName(input);

						if (type === 'button' || type === 'submit') return;

						if (input.hasAttribute('id'))	input.setAttribute('id', newID);
						if (input.hasAttribute('for'))	input.setAttribute('for', newID);
						if (input.hasAttribute('name'))	input.setAttribute('name', newName);
					});
				});
			});

			rowsWrapper.classList.toggle('empty', rowsWrapper.childElementCount === 0);
		}
	});
}



var generateName = function(elem) {
	var name = [elem.dataset.name];

	while (elem.parentNode) {
		elem = elem.parentNode;

		if (elem.dataset && elem.dataset.basename)
			name.unshift(elem.dataset.basename);

		if (elem.classList && elem.classList.contains('mpcf-repeater-row')) {
			var i = 0,
				child = elem;

			while ((child = child.previousSibling) != null) {
				if (child.classList && child.classList.contains('mpcf-repeater-row'))
					i++;
			}

			name.unshift(i);
		}
	}

	name = name.map(item => item.toString().match(/(\S+?)\[(\S+?)\]/)
						 ?  item.toString().match(/(\S+?)\[(\S+?)\]/).slice(1)
						 : item)
				.flat(9999)
				.filter((item, index, arr) => index === 0 || item !== arr[index - 1]);

	name = name.length == 1 ? name[0] : name[0] + '[' + name.slice(1).join('][') + ']';
	return name;
}


var generateID = function(elem) {
	var id = generateName(elem).replace(/\]\[/g, '-').replace('[', '-').replace(']', '');

	if (elem.tagName.toLowerCase() === 'input' && elem.type === 'radio')
		id += '-' + elem.value;
	return id;
}

var renameDynamicFields = function(parent) {
	var rows = parent.querySelectorAll('.mpcf-repeater-row, .mpcf-conditional-container');

	// each row
	[].forEach.call(rows, function(row, rowIndex) {

		var fields = row.querySelectorAll('.mpcf-field-option');

		// each field
		[].forEach.call(fields, function(field, fieldIndex) {
			var inputs = field.querySelectorAll('[name], [id], [for]'),
				valids = ['input', 'button', 'label', 'textarea', 'select', 'datalist', 'keygen', 'fieldset', 'option'];

			inputs = [].filter.call(inputs, function(input) {
				return valids.indexOf(input.tagName.toLowerCase()) > -1;
			});

			// each input
			inputs.forEach(function(input, inputIndex) {
				let type = input.getAttribute('type'),
					newID, newName;

				if (!input.dataset.name) {
					if (input.getAttribute('for') && inputIndex !== 0) {
						newID = inputs[inputIndex - 1].id;
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

				if (input.hasAttribute('name'))
					input.setAttribute('name', generateName(input));
			});
		});
	});

	parent.classList.toggle('empty', parent.childElementCount === 0);
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
				registerAsyncElements(wrapper);
			});
		}

		// var rename = function() {
		// 	var fields = wrapper.querySelectorAll('.mpcf-field-option');

		// 	[].forEach.call(fields, function(field, fieldIndex) {
		// 		var inputs = field.querySelectorAll('[name], [id], [for]');

		// 		[].forEach.call(inputs, function(input) {
		// 			let type = input.getAttribute('type'),
		// 				fieldName = options[select.value].fields[fieldIndex].name,
		// 				newID   = baseName + '-' + fieldName,
		// 				newName = baseName + '[' + fieldName +  ']';

		// 			if (type === 'button' || type === 'submit') return;
		// 			if (input.hasAttribute('id'))	input.setAttribute('id', newID);
		// 			if (input.hasAttribute('for'))	input.setAttribute('for', newID);
		// 			if (input.hasAttribute('name'))	input.setAttribute('name', newName);
		// 		});
		// 	});
		// }

		select.addEventListener('change', () => switchContent());

		if (values !== '') {
			delete values.option;
			switchContent(values);
		}

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
			parent.find('.mpcf-imagepreview').attr('src', choice.sizes.medium.url);
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
		var hook = qTranslateConfig.qtx.hasContentHook(field.id);

		if (typeof hook !== 'undefined') return;

		if (field.classList.contains('mpcf-input-editor')) {
			if (typeof qTranslateConfig.qtx.addContentHooksTinyMCE == 'function') {
				qTranslateConfig.qtx.addContentHooksTinyMCE(field);
				return;
			}
		}
		
		qTranslateConfig.qtx.addContentHook(field);
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
	var btn = document.querySelector('#publish, #submit, .editor-post-publish-button');

	if (!toRemove)	loadingElements.push(elem);
	else 			loadingElements = loadingElements.filter(elem => elem !== elem);

	btn.classList.toggle('disabled', loadingElements.length > 0);
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





