<?php
/**
 * Common functions.
 */

// -- WP Hooks
register_activation_hook('openid/openid.php', 'openid_activate_plugin');
register_deactivation_hook('openid/openid.php', 'openid_deactivate_plugin');
add_action( 'init', 'openid_activate_wpmu' ); // wpmu activation

// Add hooks to handle actions in WordPress
add_action( 'init', 'openid_textdomain' ); // load textdomain
	
// include internal stylesheet
add_action( 'wp_head', 'openid_style');

// parse request
add_action('parse_request', 'openid_parse_idib_request');

add_action( 'delete_user', 'delete_user_openids' );
add_action( 'cleanup_openid', 'openid_cleanup' );


// hooks for getting user data
add_filter('openid_auth_request_extensions', 'openid_add_sreg_extension', 10, 2);

add_filter( 'openid_user_data', 'openid_get_user_data_sreg', 10, 2);

add_filter( 'xrds_simple', 'openid_consumer_xrds_simple');



if (isset($wpmu_version)) {
	// wpmu doesn't support non-autoload options
	add_option( 'openid_associations', array(), null, 'yes' );
	add_option( 'openid_nonces', array(), null, 'yes' );
} else {
	add_option( 'openid_associations', array(), null, 'no' );
	add_option( 'openid_nonces', array(), null, 'no' );
}



/**
 * Set the textdomain for this plugin so we can support localizations.
 */
function openid_textdomain() {
	load_plugin_textdomain('openid', null, 'openid/lang');
}

/**
 * Soft verification of plugin activation
 *
 * @return boolean if the plugin is okay
 */
function openid_uptodate() {

	if( get_option('openid_db_revision') != OPENID_DB_REVISION ) {
		openid_enabled(false);
		openid_debug('Plugin database is out of date: ' . get_option('openid_db_revision') . ' != ' . OPENID_DB_REVISION);
		update_option('openid_plugin_enabled', false);
		return false;
	}
	openid_enabled(get_option('openid_plugin_enabled') == true);
	return openid_enabled();
}
// XXX - figure out when to perform  uptodate() checks and such (since late_bind is no more)


/**
 * Get the internal SQL Store.  If it is not already initialized, do so.
 *
 * @return WordPressOpenID_Store internal SQL store
 */
function openid_getStore() {
	static $store;

	if (!$store) {
		$store = new WordPress_OpenID_OptionStore();
	}

	return $store;
}


/**
 * Get the internal OpenID Consumer object.  If it is not already initialized, do so.
 *
 * @return Auth_OpenID_Consumer OpenID consumer object
 */
function openid_getConsumer() {
	static $consumer;

	if (!$consumer) {
		// setup source of randomness
		$f = @fopen( '/dev/urandom', 'r');
		if ($f === false) {
			define( 'Auth_OpenID_RAND_SOURCE', null );
		}

		set_include_path( dirname(__FILE__) . PATH_SEPARATOR . get_include_path() );
		require_once 'Auth/OpenID/Consumer.php';
		restore_include_path();

		$store = openid_getStore();
		$consumer = new Auth_OpenID_Consumer($store);
		if( null === $consumer ) {
			openid_error('OpenID consumer could not be created properly.');
			openid_enabled(false);
		}

	}

	return $consumer;
}


function openid_activate_wpmu() {
	global $wpmu_version;
	if ($wpmu_version && is_admin()) {
		if (get_option('openid_db_revision') != OPENID_DB_REVISION) {
			openid_activate_plugin();
		}
	}
}

/**
 * Called on plugin activation.
 *
 * @see register_activation_hook
 */
