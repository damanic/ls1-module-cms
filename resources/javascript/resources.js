function refresh_resources_page()
{
	$('FormElement').getForm().sendPhpr('index_onRefresh', {
		loadIndicator: {show: false}, 
		onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
		onComplete: LightLoadingIndicator.hide,
		update: 'resources_page_content'
	});
}