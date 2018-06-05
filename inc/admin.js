
$ = jQuery;

window.addEventListener('load', function() {
	panelSwitch();
	goToInvalids();
	checkHTML5Support();
	repeaterField();
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

		[].filter.call(listItems, (item) => item.getAttribute('data-index') === start).forEach((item) => item.classList.add('active'));
		[].filter.call(panels, (panel) => panel.getAttribute('data-index') === start).forEach((panel) => panel.classList.add('active-panel'));

		[].forEach.call(listItems, function(listItem) {
			listItem.addEventListener('click', function() {
				if (listItem.classList.contains('active')) return;

				var dest = listItem.getAttribute('data-index');

				[].forEach.call(listItems, (listItem) => listItem.classList.remove('active'));
				[].forEach.call(panels, (panel) => panel.classList.remove('active-panel'));

				listItem.classList.add('active');
				[].filter.call(panels, (panel) => panel.getAttribute('data-index') === dest).forEach((panel) => panel.classList.add('active-panel'));
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
	[].forEach.call(elem.querySelectorAll('input'), function(input) {
		input.addEventListener('invalid', function(e) {
			var menu = document.querySelector('.mpcf-panels-menu'),
				active = document.querySelector('.mpcf-panels-tabs .active-panel'),
				parent = e.target;

			active.classList.remove('active-panel');
			menu.querySelector('li[data-index="' + active.getAttribute('data-index') + '"]').classList.remove('active');

			while ((parent = parent.parentElement) && !parent.classList.contains('mpcf-panel'));
			parent.classList.add('active-panel');

			menu.querySelector('li[data-index="' + parent.getAttribute('data-index') + '"]').classList.add('active');
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
			invalid = elem.getAttribute('data-invalid-test');

		input.setAttribute('type', type);
		input.setAttribute('value', invalid);

		if (input.value !== invalid)
			elem.classList.remove('mpcf-nohtml5');
	});
}



/**************************************************************
	Repeater field
 **************************************************************/

var repeaterField = function() {
	var repeaters = document.querySelectorAll('.mpcf-repeater');
	[].forEach.call(repeaters, function(repeater) {
		var rowsWrapper = repeater.querySelector('.mpcf-repeater-wrapper'),
			baseName = rowsWrapper.getAttribute('data-basename'),
			loader = repeater.querySelector('.mpcf-loading-container'),
			fields = rowsWrapper.getAttribute('data-fields'),
			fieldsObj = JSON.parse(fields),
			values = rowsWrapper.getAttribute('data-values'),
			addBtn = repeater.querySelector('.mpcf-repeater-add-row'),
			rowHTML = null;

		jQuery.post(ajaxurl, { 'action': 'mpcf_get_component', 'fields': fields, 'values': values }, function(response) {
			rowsWrapper.innerHTML = response;
			[].forEach.call(rowsWrapper.querySelectorAll('.mpcf-repeater-row-remove'), function(btn) {
				btn.addEventListener('click', removeRow);
			});

			reorder();
			loader.classList.remove('mpcf-loading-active');
			registerMediaPicker();
			focusInvalids(rowsWrapper);
		});

		jQuery.post(ajaxurl, { 'action': 'mpcf_get_component', 'fields': fields }, function(response) {
			rowHTML = response;
		});

		addBtn.addEventListener('click', function() {
			var newRow = document.createElement('li');
			newRow.classList.add('mpcf-repeater-row');
			newRow.innerHTML = rowHTML;
			rowsWrapper.appendChild(newRow);
			newRow.querySelector('.mpcf-repeater-row-remove').addEventListener('click', removeRow);

			reorder();
			registerMediaPicker();
			focusInvalids(newRow);
		});

		var removeRow = function(e) {
			var el = e.target;
			el.removeEventListener('click', removeRow);

			while ((el = el.parentElement) && !el.classList.contains('mpcf-repeater-row'));
			el.parentElement.removeChild(el);
			reorder();
		}

		var reorder = function() {
			var rows = rowsWrapper.querySelectorAll('.mpcf-repeater-row');

			[].forEach.call(rows, function(row, rowIndex) {
				var inputs = row.querySelectorAll('[name], [id], [for]'),
					fieldIndex = 0;

				[].forEach.call(inputs, function(input) {
					let type = input.getAttribute('type');
					if (type === 'button' || type === 'submit') return;

					let newID   = baseName + '-' + rowIndex + '-'  + fieldsObj[fieldIndex]['name'];

					if (input.hasAttribute('id'))	input.setAttribute('id', newID);
					if (input.hasAttribute('for'))	input.setAttribute('for', newID);

					if (input.hasAttribute('name'))	{
						let newName = baseName + '[' + rowIndex + '][' + fieldsObj[fieldIndex]['name'] +  ']';

						input.setAttribute('name', newName);
						fieldIndex++;
					}
				});
			});
		}
	});
}



/**************************************************************
	Handle files
 **************************************************************/

$(document).ready(function() {
	var fields = document.querySelectorAll('.mpcf-file-input');

	[].forEach.call(fields, function(field) {
		var input = field.querySelector('.mpcf-file-picker'),
			id = field.querySelector('.mpcf-file-id'),
			label = field.querySelector('.mpcf-field label'),
			content = label.innerHTML;

		input.addEventListener('change', function(e) {
			var file = '',
				value = '';

			if (this.files && this.files.length > 1) {
				file = this.files.length + ' ' + localizedmpcf.filesSelected;
			} else {
				file = e.target.value.split('\\').pop();
			}

			label.innerHTML = file ? file : localizedmpcf.fileUpload;
			id.value = file;
		});

		input.addEventListener('focus', function() { input.classList.add('focus'); });
		input.addEventListener('blur', function() { input.classList.remove('focus'); });
	})

	var btn = $('.mpcf-remove-file');
	btn.each(function() {
		$(this).click(function(e) {
			$(this).parent().find('.mpcf-file-id').val(-1);
			$(this).parent().find('.mpcf-file-picker').val('');
			$(this).parent().find('label').text(localizedmpcf.fileUpload);
			$(this).hide();
		});
	});
});



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
	var change = $('.mpcf-mediapicker .mpcf-imagepreview, .mpcf-mediapicker .mpcf-videopreview, .mpcf-mediapicker .mpcf-changemedia'),
		clear = $('.mpcf-clearmedia');

	change.unbind('click');
	clear.unbind('click');

	change.click(function(e) { changeMedia(this, e); });
	clear.click(function() { clearimg(this); });
}

function changeMedia(elem, e) {	
	e.preventDefault();
	var image = wp.media({ 
		title: localizedmpcf.chooseMedia,
		multiple: false
	}).open()
	.on('select', function(e){
		var choice = image.state().get('selection').first().toJSON(),
			parent = $(elem).parents('.mpcf-mediapicker');

		parent.find('.mpcf-media-id').val(choice.id);
		parent.find('.mpcf-changemedia').val(localizedmpcf.changeMedia);
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
	var parent = $(elem).parents('.mpcf-mediapicker');
	parent.find('.mpcf-media-id').val('');
	parent.find('.mpcf-imagepreview').attr('src', '').removeClass('hidden');
	parent.find('.mpcf-videopreview').attr('src', '').addClass('hidden');
	parent.find('.mpcf-changemedia').val(localizedmpcf.addMedia);
	parent.find('.mpcf-clearmedia').addClass('hidden');
}