function openid_activate_plugin() {
	// if first time activation, set OpenID capability for administrators
	if (get_option('openid_plugin_revision') === false) {
		global $wp_roles;
		$role = $wp_roles->get_role('administrator');
		if ($role) $role->add_cap('use_openid_provider');
	}


	// Add custom OpenID options
	add_option( 'openid_enable_commentform', true );
	add_option( 'openid_plugin_enabled', true );
	add_option( 'openid_plugin_revision', 0 );
	add_option( 'openid_db_revision', 0 );
	add_option( 'openid_enable_approval', false );
	add_option( 'openid_enable_email_mapping', false );
	add_option( 'openid_xrds_returnto', true );
	add_option( 'openid_xrds_idib', true );
	add_option( 'openid_xrds_eaut', true );

	openid_create_tables();
	openid_migrate_old_data();

	wp_schedule_event(time(), 'hourly', 'cleanup_openid');



	// cleanup old option names
	delete_option( 'oid_db_revision' );
	delete_option( 'oid_db_version' );
	delete_option( 'oid_enable_approval' );
	delete_option( 'oid_enable_commentform' );
	delete_option( 'oid_enable_email_mapping' );
	delete_option( 'oid_enable_foaf' );
	delete_option( 'oid_enable_localaccounts' );
	delete_option( 'oid_enable_loginform' );
	delete_option( 'oid_enable_selfstyle' );
	delete_option( 'oid_enable_unobtrusive' );
	delete_option( 'oid_plugin_enabled' );
	delete_option( 'oid_plugin_revision' );
	delete_option( 'oid_plugin_version' );
	delete_option( 'oid_trust_root' );
}


/**
 * Cleanup expired nonces and associations from the OpenID store.
 */
function openid_cleanup() {
	$store =& openid_getStore();
	$store->cleanupNonces();
	$store->cleanupAssociations();
}


/**
 * Called on plugin deactivation.  Cleanup all transient data.
 *
 * @see register_deactivation_hook
 */
function openid_deactivate_plugin() {
	delete_option('openid_server_associations');
	delete_option('openid_server_nonces');
}


/*
 * Customer error handler for calls into the JanRain library
 */
function openid_customer_error_handler($errno, $errmsg, $filename, $linenum, $vars) {
	if( (2048 & $errno) == 2048 ) return;
	openid_error( "Library Error $errno: $errmsg in $filename :$linenum");
}


/**
 * Send the user to their OpenID provider to authenticate.
 *
 * @param Auth_OpenID_AuthRequest $auth_request OpenID authentication request object
 * @param string $trust_root OpenID trust root
 * @param string $return_to URL where the OpenID provider should return the user
 */
function openid_doRedirect($auth_request, $trust_root, $return_to) {
	if ($auth_request->shouldSendRedirect()) {
		$trust_root = trailingslashit($trust_root);
		$redirect_url = $auth_request->redirectURL($trust_root, $return_to);

		if (Auth_OpenID::isFailure($redirect_url)) {
			openid_error('Could not redirect to server: '.$redirect_url->message);
		} else {
			wp_redirect( $redirect_url );
		}
	} else {
		// Generate form markup and render it
		$request_message = $auth_request->getMessage($trust_root, $return_to, false);

		if (Auth_OpenID::isFailure($request_message)) {
			openid_error('Could not redirect to server: '.$request_message->message);
		} else {
			openid_repost($auth_request->endpoint->server_url, $request_message->toPostArgs());
		}
	}
}


/**
 * Finish OpenID Authentication.
 *
 * @return String authenticated identity URL, or null if authentication failed.
 */
function finish_openid_auth() {
	@session_start();

	$consumer = openid_getConsumer();
	$response = $consumer->complete($_SESSION['openid_return_to']);
	openid_response($response);
		
	switch( $response->status ) {
		case Auth_OpenID_CANCEL:
			openid_message(__('OpenID login was cancelled.', 'openid'));
			openid_status('error');
			break;

		case Auth_OpenID_FAILURE:
			openid_message(sprintf(__('OpenID login failed: %s', 'openid'), $response->message));
			openid_status('error');
			break;

		case Auth_OpenID_SUCCESS:
			openid_message(__('OpenID login successful', 'openid'));
			openid_status('success');

			$identity_url = $response->identity_url;
			$escaped_url = htmlspecialchars($identity_url, ENT_QUOTES);
			return $escaped_url;

		default:
			openid_message(__('Unknown Status. Bind not successful. This is probably a bug.', 'openid'));
			openid_status('error');
	}

	return null;
}


