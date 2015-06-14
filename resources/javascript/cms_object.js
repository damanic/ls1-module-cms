var cms_object_name_field = null;
var cms_object_file_name_field = null;
var cms_object_file_name_modified = false;
var cms_object_page = false;

function fix_file_button_click(button)
{
	if (!button.checked)
		return;

	if (button.id == 'create_new_file')
	{
		$('new_file_name_field').show();
		if ($('another_directory_field'))
			$('another_directory_field').hide();
	}
	else if (button.id == 'use_another_dir')
	{
		$('new_file_name_field').hide();
		$('another_directory_field').show();
	} else
	{
		$('new_file_name_field').hide();
		if ($('another_directory_field'))
			$('another_directory_field').hide();
	}
}

function init_file_fix_controls()
{
	$('fix_object_file').getElements('input.radio').each(function(button){
		button.addEvent('click', fix_file_button_click.pass(button));
	})
}

function check_file_fix_submit(delete_message)
{
	var action_checked = $('fix_object_file').getElements('input.radio').some(function(element){return element.checked});

	if (!action_checked)
	{
		alert('Please select an action.');
		return false;
	}
	
	var is_delete_action = false;
	$('fix_object_file').getElements('input.radio').each(function(element){
		if (element.checked && element.id == 'delete_object')
			is_delete_action = true;
	});
	
	if (is_delete_action)
		return confirm(delete_message);
	
	return true;
}

function update_cms_object_file_name(name_field)
{
	if (cms_object_file_name_modified)
		return;

	var text = name_field.value;
	
	text = text.replace(/[^a-z0-9:_]/gi, '_');
	text = text.replace(/:/g, ';');
	text = text.replace(/__/g, '_');
	text = text.replace(/__/g, '_');
	text = text.replace(/^_/g, '');
	
	if (text.match(/_$/))
		text = text.substr(0, text.length-1);
		
	if (!text.length && cms_object_page)
		text = 'home';
	
	$(cms_object_file_name_field).value = text.toLowerCase();
}

window.addEvent('domready', function(){
	if ($('fix_object_file'))
		init_file_fix_controls();

	if (cms_object_name_field && $(cms_object_name_field) && $('new_object_flag') && $(cms_object_file_name_field))
	{
		var object_name_element = $(cms_object_name_field);

		$(cms_object_file_name_field).addEvent('change', function(){
			cms_object_file_name_modified = true;
		})

		object_name_element.addEvent('keyup', update_cms_object_file_name.pass(object_name_element));
		object_name_element.addEvent('change', update_cms_object_file_name.pass(object_name_element));
		object_name_element.addEvent('paste', update_cms_object_file_name.pass(object_name_element));
			
		if (cms_object_page)
			object_name_element.addEvent('modified', update_cms_object_file_name.pass(object_name_element));
	}
})