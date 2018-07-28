
$ = jQuery;

window.addEventListener('load', function() {
	updateMetaboxHeading();
	updatePanelTitle();
});


/**************************************************************
	Update Metabox edit page heading
**************************************************************/

var updateMetaboxHeading = function() {
	var titleField = document.querySelector('.mpcf-panels-tabs .mpcf-panel:first-child input[name="title"]'),
		heading = document.querySelector('.mpcf-options .mpcf-options-heading');
	if (!titleField || !heading) return;

	titleField.addEventListener('input', function() {
		heading.innerText = localizedmpcf.editBoxHeading.replace('%s', titleField.value);
	});
}


/**************************************************************
	Update Metabox edit page panels panel titel and icon
**************************************************************/

var updatePanelTitle = function() {
	var menuItems  = document.querySelectorAll('.mpcf-panels-menu .mpcf-panel-item:not(:first-child)'),
		panelItems = document.querySelectorAll('.mpcf-panels-tabs .mpcf-panel:not(:first-child)');
	if (!menuItems || !panelItems) return;

	[].forEach.call(panelItems, function(panelItem, index) {
		var titleField = panelItem.querySelector('.mpcf-text-input:first-child input'),
			iconField  = panelItem.querySelector('.mpcf-text-input:nth-child(2) input');

		titleField.addEventListener('input', function() {
			var menuTitle = menuItems[index].querySelector('.mpcf-panel-title');
			menuTitle.innerText = localizedmpcf.editBoxPanel.replace('%s', titleField.value);
		});

		iconField.addEventListener('input', function() {
			var menuIcon = menuItems[index].querySelector('.mpcf-panel-icon');
			menuIcon.classList.value = 'mpcf-panel-icon dashicons ' + iconField.value;
		});
	});
}