/**
 * Generate a unique WordPress username for the given OpenID URL.
 *
 * @param string $url OpenID URL to generate username for
 * @return string generated username
 */
function openid_generate_new_username($url) {
	$base = openid_normalize_username($url);
	$i='';
	while(true) {
		$username = openid_normalize_username( $base . $i );
		$user = get_userdatabylogin($username);
		if ( $user ) {
			$i++;
			continue;
		}
		return $username;
	}
}


/**
 * Normalize the OpenID URL into a username.  This includes rules like:
 *  - remove protocol prefixes like 'http://' and 'xri://'
 *  - remove the 'xri.net' domain for i-names
 *  - substitute certain characters which are not allowed by WordPress
 *
 * @param string $username username to be normalized
 * @return string normalized username
 */
function openid_normalize_username($username) {
	$username = preg_replace('|^https?://(xri.net/([^@]!?)?)?|', '', $username);
	$username = preg_replace('|^xri://([^@]!?)?|', '', $username);
	$username = preg_replace('|/$|', '', $username);
	$username = sanitize_user( $username );
	$username = preg_replace('|[^a-z0-9 _.\-@]+|i', '-', $username);
	return $username;
}


function openid_begin_consumer($url) {
	static $request;

	@session_start();
	if ($request == NULL) {
		set_error_handler( 'openid_customer_error_handler');

		if (is_email($url)) {
			$_SESSION['openid_login_email'] = $url;
			set_include_path( dirname(__FILE__) . PATH_SEPARATOR . get_include_path() );
			require_once 'Auth/Yadis/Email.php';
			$mapped_url = Auth_Yadis_Email_getID($url, trailingslashit(get_option('home')));
			if ($mapped_url) {
				$url = $mapped_url;
			}
		}

		$consumer = openid_getConsumer();
		$request = $consumer->begin($url);

		restore_error_handler();
	}

	return $request;
}


/**
 * Start the OpenID authentication process.
 *
 * @param string $claimed_url claimed OpenID URL
 * @param action $action OpenID action being performed
 * @param array $arguments array of additional arguments to be included in the 'return_to' URL
 * @uses apply_filters() Calls 'openid_auth_request_extensions' to gather extensions to be attached to auth request
 */
function openid_start_login( $claimed_url, $action, $arguments = null, $return_to = null) {
	if ( empty($claimed_url) ) return; // do nothing.
		
	$auth_request = openid_begin_consumer( $claimed_url );

	if ( null === $auth_request ) {
		openid_status('error');
		openid_message(sprintf(
			__('Could not discover an OpenID identity server endpoint at the url: %s', 'openid'), 
			htmlentities($claimed_url)
		));
		if( strpos( $claimed_url, '@' ) ) {
			openid_message(openid_message() . '<br />' . __('It looks like you entered an email address, but it '
				. 'was not able to be transformed into a valid OpenID.', 'openid'));
		}
		return;
	}
		
	// build return_to URL
	if (empty($return_to)) {
		$return_to = trailingslashit(get_option('home'));
	}
	$auth_request->return_to_args['openid_consumer'] = '1';
	$auth_request->return_to_args['action'] = $action;
	if (is_array($arguments) && !empty($arguments)) {
		foreach ($arguments as $k => $v) {
			if ($k && $v) {
				$auth_request->return_to_args[urlencode($k)] = urlencode($v);
			}
		}
	}
		
	$extensions = apply_filters('openid_auth_request_extensions', array(), $auth_request);
	foreach ($extensions as $e) {
		if (is_a($e, 'Auth_OpenID_Extension')) {
			$auth_request->addExtension($e);
		}
	}

	$trust_root = get_option('home');
	if (preg_match('/^https/', $return_to)) {
		$trust_root = preg_replace('/^http\:/', 'https:', $trust_root);
	}  
		
	$_SESSION['openid_return_to'] = $return_to;
	openid_doRedirect($auth_request, $trust_root, $return_to);
	exit(0);
}


