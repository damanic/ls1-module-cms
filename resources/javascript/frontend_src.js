/*
 * LemonStand front-end JavaScript library source code
 */

function script_params_parser()
{ 
	var scripts = document.getElements('script');
	var querystring = scripts[scripts.length-1].src.toLowerCase();

	var qsReg = new RegExp("[?][^#]*","i"); 
	hRef = unescape(querystring); 
	var qsMatch = hRef.match(qsReg); 

	qsMatch = new String(qsMatch); 
	qsMatch = qsMatch.substr(1, qsMatch.length -1); 

	var rootArr = qsMatch.split("&"); 

	for(i=0;i<rootArr.length;i++){ 
		var tempArr = rootArr[i].split("="); 
		if(tempArr.length ==2){ 
			tempArr[0] = unescape(tempArr[0]); 
			tempArr[1] = unescape(tempArr[1]); 

			this[tempArr[0]]= tempArr[1]; 
		} 
	} 
}

parser = new script_params_parser();

if (!Request.Phpr)
	new Asset.javascript(parser.dir+'phproad/javascript/phproad.js', {onload: init_fronted_ajax});
else
	window.addEvent('domready', init_fronted_ajax);

/*
 * AJAX
 */

function init_fronted_ajax()
{
	Request.Phpr.implement({
		active_request_num: 0,
		loading_indicator_element: null,
		
		getRequestDefaults: function()
		{
			return {
				onBeforePost: this.frontend_before_ajax_post.bind(this),
				onComplete: this.frontend_after_ajax_post.bind(this),
				onFailure: this.popupError.bind(this),
				execScriptsOnFailure: true
			};
		},
		
		popupError: function(xhr)
		{
			alert(xhr.responseText.replace('@AJAX-ERROR@', '').replace(/(<([^>]+)>)/ig,""));
		},
		
		frontend_before_ajax_post: function()
		{
			if (this.options.noLoadingIndicator)
				return;
			
			this.active_request_num++;
			this.frontend_create_loading_indicator();
		},

		frontend_after_ajax_post: function()
		{
			if (this.options.noLoadingIndicator)
				return;

			this.active_request_num--;
			
			if (this.active_request_num == 0)
				this.frontend_remove_loading_indicator();
		},
		
		frontend_create_loading_indicator: function()
		{
			if (this.loading_indicator_element)
				return;

			this.loading_indicator_element = new Element('p', {'class': 'ajax_loading_indicator'}).inject(document.body, 'top');
			this.loading_indicator_element.innerHTML = '<span>Loading...</span>';
		},
		
		frontend_remove_loading_indicator: function()
		{
			if (this.loading_indicator_element)
				this.loading_indicator_element.destroy();
				
			this.loading_indicator_element = null;
		}
	});
	
	window.addEvent('domready', function(){
		window.fireEvent('frontendready');
	});
}

Element.implement({
	sendRequest: function(handlerName, options)
	{
		if (!$type(options))
		 	options = {extraFields: {}};

		if (!$type(options.extraFields))
			options.extraFields = {};
			
		var updateElements = $type(options.update) ? options.update : null;

		options.update = null;
		options.extraFields = $merge(options.extraFields, {
			cms_handler_name: handlerName, 
			cms_update_elements: updateElements});

		return this.sendPhpr('onHandleRequest', options);
	}
});

/*
 * Image slider
 */

var ImageSlider = new Class({
	slider_element: null,
	thumbnails: [],
	fullsize: [],
	slider: null,
	
	initialize: function(slider_element, thumbnails, fullsize)
	{
		this.thumbnails = thumbnails;
		this.fullsize = fullsize;
		
		if (Browser.loaded)
			this.init_control.delay(30, this, slider_element);
		else
			window.addEvent('domready', this.init_control.bind(this, [slider_element]));
	},
	
	init_control: function(slider_element)
	{
		this.slider_element = $(slider_element);

		this.slider = new Slider(
			$(this.slider_element).getElement('div.slider'), 
			$(this.slider_element).getElement('.knob'), 
			{
				steps: this.thumbnails.length,
				snap: false,
				onChange: this.sliderChange.bind(this)
		});
		
		this.slider_element.getElement('a').addEvent('click', this.imageClick.bind(this));
	},
	
	imageClick: function()
	{
		var images = [];
		var current_index = 0;
		var current_href = this.slider_element.getElement('a').get('href');
		for  (var i=0; i < this.fullsize.length; i++)
		{
			images.push([this.fullsize[i]]);
			if (current_href == this.fullsize[i])
				current_index = i;
		}

		open_slimbox(images, current_index);
		return false;
	},
	
	sliderChange: function(index) 
	{
		if (index > this.thumbnails.length-1)
			return;

		this.slider_element.getElement('img').set('src', this.thumbnails[index]);
		this.slider_element.getElement('span').set('text', index+1);
		this.slider_element.getElement('a').set('href', this.fullsize[index]);
	}
});

function open_slimbox(images, current_index)
{
	Slimbox.open(images, current_index);
}

/*
 * Rating selector
 */

var RatingSelector = new Class({
	stars_element: null,
	rating_element: null,

	initialize: function(selector_element)
	{
		if (Browser.loaded)
			this.init_control.delay(30, this, selector_element);
		else
			window.addEvent('domready', this.init_control.bind(this, [selector_element]));
	},

	init_control: function(selector_element)
	{
		this.stars_element = $(selector_element).getElement('span.rating_stars');
		this.rating_element = $(selector_element).getElement('input');
		if (this.stars_element && this.rating_element)
			this.stars_element.addEvent('click', this.handle_click.bindWithEvent(this));
	},
	
	handle_click: function(event)
	{
		var stars_coords = this.stars_element.getCoordinates();
		var offset = event.page.x - stars_coords.left;
		var rating = Math.ceil(offset/(stars_coords.width/5));

		this.stars_element.className = 'rating_stars rating_'+rating;
		this.rating_element.value = rating;
	}
});
