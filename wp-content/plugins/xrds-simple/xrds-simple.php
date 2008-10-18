<?php
/*
Plugin Name: XRDS-Simple
Plugin URI: http://wordpress.org/extend/plugins/xrds-simple/
Description: Provides framework for other plugins to advertise services via XRDS.
Version: 1.0
Author: DiSo Development Team
Author URI: http://diso-project.org/
License: MIT license (http://www.opensource.org/licenses/mit-license.php)
*/


// Public Functions

/**
 * Convenience function for adding a new XRD to the XRDS structure.
 *
 * @param array $xrds current XRDS-Simple structure
 * @param string $id ID of new XRD to add
 * @param array $type service types for the new XRD
 * @param string $expires expiration date for XRD, formatted as xs:dateTime
 * @return array updated XRDS-Simple structure
 * @since 1.0
 */
function xrds_add_xrd($xrds, $id, $type=array(), $expires=false) {
	if(!is_array($xrds)) $xrds = array();
	$xrds[$id] = array('type' => $type, 'expires' => $expires, 'services' => array());
	return $xrds;
}


/**
 * Convenience function for adding a new service endpoint to the XRDS structure.
 *
 * @param array $xrds current XRDS-Simple structure
 * @param string $id ID of the XRD to add the new service to.  If no XRD exists with the specified ID,
 *        a new one will be created.
 * @param string $name human readable name of the service
 * @param array $content content to be included in the service definition. Format:
 *        <code>
 *        array(
 *            'NodeName (ie, Type)' => array( 
 *                array('attribute' => 'value', 'content' => 'content string'), 
 *                ... 
 *             ),
 *             ...
 *        )
 *        </code>
 * @param int $priority service priorty
 * @return array updated XRDS-Simple structure
 * @since 1.0
 */
function xrds_add_service($xrds, $xrd_id, $name, $content, $priority=10) {
	if (!is_array($xrds[$xrd_id])) {
		$xrds = xrds_add_xrd($xrds, $xrd_id);
	}
	$xrds[$xrd_id]['services'][$name] = array('priority' => $priority, 'content' => $content);
	return $xrds;
}

/**
 * Convenience function for adding a new service with minimal options.  
 * Services will always be added to the 'main' XRD with the default priority.  
 * No additional parameters such as httpMethod on URIs can be passed.  If those 
 * are necessary, use xrds_add_service().
 *
 * @param array $xrds current XRDS-Simple structure
 * @param string $name human readable name of the service
 * @param mixed $type one type (string) or array of multiple types
 * @param mixed $uri one URI (string) or array of multiple URIs
 * @return array updated XRDS-Simple structure
 * @since 1.0
 */
function xrds_add_simple_service($xrds, $name, $type, $uri) {
	if (!is_array($type)) $type = array($type);
	if (!is_array($uri)) $uri = array($uri);
	$service = array('Type' => array(), 'URI' => array());

	foreach ($type as $t) {
		$service['Type'][] = array('content' => $t);
	}

	foreach ($uri as $u) {
		$service['URI'][] = array('content' => $u);
	}

	return xrds_add_service($xrds, 'main', $name, $service);
}



// Private Functions

add_action('wp_head', 'xrds_meta');
add_action('parse_request', 'xrds_parse_request');
add_action('admin_menu', 'xrds_admin_menu');
add_filter('xrds_simple', 'xrds_atompub_service');

/**
 * Print HTML meta tags, advertising the location of the XRDS document.
 */
function xrds_meta() {
	echo '<meta http-equiv="X-XRDS-Location" content="'.get_bloginfo('home').'/?xrds" />'."\n";
	echo '<meta http-equiv="X-Yadis-Location" content="'.get_bloginfo('home').'/?xrds" />'."\n";
}


/**
 * Build the XRDS-Simple document.
 *
 * @return string XRDS-Simple document
 */