/**
 * Build an SReg attribute query extension if we've never seen this OpenID before.
 */
function openid_add_sreg_extension($extensions, $auth_request) {
	if(!get_user_by_openid($auth_request->endpoint->claimed_id)) {
		set_include_path( dirname(__FILE__) . PATH_SEPARATOR . get_include_path() );
		require_once('Auth/OpenID/SReg.php');
		restore_include_path();

		$extensions[] = Auth_OpenID_SRegRequest::build(array(),array('nickname','email','fullname'));
	}

	return $extensions;
}


/**
 * Login user with specified identity URL.  This will find the WordPress user account connected to this
 * OpenID and set it as the current user.  Only call this function AFTER you've verified the identity URL.
 *
 * @param string $identity userID or OpenID to set as current user
 * @param boolean $remember should we set the "remember me" cookie
 * @return void
 */
function openid_set_current_user($identity, $remember = true) {
	if (is_numeric($identity)) {
		$user_id = $identity;
	} else {
		$user_id = get_user_by_openid($identity);
	}

	if (!$user_id) return;

	$user = set_current_user($user_id);
		
	if (function_exists('wp_set_auth_cookie')) {
		wp_set_auth_cookie($user->ID, $remember);
	} else {
		wp_setcookie($user->user_login, md5($user->user_pass), true, '', '', $remember);
	}

	do_action('wp_login', $user->user_login);
}


/**
 * Finish OpenID authentication. 
 *
 * @param string $action login action that is being performed
 * @uses do_action() Calls 'openid_finish_auth' hook action after processing the authentication response.
 */
function finish_openid($action) {
	$identity_url = finish_openid_auth();
	do_action('openid_finish_auth', $identity_url);
		
	global $action;
	$action = openid_status();
}


/**
 * Create a new WordPress user with the specified identity URL and user data.
 *
 * @param string $identity_url OpenID to associate with the newly
 * created account
 * @param array $user_data array of user data
 */
function openid_create_new_user($identity_url, &$user_data) {
	global $wpdb;

	// Identity URL is new, so create a user
	@include_once( ABSPATH . 'wp-admin/upgrade-functions.php');	// 2.1
	@include_once( ABSPATH . WPINC . '/registration-functions.php'); // 2.0.4

	// use email address for username if URL is from emailtoid.net
	$username = $identity_url;
	if (null != $_SESSION['openid_login_email'] and strpos($username, 'http://emailtoid.net/') == 0) {
		if($user_data['user_email'] == NULL) {
			$user_data['user_email'] = $_SESSION['openid_login_email'];
		}
		$username = $_SESSION['openid_login_email'];
		unset($_SESSION['openid_login_email']);
	}

	$user_data['user_login'] = $wpdb->escape( openid_generate_new_username($username) );
	$user_data['user_pass'] = substr( md5( uniqid( microtime() ) ), 0, 7);
	$user_id = wp_insert_user( $user_data );
		
	if( $user_id ) { // created ok

		$user_data['ID'] = $user_id;
		// XXX this all looks redundant, see openid_set_current_user

		$user = new WP_User( $user_id );

		if( ! wp_login( $user->user_login, $user_data['user_pass'] ) ) {
			openid_message(__('User was created fine, but wp_login() for the new user failed. This is probably a bug.', 'openid'));
			openid_action('error');
			openid_error(openid_message());
			return;
		}

		// notify of user creation
		wp_new_user_notification( $user->user_login );

		wp_clearcookie();
		wp_setcookie( $user->user_login, md5($user->user_pass), true, '', '', true );

		// Bind the provided identity to the just-created user
		openid_add_user_identity($user_id, $identity_url);

		openid_status('redirect');

		if ( !$user->has_cap('edit_posts') ) $redirect_to = '/wp-admin/profile.php';

	} else {
		// failed to create user for some reason.
		openid_message(__('OpenID authentication successful, but failed to create WordPress user. This is probably a bug.', 'openid'));
		openid_status('error');
		openid_error(openid_message());
	}

}


