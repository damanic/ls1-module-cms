function partials_selected()
{
	return $('listCms_Partials_index_list_body').getElements('tr td.checkbox input').some(function(element){return element.checked});
}

function delete_selected()
{
	if (!partials_selected())
	{
		alert('Please select partials to delete.');
		return false;
	}
	
	$('listCms_Partials_index_list_body').getForm().sendPhpr(
		'index_onDeleteSelected',
		{
			confirm: 'Do you really want to delete selected partial(s)?',
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
			onComplete: LightLoadingIndicator.hide,
			onAfterUpdate: update_scrollable_toolbars,
			update: 'partials_page_content'
		}
	);
	return false;
}

function refresh_partial_list()
{
	$('listCms_Partials_index_list_body').getForm().sendPhpr('index_onRefresh', {
		loadIndicator: {show: false}, 
		onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
		onComplete: LightLoadingIndicator.hide,
		update: 'partials_page_content',
		onAfterUpdate: function() {
			update_scrollable_toolbars();
		}
	});
}
