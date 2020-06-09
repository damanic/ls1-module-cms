<?php
//Allow direct access to frontend resource combiner
if (Phpr::$request->get_value_array('q', false)) {
	$combine_access_points = array(
		'cms_js_combine',
		'cms_css_combine',
	);
	foreach($combine_access_points as $ap){
		if(strpos(Phpr::$request->get_value_array('q'), $ap.'/') !== false){
			include( PATH_APP."/modules/cms/system/combine_resources.php" );
			die();
		}
	}
}