/**
 * Get user data for the given identity URL.  Data is returned as an associative array with the keys:
 *   ID, user_url, user_nicename, display_name
 *
 * Multiple soures of data may be available and are attempted in the following order:
 *   - OpenID Attribute Exchange      !! not yet implemented
 * 	 - OpenID Simple Registration
 * 	 - hCard discovery                !! not yet implemented
 * 	 - default to identity URL
 *
 * @param string $identity_url OpenID to get user data about
 * @return array user data
 * @uses apply_filters() Calls 'openid_user_data' to gather profile data associated with the identity URL
 */
function openid_get_user_data($identity_url) {
	$data = array(
			'ID' => null,
			'user_url' => $identity_url,
			'user_nicename' => $identity_url,
			'display_name' => $identity_url 
	);

	// create proper website URL if OpenID is an i-name
	if (preg_match('/^[\=\@\+].+$/', $identity_url)) {
		$data['user_url'] = 'http://xri.net/' . $identity_url;
	}

	$data = apply_filters('openid_user_data', $data, $identity_url);

	return $data;
}


/**
 * Retrieve user data from OpenID Attribute Exchange.
 *
 * @param string $identity_url OpenID to get user data about
 * @param reference $data reference to user data array
 * @see get_user_data
 */
function openid_get_user_data_ax($data, $identity_url) {
	// TODO implement attribute exchange
	return $data;
}


/**
 * Retrieve user data from OpenID Simple Registration.
 *
 * @param string $identity_url OpenID to get user data about
 * @param reference $data reference to user data array
 * @see get_user_data
 */
function openid_get_user_data_sreg($data, $identity_url) {
	require_once(dirname(__FILE__) . '/Auth/OpenID/SReg.php');
	$response = openid_response();
	$sreg_resp = Auth_OpenID_SRegResponse::fromSuccessResponse($response);
	$sreg = $sreg_resp->contents();

	if (!$sreg) return $data;

	if (array_key_exists('email', $sreg) && $sreg['email']) {
		$data['user_email'] = $sreg['email'];
	}

	if (array_key_exists('nickname', $sreg) && $sreg['nickname']) {
		$data['nickname'] = $sreg['nickname'];
		$data['user_nicename'] = $sreg['nickname'];
		$data['display_name'] = $sreg['nickname'];
	}

	if (array_key_exists('fullname', $sreg) && $sreg['fullname']) {
		$namechunks = explode( ' ', $sreg['fullname'], 2 );
		if( isset($namechunks[0]) ) $data['first_name'] = $namechunks[0];
		if( isset($namechunks[1]) ) $data['last_name'] = $namechunks[1];
		$data['display_name'] = $sreg['fullname'];
	}

	return $data;;
}


/**
 * Retrieve user data from hCard discovery.
 *
 * @param string $identity_url OpenID to get user data about
 * @param reference $data reference to user data array
 * @see get_user_data
 */
function openid_get_user_data_hcard($data, $identity_url) {
	// TODO implement hcard discovery
	return $data;
}


/**
 *
 * @uses apply_filters() Calls 'openid_consumer_return_urls' to collect return_to URLs to be included in XRDS document.
 */
