function update_user_agent_ui(selector, init)
{
	var mode = selector.get('value');
	
	if (mode == 'disabled' || mode.length == 0)
	{
		$('form_field_agent_listCms_Theme').hide();
		$('form_field_agent_detection_codeCms_Theme').hide();
	} else if (mode == 'built-in') {
		$('form_field_agent_listCms_Theme').show();
		$('form_field_agent_detection_codeCms_Theme').hide();
	} else {
		$('form_field_agent_listCms_Theme').hide();
		$('form_field_agent_detection_codeCms_Theme').show();
		
		var editor_element = $('form_field_container_agent_detection_codeCms_Theme').getElement('textarea');

		if (!phpr_field_initialized.has(editor_element.get('id')) && jQuery(editor_element).is(':visible') && !init)
		{
			init_code_editor(editor_element.get('id'), 'php', editor_config);
			phpr_field_initialized.set(editor_element.get('id'), 1);
		}
	}
	
	realignPopups();
}

function init_user_agent_ui()
{
	var selector = $('Cms_Theme_agent_detection_mode');
	if (selector) {
		selector.addEvent('change', function(){update_user_agent_ui(selector, false)});
		
		update_user_agent_ui(selector, true);
	}
}

window.addEvent('domready', init_user_agent_ui);