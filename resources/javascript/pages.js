var sidebar_tabs = null;
var url_modified = false;

window.addEvent('domready', function(){
	if ($('action_info_tabs'))
	{
		initInfoTabManager();
		(function(){setupInfoSize();}).delay(600);
		
		if ($('Cms_Page_action_reference'))
		{
			$('Cms_Page_action_reference').addEvent('change', function(){
				$('Cms_Page_action_reference').getForm().sendPhpr('onActionChanged', {
						update: 'multi', 
						loadIndicator: {show: false}, 
						onAfterUpdate: function(){
							jQuery.ui.anchorLayout.scan();
							setupInfoSize();
						}
				});
			});
		}
	}
	
	var page_title_field = $('Cms_Page_title');
	if (page_title_field && $('new_page_flag'))
	{
		page_title_field.addEvent('keyup', update_url_title.pass(page_title_field));
		page_title_field.addEvent('change', update_url_title.pass(page_title_field));
		page_title_field.addEvent('paste', update_url_title.pass(page_title_field));
	}
	
	if ($('new_page_flag'))
	{
		var url_element = $('Cms_Page_url');
		if (url_element)
			url_element.addEvent('change', function(){url_modified=true;});
	}
	
	var customer_group_filter_cb = $('Cms_Page_enable_page_customer_group_filter');
	if (customer_group_filter_cb)
	{
		if (!customer_group_filter_cb.checked)
			$('form_field_customer_groupsCms_Page').hide();

		customer_group_filter_cb.addEvent('click', update_customer_group_filter_visibility.pass(customer_group_filter_cb));
	}
});

function initInfoTabManager()
{
	sidebar_tabs = new TabManager('action_info_tabs', 'action_info_pages', {trackTab: false});
	sidebar_tabs.addEvent('onTabClick', setupInfoSize);
}

function add_page_block()
{
	var max_num = $('max_block_num').value;
	var i = 0;
	for (i=1; i<=max_num; i++)
	{
		var block_name_element = 'form_field_page_block_name_'+i+'Cms_Page';
		var block_code_element = 'form_field_page_block_content_'+i+'Cms_Page';

		if ($(block_name_element).hasClass('hidden'))
		{
			$(block_name_element).removeClass('hidden');
			$(block_code_element).removeClass('hidden');
			var textarea_element = $(block_code_element).getElement('textarea');
			if (textarea_element)
				init_code_editor(textarea_element.id, 'php', page_editors_config);

			if (i == max_num)
				$('add_block_link').hide();

			setupInfoSize();
			backend_trigger_layout_updated();
			return false;
		} 
	}

	return false;
}

function setupInfoSize()
{
	backend_trigger_layout_updated();
}

function update_url_title(field_element)
{
	if (!url_modified)
	{
		$('Cms_Page_url').value = '/' + convert_text_to_url(field_element.value);
		$('Cms_Page_url').fireEvent('modified');
	}
}

window.addEvent('phpr_editor_resized', setupInfoSize)

function update_customer_group_filter_visibility(customer_group_filter_cb)
{
	if (customer_group_filter_cb.checked)
		$('form_field_customer_groupsCms_Page').show();
	else
		$('form_field_customer_groupsCms_Page').hide();
}