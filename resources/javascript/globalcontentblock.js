function initSidebarTabManager()
{
	sidebar_tabs = new TabManager('sidebar_tabs', 'sidebar_pages', {trackTab: false});
	sidebar_tabs.addEvent('onTabClick', setupInfoSize);
}

window.addEvent('domready', function(){
	if ($('sidebar_tabs'))
	{
		initSidebarTabManager();
		(function(){setupInfoSize();}).delay(600);
	}
});

function setupInfoSize()
{
	backend_trigger_layout_updated();
}

window.addEvent('phpr_editor_resized', setupInfoSize);
window.addEvent('phpreditoradded', function(){
	(function(){setupInfoSize();}).delay(600);
});