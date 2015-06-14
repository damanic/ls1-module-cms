function cms_show_select_theme_popoup(method_name, apply_callback)
{
	new PopupForm(method_name + 'on_cms_theme_selector_load_popup', {
		popupData: {'theme_apply_callback': apply_callback}
	}); 
	
	return false;
}