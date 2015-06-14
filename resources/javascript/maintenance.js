function update_page_state(cb)
{
	var page_element = $('Cms_MaintenanceParams_maintenance_page');
	
	if (page_element)
	{
		if (cb.checked)
			page_element.disabled = false;
		else
			page_element.disabled = true;

		page_element.select_update();
	}
}

window.addEvent('domready', function(){
	var cb = $('Cms_MaintenanceParams_enabled');
	if (cb)
	{
		update_page_state(cb);
		cb.addEvent('click', update_page_state.pass(cb));
	}
});