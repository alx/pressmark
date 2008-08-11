<?php
/**
 * logic.php
 *
 * Dual License: GPLv2 & Modified BSD
 */
if (!class_exists('WordPressOpenID_Logic')):

/**
 * Basic logic for wp-openid plugin.
 */
class WordPressOpenID_Logic {

	/**
	 * Soft verification of plugin activation
	 *
	 * @return boolean if the plugin is okay
	 */
	function uptodate() {
		global $openid;

		$openid->log->debug('checking if database is up to date');
		if( get_option('oid_db_revision') != WPOPENID_DB_REVISION ) {
			$openid->enabled = false;
			$openid->log->warning('Plugin database is out of date: ' . get_option('oid_db_revision') . ' != ' . WPOPENID_DB_REVISION);
			update_option('oid_plugin_enabled', false);
			return false;
		}
		$openid->enabled = (get_option('oid_plugin_enabled') == true );
		return $openid->enabled;
	}


	/**
	 * Get the internal SQL Store.  If it is not already initialized, do so.
	 *
	 * @return WordPressOpenID_Store internal SQL store
	 */
	function getStore() {
		global $openid;

		if (!isset($openid->store)) {
			set_include_path( dirname(__FILE__) . PATH_SEPARATOR . get_include_path() );
			require_once 'store.php';

			$openid->store = new WordPressOpenID_Store($openid);
			if (null === $openid->store) {
				$openid->log->err('OpenID store could not be created properly.');
				$openid->enabled = false;
			}
		}

		return $openid->store;
	}


	/**
	 * Get the internal OpenID Consumer object.  If it is not already initialized, do so.
	 *
	 * @return Auth_OpenID_Consumer OpenID consumer object
	 */
	function getConsumer() {
		global $openid;

		if (!isset($openid->consumer)) {
			set_include_path( dirname(__FILE__) . PATH_SEPARATOR . get_include_path() );
			require_once 'Auth/OpenID/Consumer.php';

			$store = WordPressOpenID_Logic::getStore();
			$openid->consumer = new Auth_OpenID_Consumer($store);
			if( null === $openid->consumer ) {
				$openid->log->err('OpenID consumer could not be created properly.');
				$openid->enabled = false;
			}
		}

		return $openid->consumer;
	}


	/**
	 * Initialize required store and consumer and make a few sanity checks.  This method
	 * does a lot of the heavy lifting to get everything initialized, so we don't call it
	 * until we actually need it.
	 */
	function late_bind($reload = false) {
		global $wpdb, $openid;
		openid_init();

		$openid->log->debug('beginning late binding');

		$openid->enabled = true; // Be Optimistic
		if( $openid->bind_done && !$reload ) {
			$openid->log->debug('we\'ve already done the late bind... moving on');
			return WordPressOpenID_Logic::uptodate();
		}
		$openid->bind_done = true;

		$f = @fopen( '/dev/urandom', 'r');
		if ($f === false) {
			define( 'Auth_OpenID_RAND_SOURCE', null );
		}
			
		// include required JanRain OpenID library files
		set_include_path( dirname(__FILE__) . PATH_SEPARATOR . get_include_path() );
		$openid->log->debug('temporary include path for importing = ' . get_include_path());
		require_once('Auth/OpenID/Discover.php');
		require_once('Auth/OpenID/DatabaseConnection.php');
		require_once('Auth/OpenID/MySQLStore.php');
		require_once('Auth/OpenID/Consumer.php');
		require_once('Auth/OpenID/SReg.php');
		restore_include_path();

		$openid->log->debug("Bootstrap -- checking tables");
		if( $openid->enabled ) {

			$store =& WordPressOpenID_Logic::getStore();
			if (!$store) return; 	// something broke
			$openid->enabled = $store->check_tables();

			if( !WordPressOpenID_Logic::uptodate() ) {
				update_option('oid_plugin_enabled', true);
				update_option('oid_plugin_revision', WPOPENID_PLUGIN_REVISION );
				update_option('oid_db_revision', WPOPENID_DB_REVISION );
				WordPressOpenID_Logic::uptodate();
			}
		} else {
			$openid->message = 'WPOpenID Core is Disabled!';
			update_option('oid_plugin_enabled', false);
		}

		return $openid->enabled;
	}


	/**
	 * Called on plugin activation.
	 *
	 * @see register_activation_hook
	 */
	function activate_plugin() {
		$start_mem = memory_get_usage();
		global $openid;
		openid_init();

		$store =& WordPressOpenID_Logic::getStore();
		$store->create_tables();

		wp_schedule_event(time(), 'hourly', 'cleanup_openid');
		//$openid->log->warning("activation memory usage: " . (int)((memory_get_usage() - $start_mem) / 1000));
	}

	function cleanup_nonces() {
		global $openid;
		openid_init();
		$store =& WordPressOpenID_Logic::getStore();
		$store->cleanupNonces();
	}


