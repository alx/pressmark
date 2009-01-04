<?php
/**
 * @package Flickr_Embed
 * @author Matt Mullenweg
 * @version 1.0
 */
/*
Plugin Name: Flickr Embed
Plugin URI: http://github.com/alx/pressmark/
Description: Add flickr photo in posts. ATTENTION: do not forget to add your flickr api key - http://www.flickr.com/services/api/keys/ - in the header of wp-content/plugins/flickr_embed.php
Author: Alexandre Girard
Version: 1.0
Author URI: http://alexgirard.com
*/

// Put your flickr api key here
if (!defined("FLICKR_EMBED_API_KEY")) {
	define("FLICKR_EMBED_API_KEY", '0000');
}

/*
Available sizes:
Square
Thumbnail
Small
Medium (default)
Large
Original
*/
function flickr_embed($photo_id, $size = "Medium"){
	
	if(FLICKR_EMBED_API_KEY == "0000")
		return false;
	
	#
	# build the API URL to call
	#
	$params = array(
		'api_key'	=> FLICKR_EMBED_API_KEY,
		'method'	=> 'flickr.photos.getSizes',
		'photo_id'	=> $photo_id,
		'format'	=> 'php_serial',
	);

	$encoded_params = array();

	foreach ($params as $k => $v){

		$encoded_params[] = urlencode($k).'='.urlencode($v);
	}


	#
	# call the API and decode the response
	#

	$url = "http://api.flickr.com/services/rest/?".implode('&', $encoded_params);

	$rsp = file_get_contents($url);

	$rsp_obj = unserialize($rsp);


	#
	# display the photo title (or an error if it failed)
	#

	if ($rsp_obj['stat'] == 'ok'){

		$photos = $rsp_obj['sizes']["size"];
		
		switch ($size) {
		    case "Square":
		        $photo = $photos[0];
		        break;
		    case "Thumbnail":
				$photo = $photos[1];
		        break;
		    case "Small":
				$photo = $photos[2];
		        break;
		    case "Medium":
				$photo = $photos[3];
		        break;
		    case "Large":
				$photo = $photos[4];
		        break;
		    case "Original":
				$photo = $photos[5];
		        break;
			default:
				$photo = $photos[3];; //Medium by default
		}
		
		$source = $photo['source'];
		$url = $photo['source'];
		$width = $photo['width'];
		$height = $photo['height'];
		
		echo "<a href='$url'><img src='$source' width='".$width."px' height='".$height."px'/></a>";
	}else{

		echo "Call failed!";
	}
	
}
?>
