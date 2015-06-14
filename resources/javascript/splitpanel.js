var sidebar_tabs = null;

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

function initSidebarTabManager()
{
	sidebar_tabs = new TabManager('sidebar_tabs', 'sidebar_pages', {trackTab: false});
	sidebar_tabs.addEvent('onTabClick', setupInfoSize);
}

window.addEvent('phpr_editor_resized', setupInfoSize);