	/**
	 * Called on plugin deactivation.  Cleanup all transient tables.
	 *
	 * @see register_deactivation_hook
	 */
	function deactivate_plugin() {
		set_include_path( dirname(__FILE__) . PATH_SEPARATOR . get_include_path() );
		require_once 'store.php';
		WordPressOpenID_Store::destroy_tables();
	}


	/*
	 * Customer error handler for calls into the JanRain library
	 */
	function customer_error_handler($errno, $errmsg, $filename, $linenum, $vars) {
		global $openid;

		if( (2048 & $errno) == 2048 ) return;
		$openid->log->notice( "Library Error $errno: $errmsg in $filename :$linenum");
	}


	/**
	 * If we're doing openid authentication ($_POST['openid_url'] is set), start the consumer & redirect
	 * Otherwise, return and let WordPress handle the login and/or draw the form.
	 *
	 * @param string $username username provided in login form
	 */
	function wp_authenticate( &$username ) {
		global $openid;

		if( !empty( $_POST['openid_url'] ) ) {
			if( !WordPressOpenID_Logic::late_bind() ) return; // something is broken
			$redirect_to = '';
			if( !empty( $_REQUEST['redirect_to'] ) ) $redirect_to = $_REQUEST['redirect_to'];
			WordPressOpenID_Logic::start_login( $_POST['openid_url'], 'login', array('redirect_to' => $redirect_to) );
		}
		if( !empty( $openid->message ) ) {
			global $error;
			$error = $openid->message;
		}
	}


	/**
	 * Handle OpenID profile management.
	 */
	function openid_profile_management() {
		global $wp_version, $openid;
		openid_init();
		
		if( !isset( $_REQUEST['action'] )) return;
			
		$openid->action = $_REQUEST['action'];
			
		require_once(ABSPATH . 'wp-admin/admin-functions.php');

		if ($wp_version < '2.3') {
			require_once(ABSPATH . 'wp-admin/admin-db.php');
			require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
		}

		auth_redirect();
		nocache_headers();
		get_currentuserinfo();

		if( !WordPressOpenID_Logic::late_bind() ) return; // something is broken
			
		switch( $openid->action ) {
			case 'add_identity':
				check_admin_referer('wp-openid-add_identity');

				$user = wp_get_current_user();

				$store =& WordPressOpenID_Logic::getStore();
				$auth_request = WordPressOpenID_Logic::begin_consumer($_POST['openid_url']);

				$userid = $store->get_user_by_identity($auth_request->endpoint->claimed_id);

				if ($userid) {
					global $error;
					if ($user->ID == $userid) {
						$error = 'You already have this Identity URL!';
					} else {
						$error = 'This Identity URL is already connected to another user.';
					}
					return;
				}

				WordPressOpenID_Logic::start_login($_POST['openid_url'], 'verify');
				break;

			case 'drop_identity':  // Remove a binding.
				WordPressOpenID_Logic::_profile_drop_identity($_REQUEST['id']);
				break;
		}
	}


	/**
	 * Remove identity URL from current user account.
	 *
	 * @param int $id id of identity URL to remove
	 */
	function _profile_drop_identity($id) {
		global $openid;

		$user = wp_get_current_user();

		if( !isset($id)) {
			$openid->message = 'Identity url delete failed: ID paramater missing.';
			$openid->action = 'error';
			return;
		}

		$store =& WordPressOpenID_Logic::getStore();
		$deleted_identity_url = $store->get_identities($user->ID, $id);
		if( FALSE === $deleted_identity_url ) {
			$openid->message = 'Identity url delete failed: Specified identity does not exist.';
			$openid->action = 'error';
			return;
		}

		$identity_urls = $store->get_identities($user->ID);
		if (sizeof($identity_urls) == 1 && !$_REQUEST['confirm']) {
			$openid->message = 'This is your last identity URL.  Are you sure you want to delete it? Doing so may interfere with your ability to login.<br /><br /> '
			. '<a href="?confirm=true&'.$_SERVER['QUERY_STRING'].'">Yes I\'m sure.  Delete it</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'
			. '<a href="?page=openid">No, don\'t delete it.</a>';
			$openid->action = 'warning';
			return;
		}

		check_admin_referer('wp-openid-drop-identity_'.$deleted_identity_url);
			

		if( $store->drop_identity($user->ID, $id) ) {
			$openid->message = 'Identity url delete successful. <b>' . $deleted_identity_url
			. '</b> removed.';
			$openid->action = 'success';

			// ensure that profile URL is still a verified Identity URL
			set_include_path( dirname(__FILE__) . PATH_SEPARATOR . get_include_path() );
			require_once 'Auth/OpenID.php';
			if ($GLOBALS['wp_version'] >= '2.3') {
				require_once(ABSPATH . 'wp-admin/includes/admin.php');
			} else {
				require_once(ABSPATH . WPINC . '/registration.php');
			}
			$identities = $store->get_identities($user->ID);
			$current_url = Auth_OpenID::normalizeUrl($user->user_url);

			$verified_url = false;
			if (!empty($identities)) {
				foreach ($identities as $id) {
					if ($id['url'] == $current_url) {
						$verified_url = true;
						break;
					}
				}

				if (!$verified_url) {
					$user->user_url = $identities[0]['url'];
					wp_update_user( get_object_vars( $user ));
					$openid->message .= '<br /><strong>Note:</strong> For security reasons, your profile URL has been updated to match your Identity URL.';
				}
			}
			return;
		}
			
		$openid->message = 'Identity url delete failed: Unknown reason.';
		$openid->action = 'error';
	}


