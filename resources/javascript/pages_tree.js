function fix_list_zebra(list)
{
	try
	{
		var items = $A(list.getChildren());
		items.each(function(item, index){
			if (index % 2)
				$(item).addClass('even');
			else
				$(item).removeClass('even');
		})
	} catch (e) {}
}

function init_page_sortables()
{
	var list = $('listCms_Pages_reorder_pages_list_body');
	if (list)
	{
		list.makeListSortable('reorder_pages_onSetOrders', 'page_order', 'page_id', 'row_icon.handle');
		list.addEvent('dragComplete', fix_list_zebra.pass(list));
	}
}

window.addEvent('domready', function(){
	var container = $('listCms_Pages_reorder_pages_list');
	if (container)
		container.addEvent('listUpdated', init_page_sortables);
		
	init_page_sortables();
})