<?php
/*
 * Frontend resource combiner (JS/CSS)
 * Called via direct http request to yoursite.com/cms_js_combine
 * see: CMS_Controller::js_combine()
 * Called via direct http request to yoursite.com/cms_css_combine
 * see: CMS_Controller::css_combine()
 *
 * @TODO, ease phpr framework vendor dependency
 * This script should be able to load minifier dependencies independently within the CMS module.
 * The bulk of this script could also be wrapped into helper class Cms_ResourceCombine
 */

Backend::$events = new Backend_Events();

$aliases = array(
	'mootools'=>'/modules/cms/resources/javascript/mootools_src.js',
	'ls_core_mootools'=>'/modules/cms/resources/javascript/ls_mootools_core_src.js',
	'ls_core_jquery'=>'/modules/cms/resources/javascript/ls_jquery_core_src.js',
	'jquery'=>'/modules/cms/resources/javascript/jquery_src.js',
	'jquery_noconflict'=>'/modules/cms/resources/javascript/jquery_noconflict.js',
	'ls_styles'=>'/modules/cms/resources/css/frontend_css.css',
	'frontend_mootools'=>'/modules/cms/resources/javascript/ls_mootools_core_src.js',
	'frontend_jquery'=>'/modules/cms/resources/javascript/ls_jquery_core_src.js',
	'frontend_styles'=>'/modules/cms/resources/css/frontend_css.css'
);

if (Cms_Theme::is_theming_enabled() && ($theme = Cms_Theme::get_active_theme()))
	$current_theme = $theme;

$allowed_dir = $current_theme ? $theme->get_resources_path() : '/'.Cms_SettingsManager::get()->resources_dir_path;
$allowed_paths = array(
	PATH_APP.$allowed_dir,
	PATH_APP.'/modules/cms/resources/javascript',
	PATH_APP.'/modules/cms/resources/css/'
);

$files = Phpr::$request->get_value_array('f',false); //already url decoded
if(!$files){
	die();
}

$files = Cms_ResourceCombine::decode_param($files, $url_encoded = false);
if(!$files){
	die();
}

use MatthiasMullie\Minify;
$minify = false;
if(class_exists('Minify')){
	$minify = true;
}


$allowed_types  = array( 'js', 'css' );
$allowed_paths  = isset( $CONFIG['ALLOWED_RESOURCE_PATHS'] ) ? array_merge($allowed_paths,$CONFIG['ALLOWED_RESOURCE_PATHS']) : $allowed_paths;
$symbolic_links = isset( $CONFIG['RESOURCE_SYMLINKS'] ) ? $CONFIG['RESOURCE_SYMLINKS'] : array();
$enable_remote_resources = isset( $CONFIG['ENABLE_REMOTE_RESOURCES'] ) ? $CONFIG['ENABLE_REMOTE_RESOURCES'] : false; //not allowed by default

$resource_type = null;
$url_query = Phpr::$request->get_value_array('q', false);
if ( preg_match( '#cms_(.+)_combine#simU', htmlentities( $url_query, ENT_COMPAT, 'UTF-8' ), $match ) ) { // htmlentities just incase something malicious (just being safe)
	$resource_type = $match[1];
}
if(!$resource_type || !in_array( $resource_type, $allowed_types ) ){
	die();
}


$recache    = Phpr::$request->get_value_array('reset_cache', false);
$skip_cache = Phpr::$request->get_value_array('skip_cache', false);
$src_mode   = Phpr::$request->get_value_array('src_mode', false);

$assets = array();
$combined_files = array();

foreach ( $files as $file_path ) {

	$allowed = false; // is this file allowed to be an asset?

	if ( array_key_exists( $file_path, $aliases ) ) {
		$file_path = $aliases[$file_path];
	}

	$file = $orig_url = str_replace( chr( 0 ), '', urldecode( $file_path ) );
	$file_type = pathinfo( strtolower( $file ), PATHINFO_EXTENSION );
	if ( $file_type !== $resource_type) {
		continue;
	}

	if(isset($combined_files[$orig_url])){
		continue; //already included
	}

	if ( !Cms_ResourceCombine::is_remote_resource( $file ) ) {
		$file = str_replace( '\\', '/', realpath( PATH_APP . $file ) );

		foreach ( $allowed_paths as $allowed_path ) {
			$allowed_path = realpath( $allowed_path ); //no symbolic links allowed
			if ( !$allowed_path ) {
				continue;
			}
			$allowed_path = str_replace('\\','/', $allowed_path);
			$is_relative = strpos( $allowed_path, '/' ) !== 0 && strpos( $allowed_path, ':' ) !== 1;

			if ( $is_relative ) {
				//relative paths not accepted
				continue;
			}


			if ( strpos( $file, $allowed_path ) === 0 ) {
				$allowed = true; // the file is allowed to be an asset because it matches the requirements (allowed paths)
				break;
			}
		}
	} else {
		$allowed = true; // always allow remote files
	}

	if ( $allowed ) {
		$combined_files[$orig_url] = 1;
		$assets[$orig_url] = $file; //approved asset
	}
}

