window.addEvent('domready', function(){
	var switcher = $('Cms_SettingsManager_enable_filebased_templates');
	if (switcher)
	{
		switcher.addEvent('toggle', function(state){
			if (state)
				$('form_field_enable_filebased_templatesCms_SettingsManager').addClass('separatedField');
			else
				$('form_field_enable_filebased_templatesCms_SettingsManager').removeClass('separatedField');

			var fields = $(switcher).getForm().getElements('li.filebased_field');
			fields.each(function(element){
				if (state) 
					$(element).show(); 
				else
					$(element).hide();
			})
		})
	}
})

function onSaveSettings()
{
	var new_value = $('Cms_SettingsManager_enable_filebased_templateshidden').value == '1';
	var old_value = $('prev_file_based_field_state').value == '1';

	if (new_value == old_value)
		return true;

	var message = new_value ?
	 	'You just enabled the file-based CMS templates. LemonStand is going to transfer pages, partials and templates from the database to files. This operation will override existing files. Continue?' : 
		'You just disabled the file-based CMS templates. LemonStand is going to transfer pages, partials and templates from files to the database. This operation will override existing CMS objects which are bound to files. Continue?';
		
	return confirm(message);
}