$ = jQuery;

window.addEventListener('load', function() {
	panelSwitch();
	goToInvalids();
	checkHTML5Support();
	repeaterField();
	conditionalField();
});


/**************************************************************
	Panel switch
**************************************************************/

var panelSwitch = function() {
	var panelSets = document.querySelectorAll('.mpcf-panels');

	[].forEach.call(panelSets, function(panelSet) {
		var start = panelSet.querySelector('.activetab').getAttribute('value') || 0,
			listItems = panelSet.querySelectorAll('.mpcf-panels-menu li'),
			panels = panelSet.querySelectorAll('.mpcf-panels-tabs .mpcf-panel');

		[].filter.call(listItems, (item) => item.dataset.index === start).forEach((item) => item.classList.add('active'));
		[].filter.call(panels, (panel) => panel.dataset.index === start).forEach((panel) => panel.classList.add('active-panel'));

		[].forEach.call(listItems, function(listItem) {
			listItem.addEventListener('click', function() {
				if (listItem.classList.contains('active')) return;

				var dest = listItem.dataset.index;

				[].forEach.call(listItems, (listItem) => listItem.classList.remove('active'));
				[].forEach.call(panels, (panel) => panel.classList.remove('active-panel'));

				listItem.classList.add('active');
				[].filter.call(panels, (panel) => panel.dataset.index === dest).forEach((panel) => panel.classList.add('active-panel'));
				panelSet.querySelector('.activetab').setAttribute('value', dest);
			});
		});
	});
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
	Check if browser supports HTML5 input types
 **************************************************************/

var checkHTML5Support = function() {
	var elems = document.querySelectorAll('.mpcf-nohtml5');
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
			baseName = rowsWrapper.dataset.basename,
			loader = repeater.querySelector('.mpcf-loading-container'),
			fields = rowsWrapper.dataset.fields,
			fieldsObj = JSON.parse(fields),
			values = rowsWrapper.dataset.values,
			addBtn = repeater.querySelector('.mpcf-repeater-add-row'),
			rowHTML = null,
			dragDropHandler = null;


		repeater.dataset.registered = 1;

		// populate repeater

		$.post(ajaxurl, { 'action': 'mpcf_get_repeater_row', 'fields': fields, 'values': values }, function(response) {
			rowsWrapper.innerHTML = response;
			[].forEach.call(rowsWrapper.querySelectorAll('.mpcf-repeater-row-remove'), function(btn) {
				btn.addEventListener('click', removeRow);
			});

			dragDropHandler = new addDragDrop(rowsWrapper.querySelectorAll('.mpcf-repeater-row'), { cbEnd: reorder, clickElem: '.mpcf-repeater-row-move' });

			reorder();
			loader.classList.remove('mpcf-loading-active');
			registerMediaPicker();
			conditionalField(rowsWrapper);
			focusInvalids(rowsWrapper);
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
			registerMediaPicker();
			focusInvalids(newRow);
		});


		// remove Row

		var removeRow = function(e) {
			var el = e.target;
			el.removeEventListener('click', removeRow);

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
					var inputs = row.querySelectorAll('[name], [id], [for]');

					[].forEach.call(inputs, function(input) {
						let type = input.getAttribute('type'),
							newID   = baseName + '-' + rowIndex + '-'  + fieldsObj[fieldIndex]['name'],
							newName = baseName + '[' + rowIndex + '][' + fieldsObj[fieldIndex]['name'] +  ']';

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




/**************************************************************
	Conditional fields
 **************************************************************/

var conditionalField = function(parent = null) {
	parent = parent || document;

	var fields = parent.querySelectorAll('.mpcf-conditional-input');
	if (!fields) return;

	[].forEach.call(fields, function(field) {
		if (field.dataset.registered && field.dataset.registered == 1) return;

		var select = field.querySelector('.mpcf-conditional-choice select'),
			loader = field.querySelector('.mpcf-loading-container'),
			wrapper = field.querySelector('.mpcf-conditional-wrapper'),
			baseName = select.dataset.basename,
			options = JSON.parse(select.dataset.options),
			values = JSON.parse(select.dataset.values);

		field.dataset.registered = 1;

		var switchContent = function(values = false) {
			var request = {
					'action': 'mpcf_get_conditional_fields',
					'fields': JSON.stringify(options[select.value].fields),
					'values': values
				};

			wrapper.innerHTML = '';
			loader.classList.add('mpcf-loading-active');

			$.post(ajaxurl, request, function(response) {
				wrapper.innerHTML = response;
				rename();

				loader.classList.remove('mpcf-loading-active');
				registerMediaPicker();
				repeaterField(wrapper);
				focusInvalids(wrapper);
			});
		}

		var rename = function() {
			var fields = wrapper.querySelectorAll('.mpcf-field-option');

			[].forEach.call(fields, function(field, fieldIndex) {
				var inputs = field.querySelectorAll('[name], [id], [for]');

				[].forEach.call(inputs, function(input) {
					let type = input.getAttribute('type'),
						fieldName = options[select.value].fields[fieldIndex].name,
						newID   = baseName + '-' + fieldName,
						newName = baseName + '[' + fieldName +  ']';

					if (type === 'button' || type === 'submit') return;
					if (input.hasAttribute('id'))	input.setAttribute('id', newID);
					if (input.hasAttribute('for'))	input.setAttribute('for', newID);
					if (input.hasAttribute('name'))	input.setAttribute('name', newName);
				});
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

$(document).ready(function() {
	$('.mpcf-color-input').find('input').wpColorPicker();
});


/**************************************************************
	Media picker
 **************************************************************/

$(document).ready(function($) {
	registerMediaPicker();
});

function registerMediaPicker() {
	var change = $('.mpcf-mediapicker .mpcf-imagepreview, .mpcf-mediapicker .mpcf-videopreview, .mpcf-mediapicker .mpcf-changemedia, .mpcf-filepicker .filename'),
		clear = $('.mpcf-clearmedia');

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
	.on('select', function(e){
		var choice = image.state().get('selection').first().toJSON();

		parent.find('.mpcf-media-id').val(choice.id);
		parent.find('.mpcf-changemedia').val(localizedmpcf.changeMedia);
		parent.find('.mpcf-clearmedia').removeClass('hidden');

		if (parent.is('.mpcf-filepicker')) {

			parent.find('.mpcf-preview-content-file .filename').html(choice.filename);
			parent.find('.mpcf-preview-content-file .filesize').html(choice.filesizeHumanReadable);
			return;
		}

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
	var parent = $(elem).parents('.mpcf-mediapicker');
	parent.find('.mpcf-media-id').val('');
	parent.find('.mpcf-imagepreview').attr('src', '').removeClass('hidden');
	parent.find('.mpcf-videopreview').attr('src', '').addClass('hidden');
	parent.find('.mpcf-changemedia').val(localizedmpcf.addMedia);
	parent.find('.mpcf-clearmedia').addClass('hidden');
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




