/**************************************************************
	MP Custom Fields - admin interface

	The server renders the complete interface (all panels, rows
	and fields). This script makes it interactive:

	1. init() runs on DOMContentLoaded and calls build(), which
	   registers all components: panel tabs, repeaters, conditional
	   fields and panels, modules, media pickers, editors etc.
	   Components register the components nested inside them via
	   registerAsyncElements().
	2. Fields inside repeaters, conditional fields/panels and modules
	   get their names from their position (see "Field names and
	   IDs"). Renaming is collected while building and done once
	   at the end (see batchRenames).
	3. Labels are connected to their fields (connectLabels).

	Content loaded later via AJAX (new repeater rows, conditional
	fields and panels, modules) goes through the same steps.

	The global object `mpcf` gives access to the components, e.g.
	mpcf.mediaPickers.registerCallback(fn) to hook into the media
	picker. Its properties must stay stable for third-party code.
 **************************************************************/

var mpcf;

(function() {
	$ = jQuery;		// note: also makes $ available globally, kept for code relying on it

	var loadingElements   = [],
		panelSwitchers    = null,
		conditionalFields = null,
		conditionalPanels = null,
		moduleBoxes       = null,
		mediaPickers      = null,
		bulkMediaPickers  = null,
		colorSelects      = null,
		renameQueue       = null,		// parents waiting to be renamed while batching (see batchRenames)
		qtxQueue          = null,		// parents waiting for qTranslate-X hooks while batching
		painterColors = {
			icons: {
				base:    '#666666',
				current: '#FFFFFF',
				focus:   '#007CBA'
			}
		};

	var vpWidth = function() { return Math.max(document.documentElement.clientWidth, window.innerWidth || 0); }



	/**************************************************************
		Error reporting

		Errors are reported in the browser console, prefixed with
		[MPCF]. Errors that may lead to incomplete data block saving
		(see "Saving only complete forms").
	 **************************************************************/

	var reportError = function(message, details) {
		if (details !== undefined)	console.error('[MPCF] ' + message, details);
		else						console.error('[MPCF] ' + message);
	}

	var reportRequestError = function(message, xhr) {
		let status = xhr && xhr.status ? ' (' + xhr.status + (xhr.responseText ? ': ' + String(xhr.responseText).slice(0, 200) : '') + ')' : '';
		reportError(message + status);
	}

	//	JSON.parse() that reports unreadable data instead of stopping everything
	var parseJSON = function(text, fallback, what, elem) {
		try {
			return JSON.parse(text);
		} catch (error) {
			reportError('Could not read ' + what + '.', elem || error);
			return fallback;
		}
	}

	//	Runs fn() and reports errors. Content inserted after an error may be incomplete,
	//	so saving is blocked until the page is reloaded.
	var runSafely = function(message, fn) {
		try {
			return fn();
		} catch (error) {
			interfaceBroken = true;
			updateSaveState();
			reportError(message + ' Saving the custom fields is blocked to prevent data loss - please reload the page.', error);
		}
	}



	/**************************************************************
		Saving only complete forms

		Fields missing from a submitted form are saved as empty, i.e.
		their values are deleted. So a form must not be sent while the
		interface is being built or while fields are loading:

		- every box contains a hidden input .mpcf-interface-ready,
		  which is only set to 1 while saving is safe - the server
		  does not save the custom fields otherwise (see
		  inc/mpcf-submission.php)
		- submitting such a form is prevented in the meantime
		- in the block editor, saving is locked
	 **************************************************************/

	var interfaceReady  = false,		// interface completely built
		interfaceBroken = false,		// an error occurred, content may be incomplete
		pendingRequests = 0;			// fields currently loading

	var canBeSaved = function() {
		return interfaceReady && !interfaceBroken && pendingRequests === 0;
	}

	var updateSaveState = function() {
		let safe = canBeSaved();

		[].forEach.call(document.querySelectorAll('.mpcf-interface-ready'), input => input.value = safe ? '1' : '0');

	//	block editor: lock / unlock the save button
		if (document.body && document.body.classList.contains('block-editor-page') && window.wp && wp.data && wp.data.dispatch) {
			try {
				let editor = wp.data.dispatch('core/editor');
				if (editor && safe)  editor.unlockPostSaving('mpcf');
				if (editor && !safe) editor.lockPostSaving('mpcf');
			} catch (error) {
				reportError('Could not lock saving in the block editor.', error);
			}
		}
	}

	//	counts a request as pending until it is finished
	var trackRequest = function(request) {
		pendingRequests++;
		updateSaveState();

		request.always(() => {
			pendingRequests--;
			updateSaveState();
		});

		return request;
	}

	//	active immediately, i.e. also while the page is still loading
	document.addEventListener('submit', function(e) {
		let form = e.target;
		if (!form || !form.querySelector || !form.querySelector('.mpcf-interface-ready') || canBeSaved()) return;

		e.preventDefault();
		e.stopImmediatePropagation();

		let strings = window.localizedmpcf || {};
		window.alert(interfaceBroken
			? (strings.interfaceBroken || 'The custom fields could not be loaded completely. Please reload the page before saving, otherwise data may be lost.')
			: (strings.waitForFields   || 'Please wait until all fields have been loaded, then save again.'));
	}, true);



	/**************************************************************
		AJAX requests

		All requests of this plugin carry a nonce, which the server
		checks (see inc/mpcf-ajax.php). It is refreshed via the
		WordPress heartbeat, so it does not expire on screens that
		stay open for a long time.
	 **************************************************************/

	var withNonce = function(data) {
		data.nonce = window.localizedmpcf && localizedmpcf.nonce ? localizedmpcf.nonce : '';
		return data;
	}

	$(document).on('heartbeat-send', function(e, data) {
		data.mpcf_refresh_nonce = 1;
	});

	$(document).on('heartbeat-tick', function(e, data) {
		if (data && data.mpcf_nonce && window.localizedmpcf)
			localizedmpcf.nonce = data.mpcf_nonce;
	});



	var init = function() {
		if (!wp || !('svgPainter' in wp))
			return setTimeout(init, 30);

		try {
			batchRenames(build);

		//	also fields outside of repeaters etc., which are never renamed
			connectLabels(document);
		} catch (error) {
			interfaceBroken = true;
			updateSaveState();
			reportError('The interface could not be built completely. Saving the custom fields is blocked to prevent data loss - please reload the page.', error);
			return;
		}

		mpcf = {
			loadingElements, panelSwitchers, conditionalFields, conditionalPanels, moduleBoxes, mediaPickers, bulkMediaPickers, colorSelects, painterColors
		};

		interfaceReady = true;
		updateSaveState();
	};

	//	registers all components of the interface, see the overview at the top
	var build = function() {
		panelSwitchers    = new PanelSwitchers();
		conditionalFields = new ConditionalFields();
		conditionalPanels = new ConditionalPanelsFields();
		moduleBoxes       = new ModuleBoxes();
		mediaPickers      = new MediaPickers();
		bulkMediaPickers  = new BulkMediaPickers();
		colorSelects      = new ColorSelects();

		goToInvalids();
		checkHTML5Support();
		registerColorPicker();
		registerEditors();			// editors are initialised when they become visible
		repeaterField();

		gridField();
		addQTranslateX();
		dragDropLists();
		paintImageButtonGroup();
		reorderSelectFields();

		conditionalFields.registerNew();
		conditionalPanels.registerNew();
		moduleBoxes.registerNew();
	};



	/**************************************************************
		Register asynchronously loaded elements

		Registers all components inside a parent element: used for
		content loaded via AJAX and for content nested inside other
		components (e.g. the rows of a repeater). Components that are
		already registered are skipped.
	 **************************************************************/

	var registerAsyncElements = function(parent) {
		mediaPickers.registerNew(parent);
		bulkMediaPickers.registerNew(parent);
		addQTranslateX(parent);
		registerEditors(parent);
		registerColorPicker(parent);
		conditionalFields.registerNew(parent);
		conditionalPanels.registerNew(parent);
		moduleBoxes.registerNew(parent);
		colorSelects.registerNew(parent);
		gridField(parent);
		repeaterField(parent);

		checkHTML5Support(parent);
		focusInvalids(parent);
		paintImageButtonGroup(parent);
		reorderSelectFields(parent);
	}

	


	/**************************************************************
		Panel switch

		The tabs (panels) of a meta box and their menu. One "set"
		per meta box, identified by the ID of the meta box or form.
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
					element:   newSet,
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
		//	one listener is enough: resizePanelMenus() always handles all sets
			if (this.resizeListener) return;

			this.resizeListener = this.resizePanelMenus.bind(this);
			window.addEventListener('resize', this.resizeListener);
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
			this.sets[id].menuItems = this.sets[id].menuItems.filter(otherItem => otherItem !== menuItem);

			if (panel.classList.contains('active-panel'))
				this.activatePanel(id, this.sets[id].panels[0].dataset.index);
			
			panel.parentElement.removeChild(panel);
			menuItem.parentElement.removeChild(menuItem);
		}

		//	Measures the width of every menu title (in bold, as for the active item) and sets
		//	min-width: on small screens per title, otherwise the widest title for all of a set.
		resizePanelMenus() {
			let isSmallScreen = vpWidth() < this.threshold;

			if (isSmallScreen)
				return this.resizePanelMenusSequentially();

		//	Large screens: the menu is a vertical list, so the width of one title does not depend
		//	on the others. Done in three phases - change all, measure all, restore all - so the
		//	browser only calculates the layout once instead of once per menu item.
			let sets = [];

		//	1. prepare all titles for measuring
			for (let id in this.sets) {
				let titles = this.sets[id].menuItems
							.map(item => item.querySelector('.mpcf-panel-title'))
							.filter(title => title !== null);

				titles.forEach(title => {
					title.style.minWidth   = '';
					title.style.position   = 'fixed';
					title.style.fontWeight = 'bold';
				});

				sets.push(titles);
			}

		//	2. measure
			let widths = sets.map(titles => titles.map(title => Math.ceil(title.clientWidth)));

		//	3. restore and apply the width of the widest title to all titles of a set
			sets.forEach((titles, i) => {
				let setWidth = Math.min(widths[i].reduce((max, width) => Math.max(max, width), 0), this.maxWidth);

				titles.forEach(title => {
					title.style.position   = '';
					title.style.fontWeight = '';
					title.style.minWidth   = setWidth + 'px';
				});
			});
		}

		//	Small screens: the menu items wrap in rows, so the width available to a title depends on
		//	the widths already set on the titles before it - they have to be measured one after another.
		resizePanelMenusSequentially() {
			for (let id in this.sets) {
				this.sets[id].menuItems.forEach((item => {
					let title = item.querySelector('.mpcf-panel-title');
					if (title === null) return;

					title.style.minWidth   = '';
					title.style.position   = 'fixed';
					title.style.fontWeight = 'bold';

					let minWidth = Math.ceil(title.clientWidth);
					title.style.position   = '';
					title.style.fontWeight = '';
					title.style.minWidth   = Math.min(minWidth, this.maxWidth) + 'px';
				}).bind(this));
			}
		}

		reorder(set, newOrder) {
			if (!Array.isArray(newOrder)) return;

			let currentSetKey = Object.keys(this.sets).find((s => this.sets[s].element == set).bind(this));
			if (typeof currentSetKey == 'undefined') return;

			let currentSet = this.sets[currentSetKey],
				menu       = currentSet.element.querySelector('.mpcf-panels-menu');

			newOrder.forEach(elem => menu.appendChild(elem));
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

	//	when the browser finds an invalid field on submit, switch to the panel containing it
	var focusInvalids = function(elem) {
		[].forEach.call(elem.querySelectorAll('input, select, textarea'), function(input) {
			if (input.dataset.mpcfInvalidHandler) return;		// already registered
			input.dataset.mpcfInvalidHandler = 1;

			input.addEventListener('invalid', function(e) {
				var set    = input.closest('.mpcf-panels, .mpcf-modules'),
					menu   = set ? set.querySelector('.mpcf-panels-menu') : null,
					active = set ? set.querySelector('.mpcf-panels-tabs .active-panel') : null,
					parent = e.target.closest('.mpcf-panel');

			//	not inside a panel (e.g. in a module box): nothing to switch
				if (!menu || !active || !parent) return;

				let activeItem = menu.querySelector('li[data-index="' + active.dataset.index + '"]'),
					targetItem = menu.querySelector('li[data-index="' + parent.dataset.index + '"]');

				active.classList.remove('active-panel');
				if (activeItem) activeItem.classList.remove('active');

				parent.classList.add('active-panel');
				if (targetItem) targetItem.classList.add('active');
			});
		});
	}



	/**************************************************************
		Editors (TinyMCE)

		Editors are output as plain textareas and only initialised
		via wp.editor once they become visible, e.g. when their panel
		is opened - initialising all editors of a page is slow. Until
		then the textarea holds the content, so saving works the same
		(an editor that is never opened saves its content unchanged).

		TinyMCE does not survive being moved within the page (its
		iframe reloads), so editors are removed before a repeater row
		or module is moved and initialised again afterwards
		(see detachEditors).
	 **************************************************************/

	var editorObserver = null;

	var registerEditors = function(parent) {
		parent = parent || document;

		var fields = [].slice.call(parent.querySelectorAll('.mpcf-editor-input .mpcf-field'));

		if (fields.length && !(window.wp && wp.editor && wp.editor.initialize)) {
			reportError('The WordPress editor scripts are not loaded - editor fields cannot be initialised.');
			return;
		}

		fields.forEach(function(field) {
			let inner = field.querySelector('.mpcf-editor-inner');
			if (!inner || inner.dataset.isRegistered == '1' || inner.dataset.isObserved == '1') return;

			observeEditor(inner);
		});
	}


	//	initialise the editor as soon as it is (nearly) visible
	var observeEditor = function(inner) {
		if (!('IntersectionObserver' in window)) {
			runSafely('An editor could not be initialised.', () => initEditor(inner.closest('.mpcf-field')));
			return;
		}

		if (!editorObserver) {
			editorObserver = new IntersectionObserver(function(entries) {
				entries.forEach(function(entry) {
					if (!entry.isIntersecting) return;

					editorObserver.unobserve(entry.target);
					entry.target.removeAttribute('data-is-observed');
					runSafely('An editor could not be initialised.', () => initEditor(entry.target.closest('.mpcf-field')));
				});
			}, { rootMargin: '300px 0px' });
		}

		inner.dataset.isObserved = 1;
		editorObserver.observe(inner);
	}


	//	Replaces the textarea with a new one (new ID) and initialises the editor on it.
	var initEditor = function(field) {
		if (!field || !field.isConnected) return;

		var inner       = field.querySelector('.mpcf-editor-inner'),
			oldTextarea = field.querySelector('.mpcf-input-editor');
		if (!inner || !oldTextarea || inner.dataset.isRegistered == '1') return;

	//	mark as registered (on inner, where it is checked, and on field as before)
		field.setAttribute('data-is-registered', 1);
		inner.setAttribute('data-is-registered', 1);

		var	description = field.querySelector('.mpcf-description'),
			oldID       = oldTextarea.id,
			newID       = randomString(20),
			newTextarea = oldTextarea.cloneNode(),
			settings    = parseJSON(inner.dataset.settings, {}, 'the settings of an editor', field),
			content     = wp.editor.getContent(oldID) || '',
			wrapper     = field.querySelector('#wp-' + oldID + '-wrap'),
			style       = inner.querySelector('style');

	//	editors with automatic paragraphs expect content with paragraphs
		newTextarea.textContent = settings.wpautop === false ? content : wp.editor.autop(content);
		newTextarea.removeAttribute('style');

		if (wrapper)	wrapper.remove();		// markup of wp_editor()
		else			oldTextarea.remove();	// after detachEditors()

		newTextarea.setAttribute('id', newID);
		inner.appendChild(newTextarea);
		if (description)
			field.appendChild(description);

	//	keep the minimum height defined for the old ID
		if (style)
			style.textContent = style.textContent.split(oldID).join(newID);

		wp.editor.initialize(newID, settings);
	}


	//	Before an element containing editors is moved: save their content to the textareas and remove
	//	the editors. Call registerEditors() on the element afterwards to initialise them again.
	var detachEditors = function(container) {
		[].forEach.call(container.querySelectorAll('.mpcf-editor-inner[data-is-registered="1"]'), function(inner) {
			let textarea = inner.querySelector('textarea.wp-editor-area');

			if (textarea && window.wp && wp.editor && wp.editor.remove)
				wp.editor.remove(textarea.id);

			inner.setAttribute('data-is-registered', 0);
			let field = inner.closest('.mpcf-field');
			if (field) field.setAttribute('data-is-registered', 0);
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

		A list of rows with the same sub-fields. Rows can be added
		(the blank row is fetched from the server via AJAX when the
		user approaches the "add" button), moved and removed; after
		each change the fields of the rows are renamed.
	 **************************************************************/

	var repeaterField = function(parent = null) {
		parent = parent !== null ? parent : document;

		var repeaters = parent.querySelectorAll('.mpcf-repeater-input');
		if (!repeaters.length) return;
				
		[].forEach.call(repeaters, function(repeater) {
			if (repeater.dataset.registered && repeater.dataset.registered == 1) return;

			var set             = repeater.closest('.mpcf-panels, .mpcf-modules'),
				uniqid          = repeater.dataset.uniqid,
				rowsWrapper     = repeater.querySelector('.mpcf-repeater-wrapper[data-uniqid="'  + uniqid + '"]'),
				loader          = repeater.querySelector('.mpcf-loading-container[data-uniqid="' + uniqid + '"]'),
				addBtn          = repeater.querySelector('.mpcf-repeater-add-row[data-uniqid="'  + uniqid + '"]'),
				emtpyField      = repeater.querySelector('.mpcf-repeater-empty[data-uniqid="'    + uniqid + '"]'),
				fields          = rowsWrapper.dataset.fields,
				fieldsObj       = JSON.parse(fields),
				maxRows         = parseInt(rowsWrapper.dataset.maxrows),
				rowHTML         = null;

			repeater.dataset.registered = 1;

		//	rows and buttons that belong to this repeater, not to repeaters nested inside its rows
			var ownRows = function() {
				return [].filter.call(rowsWrapper.children, child => child.classList.contains('mpcf-repeater-row'));
			};

		//	the row of this repeater an element belongs to (null for elements of nested repeaters)
			var ownRow = function(elem) {
				let row = elem.closest('.mpcf-repeater-row');
				return row !== null && row.parentElement === rowsWrapper ? row : null;
			};

		// 	prefetch blank row - lazily, as soon as the user approaches the "add" button,
		//	instead of firing one request per repeater on page load

			var rowRequest = null;

			var prefetchRow = function() {
				if (rowHTML !== null || rowRequest !== null) return rowRequest;

				rowRequest = $.post(ajaxurl, withNonce({ 'action': 'mpcf_get_repeater_row', 'fields': fields }), function(response) {
					rowHTML = response.replaceAll('data-is-registered="1"', 'data-is-registered="0"');
				});

				rowRequest.fail(function(xhr) {
					rowRequest = null;
					reportRequestError('A new row could not be loaded.', xhr);
				});
				return rowRequest;
			};

			addBtn.addEventListener('pointerenter', prefetchRow);
			addBtn.addEventListener('focus',        prefetchRow);

		//	Moves a row in front of another row (null: to the end). Editors in the row are
		//	removed before and initialised again after the move (TinyMCE does not survive it).
			var moveRow = function(row, before) {
				if (before === row || row.nextElementSibling === before) return false;

				detachEditors(row);
				rowsWrapper.insertBefore(row, before);
				afterRowsMoved(row);
				return true;
			};

			var afterRowsMoved = function(row) {
				renameDynamicFields(set);
				updateConditionalPanelsOrder();
				registerEditors(row);
			};

			var moveRowUp = function(row) {
				if (row.previousElementSibling) moveRow(row, row.previousElementSibling);
			};

			var moveRowDown = function(row) {
				if (row.nextElementSibling) moveRow(row, row.nextElementSibling.nextElementSibling);
			};

		//	remove Row
			var removeRow = function(row) {
				removeQTranslateX(row);

				// find and also remove attached panels, if there are conditional panels
				var panelSelects = [].slice.call(row.querySelectorAll('.mpcf-conditionalpanels-input'));
				panelSelects.forEach(function(select) {
					var panelId  = select.dataset.panelId,
						set      = select.closest('.mpcf-panels, .mpcf-modules'),
						panel    = set.querySelector('.mpcf-panel[data-panel-id="' + panelId + '"]'),
						menuItem = set.querySelector('li.mpcf-panel-item[data-panel-id="' + panelId + '"]');

					panelSwitchers.removePanel(select.closest('.postbox').id, panel, menuItem);
				});

				row.parentElement.removeChild(row);

				addBtn.classList.toggle('hide', maxRows !== 0 && maxRows <= rowsWrapper.children.length);
				checkIfEmpty();
				renameDynamicFields(set);
				updateConditionalPanelsOrder();
			};


		//	Drag and drop with the move handle (mouse, pen and touch). The row itself is moved
		//	while dragging, it is placed before the first row whose middle is below the pointer.
			var dragRow = function(handle, e) {
				let row = ownRow(handle);
				if (!row || e.button !== 0) return;

				e.preventDefault();

				let startOrder = ownRows(),
					pointerY   = e.clientY,
					dragging   = true;

				detachEditors(row);
				row.classList.add('mpcf-dragging');
				rowsWrapper.classList.add('mpcf-sorting');

			//	listening on the document: moving the row within the page would end a pointer capture on the handle
				let onMove = function(ev) {
					if (ev.pointerId === e.pointerId) pointerY = ev.clientY;
				};

				let frame = function() {
					if (!dragging) return;

				//	scroll the page near the top or bottom edge of the window
					let edge = 60;
					if (pointerY < edge)							window.scrollBy(0, -Math.ceil((edge - pointerY) / 4));
					else if (pointerY > window.innerHeight - edge)	window.scrollBy(0, Math.ceil((pointerY - window.innerHeight + edge) / 4));

					let before = ownRows().find(other => {
						if (other === row) return false;
						let rect = other.getBoundingClientRect();
						return pointerY < rect.top + rect.height / 2;
					}) || null;

					if (before !== row.nextElementSibling)
						rowsWrapper.insertBefore(row, before);

					requestAnimationFrame(frame);
				};

				let onEnd = function(ev) {
					if (ev.pointerId !== e.pointerId) return;

					dragging = false;
					document.removeEventListener('pointermove',   onMove);
					document.removeEventListener('pointerup',     onEnd);
					document.removeEventListener('pointercancel', onEnd);

					row.classList.remove('mpcf-dragging');
					rowsWrapper.classList.remove('mpcf-sorting');

					let moved = ownRows().some((other, i) => other !== startOrder[i]);
					if (moved)	afterRowsMoved(row);
					else		registerEditors(row);
				};

				document.addEventListener('pointermove',   onMove);
				document.addEventListener('pointerup',     onEnd);
				document.addEventListener('pointercancel', onEnd);
				requestAnimationFrame(frame);
			};


		//	The controls of the rows: one listener for all rows of this repeater, ignoring
		//	the controls of nested repeaters (they have their own listeners).
			var controlSelector = '.mpcf-repeater-row-move-up, .mpcf-repeater-row-move-down, .mpcf-repeater-row-remove, .mpcf-repeater-row-move';

			var ownControl = function(target) {
				let control = target.closest ? target.closest(controlSelector) : null;
				if (!control || !control.parentElement.closest('.mpcf-repeater-row-controls')) return null;

				let row = ownRow(control);
				return row && control.closest('.mpcf-repeater-row') === row ? { control, row } : null;
			};

			rowsWrapper.addEventListener('click', function(e) {
				let found = ownControl(e.target);
				if (!found) return;

				if      (found.control.matches('.mpcf-repeater-row-move-up'))	moveRowUp(found.row);
				else if (found.control.matches('.mpcf-repeater-row-move-down'))	moveRowDown(found.row);
				else if (found.control.matches('.mpcf-repeater-row-remove'))	removeRow(found.row);
			});

			rowsWrapper.addEventListener('pointerdown', function(e) {
				let found = ownControl(e.target);
				if (found && found.control.matches('.mpcf-repeater-row-move'))
					dragRow(found.control, e);
			});

		//	keyboard: Enter / Space activate a control, the arrow keys move a row via its move handle
			rowsWrapper.addEventListener('keydown', function(e) {
				let found = ownControl(e.target);
				if (!found) return;

				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					found.control.click();
					return;
				}

				if (!found.control.matches('.mpcf-repeater-row-move') || (e.key !== 'ArrowUp' && e.key !== 'ArrowDown')) return;

				e.preventDefault();
				if (e.key === 'ArrowUp')	moveRowUp(found.row);
				else						moveRowDown(found.row);

				found.control.focus();		// moving the row removes the focus
			});


		//	if there are no rows, "activate" the empty field so there is an empty value posted on submit
			var checkIfEmpty = function() {
				rowsWrapper.classList.toggle('empty', rowsWrapper.children.length == 0);
				if (!emtpyField) return;

				if (rowsWrapper.children.length == 0) {
					emtpyField.setAttribute('name', emtpyField.dataset.name);
				} else {
					emtpyField.removeAttribute('name');
				}
			};

		//	if there are conditional panels, update the order of the corresponding tabs
			var updateConditionalPanelsOrder = function() {
				let panelSelects = [].slice.call(repeater.querySelectorAll('.mpcf-conditionalpanels-input'));
				if (panelSelects.length == 0) return;

				let menuItemsAll      = [].slice.call(set.querySelectorAll('li.mpcf-panel-item')),
					ids               = panelSelects.map(panel => panel.dataset.panelId),
					menuItemsNewOrder = panelSelects.map(panel => set.querySelector('li.mpcf-panel-item[data-panel-id="' + panel.dataset.panelId + '"]'));

				menuItemsAll.forEach((item, i) => {
					if (item.dataset && item.dataset.panelId === 'undefined') return;
					if (ids.includes(item.dataset.panelId)) return;
					
					menuItemsNewOrder.splice(i, 0, item);
				});
				
				panelSwitchers.reorder(set, menuItemsNewOrder);
			};


		//	add row
			addBtn.addEventListener('click', function() {
				let populateRow = function() {
					runSafely('A new row could not be inserted.', () => batchRenames(insertRow));
				};

				let insertRow = function() {
					var newRow = document.createElement('li');
					newRow.classList.add('mpcf-repeater-row');
					newRow.innerHTML = rowHTML;
					rowsWrapper.appendChild(newRow);

					let conditionalItems = [].slice.call(newRow.querySelectorAll('.mpcf-conditionalpanels-input'));
					conditionalItems.forEach(item => item.setAttribute('data-panel-id', Math.floor(Math.random() * Math.pow(10,10))));

					addBtn.classList.toggle('hide', maxRows !== 0 && maxRows <= rowsWrapper.children.length);

					checkIfEmpty();
					renameDynamicFields(set);
					registerAsyncElements(newRow);
				};

				if (rowHTML !== null) {
					populateRow();
				} else {
					let request = prefetchRow();
					if (request) request.done(populateRow);
				}
			});


		//	populate repeater
			renameDynamicFields(set);
			checkCheckableElements(rowsWrapper);
			registerAsyncElements(rowsWrapper);
			updateLoadingElements(repeater, true);

			checkIfEmpty();
		});
	}



	/**************************************************************
		Field names and IDs

		Fields inside repeater rows, conditional fields, conditional
		panels and modules get their name attribute from the nesting
		they are in, e.g. the field "title" in the third row of the
		repeater "slides" inside the module "modules[2]" is named

			modules[2][slides][2][title]

		The parts of a name are collected from the field's ancestors:
		every element with a data-basename attribute adds that name,
		every repeater row adds its index. The field's own name is
		stored in data-name the first time it is renamed.

		The resulting names decide how values are stored in the
		database, so the rules below must never change.
	 **************************************************************/

	//	Name parts contributed by all ancestors of an element (outermost first) and whether
	//	one of them is a table field (which allows repeated parts, e.g. table[0][0]).
	var collectAncestorNameParts = function(elem) {
		var parts                = [],
			parent               = elem,
			canContainDeepFields = false;

		while (parent.parentNode) {
			parent = parent.parentNode;
			canContainDeepFields = canContainDeepFields || (parent.classList && parent.classList.contains('mpcf-table-input'));

			if (parent.dataset && parent.dataset.basename)
				parts.unshift(parent.dataset.basename);

			if (parent.classList && parent.classList.contains('mpcf-repeater-row'))
				parts.unshift(getRowIndex(parent));
		}

		return { parts: parts, canContainDeepFields: canContainDeepFields };
	}


	//	position of a repeater row among its sibling rows
	var getRowIndex = function(row) {
		var index = 0,
			child = row;

		while ((child = child.previousSibling) != null) {
			if (child.classList && child.classList.contains('mpcf-repeater-row'))
				index++;
		}

		return index;
	}


	//	Builds the name attribute from the collected parts, e.g.
	//	[ 'modules', 2, 'slides', 2, 'title' ]  ->  modules[2][slides][2][title]
	//	Parts may themselves contain brackets (e.g. a base name "modules[2]"),
	//	those are split up and duplicates of the previous part are removed.
	var composeName = function(name, canContainDeepFields, allowsMultiple) {

	//	split parts with brackets into sub-parts
		name = name.filter(item => typeof item !== 'undefined' && item !== null);
		name = name.map(function(item, i) {
			var regex     = /(\S+?)\[(\S+?)\](\[(\S+?)\])?/g,
				subfields = [...item.toString().matchAll(regex)];

			if (subfields.length == 0) return item;

			subfields = subfields[0].filter(subfield => subfield && subfield.indexOf('[') == -1);
			return subfields;
		});

	//	remove sub-parts that repeat the part at the same position
		name = name.map((item, i) => {
			if (!Array.isArray(item)) return item;

			item = item.filter((subItem, j) => subItem != name[j]);
			return item;
		});

		name = name.flat(9999);

	//	remove remaining brackets and consecutive duplicates (allowed inside tables)
		name = name.map(item => item.toString().replace(/[\[\]]/g, ''));
		name = name.filter((item, index, arr) => index === 0 || item !== arr[index - 1] || canContainDeepFields);

	//	join parts in the format for nested form fields: first[second][third]
		name = name.length == 1 ? name[0] : name[0] + '[' + name.slice(1).join('][') + ']';

	//	fields that allow multiple values keep their trailing []
		return allowsMultiple ? name + '[]' : name;
	}


	var generateName = function(elem, ancestorCache) {
		var ownName        = elem.dataset.name || elem.name,
			allowsMultiple = ownName.slice(-2) == '[]',
			ancestors      = ancestorCache ? ancestorCache.get(elem.parentNode) : collectAncestorNameParts(elem);

		return composeName(ancestors.parts.concat([ ownName ]), ancestors.canContainDeepFields, allowsMultiple);
	}


	var generateID = function(elem, ancestorCache) {
	//	a label directly after an input gets the input's ID
		if (elem.tagName && elem.tagName.toLowerCase() == 'label' &&
			elem.previousElementSibling && elem.previousElementSibling.tagName.toLowerCase() == 'input') {
			return generateID(elem.previousElementSibling, ancestorCache);
		}

		return idFromName(elem, generateName(elem, ancestorCache));
	}


	//	modules[2][title] -> modules-2-title, checkboxes and radio buttons get their value appended
	var idFromName = function(elem, name) {
		var id = name.replace(/\]\[/g, '-').replace('[', '-').replace(']', '');

		if (elem.tagName.toLowerCase() === 'input' && (elem.type === 'radio' || elem.type === 'checkbox'))
			id += '-' + elem.value;
		return id;
	}


	//	Caches the ancestor name parts of every element during one renaming pass, so the
	//	ancestors of the ~10 inputs of a row are only walked once instead of for each input.
	//	Gives exactly the same result as collectAncestorNameParts().
	class AncestorNamePartsCache {
		constructor() {
			this.entries = new Map();
		}

		get(node) {
			if (!node)
				return { parts: [], canContainDeepFields: false };

			let entry = this.entries.get(node);
			if (entry) return entry;

			let parent = this.get(node.parentNode),
				parts  = parent.parts.slice();

		//	same order as in collectAncestorNameParts: row index before the element's base name
			if (node.classList && node.classList.contains('mpcf-repeater-row'))
				parts.push(getRowIndex(node));

			if (node.dataset && node.dataset.basename)
				parts.push(node.dataset.basename);

			entry = {
				parts:                parts,
				canContainDeepFields: parent.canContainDeepFields || !!(node.classList && node.classList.contains('mpcf-table-input'))
			};

			this.entries.set(node, entry);
			return entry;
		}

	//	base names have changed - cached parts are outdated
		clear() {
			this.entries.clear();
		}
	}



	/**************************************************************
		Renaming dynamic fields

		Whenever fields are added, moved or removed, the names of
		all fields in the affected container are recalculated.

		While the interface is being built (see batchRenames), all
		rename requests are collected and carried out once at the
		end, for the outermost containers only. This used to happen
		dozens of times on pages with many dynamic fields.
	 **************************************************************/

	var renameRowSelector   = '.mpcf-repeater-row, .mpcf-conditional-container, .mpcf-conditionalpanel, .mpcf-module',
		renameFieldSelector = '.mpcf-field-option, .mpcf-module-controls',
		renameValidInputs   = [ 'input', 'textarea', 'select', 'fieldset' ],
		renameValidElements = renameValidInputs.concat([ 'button', 'label', 'datalist', 'keygen', 'option' ]);


	var renameDynamicFields = function(parent) {
		if (renameQueue !== null) {
			if (renameQueue.indexOf(parent) === -1) renameQueue.push(parent);
			return;
		}

		flushRenames([parent]);
	}


	//	Runs fn() and collects all rename requests made meanwhile, then renames once.
	var batchRenames = function(fn) {
		if (renameQueue !== null)
			return fn();

		renameQueue = [];
		qtxQueue    = [];

		try {
			return fn();
		} finally {
			let parents = renameQueue,
				qtx     = qtxQueue;

			renameQueue = null;
			qtxQueue    = null;

			flushRenames(parents);
			qtx.forEach(parent => addQTranslateX(parent));
		}
	}


	var flushRenames = function(parents) {
		parents = parents.filter(parent => parent && parent.querySelectorAll && (parent === document || parent.isConnected));

	//	only rename the outermost containers, nested ones are included
		let roots    = parents.filter(parent => !parents.some(other => other !== parent && other.contains(parent))),
			maxPass  = 10,
			pass     = 0,
			repeat   = true;

	//	usually one pass is enough, see renameDynamicFieldsPass()
		while (repeat && pass < maxPass) {
			repeat = false;
			roots.forEach(root => { if (renameDynamicFieldsPass(root)) repeat = true; });
			pass++;
		}

		if (repeat)
			reportError('Field names did not stabilise after ' + maxPass + ' passes.');

		parents.forEach(parent => { if (parent.classList) parent.classList.toggle('empty', parent.childElementCount === 0); });
		roots.forEach(root => connectLabels(root));
	}


	//	Stores an input's own (last) name segment in data-name, e.g. "title" for "modules[2][title]".
	//	The same rules have always been used, they must not change.
	var initOwnName = function(input) {
		if (input.dataset.name) return;

		if (input.closest('.mpcf-table-inner')) {
			input.dataset.name = input.dataset.ownName;
			return;
		}

		let name  = input.name || input.dataset.ownName || '',
			split = name.split('][').slice(-1)[0];

		if ((split.match(/\]/g) || []).length > (split.match(/\[/g) || []).length)
			split = split.replace(/\]$/, '');
		input.dataset.name = split;
	}


	//	One renaming pass over a container. Gives the same result as the original implementation
	//	(which renamed the whole container once per registered component) repeated until nothing
	//	changes - verified on a page with ~1000 fields - but:
	//	- every field is processed once (fields in nested rows used to be processed repeatedly)
	//	- the ancestors' name parts are cached (AncestorNamePartsCache)
	//	- a label's input gets its own name stored before the label's ID is calculated
	//	- attributes are only written if their value changes
	//
	//	Returns true if another pass is needed: when a conditional panel's base name changes
	//	after fields inside that panel have already been renamed with the old base name.

	var renameDynamicFieldsPass = function(parent) {
		var rows          = [].slice.call(parent.querySelectorAll(renameRowSelector)),
			ancestorCache = new AncestorNamePartsCache(),
			renamedFields = new Set(),
			needsRepeat   = false;

		var setAttr = function(elem, attr, value) {
			if (elem.getAttribute(attr) !== String(value))
				elem.setAttribute(attr, value);
		};

		var renameInput = function(input, field) {
			let type    = input.getAttribute('type'),
				tagName = input.tagName.toLowerCase(),
				parent  = input.parentElement,
				name    = undefined,
				newID, newName;

		//	the title label and the option labels of radio buttons and button groups
		//	get the ID of the first input of their field / option
			if (!input.dataset.name) {
				let isLabel = input.getAttribute('for') && tagName == 'label'
							  && (parent.classList.contains('mpcf-title') || parent.classList.contains('mpcf-buttongroup-option') || parent.classList.contains('mpcf-radio-option'));

				if (isLabel) {
					let labelFor = parent.closest('.mpcf-field-option, .mpcf-buttongroup-option, .mpcf-radio-option').querySelector(renameValidInputs.join(','));

					if (labelFor && labelFor.matches('[name], [id], [for]'))
						initOwnName(labelFor);

					newID = labelFor ? generateID(labelFor, ancestorCache) : '';
				} else {
					initOwnName(input);
				}
			}

			if (!input.getAttribute('for')) {
				let isLabelAfterInput = tagName == 'label' && input.previousElementSibling && input.previousElementSibling.tagName.toLowerCase() == 'input';

				if (isLabelAfterInput) {
					newID = generateID(input, ancestorCache);
				} else {
					name  = generateName(input, ancestorCache);
					newID = idFromName(input, name);
				}
			}

			if (type === 'button' || type === 'submit') return;

		//	TinyMCE editors keep the ID they were initialised with
			if (input.hasAttribute('id') && (tagName != 'textarea' || !input.classList.contains('wp-editor-area')))
				setAttr(input, 'id', newID);

		//	note: labels not covered above get for="undefined" here, as they always did - connectLabels() repairs them
			if (input.hasAttribute('for'))
				setAttr(input, 'for', newID);

			if (input.hasAttribute('name')) {
				newName = name !== undefined ? name : generateName(input, ancestorCache);
				setAttr(input, 'name', newName);
			}

		//	a conditional panels field passes its name on to the panel it creates
			if (field.classList.contains('mpcf-conditionalpanels-input') && newName !== undefined)
				updateConditionalPanelBaseName(field, newName);
		};

		var updateConditionalPanelBaseName = function(field, fieldName) {
			let set         = field.closest('.mpcf-panels'),
				panel       = set.querySelector('.mpcf-panel[data-panel-id="' + field.dataset.panelId + '"]'),
				newBaseName = fieldName.replace(/(\[type\]$)/g, '');

			if (!panel || panel.dataset.basename === newBaseName) return;

			panel.dataset.basename = newBaseName;
			ancestorCache.clear();

		//	fields of this panel renamed earlier in this pass used the old base name
			let panelFields = [].slice.call(panel.querySelectorAll(renameFieldSelector));
			if (panelFields.some(panelField => renamedFields.has(panelField)))
				needsRepeat = true;
		};

		rows.forEach(function(row) {
			[].forEach.call(row.querySelectorAll(renameFieldSelector), function(field) {
				if (renamedFields.has(field)) return;
				renamedFields.add(field);

				[].filter.call(field.querySelectorAll('[name], [id], [for]'), input => renameValidElements.indexOf(input.tagName.toLowerCase()) > -1)
					.forEach(input => renameInput(input, field));
			});
		});

		return needsRepeat;
	}


	/**************************************************************
		Label connections

		Some labels point to an ID that does not exist (for="undefined",
		the field's name instead of its ID, an editor that got a new ID,
		a hidden input). This connects them to their field - without
		changing any name or ID of a field:

		- labels that already work are left alone
		- a field's title label is connected to the first text input,
		  select or textarea of its own field, never to a checkbox,
		  radio button or button (clicking the title would change a
		  value or open a dialog)
		- other labels are connected to the input right before them
		  (e.g. the label next to a checkbox)
		- if there is no suitable field, the broken "for" is removed
	 **************************************************************/

	var labelTargetSelector = 'select, textarea, input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"])'
							+ ':not([type="button"]):not([type="submit"]):not([type="reset"]):not([type="image"]):not([type="file"])';

	var connectLabels = function(root) {
		root = root || document;

		[].forEach.call(root.querySelectorAll('.mpcf-field-option label[for]'), function(label) {
			let field = label.closest('.mpcf-field-option');

			if (label.closest('.wp-editor-wrap')) return;							// labels of the WordPress editor
			if (label.control && ownField(label.control) === field) return;		// already working

			let target = label.parentElement.classList.contains('mpcf-title')
					   ? findTitleLabelTarget(field)
					   : findOptionLabelTarget(label, field);

			if (target && target.id)
				label.setAttribute('for', target.id);
			else
				label.removeAttribute('for');
		});
	}

	//	the field an element belongs to (not the field of a repeater or conditional field around it)
	var ownField = function(elem) {
		return elem.closest('.mpcf-field-option');
	}

	var findTitleLabelTarget = function(field) {
		return [].find.call(field.querySelectorAll(labelTargetSelector), elem => ownField(elem) === field) || null;
	}

	var findOptionLabelTarget = function(label, field) {
		let previous = label.previousElementSibling;

		if (previous && previous.matches('input:not([type="hidden"]), select, textarea') && ownField(previous) === field)
			return previous;

		return null;
	}



	// bugfix for Chrome
	var checkCheckableElements = function(parent) {
		var fields = [].slice.call(parent.querySelectorAll('input[type="checkbox"], input[type="radio"]'));
		fields.forEach(field => field.checked = field.getAttribute('checked') !== null);
	}



	/**************************************************************
		Options of conditional fields / panels

		Either inline in data-options (as before, e.g. in AJAX
		responses) or, on page load, printed once per page in
		<script type="application/json" id="mpcf-options-{ref}">
		and referenced with data-options-ref. Parsed once per
		page and shared - the options are only read, never changed.
	 **************************************************************/

	var optionsStoreCache = {};

	var readOptions = function(elem, fallback) {
		let ref = elem.dataset.optionsRef;

		if (ref && elem.dataset.options === undefined) {
			if (!(ref in optionsStoreCache)) {
				let store = document.getElementById('mpcf-options-' + ref);
				if (!store) reportError('The options of a field are missing (data-options-ref="' + ref + '").', elem);

				optionsStoreCache[ref] = store ? parseJSON(store.textContent, null, 'the options of a field', elem) : null;
			}

			if (optionsStoreCache[ref] !== null)
				return optionsStoreCache[ref];
		}

		return parseJSON(fallback === undefined ? elem.dataset.options : (elem.dataset.options || fallback), {}, 'the options of a field', elem);
	}



	/**************************************************************
		Conditional fields

		A select (or checkbox) that decides which sub-fields are
		shown. When the choice changes, the sub-fields are fetched
		from the server via AJAX.
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
					options  = readOptions(select, '[]'),
					isSingle = select.tagName.toLowerCase() === 'input';

				element.setAttribute('data-registered', 1);
				element.setAttribute('data-id', id);

				select.removeAttribute('data-options');
				select.removeAttribute('data-options-ref');
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

			let request = withNonce({
				'action': 'mpcf_get_conditional_fields',
				'fields': JSON.stringify(field.options[field.select.value].fields)
			});

			updateLoadingElements(field.element);
			field.loader.classList.add('mpcf-loading-active');

			trackRequest($.post(ajaxurl, request, response => {
				runSafely('The fields of this option could not be inserted.', () => batchRenames(() => {
					removeQTranslateX(field.wrapper);
					field.wrapper.innerHTML = response;
					renameDynamicFields(field.parent);

					field.loader.classList.remove('mpcf-loading-active');
					updateLoadingElements(field.element, true);

					checkCheckableElements(field.wrapper);
					registerAsyncElements(field.wrapper);
				}));
			}).fail(xhr => {
			//	request failed: stop the loading state, so the post can still be saved
				field.loader.classList.remove('mpcf-loading-active');
				updateLoadingElements(field.element, true);
				reportRequestError('The fields of this option could not be loaded.', xhr);
			}));

		}

		generateID() {
			return Math.floor(Math.random() * Math.pow(10,10));
		}
	}



	/**************************************************************
		Conditional panel fields

		Like conditional fields, but the sub-fields are shown in an
		additional panel (tab) of the meta box. Often used inside a
		repeater to build pages from "modules".
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
					set      = field.closest('.mpcf-panels, .mpcf-modules'),
					menu     = set.querySelector('.mpcf-panels-menu'),
					menuItem = menu.querySelector('.mpcf-panel-item[data-panel-id="' + id + '"]'),
					tabs     = set.querySelector('.mpcf-panels-tabs'),
					tab      = tabs.querySelector('.mpcf-panel[data-panel-id="' + id + '"]'),
					select   = field.querySelector('.mpcf-conditional-choice select, .mpcf-conditional-choice input[type="checkbox"]'),
					options  = readOptions(select),
					values   = parseJSON(select.dataset.values, {}, 'the values of a conditional panels field', field);				

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
				select.removeAttribute('data-options-ref');
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

			let request = withNonce({
				'action': 'mpcf_get_conditional_panels_fields',
				'panel':  JSON.stringify(field.options[field.select.value].panel),
				'values': field.values
			});

			trackRequest($.post(ajaxurl, request, (response => {
				runSafely('The panel of this option could not be inserted.', () => batchRenames(() => {
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
				}));
			}).bind(this)).fail(xhr => {
			//	request failed: stop the loading state, so the post can still be saved
				updateLoadingElements(field.element, true);
				reportRequestError('The panel of this option could not be loaded.', xhr);
			}));
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
		Module boxes

		Meta boxes registered with mpcf_add_module_metabox(): a list
		of collapsible modules of different types that can be added,
		moved and removed.
	 **************************************************************/

	class ModuleBoxes {
		constructor() {
			this.boxes      = {};
			this.fieldClass = 'mpcf-modules';
		}

		registerNew(parent = null) {
			parent = parent || document;

			let newBoxes = [].slice.call(parent.querySelectorAll('.' + this.fieldClass + ':not([data-registered])'));
			if (newBoxes.length == 0) return;

			newBoxes = newBoxes.map((box => {
				let id          = box.dataset.moduleId || this.generateID(),
					modulesList = box.querySelector('.mpcf-modules-inner'),
					modules     = [].slice.call(modulesList.querySelectorAll('.mpcf-module')),
					select      = box.querySelector('.mpcf-modules-controls-inner select'),
					addBtn      = box.querySelector('.mpcf-modules-add-module'),
					activeInput = box.querySelector('.activetab'),
					options     = parseJSON(select.dataset.modules, {}, 'the module types of a module box', box);		

				box.dataset.registered = 1;
				box.setAttribute('data-module-id', id);
				select.removeAttribute('data-modules');

				modules.forEach(mod => this.registerModuleEvents(id, mod));
				addBtn.addEventListener('click', (() => this.addModule(id)).bind(this));

				renameDynamicFields(box);
				checkCheckableElements(box);
				registerAsyncElements(box);

				return {
					box:			box,
					id:				id,
					modulesList:	modulesList,
					modules:		modules,
					select:			select,
					baseName:		select.dataset.basename,
					options:		options,
					activeInput:	activeInput
				};
			}).bind(this));

			newBoxes.forEach((newBox => this.boxes[newBox.id] = newBox).bind(this));
			newBoxes.forEach((newBox => this.activateModule(newBox.id)).bind(this));
		}


		addModule(id) {
			let box = this.boxes[id];
			if (box.select.value == '' || box.select.value == -1) return;

			updateLoadingElements(box.box);

			let type = box.select.value,
				request = withNonce({
					'action':	'mpcf_get_modules_fields',
					'module':	JSON.stringify(box.options[type]),
					'type':		type,
					'i': 		box.modules.length,
					'baseName':	box.baseName
				});

			trackRequest($.post(ajaxurl, request, (response => {
				runSafely('The new module could not be inserted.', () => batchRenames(() => {
					let newModule = document.createElement('section');
					box.modulesList.appendChild(newModule);
					newModule.outerHTML = response;

					newModule = box.modulesList.children[box.modulesList.children.length - 1];

					this.boxes[id].modules = [].slice.call(box.modulesList.querySelectorAll('.mpcf-module'));
					this.activateModule(id, newModule);
					this.registerModuleEvents(id, newModule);

					this.reorderModules(id);
					updateLoadingElements(box.box, true);
					checkCheckableElements(box.box);
					registerAsyncElements(box.box);
				}));
			}).bind(this)).fail(xhr => {
			//	request failed: stop the loading state, so the post can still be saved
				updateLoadingElements(box.box, true);
				reportRequestError('The new module could not be loaded.', xhr);
			}));
		}


		registerModuleEvents(id, mod) {
			let header    = mod.querySelector('.mpcf-module-header'),
				btnRemove = mod.querySelector('.mpcf-btn-remove-module'),
				btnUp     = mod.querySelector('.mpcf-btn-move-module-up'),
				btnDown   = mod.querySelector('.mpcf-btn-move-module-down');

			header.addEventListener('click',    ((e) => this.toggleModule(id, e)).bind(this));
			btnRemove.addEventListener('click', ((e) => this.removeModule(id, e)).bind(this));
			btnUp.addEventListener('click',     ((e) => this.moveModuleUp(id, e)).bind(this));
			btnDown.addEventListener('click',   ((e) => this.moveModuleDown(id, e)).bind(this));
		}


		toggleModule(id, e) {
			let mod        = e.target.classList.contains('mpcf-module') ? e.target : e.target.closest('.mpcf-module'),
				toActivate = !mod.classList.contains('active'),
				inner      = mod.querySelector('.mpcf-module-inner');

			mod.classList.toggle('active', toActivate);
			inner.style.maxHeight = toActivate ? inner.scrollHeight + 'px' : '0px';

			if (toActivate) setTimeout(() => inner.style.maxHeight = '', 250);

			this.updateActiveModules(id);
		}


		removeModule(id, e) {
			e.stopPropagation();

			let mod = e.target.classList.contains('mpcf-module') ? e.target : e.target.closest('.mpcf-module');
			
			mod.parentElement.removeChild(mod);
			this.reorderModules(id);
		}


		moveModuleUp(id, e) {
			e.stopPropagation();

			let mod = e.target.classList.contains('mpcf-module') ? e.target : e.target.closest('.mpcf-module');
			if (mod.previousElementSibling === null) return;

			detachEditors(mod);			// TinyMCE does not survive being moved
			mod.previousElementSibling.before(mod);
			this.reorderModules(id);
			registerEditors(mod);
		}


		moveModuleDown(id, e) {
			e.stopPropagation();

			let mod = e.target.classList.contains('mpcf-module') ? e.target : e.target.closest('.mpcf-module');
			if (mod.nextElementSibling === null) return;

			detachEditors(mod);
			mod.nextElementSibling.after(mod);
			this.reorderModules(id);
			registerEditors(mod);
		}


		reorderModules(id) {
			this.boxes[id].modules = [].slice.call(this.boxes[id].modulesList.querySelectorAll('.mpcf-module'));
			this.boxes[id].modules.forEach((mod, i) => {
				mod.dataset.basename = this.boxes[id].baseName + '[' + i + ']';
			});

			renameDynamicFields(this.boxes[id].box);
			this.updateActiveModules(id);
		}


		activateModule(id, mod = null) {
			if (mod) {
				let inner = mod.querySelector('.mpcf-module-inner');

				mod.classList.add('active');
				inner.style.maxHeight = inner.scrollHeight + 'px';

				setTimeout(() => inner.style.maxHeight = '', 250);
			}

			this.updateActiveModules(id);
		};


		updateActiveModules(id) {
			let actives = [];
			wp.svgPainter.setColors(painterColors);

			this.boxes[id].modules.forEach((mod, i) => {
				let icon      = mod.querySelector('.mpcf-module-icon'),
					isActive  = mod.classList.contains('active'),
					paintMode = isActive ? 'focus' : 'base';

				if (icon) 		wp.svgPainter.paintElement($(icon), paintMode);
				if (isActive) 	actives.push(i);
			});

			this.boxes[id].activeInput.value = actives.join(',');
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
					center: { lat: parseFloat(center[0]), lng: parseFloat(center[1]) },
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

	//	Only the text input of color fields that are not initialised yet: once initialised, the field
	//	also contains the picker's own "Clear" button, which must not become a color picker itself.
	function registerColorPicker(parent) {
		$(parent || document).find('.mpcf-color-input input[type="text"]:not(.wp-color-picker)').wpColorPicker();
	}


	/**************************************************************
		Media picker
	 **************************************************************/

	class MediaPickers {
		constructor() {
			this.pickers   = {};
			this.callbacks = [];
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

				let picker = {
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

				let newPicker = this.callbacks.forEach(cb => cb('registerPicker', picker, id));
				this.pickers[id] = newPicker || picker;
			}).bind(this));
		}


		registerCallback(cb) {
			this.callbacks.push(cb);
		}


		changeMedia(id, event) {
			event.preventDefault();

			let picker          = this.pickers[id],
				currentVal      = picker.idField.getAttribute('value'),
				modalOptions    = { title: localizedmpcf.chooseMedia, multiple: picker.multiple },
				newModalOptions = this.callbacks.forEach(cb => cb('modalRegister', picker, id, modalOptions)),
				modal           = wp.media(newModalOptions || modalOptions);

			modal.on('open', () => {
				let selection  = modal.state().get('selection'),
					attachment = wp.media.attachment(currentVal);

				attachment.fetch();
				selection.add(attachment ? [ attachment ] : []);

				this.callbacks.forEach(cb => cb('modalOpen', picker, id, selection, modal));
			});

			modal.on('select', () => {
				modal.detach();

				let choice  = modal.state().get('selection').first().toJSON(),
					isImage = choice.mime.indexOf('image') > -1,
					isVideo = choice.mime.indexOf('video') > -1;

				this.callbacks.forEach(cb => cb('select', picker, id, choice, modal));

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

			this.callbacks.forEach(cb => cb('clear', picker, id));

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
		Bulk Media picker
	 **************************************************************/

	class BulkMediaPickers {
		constructor() {
			this.pickers = {};
			this.registerNew();
		}

		registerNew(parent) {
			parent = parent || document;

			let newElems = [].slice.call(document.querySelectorAll('.mpcf-bulkmediapicker:not([data-registered])'));

			newElems.forEach((elem => {
				elem.setAttribute('data-registered', 1);

				let id             = elem.dataset.id,
					items          = [].slice.call(elem.querySelectorAll('.mpcf-bulkmedia-item')),
					dropArea       = elem.querySelector('.mpcf-drop-zone'),
					errorContainer = elem.querySelector('.mpcf-media-upload-error'),
					addMediaBtn    = elem.querySelector('.mpcf-bulk-choose-button'),
					uploader       = new plupload.Uploader(window['uploaderInit' + id]);

				uploader.bind('Init', function(u) {
					if (u.features.dragdrop && !document.querySelector('.mobile')) {
						elem.classList.add('drag-drop');

						dropArea.addEventListener('dragover',  () => elem.classList.add('drag-over'));
						dropArea.addEventListener('dragleave', () => elem.classList.remove('drag-over'));
						dropArea.addEventListener('drop',      () => elem.classList.remove('drag-over'));
					} else {
						elem.classList.remove('drag-drop');		// no listeners have been added in this case
					}

					let flashBypass = elem.querySelector('.upload-flash-bypass');
					if (u.runtime === 'html4' && flashBypass)
						flashBypass.style.display = 'none';
				});

				uploader.bind('postinit', u => u.refresh());

				uploader.bind('FilesAdded', ((u, files) => {
					errorContainer.innerHTML = '';
					this.uploadStart();

					plupload.each(files, (file => this.fileQueued(id, file)).bind(this));

					u.refresh();
					u.start();
				}).bind(this));

				uploader.bind('UploadFile',     ((u, file)           => this.fileUploading (id, u, file)).bind(this));
				uploader.bind('UploadProgress', ((u, file)           => this.uploadProgress(id, u, file)).bind(this));
				uploader.bind('FileUploaded',   ((u, file, response) => this.uploadSuccess (id, file, response.response)).bind(this));
				uploader.bind('UploadComplete', (()                  => this.uploadComplete()).bind(this));

				uploader.bind('Error', ((u, error) => {
					this.uploadError(id, error.file, error.code, error.message, u);
					elem.querySelector('.mpcf-bulkmedia-items #mpcf-bulkmedia-item-' + error.file.id).remove();
					u.refresh();
				}).bind(this));


				uploader.init();

				items.forEach((item => this.registerItemEvents(item)).bind(this));
				addMediaBtn.addEventListener('click', (() => this.addMedia(id)).bind(this));

				this.pickers[id] = {
					element:		elem,
					dropArea:		dropArea,
					itemsContainer:	elem.querySelector('.mpcf-bulkmedia-items'),
					items:			items,
					errorContainer:	errorContainer,
					uploader:		uploader
				};
			}).bind(this));
		}


		registerItemEvents(item) {
			let media       = item.querySelector('.mpcf-bulkmedia-item-media'),
				moveUpBtn   = item.querySelector('.mpcf-bulkmedia-move-up'),
				moveDownBtn = item.querySelector('.mpcf-bulkmedia-move-down'),
				removeBtn   = item.querySelector('.mpcf-bulkmedia-remove');

			media.addEventListener('click',       this.changeMedia.bind(this));
			moveUpBtn.addEventListener('click',   this.moveItemUp.bind(this));
			moveDownBtn.addEventListener('click', this.moveItemDown.bind(this));
			removeBtn.addEventListener('click',   this.removeItem.bind(this));
		}



	//	Upload process

		uploadStart() {}


		fileQueued(id, file) {
			let picker       = this.pickers[id],
				item         = document.createElement('div'),
				inner        = document.createElement('div'),
				progress;

			item.classList.add('mpcf-bulkmedia-item');
			item.setAttribute('id', 'mpcf-bulkmedia-item-' + file.id);
			item.innerHTML = `<div class="item">
				<div class="progress">
					<div class="progress-text">0%</div>
					<div class="progress-bar"></div>
				</div>
			</div>`;

			picker.itemsContainer.appendChild(item);
		}


		fileUploading(id, up, file) {
			let hundredMB = 100 * 1024 * 1024,
				max       = parseInt(up.settings.max_file_size, 10);

			if (max <= hundredMB || file.size <= hundredMB) return;

			setTimeout(() => {
				if (file.status >= 3 || file.loaded !== 0) return;

				this.displayErrorMessage(id, up, file, localizedmpcf.bigUploadFailed.replace('%1$s', '<a class="uploader-html" href="#">').replace('%2$s', "</a>"));
				up.stop();
				up.removeFile(file);
				up.start();
			}, 10000);
		}


		uploadProgress(id, up, file) {
			let picker       = this.pickers[id],
				item         = picker.element.querySelector('#mpcf-bulkmedia-item-' + file.id) || picker.element,
				progressBar  = item.querySelector('.progress-bar'),
				progressText = item.querySelector('.progress-text');

			if (!progressBar || !progressText) return;

			progressBar.style.transform = 'translate(0%, -50%) scaleX(' + (file.percent / 100) + ')';
			progressText.innerText = (file.percent || 0) + '%';
		}


		uploadComplete() {}


		uploadSuccess(id, file, response) {
			let picker  = this.pickers[id],
				item    = picker.element.querySelector('#mpcf-bulkmedia-item-' + file.id);

			if (typeof response === 'string') {
				response = response.replace(/^<pre>(\d+)<\/pre>$/, '$1');

				if (/media-upload-error|error-div/.test(response)) {
					item.innerHTML = response;
					return;
				}
			}

			item.querySelector('.progress-text').innerText = localizedmpcf.uploading;
			this.prepareMediaItem(id, file, response);
		}


		prepareMediaItem(id, file, response) {
			let picker = this.pickers[id],
				item   = picker.element.querySelector('#mpcf-bulkmedia-item-' + file.id + ' .item');

			$.get(ajaxurl, withNonce({
				attachment_id:	response,
				fieldName:		picker.element.getAttribute('name'),
				fieldOwnName: 	picker.element.getAttribute('data-own-name'),
				action:			'mpcf_bulk_media_fetch_action'
			}), response => {
				item.innerHTML = response;
				this.registerItemEvents(item);
			}).fail(xhr => reportRequestError('An uploaded file could not be added to the list.', xhr));
		}



	//	Error handling

		uploadError(id, file = {}, errorCode = 0, message = '', up = null) {
			let picker    = this.pickers[id],
				hundredMB = 100 * 1024 * 1024,
				max;

			switch (errorCode) {
				case plupload.FAILED:
					this.displayErrorMessage(id, up, file, localizedmpcf.uploadFailed);
					break;
				case plupload.FILE_EXTENSION_ERROR:
					this.displayErrorMessage(id, up, file, localizedmpcf.invalidType);
					break;
				case plupload.FILE_SIZE_ERROR:
					this.uploadSizeError(id, up, file);
					break;
				case plupload.IMAGE_FORMAT_ERROR:
					this.displayErrorMessage(id, up, file, localizedmpcf.notAnImage);
					break;
				case plupload.IMAGE_MEMORY_ERROR:
					this.displayErrorMessage(id, up, file, localizedmpcf.memoryExceeded);
					break;
				case plupload.IMAGE_DIMENSIONS_ERROR:
					this.displayErrorMessage(id, up, file, localizedmpcf.dimensionsExceeded);
					break;
				case plupload.GENERIC_ERROR:
					this.displayErrorMessage(id, null, {}, localizedmpcf.uploadFailed);
					break;
				case plupload.IO_ERROR:
					max = parseInt(up.settings.filters.max_file_size, 10);
					if (max > hundredMB && file.size > hundredMB) {
						this.displayErrorMessage(id, up, file, localizedmpcf.bigUploadFailed.replace('%1$s', '<a class="uploader-html" href="#">').replace('%2$s', '</a>'));
					} else {
						this.displayErrorMessage(id, null, {}, localizedmpcf.ioError);
					}
					break;
				case plupload.HTTP_ERROR:
					this.displayErrorMessage(id, null, file, localizedmpcf.httpError);
					break;
				case plupload.INIT_ERROR:
					picker.element.classList.add('html-uploader');
					break;
				case plupload.SECURITY_ERROR:
					this.displayErrorMessage(id, null, {}, localizedmpcf.securityError);
					break;
				default:
					this.displayErrorMessage(id, up, file, localizedmpcf.defaultUploadError);
					break;
			}
		}


		displayErrorMessage(id, up = null, file = {}, message = '') {
			let errorDiv = document.createElement('div'),
				errorP   = document.createElement('p');

			if (file.id)
				errorDiv.setAttribute('id', 'mpcf-bulkmedia-item-' + file.id);

			errorDiv.classList.add('error');
			errorDiv.appendChild(errorP);
			errorP.innerText = localizedmpcf.uploadError.replace('%s', file.name) + ' ' + message;	
			this.pickers[id].errorContainer.appendChild(errorDiv);

			if (up)
				up.removeFile(file);
		}


		uploadSizeError(id, up, file) {
			let message = localizedmpcf.fileSizeExceeded.replace('%s', file.name);
			this.displayErrorMessage(id, up, file, message);
		}


	//	Media library


		addMedia(id) {
			let picker = this.pickers[id],
				modal  = new wp.media.view.MediaFrame.Select({ title: localizedmpcf.chooseMedia, multiple: true });

			modal.on('select', () => {
				modal.detach();

				let choices = modal.state().get('selection').toJSON();

				choices.forEach(choice => {
					let item   = document.createElement('div');

					item.classList.add('mpcf-bulkmedia-item');
					item.setAttribute('id', 'mpcf-bulkmedia-item-' + choice.id);
					picker.itemsContainer.appendChild(item);

					$.get(ajaxurl, withNonce({
						attachment_id:	choice.id,
						fieldName:		picker.element.getAttribute('name'),
						fieldOwnName: 	picker.element.getAttribute('data-own-name'),
						action:			'mpcf_bulk_media_fetch_action',
						removeOuter:    true
					}), response => {
						item.innerHTML = response;
						this.registerItemEvents(item);
					}).fail(xhr => reportRequestError('A selected file could not be added to the list.', xhr));
				});
			});

			modal.open();
		}

		changeMedia(e) {
			e.preventDefault();

			let target       = e.target.classList.contains('mpcf-bulkmedia-item') ? e.target : e.target.closest('.mpcf-bulkmedia-item'),
				imagePreview = target.querySelector('img'),
				videoPreview = target.querySelector('video'),
				isImage      = !imagePreview.classList.contains('hidden'),
				idField      = target.querySelector('input[type="hidden"]'),
				currentVal   = idField.value,
				modal        = new wp.media.view.MediaFrame.Select({ title: localizedmpcf.chooseMedia, multiple: false });

			modal.on('open', () => {
				let selection  = modal.state().get('selection'),
					attachment = wp.media.attachment(currentVal);

				attachment.fetch();
				selection.add(attachment ? [ attachment ] : []);

			});

			modal.on('select', () => {
				modal.detach();

				let choice  = modal.state().get('selection').first().toJSON(),
					isImage = choice.mime.indexOf('image') > -1,
					isVideo = choice.mime.indexOf('video') > -1;

				idField.setAttribute('value', choice.id);

				imagePreview.setAttribute('src', isImage ? choice.url : '');
				imagePreview.classList.toggle('hidden', !isImage);
				videoPreview.setAttribute('src', isVideo ? choice.url : '');
				videoPreview.classList.toggle('hidden', !isVideo);
			});

			modal.open();
		}


		moveItemUp(e) {
			let target   = e.target.classList.contains('mpcf-bulkmedia-move-up') ? e.target : e.target.closest('.mpcf-bulkmedia-move-up'),
				item     = target.closest('.mpcf-bulkmedia-item'),
				prevItem = item.previousElementSibling;
			if (!prevItem) return;

			prevItem.insertAdjacentElement('beforebegin', item);
		}


		moveItemDown(e) {
			let target   = e.target.classList.contains('mpcf-bulkmedia-move-down') ? e.target : e.target.closest('.mpcf-bulkmedia-move-down'),
				item     = target.closest('.mpcf-bulkmedia-item'),
				nextItem = item.nextElementSibling;
			if (!nextItem) return;

			nextItem.insertAdjacentElement('afterend', item);
		}


		removeItem(e) {
			let target = e.target.classList.contains('mpcf-bulkmedia-remove') ? e.target : e.target.closest('.mpcf-bulkmedia-remove'),
				item   = target.closest('.mpcf-bulkmedia-item');

			item.remove();
		}
	}

	




	/**************************************************************
		Add qTranslate-X/qTranslate-XT after dynamically loaded fields
	**************************************************************/

	function addQTranslateX(parent = null) {
		if (typeof qTranslateConfig === 'undefined') return;

		parent = parent || document;

	//	while batching, field IDs are not final yet - hook fields up after renaming
		if (qtxQueue !== null) {
			if (qtxQueue.indexOf(parent) === -1) qtxQueue.push(parent);
			return;
		}

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

		Disables the publish/update button while content is loaded.
		Note: removing one element clears the whole list (the filter
		compares each element with itself). Kept as is, as fixing it
		could leave the button disabled when a request fails.
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
			if (module.dataset.mpcfPainted) return;			// already registered
			module.dataset.mpcfPainted = 1;

			var options  = module.querySelectorAll('.mpcf-imagebuttongroup-option');

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
		Reorder select fields
	 **************************************************************/

	var reorderSelectFields = function(parent) {
		parent = parent || document;

		let elems = [].slice.call(parent.querySelectorAll('[data-json-safe-order]'));

	//	no JSON.parse() as it sometimes messes with the order
		let decodeOrderList = function(list) {
			let arr = [];

			list = list.substring(1, list.length - 1);

			while (list.length > 0) {
				let nextComma        = list.indexOf(','),
					nextOpenBracket  = list.indexOf('[');

				if (nextOpenBracket === 0) {
					let nextCloseBracket = list.indexOf(']'),
						item             = list.substring(0, nextCloseBracket);

					arr.push(decodeOrderList(item));
					list  = list.substring(nextCloseBracket + 2);
				} else if (nextComma > -1) {
					let item = list.substring(0, nextComma);

					if (item[0] == '"') item = item.substring(1, item.length - 1);
					else 				item = Number(item);

					arr.push(item);
					list  = list.substring(nextComma + 1);
				} else {
					let item = list;

					if (item[0] == '"') item = item.substring(1, item.length - 1);
					else 				item = Number(item);

					arr.push(item);
					list = '';
				}
			}

			return arr;
		};


		elems.forEach(elem => {
			let order = decodeOrderList(elem.dataset.jsonSafeOrder);

			order.forEach(key => {
				let child = elem.querySelector('[value="' + CSS.escape(String(key)) + '"]');

			//	e.g. values containing a comma are split up in the order list - leave those where they are
				if (!child) return;

				while (child.parentElement !== elem) {
					child = child.parentElement;
				}

				elem.appendChild(child);
			});

			elem.removeAttribute('data-json-safe-order');
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
		Helper functions
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


	var randomString = function(length) {
		const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";

		let result = "";
		for (let i = 0; i < length; i++) {
			result += chars.charAt(Math.floor(Math.random() * chars.length));
		}

		return result;
	}


	document.addEventListener('DOMContentLoaded', init);
})();