	/**
	 * Send the user to their OpenID provider to authenticate.
	 *
	 * @param Auth_OpenID_AuthRequest $auth_request OpenID authentication request object
	 * @param string $trust_root OpenID trust root
	 * @param string $return_to URL where the OpenID provider should return the user
	 */
	function doRedirect($auth_request, $trust_root, $return_to) {
		global $openid;

		if ($auth_request->shouldSendRedirect()) {
			$trust_root = trailingslashit($trust_root);
			$redirect_url = $auth_request->redirectURL($trust_root, $return_to);

			if (Auth_OpenID::isFailure($redirect_url)) {
				$openid->log->error('Could not redirect to server: '.$redirect_url->message);
			} else {
				wp_redirect( $redirect_url );
			}
		} else {
			// Generate form markup and render it
			$request_message = $auth_request->getMessage($trust_root, $return_to, false);

			if (Auth_OpenID::isFailure($request_message)) {
				$openid->log->error('Could not redirect to server: '.$request_message->message);
			} else {
				WordPressOpenID_Interface::repost($auth_request->endpoint->server_url, $request_message->toPostArgs());
			}
		}
	}


	/**
	 * Finish OpenID Authentication.
	 *
	 * @return String authenticated identity URL, or null if authentication failed.
	 */
	function finish_openid_auth() {
		global $openid;

		//set_error_handler( array('WordPressOpenID_Logic', 'customer_error_handler'));
		$consumer = WordPressOpenID_Logic::getConsumer();
		$openid->response = $consumer->complete($_SESSION['oid_return_to']);
		//restore_error_handler();
			
		switch( $openid->response->status ) {
			case Auth_OpenID_CANCEL:
				$openid->message = 'OpenID assertion cancelled';
				$openid->action = 'error';
				break;

			case Auth_OpenID_FAILURE:
				$openid->message = 'OpenID assertion failed: ' . $openid->response->message;
				$openid->action = 'error';
				break;

			case Auth_OpenID_SUCCESS:
				$openid->message = 'OpenID assertion successful';
				$openid->action = 'success';

				$identity_url = $openid->response->identity_url;
				$escaped_url = htmlspecialchars($identity_url, ENT_QUOTES);
				$openid->log->notice('Got back identity URL ' . $escaped_url);

				if ($openid->response->endpoint->canonicalID) {
					$openid->log->notice('XRI CanonicalID: ' . $openid->response->endpoint->canonicalID);
				}

				return $escaped_url;

			default:
				$openid->message = 'Unknown Status. Bind not successful. This is probably a bug';
				$openid->action = 'error';
		}

		return null;
	}


