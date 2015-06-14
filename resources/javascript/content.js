function blocks_selected()
{
	return $('listCms_Content_index_list_body').getElements('tr td.checkbox input').some(function(element){return element.checked});
}

function delete_selected()
{
	if (!blocks_selected())
	{
		alert('Please select content block(s) to delete.');
		return false;
	}
	
	$('listCms_Content_index_list_body').getForm().sendPhpr(
		'index_onDeleteSelected',
		{
			confirm: 'Do you really want to delete selected content blocks(s)?',
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
			onComplete: LightLoadingIndicator.hide,
			onAfterUpdate: update_scrollable_toolbars,
			update: 'content_page_content'
		}
	);
	return false;
}