function openid_consumer_xrds_simple($xrds) {

	if (get_option('openid_xrds_returnto')) {
		// OpenID Consumer Service
		$return_urls = array_unique(apply_filters('openid_consumer_return_urls', array()));
		if (!empty($return_urls)) {
			$xrds = xrds_add_simple_service($xrds, 'OpenID Consumer Service', 'http://specs.openid.net/auth/2.0/return_to', $return_urls);
		}
	}

	if (get_option('openid_xrds_idib')) {
		// Identity in the Browser Login Service
		$xrds = xrds_add_service($xrds, 'main', 'Identity in the Browser Login Service', 
			array(
				'Type' => array(array('content' => 'http://specs.openid.net/idib/1.0/login') ),
				'URI' => array(
					array(
						'simple:httpMethod' => 'POST',
						'content' => site_url('/wp-login.php', 'login_post'),
					),
				),
			)
		);

		// Identity in the Browser Indicator Service
		$xrds = xrds_add_simple_service($xrds, 'Identity in the Browser Indicator Service', 
			'http://specs.openid.net/idib/1.0/indicator', site_url('/') . '?openid_check_login');
	}

	return $xrds;
}


/**
 * Parse the WordPress request.  If the pagename is 'openid_consumer', then the request
 * is an OpenID response and should be handled accordingly.
 *
 * @param WP $wp WP instance for the current request
 */
function openid_parse_idib_request($wp) {
	if (array_key_exists('openid_check_login', $_REQUEST)) {
		echo is_user_logged_in() ? 'true' : 'false';
		exit;
	}
}


/**
 * Delete user.
 */
function delete_user_openids($userid) {
	openid_drop_all_identities($userid);
}


function openid_add_user_identity($user_id, $identity_url) {
	openid_add_identity($user_id, $identity_url);
}

function openid_status($new = null) {
	static $status;
	return ($new == null) ? $status : $status = $new;
}

function openid_message($new = null) {
	static $message;
	return ($new == null) ? $message : $message = $new;
}

function openid_response($new = null) {
	static $response;
	return ($new == null) ? $response : $response = $new;
}

function openid_enabled($new = null) {
	static $enabled;
	if ($enabled == null) $enabled = true;
	return ($new == null) ? $enabled : $enabled = $new;
}


/**
 * Send HTTP post through the user-agent.  If javascript is not supported, the 
 * user will need to click on a "continue" button.
 *
 * @param string $action form action (URL to POST form to)
 * @param array $parameters key-value pairs of parameters to include in the form
 * @uses do_action() Calls 'openid_page_head' hook action
 */
function openid_repost($action, $parameters) {
	$html = '
	<noscript><p>' . __('Since your browser does not support JavaScript, you must press the Continue button once to proceed.', 'openid') . '</p></noscript>
	<form action="'.$action.'" method="post">';

	foreach ($parameters as $k => $v) {
		if ($k == 'submit') continue;
		$html .= "\n" . '<input type="hidden" name="'.$k.'" value="' . htmlspecialchars(stripslashes($v), ENT_COMPAT, get_option('blog_charset')) . '" />';
	}
	$html .= '
		<noscript><div><input type="submit" value="' . __('Continue') . '" /></div></noscript>
	</form>
	
	<script type="text/javascript">
		document.write("<h2>'.__('Please Wait...', 'openid').'</h2>"); 
		document.forms[0].submit()
	</script>';

	openid_page($html, __('OpenID Authentication Redirect', 'openid'));
}


function openid_page($message, $title = '') {
	global $wp_locale;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" <?php if ( function_exists( 'language_attributes' ) ) language_attributes(); ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title><?php echo $title ?></title>
<?php
	wp_admin_css('install', true);
	if ( ($wp_locale) && ('rtl' == $wp_locale->text_direction) ) {
		wp_admin_css('login-rtl', true);
	}

	do_action('admin_head');
	do_action('openid_page_head');
?>
</head>
<body id="openid-page">
	<?php echo $message; ?>
</body>
</html>
<?php
	die();
}


