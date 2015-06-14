function init_list_ui()
{
	update_scrollable_toolbars();
	jQuery('#listCms_Pages_index_list').tableRowMenu();
	$('listCms_Pages_index_list').addEvent('listUpdated', function(){
		jQuery('#listCms_Pages_index_list').tableRowMenu();
	})
}

window.addEvent('domready', init_list_ui);

function refresh_page_list()
{
	$('listCms_Pages_index_list').getForm().sendPhpr('index_onRefresh', {
		loadIndicator: {show: false}, 
		onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
		onComplete: LightLoadingIndicator.hide,
		update: 'page_list_content',
		onAfterUpdate: function() {
			init_list_ui();
		}
	});
}