	/**
	 * Generate a unique WordPress username for the given OpenID URL.
	 *
	 * @param string $url OpenID URL to generate username for
	 * @return string generated username
	 */
	function generate_new_username($url) {
		global $openid;

		$base = WordPressOpenID_Logic::normalize_username($url);
		$i='';
		while(true) {
			$username = WordPressOpenID_Logic::normalize_username( $base . $i );
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
	function normalize_username($username) {
		$username = preg_replace('|^https?://(xri.net/([^@]!?)?)?|', '', $username);
		$username = preg_replace('|^xri://([^@]!?)?|', '', $username);
		$username = preg_replace('|/$|', '', $username);
		$username = sanitize_user( $username );
		$username = preg_replace('|[^a-z0-9 _.\-@]+|i', '-', $username);
		return $username;
	}

	function begin_consumer($url) {
		global $openid_auth_request;

		if ($openid_auth_request == NULL) {
			set_error_handler( array('WordPressOpenID_Logic', 'customer_error_handler'));

			if (WordPressOpenID_Logic::isValidEmail($url)) {
				$_SESSION['openid_login_email'] = $url;
				set_include_path( dirname(__FILE__) . PATH_SEPARATOR . get_include_path() );
				require_once 'Auth/Yadis/Email.php';
				$mapped_url = Auth_Yadis_Email_getID($url, trailingslashit(get_option('home')));
				if ($mapped_url) {
					$url = $mapped_url;
				}
			}

			$consumer = WordPressOpenID_Logic::getConsumer();
			$openid_auth_request = $consumer->begin($url);

			restore_error_handler();
		}

		return $openid_auth_request;;
	}

	function isValidEmail($email) {
	    return eregi("^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$", $email);
	}


	/**
	 * Start the OpenID authentication process.
	 *
	 * @param string $claimed_url claimed OpenID URL
	 * @param action $action OpenID action being performed
	 * @param array $arguments array of additional arguments to be included in the 'return_to' URL
	 */
	function start_login( $claimed_url, $action, $arguments = null) {
		global $openid;

		if ( empty($claimed_url) ) return; // do nothing.
			
		if( !WordPressOpenID_Logic::late_bind() ) return; // something is broken

		$auth_request = WordPressOpenID_Logic::begin_consumer( $claimed_url );

		if ( null === $auth_request ) {
			$openid->action = 'error';
			$openid->message = 'Could not discover an OpenID identity server endpoint at the url: '
			. htmlentities( $claimed_url );
			if( strpos( $claimed_url, '@' ) ) {
				$openid->message .= '<br />It looks like you entered an email address, but it '
					. 'was not able to be transformed into a valid OpenID.';
			}
			$openid->log->debug('OpenIDConsumer: ' . $openid->message );
			return;
		}
			
		$openid->log->debug('OpenIDConsumer: Is an OpenID url. Starting redirect.');


		// build return_to URL
		$return_to = trailingslashit(get_option('home'));
		$auth_request->return_to_args['openid_consumer'] = '1';
		$auth_request->return_to_args['action'] = $action;
		if (is_array($arguments) && !empty($arguments)) {
			foreach ($arguments as $k => $v) {
				if ($k && $v) {
					$auth_request->return_to_args[urlencode($k)] = urlencode($v);
				}
			}
		}
			

		/* If we've never heard of this url before, do attribute query */
		$store =& WordPressOpenID_Logic::getStore();
		if( $store->get_user_by_identity( $auth_request->endpoint->identity_url ) == NULL ) {
			$attribute_query = true;
		}
		if ($attribute_query) {
			// SREG
			$sreg_request = Auth_OpenID_SRegRequest::build(array(),array('nickname','email','fullname'));
			if ($sreg_request) $auth_request->addExtension($sreg_request);

			// AX
		}
			
		$_SESSION['oid_return_to'] = $return_to;
		WordPressOpenID_Logic::doRedirect($auth_request, get_option('home'), $return_to);
		exit(0);
	}


	/**
	 * Intercept login requests on wp-login.php if they include an 'openid_url' 
	 * value and start OpenID authentication.  This hook is only necessary in 
	 * WordPress 2.5.x because it has the 'wp_authenticate' action call in the 
	 * wrong place.
	 */
	function wp_login_openid() {
		global $wp_version;

		// this is only needed in WordPress 2.5.x
		if (strpos($wp_version, '2.5') != 0) {
			return;
		}

		$self = basename( $GLOBALS['pagenow'] );
			
		if ($self == 'wp-login.php' && !empty($_POST['openid_url'])) {
			if (function_exists('wp_signon')) {
				wp_signon(array('user_login'=>'openid', 'user_password'=>'openid'));
			}
		}
	}


	/**
	 * Login user with specified identity URL.  This will find the WordPress user account connected to this
	 * OpenID and set it as the current user.  Only call this function AFTER you've verified the identity URL.
	 *
	 * @param string $identity userID or OpenID to set as current user
	 * @param boolean $remember should we set the "remember me" cookie
	 * @return void
	 */
	function set_current_user($identity, $remember = true) {
		global $openid;

		if (is_numeric($identity)) {
			$user_id = $identity;
		} else {
			$store =& WordPressOpenID_Logic::getStore();
			$user_id = $store->get_user_by_identity( $identity );
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
	 * Finish OpenID authentication.  After doing the basic stuff, the action method is called to complete the
	 * process.  Action methods are based on the action name passed in and are of the form
	 * '_finish_openid_$action'.  Action methods are passed the verified identity URL, or null if OpenID
	 * authentication failed.
	 *
	 * @param string $action login action that is being performed
	 */
	function finish_openid($action) {
		global $openid;

		if( !WordPressOpenID_Logic::late_bind() ) return; // something is broken
			
		$identity_url = WordPressOpenID_Logic::finish_openid_auth();
			
		if (!empty($action) && method_exists('WordPressOpenID_Logic', '_finish_openid_' . $action)) {
			call_user_func(array('WordPressOpenID_Logic', '_finish_openid_' . $action), $identity_url);
		}
			
		global $action;
		$action = $openid->action;
	}


	/**
	 * Action method for completing the 'login' action.  This action is used when a user is logging in from
	 * wp-login.php.
	 *
	 * @param string $identity_url verified OpenID URL
	 */
	function _finish_openid_login($identity_url) {
		global $openid;

		$redirect_to = urldecode($_REQUEST['redirect_to']);
			
		if (empty($identity_url)) {
			WordPressOpenID_Logic::set_error('Unable to authenticate OpenID.');
			wp_safe_redirect(get_option('siteurl') . '/wp-login.php');
			exit;
		}
			
		WordPressOpenID_Logic::set_current_user($identity_url);

		if (!is_user_logged_in()) {
			if ( get_option('users_can_register') ) {
				$user_data =& WordPressOpenID_Logic::get_user_data($identity_url);
				$user = WordPressOpenID_Logic::create_new_user($identity_url, $user_data);
				WordPressOpenID_Logic::set_current_user($user->ID);
			} else {
				// TODO - Start a registration loop in WPMU.
				WordPressOpenID_Logic::set_error('OpenID authentication valid, but unable '
				. 'to find a WordPress account associated with this OpenID.<br /><br />'
				. 'Enable "Anyone can register" to allow creation of new accounts via OpenID.');
				wp_safe_redirect(get_option('siteurl') . '/wp-login.php');
				exit;
			}

		}
			
		if (empty($redirect_to)) {
			$redirect_to = 'wp-admin/';
		}
		if ($redirect_to == 'wp-admin/') {
			if (!current_user_can('edit_posts')) {
				$redirect_to .= 'profile.php';
			}
		}
		if (!preg_match('#^(http|\/)#', $redirect_to)) {
			$wpp = parse_url(get_option('siteurl'));
			$redirect_to = $wpp['path'] . '/' . $redirect_to;
		}

		if (function_exists('wp_safe_redirect')) {
			wp_safe_redirect( $redirect_to );
		} else {
			wp_redirect( $redirect_to );
		}
			
		exit;
	}


	/**
	 * Action method for completing the 'comment' action.  This action is used when leaving a comment.
	 *
	 * @param string $identity_url verified OpenID URL
	 */
	function _finish_openid_comment($identity_url) {
		global $openid;

		if (empty($identity_url)) {
			WordPressOpenID_Interface::repost_comment_anonymously($_SESSION['oid_comment_post']);
		}
			
		WordPressOpenID_Logic::set_current_user($identity_url);
			
		if (is_user_logged_in()) {
			// simulate an authenticated comment submission
			$_SESSION['oid_comment_post']['author'] = null;
			$_SESSION['oid_comment_post']['email'] = null;
			$_SESSION['oid_comment_post']['url'] = null;
		} else {
			// try to get user data from the verified OpenID
			$user_data =& WordPressOpenID_Logic::get_user_data($identity_url);

			if (!empty($user_data['display_name'])) {
				$_SESSION['oid_comment_post']['author'] = $user_data['display_name'];
			}
			if (!empty($user_data['user_email'])) {
				$_SESSION['oid_comment_post']['email'] = $user_data['user_email'];
			}
			$_SESSION['oid_comment_post']['url'] = $identity_url;
		}
			
		// record that we're about to post an OpenID authenticated comment.
		// We can't actually record it in the database until after the repost below.
		$_SESSION['oid_posted_comment'] = true;

		$wpp = parse_url(get_option('siteurl'));
		WordPressOpenID_Interface::repost($wpp['path'] . '/wp-comments-post.php',
		array_filter($_SESSION['oid_comment_post']));
	}


	/**
	 * Action method for completing the 'verify' action.  This action is used adding an identity URL to a
	 * WordPress user through the admin interface.
	 *
	 * @param string $identity_url verified OpenID URL
	 */
	function _finish_openid_verify($identity_url) {
		global $openid;

		$user = wp_get_current_user();
		if (empty($identity_url)) {
			WordPressOpenID_Logic::set_error('Unable to authenticate OpenID.');
		} else {
			$store =& WordPressOpenID_Logic::getStore();
			if( !$store->insert_identity($user->ID, $identity_url) ) {
				WordPressOpenID_Logic::set_error('OpenID assertion successful, but this URL is already claimed by '
				. 'another user on this blog. This is probably a bug. ' . $identity_url);
			} else {
				$openid->action = 'success';
				$openid->message = "Successfully added Identity URL: $identity_url.";
				
				// ensure that profile URL is a verified Identity URL
				set_include_path( dirname(__FILE__) . PATH_SEPARATOR . get_include_path() );
				require_once 'Auth/OpenID.php';
				if ($GLOBALS['wp_version'] >= '2.3') {
					require_once(ABSPATH . 'wp-admin/includes/admin.php');
				} else {
					require_once(ABSPATH . WPINC . '/registration.php');
				}
				$identities = $store->get_identities($user->ID);
				$current_url = Auth_OpenID::normalizeUrl($user->user_url);

				$verified_url = false;
				if (!empty($identities)) {
					foreach ($identities as $id) {
						if ($id['url'] == $current_url) {
							$verified_url = true;
							break;
						}
					}

					if (!$verified_url) {
						$user->user_url = $identity_url;
						wp_update_user( get_object_vars( $user ));
						$openid->message .= '<br /><strong>Note:</strong> For security reasons, your profile URL has been updated to match your Identity URL.';
					}
				}
			}
		}

		$_SESSION['oid_message'] = $openid->message;
		$_SESSION['oid_action'] = $openid->action;	
		$wpp = parse_url(get_option('siteurl'));
		$redirect_to = $wpp['path'] . '/wp-admin/' . (current_user_can('edit_users') ? 'users.php' : 'profile.php') . '?page=openid';
		if (function_exists('wp_safe_redirect')) {
			wp_safe_redirect( $redirect_to );
		} else {
			wp_redirect( $redirect_to );
		}
		exit;
	}


	/**
	 * If last comment was authenticated by an OpenID, record that in the database.
	 *
	 * @param string $location redirect location
	 * @param object $comment comment that was just left
	 * @return string redirect location
	 */
	function comment_post_redirect($location, $comment) {
		global $openid;

		if ($_SESSION['oid_posted_comment']) {
			WordPressOpenID_Logic::set_comment_openid($comment->comment_ID);
			$_SESSION['oid_posted_comment'] = null;
		}
			
		return $location;
	}


	/**
	 * Create a new WordPress user with the specified identity URL and user data.
	 *
	 * @param string $identity_url OpenID to associate with the newly
	 * created account
	 * @param array $user_data array of user data
	 */
	function create_new_user($identity_url, &$user_data) {
		global $wpdb, $openid;

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

		$user_data['user_login'] = $wpdb->escape( WordPressOpenID_Logic::generate_new_username($username) );
		$user_data['user_pass'] = substr( md5( uniqid( microtime() ) ), 0, 7);
		$user_id = wp_insert_user( $user_data );
			
		$openid->log->debug("wp_create_user( $user_data )  returned $user_id ");

		if( $user_id ) { // created ok

			$user_data['ID'] = $user_id;
			// XXX this all looks redundant, see WordPressOpenID_Logic::set_current_user

			$openid->log->debug("OpenIDConsumer: Created new user $user_id : " . $user_data['user_login'] . " and metadata: "
			. var_export( $user_data, true ) );

			$user = new WP_User( $user_id );

			if( ! wp_login( $user->user_login, $user_data['user_pass'] ) ) {
				$openid->message = 'User was created fine, but wp_login() for the new user failed. '
				. 'This is probably a bug.';
				$openid->action= 'error';
				$openid->log->err( $openid->message );
				return;
			}

			// notify of user creation
			wp_new_user_notification( $user->user_login );

			wp_clearcookie();
			wp_setcookie( $user->user_login, md5($user->user_pass), true, '', '', true );

			// Bind the provided identity to the just-created user
			global $userdata;
			$userdata = get_userdata( $user_id );
			$store = WordPressOpenID_Logic::getStore();
			$store->insert_identity($user_id, $identity_url);

			$openid->action = 'redirect';

			if ( !$user->has_cap('edit_posts') ) $redirect_to = '/wp-admin/profile.php';

		} else {
			// failed to create user for some reason.
			$openid->message = 'OpenID authentication successful, but failed to create WordPress user. '
			. 'This is probably a bug.';
			$openid->action= 'error';
			$openid->log->error( $openid->message );
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
	 */
	function get_user_data($identity_url) {
		global $openid;

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

		$data = apply_filters('openid_user_data', $identity_url, $data);

		return $data;
	}


	/**
	 * Retrieve user data from OpenID Attribute Exchange.
	 *
	 * @param string $identity_url OpenID to get user data about
	 * @param reference $data reference to user data array
	 * @see get_user_data
	 */
	function get_user_data_ax($identity_url, $data) {
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
	function get_user_data_sreg($identity_url, $data) {
		global $openid;

		$sreg_resp = Auth_OpenID_SRegResponse::fromSuccessResponse($openid->response);
		$sreg = $sreg_resp->contents();

		$openid->log->debug(var_export($sreg, true));
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
	function get_user_data_hcard($identity_url, $data) {
		// TODO implement hcard discovery
		return $data;
	}

	/**
	 * Retrieve user data from comment form.
	 *
	 * @param string $identity_url OpenID to get user data about
	 * @param reference $data reference to user data array
	 * @see get_user_data
	 */
	function get_user_data_form($identity_url, $data) {
		$comment = $_SESSION['oid_comment_post'];

		if (!$comment) {
			return $data;
		}

		if ($comment['email']) {
			$data['user_email'] = $comment['email'];
		}

		if ($comment['author']) {
			$data['nickname'] = $comment['author'];
			$data['user_nicename'] = $comment['author'];
			$data['display_name'] = $comment['author'];
		}

		return $data;
	}


	/**
	 * For comments that were handled by WordPress normally (not our code), check if the author
	 * registered with OpenID and set comment openid flag if so.
	 *
	 * @action post_comment
	 */
	function check_author_openid($comment_ID) {
		global $openid;

		$comment = get_comment($comment_ID);
		if ( $comment->user_id && !$comment->openid && is_user_openid($comment->user_id) ) {
			WordPressOpenID_Logic::set_comment_openid($comment_ID);
		}
	}

	/**
	 * hook in and call when user is updating their profile URL... make sure it is an OpenID they control.
	 */
	function personal_options_update() {
		set_include_path( dirname(__FILE__) . PATH_SEPARATOR . get_include_path() );
		require_once 'Auth/OpenID.php';
		$claimed = Auth_OpenID::normalizeUrl($_POST['url']);

		$user = wp_get_current_user();

		openid_init();
		$store =& WordPressOpenID_Logic::getStore();
		$identities = $store->get_identities($user->ID);

		if (!empty($identities)) {
			$urls = array();
			foreach ($identities as $id) {
				if ($id['url'] == $claimed) {
					return; 
				} else {
					$urls[] = $id['url'];
				}
			}

			wp_die('For security reasons, your profile URL must be one of your claimed '
			   . 'Identity URLs: <ul><li>' . join('</li><li>', $urls) . '</li></ul>');
		}
	}


	/**
	 * Mark the provided comment as an OpenID comment
	 *
	 * @param int $comment_ID id of comment to set as OpenID
	 */
	function set_comment_openid($comment_ID) {
		global $wpdb, $openid;

		$comments_table = WordPressOpenID::comments_table_name();
		$wpdb->query("UPDATE $comments_table SET openid='1' WHERE comment_ID='$comment_ID' LIMIT 1");
	}


	/**
	 * If the comment contains a valid OpenID, skip the check for requiring a name and email address.  Even if
	 * this data is provided in the form, we may get it through other methods, so we don't want to bail out
	 * prematurely.  After OpenID authentication has completed (and $_SESSION['oid_skip'] is set), we don't
	 * interfere so that this data can be required if desired.
	 *
	 * @param boolean $value existing value of flag, whether to require name and email
	 * @return boolean new value of flag, whether to require name and email
	 * @see get_user_data
	 */
	function bypass_option_require_name_email( $value ) {
		global $openid;
			
		if ($_REQUEST['oid_skip']) {
			return $value;
		}

		if (array_key_exists('openid_url', $_POST)) {
			if( !empty( $_POST['openid_url'] ) ) {
				return false;
			}
		} else {
			if (!empty($_POST['url'])) {
				if (WordPressOpenID_Logic::late_bind()) {
					// check if url is valid OpenID by forming an auth request
					$auth_request = WordPressOpenID_Logic::begin_consumer($_POST['url']);

					if (null !== $auth_request) {
						return false;
					}
				}
			}
		}

		return $value;
	}


	/**
	 * Intercept comment submission and check if it includes a valid OpenID.  If it does, save the entire POST
	 * array and begin the OpenID authentication process.
	 *
	 * regarding comment_type: http://trac.wordpress.org/ticket/2659
	 *
	 * @param object $comment comment object
	 * @return object comment object
	 */
	function comment_tagging( $comment ) {
		global $openid;

		if ($_REQUEST['oid_skip']) return $comment;
			
		$openid_url = (array_key_exists('openid_url', $_POST) ? $_POST['openid_url'] : $_POST['url']);

		if( !empty($openid_url) ) {  // Comment form's OpenID url is filled in.
			$_SESSION['oid_comment_post'] = $_POST;
			$_SESSION['oid_comment_post']['comment_author_openid'] = $openid_url;
			$_SESSION['oid_comment_post']['oid_skip'] = 1;

			WordPressOpenID_Logic::start_login( $openid_url, 'comment');

			// Failure to redirect at all, the URL is malformed or unreachable.

			// Display an error message only if an explicit OpenID field was used.  Otherwise,
			// just ignore the error... it just means the user entered a normal URL.
			if (array_key_exists('openid_url', $_POST)) {
				WordPressOpenID_Interface::repost_comment_anonymously($_SESSION['oid_comment_post']);
			}
		}

		/*
		if (get_option('oid_enable_email_mapping') && !empty($_POST['email'])) {
			$_SESSION['oid_comment_post'] = $_POST;
			$_SESSION['oid_comment_post']['comment_author_openid'] = $openid_url;
			$_SESSION['oid_comment_post']['oid_skip'] = 1;

			set_include_path( dirname(__FILE__) . PATH_SEPARATOR . get_include_path() );
			require_once 'Auth/Yadis/Email.php';
			$id = Auth_Yadis_Email_getID($_POST['email'], trailingslashit(get_option('home')));
			WordPressOpenID_Logic::start_login( $id, 'comment');
		}
		*/
			
		return $comment;
	}


	/**
	 * This filter callback simply approves all OpenID comments, but later it could do more complicated logic
	 * like whitelists.
	 *
	 * @param string $approved comment approval status
	 * @return string new comment approval status
	 */
	function comment_approval($approved) {
		if ($_SESSION['oid_posted_comment']) {
			return 1;
		}
			
		return $approved;
	}


	/**
	 * Get any additional comments awaiting moderation by this user.  WordPress
	 * core has been udpated to grab most, but we still do one last check for
	 * OpenID comments that have a URL match with the current user.
	 *
	 * @param array $comments array of comments to display
	 * @param int $post_id id of the post to display comments for
	 * @return array new array of comments to display
	 */
	function comments_awaiting_moderation(&$comments, $post_id) {
		global $wpdb, $openid;
		$user = wp_get_current_user();

		$commenter = wp_get_current_commenter();
		extract($commenter);

		$author_db = $wpdb->escape($comment_author);
		$email_db  = $wpdb->escape($comment_author_email);
		$url_db  = $wpdb->escape($comment_author_url);

		if ($url_db) {
			$comments_table = WordPressOpenID::comments_table_name();
			$additional = $wpdb->get_results(
					"SELECT * FROM $comments_table"
			. " WHERE comment_post_ID = '$post_id'"
			. " AND openid = '1'"             // get OpenID comments
			. " AND comment_author_url = '$url_db'"      // where only the URL matches
			. ($user ? " AND user_id != '$user->ID'" : '')
			. ($author_db ? " AND comment_author != '$author_db'" : '')
			. ($email_db ? " AND comment_author_email != '$email_db'" : '')
			. " AND comment_approved = '0'"
			. " ORDER BY comment_date");

			if ($additional) {
				$comments = array_merge($comments, $additional);
				usort($comments, create_function('$a,$b',
						'return strcmp($a->comment_date_gmt, $b->comment_date_gmt);'));
			}
		}

		return $comments;
	}

	/**
	 * Delete user.
	 */
	function delete_user($userid) {
		openid_init();
		$store = WordPressOpenID_Logic::getStore();
		$store->drop_all_identities_for_user($userid);
	}


	/**
	 * Make sure that a user's OpenID is stored and retrieved properly.  This is important because the OpenID
	 * may be an i-name, but WordPress is expecting the comment URL cookie to be a valid URL.
	 *
	 * @wordpress-action sanitize_comment_cookies
	 */
	function sanitize_comment_cookies() {
		if ( isset($_COOKIE['comment_author_openid_'.COOKIEHASH]) ) {

			// this might be an i-name, so we don't want to run clean_url()
			remove_filter('pre_comment_author_url', 'clean_url');

			$comment_author_url = apply_filters('pre_comment_author_url',
			$_COOKIE['comment_author_openid_'.COOKIEHASH]);
			$comment_author_url = stripslashes($comment_author_url);
			$_COOKIE['comment_author_url_'.COOKIEHASH] = $comment_author_url;
		}
	}


	function xrds_simple($xrds) {
		$xrds = xrds_add_service($xrds, 'main', 'OpenID Consumer Service', 
			array(
				'Type' => array(array('content' => 'http://specs.openid.net/auth/2.0/return_to') ),
				'URI' => array(array('content' => trailingslashit(get_option('home'))) ),
			)
		);

		$siteurl = function_exists('site_url') ? site_url('/wp-login.php', 'login_post') : get_option('siteurl').'/wp-login.php';
		$xrds = xrds_add_service($xrds, 'main', 'Identity in the Browser Login Service', 
			array(
				'Type' => array(array('content' => 'http://specs.openid.net/idib/1.0/login') ),
				'URI' => array(
					array(
						'simple:httpMethod' => 'POST',
						'content' => $siteurl,
					),
				),
			)
		);

		$xrds = xrds_add_service($xrds, 'main', 'Identity in the Browser Indicator Service', 
			array(
				'Type' => array(array('content' => 'http://specs.openid.net/idib/1.0/indicator') ),
				'URI' => array(array('content' => trailingslashit(get_option('home')) . '?openid_check_login')),
			)
		);

		return $xrds;
	}

	/**
	 * Parse the WordPress request.  If the pagename is 'openid_consumer', then the request
	 * is an OpenID response and should be handled accordingly.
	 *
	 * @param WP $wp WP instance for the current request
	 */
	function parse_request($wp) {
		if (array_key_exists('openid_check_login', $_REQUEST)) {
			echo is_user_logged_in() ? 'true' : 'false';
			exit;
		}

		if (array_key_exists('openid_consumer', $_REQUEST) && $_REQUEST['action']) {
			openid_init();
			WordPressOpenID_Logic::finish_openid($_REQUEST['action']);
		}
	}

	function set_error($error) {
		$_SESSION['oid_error'] = $error;
		return;
	}

} // end class definition
endif; // end if-class-exists test

?>