/**
 * Enqueue required javascript libraries.
 *
 * @action: init
 **/
function openid_js_setup() {
	if (is_single() || is_comments_popup() || is_admin()) {
		wp_enqueue_script( 'jquery' );
		//wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script('jquery.textnode', openid_plugin_url() . '/f/jquery.textnode.min.js', 
			array('jquery'), OPENID_PLUGIN_REVISION);
		wp_enqueue_script('jquery.xpath', openid_plugin_url() . '/f/jquery.xpath.min.js', 
			array('jquery'), OPENID_PLUGIN_REVISION);

		$js_file = (defined('WP_DEBUG') && WP_DEBUG) ? 'openid.js' : 'openid.min.js';
		wp_enqueue_script('openid', openid_plugin_url() . '/f/' . $js_file, 
			array('jquery','jquery.textnode'), OPENID_PLUGIN_REVISION);
	}
}


/**
 * Get opend plugin URL, keeping in mind that for WordPress MU, it may be in either the normal
 * plugins directory or mu-plugins.
 */
function openid_plugin_url() {
	static $openid_plugin_url;

	if (!$openid_plugin_url) {
		if (defined('MUPLUGINDIR') && file_exists(ABSPATH . MUPLUGINDIR . '/openid')) {
			$openid_plugin_url =  trailingslashit(get_option('siteurl')) . MUPLUGINDIR . '/openid';
		} else {
			$openid_plugin_url =  plugins_url('openid');
		}
	}

	return $openid_plugin_url;
}


/**
 * Include internal stylesheet.
 *
 * @action: wp_head, login_head
 **/
function openid_style() {
	$css_file = (defined('WP_DEBUG') && WP_DEBUG) ? 'openid.css' : 'openid.min.css';
	$css_path = openid_plugin_url() . '/f/' . $css_file . '?ver=' . OPENID_PLUGIN_REVISION;

	echo '
		<link rel="stylesheet" type="text/css" href="'.clean_url($css_path).'" />';
}


/**
 * Add identity url to user.
 *
 * @param int $user_id user id
 * @param string $url identity url to add
 */
function openid_add_identity($user_id, $url) {
	global $wpdb;
	return $wpdb->query( wpdb_prepare('INSERT INTO '.openid_identity_table().' (user_id,url,hash) VALUES ( %s, %s, MD5(%s) )', $user_id, $url, $url) );
}


/**
 * Get OpenIDs for the specified user.
 *
 * @param int $user_id user id
 * @return array OpenIDs for the user
 */
function _get_user_openids($user_id) {
	global $wpdb;
	return $wpdb->get_col( wpdb_prepare('SELECT url FROM '.openid_identity_table().' WHERE user_id = %s', $user_id) );
}


/**
 * Format OpenID for display... namely, remove the fragment if present.
 * @param string $url url to display
 * @return url formatted for display
 */
function openid_display_identity($url) {
	return preg_replace('/#.+$/', '', $url);
}


/**
 * Remove identity url from user.
 *
 * @param int $user_id user id
 * @param string $identity_url identity url to remove
 */
function openid_drop_identity($user_id, $identity_url) {
	global $wpdb;
	return $wpdb->query( wpdb_prepare('DELETE FROM '.openid_identity_table().' WHERE user_id = %s AND url = %s', $user_id, $identity_url) );
}


/**
 * Remove all identity urls from user.
 *
 * @param int $user_id user id
 */
function openid_drop_all_identities($user_id) {
	global $wpdb;
	return $wpdb->query( wpdb_prepare('DELETE FROM '.openid_identity_table().' WHERE user_id = %s', $user_id ) );
}


function openid_error($msg) {
	error_log('[OpenID] ' . $msg);
}


function openid_debug($msg) {
	if (defined('WP_DEBUG') && WP_DEBUG) {
		openid_error($msg);
	}
}

?>
