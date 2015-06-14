function ls_root_url(url)
{
	if (!lemonstand_root_dir)
		return url;
		
	if (url.substr(0,1) == '/')
		url = url.substr(1)
	
	return lemonstand_root_dir + url;
}

if (Browser.Engine.webkit)
	new Asset.css(ls_root_url('/modules/cms/resources/css/frontendmessages_webkit.css'));