/*
 * Check whether GZIP is supported by the browser
 */
$supportsGzip = false;
$encodings    = array();
if ( isset( $_SERVER['HTTP_ACCEPT_ENCODING'] ) ) {
	$encodings = explode( ',', strtolower( preg_replace( '/\s+/', '', $_SERVER['HTTP_ACCEPT_ENCODING'] ) ) );
}

if (
	( in_array( 'gzip', $encodings ) || in_array( 'x-gzip', $encodings ) || isset( $_SERVER['---------------'] ) )
	&& function_exists( 'ob_gzhandler' )
	&& !ini_get( 'zlib.output_compression' )
) {
	$enc          = in_array( 'x-gzip', $encodings ) ? 'x-gzip' : 'gzip';
	$supportsGzip = true;
}

/*
 * Caching
 */

$mime = 'text/' . ( $resource_type == 'js' ? 'javascript' : $resource_type );

$cache_path = PATH_APP . '/temp/resource_cache';
if ( !file_exists( $cache_path ) ) {
	mkdir( $cache_path );
}

$cache_hash = sha1( implode( ',', $assets ) );

$cache_filename = $cache_path . '/' . $cache_hash . '.' . $resource_type;
if ( $supportsGzip ) {
	$cache_filename .= '.gz';
}

$cache_exists = file_exists( $cache_filename );

if ( $recache && $cache_exists ) {
	@unlink( $cache_filename );
}

$assets_mod_time = 0;
foreach ( $assets as $file ) {
	if ( !Cms_ResourceCombine::is_remote_resource( $file ) ) {
		if ( file_exists( $file ) ) {
			$assets_mod_time = max( $assets_mod_time, filemtime( $file ) );
		}
	} else {
		/*
		 * We cannot reliably check the modification time of a remote resource,
		 * because time on the remote server could not exactly match the time
		 * on this server.
		 */

		//$assets_mod_time = 0;
	}
}

$cached_mod_time = $cache_exists ? (int) @filemtime( $cache_filename ) : 0;

if ( $resource_type == 'css' ) {
	require PATH_APP . '/phproad/thirdpart/csscompressor/UriRewriter.php';
}

$content = '';
if ( $skip_cache || $cached_mod_time < $assets_mod_time || !$cache_exists ) {
	foreach ( $assets as $orig_url => $file ) {
		$is_remote = Cms_ResourceCombine::is_remote_resource( $file );

		if ( $is_remote && !$enable_remote_resources ) {
			continue;
		}

		if ( file_exists( $file ) || $is_remote ) {
			$data = @file_get_contents( $file ) . "\r\n";

			if ( $resource_type == 'css' ) {
				if ( !$is_remote ) {
					$data = Minify_CSS_UriRewriter::rewrite(
						$data,
						dirname( $file ),
						null,
						$symbolic_links
					);
				} else {
					$data = Minify_CSS_UriRewriter::prepend(
						$data,
						dirname( $file ) . '/'
					);
				}
			}

			$content .= $data;
		} else {
			$content .= sprintf( "\r\n/* Asset Error: asset %s not found. */\r\n", $orig_url );
		}
	}

	if ( $resource_type == 'js' && !$src_mode ) {
		if($minify) {
			$minifier = new Minify\JS( $content );
			$content  = $minifier->minify();
		}
	} elseif ( $resource_type == 'css' && !$src_mode ) {
		if($minify) {
			$minifier = new Minify\CSS( $content );
			$content  = $minifier->minify();
		}
	}

	if ( $supportsGzip ) {
		$content = gzencode( $content, 9, FORCE_GZIP );
	}

	if ( !$skip_cache ) {
		@file_put_contents( $cache_filename, $content );
	}
} elseif (
	isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) &&
	$assets_mod_time <= strtotime( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) {
	header( 'Content-Type: ' . $mime );
	if ( php_sapi_name() == 'CGI' ) {
		header( 'Status: 304 Not Modified' );
	} else {
		header( 'HTTP/1.0 304 Not Modified' );
	}

	exit();
} elseif ( file_exists( $cache_filename ) ) {
	$content = @file_get_contents( $cache_filename );
}

/*
 * Output
 */

header( 'Content-Type: ' . $mime );
header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $assets_mod_time ) . ' GMT' );

if ( $supportsGzip ) {
	header( 'Vary: Accept-Encoding' );  // Handle proxies
	header( 'Content-Encoding: ' . $enc );
}

echo $content;
