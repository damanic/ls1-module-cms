var prevWidth = 0;

function save_file()
{
	$('FormElement').sendPhpr('edit_onSave', {
		prepareFunction: function(){phprTriggerSave();}, 
		extraFields: {redirect: 0}, 
		loadIndicator: {show: false}, 
		onBeforePost: LightLoadingIndicator.show('Saving...'), 
		onComplete: LightLoadingIndicator.hide, 
		onFailure: popupAjaxError,
		onBeforeScriptEval: function(){UnloadManager.reset_changes()},
		update: $('FormElement').getElement('div.formFlash')}); 

	return false;
}

window.addEvent('domready', function(){
	$(document.getElement('html')).bindKeys({
		'meta+s, ctrl+s': save_file
	});
});