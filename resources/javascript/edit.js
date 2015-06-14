var formTabManager = null;
var update_browser_title_active = false;
var update_browser_title_field = null;
var update_browser_title_text = null;

function save_code()
{
	var options = {
		prepareFunction: function(){phprTriggerSave();}, 
		extraFields: {redirect: 0}, 
		loadIndicator: {show: false}, 
		onBeforePost: LightLoadingIndicator.show.pass('Saving...'), 
		onComplete: LightLoadingIndicator.hide, 
		onFailure: popupAjaxError,
		onAfterUpdate: false, 
		onAfterError: false,
		onBeforeScriptEval: function(){UnloadManager.reset_changes()},
		update: 'multi'
	}

	if ($('action_info_pages') || $('sidebar_pages'))
	{
		options.onAfterUpdate = setupInfoSize;
		options.onAfterError = setupInfoSize;
	}
	
	$('form_element').sendPhpr('onSave', options); 

	return false;
}

window.addEvent('domready', function(){
	$(document.getElement('html')).bindKeys({
		'meta+s, ctrl+s': save_code
	});
	
	if ($('action_info_tabs'))
	{
		window.addEvent('phpr_editor_resized', setupInfoSize);
		window.addEvent('phpr_form_collapsable_updated', setupInfoSize);
	}
});

function onFileBrowserFileClick(path)
{
	path = ls_root_url(path);
	
	var 
		first_editor_id = false,
		current_editor = null,
		current_editor_id = false,
 		processed = false;

	if (formTabManager)
	{
		formTabManager.current_page.getElements('li.code_editor textarea').each(function(ta_element){
			if (!first_editor_id)
				first_editor_id = ta_element.id;

			if (phpr_active_code_editor == ta_element.id)
				current_editor_id = phpr_active_code_editor;
		});
	} else
	{
		$('form_element').getElements('li.code_editor textarea').each(function(ta_element){
			if (!first_editor_id)
				first_editor_id = ta_element.id;

			if (phpr_active_code_editor == ta_element.id)
				current_editor_id = phpr_active_code_editor;
		});
	}

	if (!current_editor_id && first_editor_id)
		current_editor = find_code_editor(first_editor_id);
	else if (current_editor_id)
		current_editor = find_code_editor(current_editor_id);

	if (current_editor && !processed)
	{
		current_editor.insert(path);
		current_editor.focus();
		processed = true;
	}
	
	return false;
}

window.addEvent('domready', function()
{
	if(update_browser_title_active)
	{
		var name_field = $(update_browser_title_field);
		if(name_field)
		{
			name_field.addEvent('keyup', update_browser_title);
			name_field.addEvent('change', update_browser_title);
			name_field.addEvent('paste', update_browser_title);
			update_browser_title();
		}
	}
	
	jQuery('#splitter-table').backendSplitter({
		minWidth: 300,
		saveWidth: true
	});
	jQuery('#content').fullHeightLayout();
});

function update_browser_title()
{
	var new_title;
	if(name_field = $(update_browser_title_field))
	{
		new_title = update_browser_title_text + ' "' + name_field.value +'"';
		document.title = new_title + ' | LemonStand';
	}
}