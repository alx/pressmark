<?php

require_once 'Auth/OpenID/SReg.php';

add_action('openid_server_post_auth', 'openid_server_sreg_post_auth');

/**
 * See if the OpenID authentication request includes SReg and add additional hooks if so.
 */
function openid_server_sreg_post_auth($request) {
	$sreg_request = Auth_OpenID_SRegRequest::fromOpenIDRequest($request);
	if ($sreg_request) {
		$GLOBALS['openid_server_sreg_request'] = $sreg_request;
		add_action('openid_server_trust_form', 'openid_server_sreg_trust_form');
		add_action('openid_server_trust_submit', 'openid_server_sreg_trust_submit', 10, 2);
		add_filter('openid_server_store_trusted_site', 'openid_server_sreg_store_trusted_site');
		add_action('openid_server_auth_response', 'openid_server_sreg_auth_response' );
	}
}


/**
 * Add SReg input fields to the OpenID Trust Form
 */
function openid_server_sreg_trust_form() {
	$sreg_request = $GLOBALS['openid_server_sreg_request'];
	$sreg_fields = $sreg_request->allRequestedFields();

	if (!empty($sreg_fields)) {
		$display_fields = array();
		foreach ($sreg_fields as $field) {
			$value = openid_server_sreg_from_profile($field);
			if (!empty($value)) {
				$display_fields[] = strtolower($GLOBALS['Auth_OpenID_sreg_data_fields'][$field]);
			}
		}

		if (!empty($display_fields)) {
			$fields = openid_server_sreg_field_string($display_fields);

			echo '
			<p class="trust_form_add" style="padding: 0">
				<input type="checkbox" id="include_sreg" name="include_sreg" checked="checked" style="display: block; float: left; margin: 0.8em;" />
				<label for="include_sreg" style="display: block; padding: 0.5em 2em;">'.sprintf(__('Also grant access to see my %s.', 'openid'), $fields) . '</label>
			</p>';
		}

	}
}

function openid_server_sreg_field_string($fields, $string = '') {
	if (empty($fields)) return $string;

	if (empty($string)) {
		if (sizeof($fields) == 2) 
			return join(' and ', $fields);
		$string = array_shift($fields);
	} else if (sizeof($fields) == 1) {
		$string .= ', and ' . array_shift($fields);
	} else if (sizeof($fields) > 1) {
		$string .= ', ' . array_shift($fields);
	}

	return openid_server_sreg_field_string($fields, $string);
}


/**
 * Based on input from the OpenID trust form, prep data to be included in the authentication response
 */
function openid_server_sreg_trust_submit($trust, $request) {
	if ($trust && $_REQUEST['include_sreg'] == 'on') {
		$GLOBALS['openid_server_sreg_trust'] = true;
	} else {
		$GLOBALS['openid_server_sreg_trust'] = false;
	}
}


/**
 * Store user's decision on whether to release attributes to the site.
 */
function openid_server_sreg_store_trusted_site($site) {
	$site['release_attributes'] = $GLOBALS['openid_server_sreg_trust'];
	return $site;
}


/**
 * Attach SReg response to authentication response.
 */
function openid_server_sreg_auth_response($response) {
	$user = wp_get_current_user();

	// should we include SREG in the response?
	$include_sreg = false;

	if (isset($GLOBALS['openid_server_sreg_trust'])) {
		$include_sreg = $GLOBALS['openid_server_sreg_trust'];
	} else {
		$trusted_sites = get_usermeta($user->ID, 'openid_trusted_sites');
		$request = $response->request;
		$site_hash = md5($request->trust_root);
		if (is_array($trusted_sites) && array_key_exists($site_hash, $trusted_sites)) {
			$include_sreg = $trusted_sites[$site_hash]['release_attributes'];
		}
	}

	if ($include_sreg) {
		$sreg_data = array();
		foreach ($GLOBALS['Auth_OpenID_sreg_data_fields'] as $field => $name) {
			$sreg_data[$field] = openid_server_sreg_from_profile($field);
		}

		$sreg_response = Auth_OpenID_SRegResponse::extractResponse($GLOBALS['openid_server_sreg_request'], $sreg_data);
		$response->addExtension($sreg_response);
	}

	return $response;
}


/**
 * Try to pre-populate SReg data from user's profile.  The following fields 
 * are not handled by the plugin: dob, gender, postcode, country, and language.
 * Other plugins may provide this data by implementing the filter 
 * openid_server_sreg_${fieldname}.
 *
 * @uses apply_filters() Calls 'openid_server_sreg_*' before returning sreg values, 
 *       where '*' is the name of the sreg attribute.
 */
function openid_server_sreg_from_profile($field) {
	$user = wp_get_current_user();
	$value = '';

	switch($field) {
		case 'nickname':
			$value = get_usermeta($user->ID, 'nickname');
			break;

		case 'email':
			$value = $user->user_email;
			break;

		case 'fullname':
			$value = get_usermeta($user->ID, 'display_name');
			break;
	}

	return apply_filters('openid_server_sreg_' . $field, $value, $user->ID);
}


?>