function xrds_write() {

	$xrds = array();
	$xrds = apply_filters('xrds_simple', $xrds);
	
	//make sure main is last
	if($xrds['main']) {
		$o = $xrds['main'];
		unset($xrds['main']);
		$xrds['main'] = $o;
	}

	$xml = '<?xml version="1.0" encoding="UTF-8" ?>'."\n";
	$xml .= '<xrds:XRDS xmlns:xrds="xri://$xrds" xmlns="xri://$xrd*($v*2.0)" xmlns:simple="http://xrds-simple.net/core/1.0" xmlns:openid="http://openid.net/xmlns/1.0">'."\n";
	foreach($xrds as $id => $xrd) {
		$xml .= '	<XRD xml:id="'.htmlspecialchars($id).'" version="2.0">' . "\n";
		$xml .= '		<Type>xri://$xrds*simple</Type>'."\n";
		if(!$xrd['type']) $xrd['type'] = array();
		if(!is_array($xrd['type'])) $xrd['type'] = array($xrd['type']);
		foreach($xrd['type'] as $type)
			$xml .= '		<Type>'.htmlspecialchars($type).'</Type>'."\n";
		if($xrd['expires'])
			$xml .= '	<Expires>'.htmlspecialchars($xrd['expires']).'</Expires>'."\n";
		foreach($xrd['services'] as $name => $service) {
			$xml .= "\n".'		<!-- ' . $name . ' -->'."\n";
			$xml .= '		<Service priority="'.floor($service['priority']).'">'."\n";
			foreach($service['content'] as $node => $nodes) {
				if(!is_array($nodes)) $nodes = array($nodes);//sanity check
				foreach($nodes as $attr) {
					$xml .= '			<'.htmlspecialchars($node);
					if(!is_array($attr)) $attr = array('content' => $attr);//sanity check
					foreach($attr as $name => $v) {
						if($name == 'content') continue;
						$xml .= ' '.htmlspecialchars($name).'="'.htmlspecialchars($v).'"';
					}//end foreach attr
					$xml .= '>'.htmlspecialchars($attr['content']).'</'.htmlspecialchars($node).'>'."\n";
				}//end foreach content
			}//end foreach
			$xml .= '		</Service>'."\n";
		}//end foreach services
		$xml .= '	</XRD>'."\n";
	}//end foreach

	$xml .= '</xrds:XRDS>'."\n";

	return $xml;
}


/**
 * Handle options page for XRDS-Simple.
 */
function xrds_options_page() {
	echo "<div class=\"wrap\">\n";
	echo "<h2>XRDS-Simple</h2>\n";

	echo '<h3>XRDS Document</h3>';
	echo '<pre>';
	echo htmlentities(xrds_write());
	echo '</pre>';

	echo '<h3>Registered Filters</h3>';
	global $wp_filter;
	if (array_key_exists('xrds_simple', $wp_filter) && !empty($wp_filter['xrds_simple'])) {
		echo '<ul>';
		foreach ($wp_filter['xrds_simple'] as $priority) {
			foreach ($priority as $idx => $data) {
				$function = $data['function'];
				if (is_array($function)) {
					list($class, $func) = $function;
					$function = "$class::$func";
				}
				echo '<li>'.$function.'</li>';
			}
		}
		echo '</ul>';
	} else {
		echo '<p>No registered filters.</p>';
	}

	echo '</div>';
}//end xrds_options_page


/**
 * Setup admin menu for XRDS.
 */
function xrds_admin_menu() {
	add_options_page('XRDS-Simple', 'XRDS-Simple', 8, 'xrds-simple', 'xrds_options_page');
}


/**
 * Parse the WordPress request.  If the request is for the XRDS document, handle it accordingly.
 *
 * @param object $wp WP instance for the current request
 */
function xrds_parse_request($wp) {
	$accept = explode(',', $_SERVER['HTTP_ACCEPT']);
	if(isset($_GET['xrds']) || in_array('application/xrds+xml', $accept)) {
		header('Content-type: application/xrds+xml');
		echo xrds_write();
		exit;
	} else {
		@header('X-XRDS-Location: '.get_bloginfo('home').'/?xrds');
		@header('X-Yadis-Location: '.get_bloginfo('home').'/?xrds');
	}
}


/**
 * Contribute the AtomPub Service to XRDS-Simple.
 *
 * @param array $xrds current XRDS-Simple array
 * @return array updated XRDS-Simple array
 */
function xrds_atompub_service($xrds) {
	$xrds = xrds_add_service($xrds, 'main', 'AtomPub Service', 
		array(
			'Type' => array( array('content' => 'http://www.w3.org/2007/app') ),
			'MediaType' => array( array('content' => 'application/atomsvc+xml') ),
			'URI' => array( array('content' => get_bloginfo('wpurl').'/wp-app.php/service' ) ),
		)
	);

	return $xrds;
}


/**
 * Check if data is well-formed XML.
 *
 * @param string $data XML structure to test
 * @return mixed FALSE if data is well-formed XML, XML error code otherwise
 */
function xrds_checkXML($data) {//returns FALSE if $data is well-formed XML, errorcode otherwise
	$rtrn = 0;
	$theParser = xml_parser_create();
	if(!xml_parse_into_struct($theParser,$data,$vals)) {
		$errorcode = xml_get_error_code($theParser);
		if($errorcode != XML_ERROR_NONE && $errorcode != 27)
			$rtrn = $errorcode;
	}//end if ! parse
	xml_parser_free($theParser);
	return $rtrn;
}

?>
