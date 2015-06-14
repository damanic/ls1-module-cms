function templates_selected()
{
	return $('listCms_Templates_index_list_body').getElements('tr td.checkbox input').some(function(element){return element.checked});
}

function delete_selected()
{
	if (!templates_selected())
	{
		alert('Please select layouts to delete.');
		return false;
	}
	
	$('listCms_Templates_index_list_body').getForm().sendPhpr(
		'index_onDeleteSelected',
		{
			confirm: 'Do you really want to delete selected layout(s)?',
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
			onComplete: LightLoadingIndicator.hide,
			onAfterUpdate: update_scrollable_toolbars,
			update: 'templates_page_content'
		}
	);
	return false;
}

function refresh_layout_list()
{
	$('listCms_Templates_index_list_body').getForm().sendPhpr('index_onRefresh', {
		loadIndicator: {show: false}, 
		onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
		onComplete: LightLoadingIndicator.hide,
		update: 'templates_page_content',
		onAfterUpdate: function() {
			update_scrollable_toolbars();
		}
	});
}