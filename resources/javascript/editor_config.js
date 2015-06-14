var element_sortables = null;

function item_drop(item)
{
	if (!item.hasClass('separator'))
		return;
		
	if (item.getParent().id == 'available_controls_1')
	{
		var separators = item.getParent().getElements('li.separator');
		
		if (separators.length > 1)
			item.destroy();
	} else
	{
		var separators = $('available_controls_1').getElements('li.separator');
		
		if (separators.length == 0)
		{
			var separator = new Element('li', {'class': 'separator', 'text': 'Separator'});
			separator.inject($('available_controls_1'), 'top');
		}
	}
	
	element_sortables.detach();
	element_sortables.attach();
}

window.addEvent('domready', function(){
	element_sortables = new Sortables(
		[$('controls_row_1'), $('available_controls_1'), $('controls_row_2'), $('controls_row_3')], 
		{
			clone: true, 
			opacity: 0.5, 
			revert: true,
			onComplete: item_drop
		}
	)
})
