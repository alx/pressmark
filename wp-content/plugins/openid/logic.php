<?php
/**
 * logic.php
 *
 * Dual License: GPL & Modified BSD
 */
if  ( !class_exists('WordpressOpenIDLogic') ) {
	class WordpressOpenIDLogic {

		var $core;        // WordpressOpenID instance
		var $store;	      // WordpressOpenIDStore instance
		var $_consumer;   // Auth_OpenID_Consumer
		
		var $error;		  // User friendly error message, defaults to ''.
		var $action;	  // Internal action tag. '', 'error', 'redirect'.

		var $response;

		var $enabled = true;

		var $flag_doing_openid_comment = false;

		var $bind_done = false;

		/**
		 * Constructor.
		 */
		function WordpressOpenIDLogic($core) {
			$this->core =& $core;
		}


		/* Soft verification of plugin activation OK */
		function uptodate() {
			$this->core->log->debug('checking if database is up to date');
			if( get_option('oid_db_revision') != WPOPENID_DB_REVISION ) {  
				// Database version mismatch, force dbDelta() in admin interface.
				$this->enabled = false;
				$this->core->setStatus('Plugin Database Version', false, 'Plugin database is out of date. ' 
					. get_option('oid_db_revision') . ' != ' . WPOPENID_DB_REVISION );
				update_option('oid_plugin_enabled', false);
				return false;
			}
			$this->enabled = (get_option('oid_plugin_enabled') == true );
			return $this->enabled;
		}
		
		/**
		 * Get the internal SQL Store.  If it is not already initialized, do so.
		 */
		function getStore() {
			if (!isset($this->store)) {
				require_once 'store.php';

				$this->store = new WordpressOpenIDStore($this->core);
				if (null === $this->store) {

					$this->core->setStatus('object: OpenID Store', false, 
						'OpenID store could not be created properly.');

					$this->enabled = false;
				} else {
					$this->core->setStatus('object: OpenID Store', true, 'OpenID store created properly.');
				}
			}

			return $this->store;
		}

		/**
		 * Get the internal OpenID Consumer object.  If it is not already initialized, do so.
		 */
		function getConsumer() {
			if (!isset($this->_consumer)) {
				require_once 'Auth/OpenID/Consumer.php';

				$this->_consumer = new Auth_OpenID_Consumer($this->store);
				if( null === $this->_consumer ) {
					$this->core->setStatus('object: OpenID Consumer', false, 
						'OpenID consumer could not be created properly.');

					$this->enabled = false;
				} else {
					$this->core->setStatus('object: OpenID Consumer', true, 
						'OpenID consumer created properly.');
				}
			}

			return $this->_consumer;
		}
		

		/** 
		 * Initialize required store and consumer and make a few sanity checks.  This method 
		 * does a lot of the heavy lifting to get everything initialized, so we don't call it 
		 * until we actually need it.
		 */
		function late_bind($reload = false) {
			global $wpdb;

			$this->core->log->debug('beginning late binding');

			$this->enabled = true; // Be Optimistic
			if( $this->bind_done && !$reload ) {
				$this->core->log->debug('we\'ve already done the late bind... moving on');
				return $this->uptodate();
			}
			$this->bind_done = true;

			$f = @fopen( '/dev/urandom', 'r');
            if ($f === false) {
                define( 'Auth_OpenID_RAND_SOURCE', null );
            }
			
			// include required JanRain OpenID library files
			set_include_path( dirname(__FILE__) . PATH_SEPARATOR . get_include_path() );   
			$this->core->log->debug('temporary include path for importing = ' . get_include_path());
			require_once('Auth/OpenID/Discover.php');
			require_once('Auth/OpenID/DatabaseConnection.php');
			require_once('Auth/OpenID/MySQLStore.php');
			require_once('Auth/OpenID/Consumer.php');
			require_once('Auth/OpenID/SReg.php');
			restore_include_path();

			$this->core->setStatus('database: WordPress\' table prefix', 'info', isset($wpdb->base_prefix) ? $wpdb->base_prefix : $wpdb->prefix );

			$this->core->log->debug("Bootstrap -- checking tables");
			if( $this->enabled ) {

				$store =& $this->getStore();
				if (!$store) return; 	// something broke
				$this->enabled = $store->check_tables();

				if( !$this->uptodate() ) {
					update_option('oid_plugin_enabled', true);
					update_option('oid_plugin_revision', WPOPENID_PLUGIN_REVISION );
					update_option('oid_db_revision', WPOPENID_DB_REVISION );
					$this->uptodate();
				}
			} else {
				$this->error = 'WPOpenID Core is Disabled!';
				update_option('oid_plugin_enabled', false);
			}

			return $this->enabled;
		}
		

		/**
		 * Called on plugin activation.
		 *
		 * @see register_activation_hook
		 */
		function activate_plugin() {
			$this->late_bind();
		}


		/**
		 * Called on plugin deactivation.  Cleanup all transient tables.
		 *
		 * @see register_deactivation_hook
		 */
		function deactivate_plugin() {
			$this->late_bind();

			if( $this->store == null) {
				$this->error = 'OpenIDConsumer: Disabled. Cannot locate libraries, therefore cannot clean '
					. 'up database tables. Fix the libraries, or drop the tables yourself.';
				$this->core->log->notice($this->error);
				return;
			}

			$this->core->log->debug('Dropping all database tables.');
			$this->store->destroy_tables();
		}
		


		/*
		 * Customer error handler for calls into the JanRain library
		 */
		function customer_error_handler($errno, $errmsg, $filename, $linenum, $vars) {
			if( (2048 & $errno) == 2048 ) return;
			$this->core->log->notice( "Library Error $errno: $errmsg in $filename :$linenum");
		}

 
		/*
		 * Hook - called as wp_authenticate
		 * If we're doing openid authentication ($_POST['openid_url'] is set), start the consumer & redirect
		 * Otherwise, return and let WordPress handle the login and/or draw the form.
		 */
		function wp_authenticate( &$username ) {
			if( !empty( $_POST['openid_url'] ) ) {
				if( !$this->late_bind() ) return; // something is broken
				$redirect_to = '';
				if( !empty( $_REQUEST['redirect_to'] ) ) $redirect_to = $_REQUEST['redirect_to'];
				$this->start_login( $_POST['openid_url'], '/wp-login.php', 'loginopenid',
				   array('redirect_to' => $redirect_to) );
			}
			if( !empty( $this->error ) ) {
				global $error;
				$error = $this->error;
			}
		}


		/**
		 * Start and finish the redirect loop for the admin pages profile.php & users.php
		 **/
		function openid_profile_management() {
			global $wp_version;

			if( !isset( $_REQUEST['action'] )) return;
			
			$this->action = $_REQUEST['action'];
			
			require_once(ABSPATH . 'wp-admin/admin-functions.php');

			if ($wp_version < '2.3') {
				require_once(ABSPATH . 'wp-admin/admin-db.php');
				require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
			}

			auth_redirect();
			nocache_headers();
			get_currentuserinfo();

			if( !$this->late_bind() ) return; // something is broken
			
			switch( $this->action ) {
				case 'add_identity':
					check_admin_referer('wp-openid-add_identity');
					$return_to = '/wp-admin/' . (current_user_can('edit_users') ? 'users.php' : 'profile.php');
					$this->start_login($_POST['openid_url'], $return_to, 'verify_identity',
						array('page'=>$this->core->interface->profile_page_name));
					break;
					
				case 'verify_identity': // Return from verify loop.
					$this->_profile_verify_identity();
					break;
					
				case 'drop_identity':  // Remove a binding.
					$this->_profile_drop_identity($_REQUEST['id']);
					break;
			}
		}


		/**
		 * Step 2 of adding new identity URL to user account.
		 *
		 * @private
		 **/
		function _profile_verify_identity() {
			if ( !isset($_REQUEST['openid_mode']) ) {
				return; // no mode? probably a spoof or bad cancel.
			}

			$identity_url = $this->finish_openid_auth();
			if (!$identity_url) return;

			if( !$this->store->insert_identity($identity_url) ) {
				// TODO should we check for this duplication *before* authenticating the ID?
				$this->error = 'OpenID assertion successful, but this URL is already claimed by '
					. 'another user on this blog. This is probably a bug.';
			} else {
				$this->action = 'success';
			}
		}


		/**
		 * Remove identity URL from user account.
		 *
		 * @private
		 **/
		function _profile_drop_identity($id) {

			if( !isset( $id)) {
				$this->error = 'Identity url delete failed: ID paramater missing.';
				return;
			}

			$deleted_identity_url = $this->store->get_my_identities($id);
			if( FALSE === $deleted_identity_url ) {
				$this->error = 'Identity url delete failed: Specified identity does not exist.';
				return;
			}

			$identity_urls = $this->store->get_my_identities();
			if (sizeof($identity_urls) == 1 && !$_REQUEST['confirm']) {
				$this->error = 'This is your last identity URL.  Are you sure you want to delete it? Doing so may interfere with your ability to login.<br /><br /> '
					. '<a href="?confirm=true&'.$_SERVER['QUERY_STRING'].'">Yes I\'m sure.  Delete it</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'
					. '<a href="?page='.$this->core->interface->profile_page_name.'">No, don\'t delete it.</a>';
				$this->action = 'warning';
				return;
			}

			check_admin_referer('wp-openid-drop-identity_'.$deleted_identity_url);
			
			if( $this->store->drop_identity($id) ) {
				$this->error = 'Identity url delete successful. <b>' . $deleted_identity_url 
					. '</b> removed.';
				$this->action = 'success';
				return;
			}
			
			$this->error = 'Identity url delete failed: Unknown reason.';
		}


		/**
		 * Send the user to their OpenID provider to authenticate.
		 **/
		function doRedirect($auth_request, $trust_root, $return_to) {
			if ($auth_request->shouldSendRedirect()) {
				if (substr($trust_root, -1, 1) != '/') $trust_root .= '/';
				$redirect_url = $auth_request->redirectURL($trust_root, $return_to);

				if (Auth_OpenID::isFailure($redirect_url)) {
					$this->core->log->error('Could not redirect to server: '.$redirect_url->message);
				} else {
					wp_redirect( $redirect_url );
				}
			} else {
				// Generate form markup and render it
				$form_id = 'openid_message';
				$form_html = $auth_request->formMarkup($trust_root, $return_to, false, array('id'=>$form_id));

				if (Auth_OpenID::isFailure($form_html)) {
					$this->core->log->error('Could not redirect to server: '.$form_html->message);
				} else {
					?>
						<html>
							<head>
								<title>Redirecting to OpenID Provider</title>
							</head>
							<body onload="document.getElementById('<?php echo $form_id ?>').submit();">
								<h3>Redirecting to OpenID Provider</h3>
								<?php echo $form_html ?>
							</body>
						</html>
					<?php
				}
			}
		}

		
		/**
		 * Finish OpenID Authentication.
		 *
		 * @param 	object		OpenID Consumer
		 * @return	String		authenticated Identity URL
		 */
		function finish_openid_auth() {
			set_error_handler( array($this, 'customer_error_handler'));
			$consumer = $this->getConsumer();
			$this->response = $consumer->complete($_SESSION['oid_return_to']);
			restore_error_handler();
			
			switch( $this->response->status ) {
				case Auth_OpenID_CANCEL:
					$this->error = 'OpenID assertion cancelled'; 
					break;

				case Auth_OpenID_FAILURE:
					$this->error = 'OpenID assertion failed: ' . $this->response->message; 
					break;

				case Auth_OpenID_SUCCESS:
					$this->error = 'OpenID assertion successful';

					$identity_url = $this->response->identity_url;
					$escaped_url = htmlspecialchars($identity_url, ENT_QUOTES);
					$this->core->log->notice('Got back identity URL ' . $escaped_url);

					if ($this->response->endpoint->canonicalID) {
						$this->core->log->notice('XRI CanonicalID: ' . $this->response->endpoint->canonicalID);
					}

					return $escaped_url;

				default:
					$this->error = 'Unknown Status. Bind not successful. This is probably a bug';
			}

			return null;
		}
		


		/* Simple loop to reduce collisions for usernames for urls like:
		 * Eg: http://foo.com/80/to/magic.com
		 * and http://foo.com.80.to.magic.com
		 * and http://foo.com:80/to/magic.com
		 * and http://foo.com/80?to=magic.com
		 */
		function generate_new_username($url) {
			$base = $this->normalize_username($url);
			$i='';
			while(true) {
				$username = $this->normalize_username( $base . $i );
				$user = get_userdatabylogin($username);
				if ( $user ) {
					$i++;
					continue;
				}
				return $username;
			}
		}
		
		function normalize_username($username) {
			$username = preg_replace('|^https?://(xri.net/([^@]!?)?)?|', '', $username);
			$username = preg_replace('|^xri://([^@]!?)?|', '', $username);
			$username = preg_replace('|/$|', '', $username);
			$username = sanitize_user( $username );
			$username = preg_replace('|[^a-z0-9 _.\-@]+|i', '-', $username);
			return $username;
		}



		/*  
		 * Prepare to start the redirect loop
		 * This function is mainly for assembling urls
		 * Called from wp_authenticate (for login form) and comment_tagging (for comment form)
		 * If using comment form, specify optional parameters action=commentopenid and wordpressid=PostID.
		 */
		function start_login( $claimed_url, $return_to, $action, $arguments ) {

			if ( empty($claimed_url) ) return; // do nothing.
			
			if( !$this->late_bind() ) return; // something is broken

			if ( null !== $openid_auth_request) {
				$auth_request = $openid_auth_request;
			} else {
				set_error_handler( array($this, 'customer_error_handler'));
				$consumer = $this->getConsumer();
				$auth_request = $consumer->begin( $claimed_url );
				restore_error_handler();
			}

			if ( null === $auth_request ) {
				$this->error = 'Could not discover an OpenID identity server endpoint at the url: ' 
					. htmlentities( $claimed_url );
				if( strpos( $claimed_url, '@' ) ) { 
					$this->error .= '<br/>The address you specified had an @ sign in it, but OpenID '
						. 'Identities are not email addresses, and should probably not contain an @ sign.'; 
				}
				$this->core->log->debug('OpenIDConsumer: ' . $this->error );
				return;
			}
			
			$this->core->log->debug('OpenIDConsumer: Is an OpenID url. Starting redirect.');


			// build return_to URL
			$return_to = get_option('siteurl') . $return_to . "?action=$action";
			if (is_array($arguments) && !empty($arguments)) {
				foreach ($arguments as $k => $v) {
					if ($k && $v) {
						$return_to .= sprintf('&%s=%s', urlencode($k), urlencode($v));
					}
				}
			}
			

			/* If we've never heard of this url before, do attribute query */
			if( $this->store->get_user_by_identity( $auth_request->endpoint->identity_url ) == NULL ) {
				$attribute_query = true;
			}
			if ($attribute_query) {
				// SREG
				$sreg_request = Auth_OpenID_SRegRequest::build(array(),array('nickname','email','fullname'));
				if ($sreg_request) $auth_request->addExtension($sreg_request);

				// AX
			}
			
			$_SESSION['oid_return_to'] = $return_to;
			$this->doRedirect($auth_request, get_option('home'), $return_to);
			exit(0);
		}


		/* 
		 * Finish the redirect loop.
		 * If returning from openid server with action set to loginopenid or commentopenid, complete the loop
		 * If we fail to login, pass on the error message.
		 */	
		function finish_login( ) {
			$self = basename( $GLOBALS['pagenow'] );
			
			switch ( $self ) {
				case 'wp-login.php':
					if( $action == 'register' ) {
						return;
					}
					if ($action == 'login' || $action == '') {
						if (function_exists('wp_signon') && isset($_REQUEST['openid_url'])) {
							wp_signon(array('user_login'=>'openid', 'user_password'=>'openid'));
						}
					}
					if ( !isset( $_REQUEST['openid_mode'] ) ) return;
					if( $_REQUEST['action'] == 'loginopenid' ) break;
					if( $_REQUEST['action'] == 'commentopenid' ) break;
					return;
					break;
					

				case 'wp-register.php':
					return;

				default:
					return;				
			}						
			
			if( !$this->late_bind() ) return; // something is broken
			
			// We're doing OpenID login, so zero out these variables
			unset( $_POST['user_login'] );
			unset( $_POST['user_pass'] );

			$identity_url = $this->finish_openid_auth();

			if ($identity_url) {
				$this->error = 'OpenID Authentication Success.';

				$this->action = '';
				$redirect_to = 'wp-admin/';

				$matching_user_id = $this->store->get_user_by_identity( $identity_url );
				
				if( NULL !== $matching_user_id ) {
					$user = new WP_User( $matching_user_id );
					$success = false;

					if (function_exists('wp_authenticate')) {
						$success = wp_authenticate($user->user_login, md5($user->user_pass));
					} else {
						$success = wp_login( $user->user_login, md5($user->user_pass), true );
					}

					if( $success) {
						$this->core->log->debug('OpenIDConsumer: Returning user logged in: '
							.$user->user_login); 

						if (function_exists('wp_set_auth_cookie')) {
							// TODO: honor rememberme checkbox
							wp_set_auth_cookie($user->ID, true);
						} else {
							wp_setcookie($user->user_login, md5($user->user_pass), true, '', '', true);
						}

						do_action('wp_login', $user_login);
						
						// put user data into an array to be stored with the comment itself
						$oid_user_data = array( 
							'ID' => $user->ID,
							'user_url' => $user->user_url,
							'user_nicename' => $user->user_nicename,
							'display_name' => $user->display_name, 
						);

						$this->action = 'redirect';
						if ( !$user->has_cap('edit_posts') ) $redirect_to = '/wp-admin/profile.php';

					} else {
						$this->error = 'OpenID authentication valid, but WordPress login failed. '
							. 'OpenID login disabled for this account.';
						$this->action = 'error';
					}
					
				} else {

					$oid_user_data =& $this->get_user_data($identity_url);

					if ($_REQUEST['action'] == 'loginopenid') {
						if ( get_option('users_can_register') ) {
								$oid_user = $this->create_new_user($identity_url, $oid_user_data);
						} else {
							// TODO - Start a registration loop in WPMU.
							$this->error = 'OpenID authentication valid, but unable '
								. 'to find a WordPress account associated with this OpenID.<br /><br />'
								. 'Enable "Anyone can register" to allow creation of new accounts via OpenID.';
							$this->action = 'error';
						}
					} else {
						$this->action = 'redirect';
					}

				}
			} else {
				//XXX: option to comment anonymously
				$this->error = "We were unable to authenticate your OpenID";
			}
			

			$this->core->log->debug('OpenIDConsumer: Finish Auth for "' . $identity_url . 
				'". ' . $this->error );
			
			if( $this->action == 'redirect' ) {
				if ( !empty( $_REQUEST['redirect_to'] )) {
					$redirect_to = $_REQUEST['redirect_to'];
				} else if ( !empty($_REQUEST['comment_post_ID']) ) {
					$redirect_to = get_permalink($_REQUEST['comment_post_ID']);
				}
				
				if( $_REQUEST['action'] == 'commentopenid' ) {
					$comment_id = $this->post_comment($oid_user_data);
					$redirect_to .= '#comment-' . $comment_id;
					$comment = get_comment($comment_id);
					$redirect_to = apply_filters('comment_post_redirect', $redirect_to, $comment);
				}

				if( $redirect_to == '/wp-admin' and !$user->has_cap('edit_posts') ) 
					$redirect_to = '/wp-admin/profile.php';

				if (function_exists('wp_safe_redirect')) {
					wp_safe_redirect( $redirect_to );
				} else {
					wp_redirect( $redirect_to );
				}
				exit();
			}

			global $action;
			$action=$this->action; 

		}


		function create_new_user($identity_url, &$oid_user_data) {
			global $wpdb;

			// Identity URL is new, so create a user with md5()'d password
			@include_once( ABSPATH . 'wp-admin/upgrade-functions.php');	// 2.1
			@include_once( ABSPATH . WPINC . '/registration-functions.php'); // 2.0.4

			$oid_user_data['user_login'] = $wpdb->escape( $this->generate_new_username($identity_url) );
			$oid_user_data['user_pass'] = substr( md5( uniqid( microtime() ) ), 0, 7);
			$user_id = wp_insert_user( $oid_user_data );
			
			$this->core->log->debug("wp_create_user( $oid_user_data )  returned $user_id ");

			if( $user_id ) { // created ok

				$oid_user_data['ID'] = $user_id;

				$this->core->log->debug("OpenIDConsumer: Created new user $user_id : $username and metadata: "
					. var_export( $oid_user_data, true ) );
				
				$user = new WP_User( $user_id );

				if( ! wp_login( $user->user_login, $oid_user_data['user_pass'] ) ) {
					$this->error = 'User was created fine, but wp_login() for the new user failed. '
						. 'This is probably a bug.';
					$this->action= 'error';
					$this->core->log->err( $this->error );
					return;
				}
				
				// notify of user creation
				wp_new_user_notification( $user->user_login );
				
				wp_clearcookie();
				wp_setcookie( $user->user_login, md5($user->user_pass), true, '', '', true );
				
				// Bind the provided identity to the just-created user
				global $userdata;
				$userdata = get_userdata( $user_id );
				$this->store->insert_identity( $identity_url );
				
				$this->action = 'redirect';
				
				if ( !$user->has_cap('edit_posts') ) $redirect_to = '/wp-admin/profile.php';
				
			} else {
				// failed to create user for some reason.
				$this->error = 'OpenID authentication successful, but failed to create WordPress user. '
					. 'This is probably a bug.';
				$this->action= 'error';
				$this->core->log->error( $this->error );
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
		 * 	 - WordPress comment form
		 * 	 - default to identity URL
		 */
		function get_user_data($identity_url) {

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


			$result = $this->get_user_data_sreg($identity_url, $data);

			if (!$result) {
				$result = $this->get_user_data_form($identity_url, $data);
			}

			return $data;
		}


		/**
		 * Retrieve user data from OpenID Attribute Exchange.
		 *
		 * @see get_user_data
		 */
		function get_user_data_ax($identity_url, &$data) {
			// TODO
		}


		/**
		 * Retrieve user data from OpenID Simple Registration.
		 *
		 * @see get_user_data
		 */
		function get_user_data_sreg($identity_url, &$data) {

			$sreg_resp = Auth_OpenID_SRegResponse::fromSuccessResponse($this->response);
			$sreg = $sreg_resp->contents();

			$this->core->log->debug(var_export($sreg, true));
			if (!$sreg) return false;

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

			return true;
		}


		/**
		 * Retrieve user data from hCard discovery.
		 *
		 * @see get_user_data
		 */
		function get_user_data_hcard($identity_url, &$data) {
			// TODO
		}


		/**
		 * Retrieve user data from WordPress comment form.
		 *
		 * @see get_user_data
		 */
		function get_user_data_form($identity_url, &$data) {
			$comment = $this->get_comment();

			if (!$comment || !is_array($comment)) {
				return false;
			}

			if (array_key_exists('comment_author_email', $comment) && $comment['comment_author_email']) {
				$data['user_email'] = $comment['comment_author_email'];
			}
			if (array_key_exists('comment_author', $comment) && $comment['comment_author']) {
				$namechunks = explode( ' ', $comment['comment_author'], 2 );
				if( isset($namechunks[0]) ) $data['first_name'] = $namechunks[0];
				if( isset($namechunks[1]) ) $data['last_name'] = $namechunks[1];
				$data['display_name'] = $comment['comment_author'];
			}
		}


		/** 
		 * Transparent inline login and commenting.
		 * The comment text is in the session.
		 * Post it and redirect to the permalink.
		 */
		function post_comment(&$oid_user_data) {
			
			$comment = $this->get_comment();
			$comment_content = $comment['comment_content'];
			$this->clear_comment();
			
			if ( '' == trim($comment_content) )
				die( __('Error: please type a comment.') );
			
			$this->core->log->debug('OpenIDConsumer: action=commentopenid  redirect_to=' . $redirect_to);
			$this->core->log->debug('OpenIDConsumer: comment_content = ' . $comment_content);
			
			nocache_headers();
			
			// Do essentially the same thing as wp-comments-post.php
			global $wpdb;
			$comment_post_ID = (int) $_REQUEST['wordpressid'];
			$status = $wpdb->get_row("SELECT post_status, comment_status FROM $wpdb->posts "
				. "WHERE ID = '$comment_post_ID'");
			if ( empty($status->comment_status) ) {
				do_action('comment_id_not_found', $comment_post_ID);
				exit();
			} elseif ( 'closed' ==  $status->comment_status ) {
				do_action('comment_closed', $comment_post_ID);
				die( __('Sorry, comments are closed for this item.') );
			} elseif ( 'draft' == $status->post_status ) {
				do_action('comment_on_draft', $comment_post_ID);
				exit;
			}
			
			$comment_author       = $wpdb->escape($oid_user_data['display_name']);
			$comment_author_email = $wpdb->escape($oid_user_data['user_email']);
			$comment_author_url   = $wpdb->escape($oid_user_data['user_url']);
			$user_ID              = $oid_user_data['ID'];
			$this->flag_doing_openid_comment = true;

			$commentdata = compact('comment_post_ID', 'comment_author', 'comment_author_email',
										'comment_author_url', 'comment_content', 'comment_type', 'user_ID');

			if ( !$user_id ) {
				setcookie('comment_author_' . COOKIEHASH, $comment['comment_author'], 
					time() + 30000000, COOKIEPATH, COOKIE_DOMAIN);
				setcookie('comment_author_email_' . COOKIEHASH, $comment['comment_author_email'], 
					time() + 30000000, COOKIEPATH, COOKIE_DOMAIN);
				setcookie('comment_author_url_' . COOKIEHASH, clean_url($comment['comment_author_url']), 
					time() + 30000000, COOKIEPATH, COOKIE_DOMAIN);

				// save openid url in a separate cookie so wordpress doesn't muck with it when we 
				// read it back in later
				setcookie('comment_author_openid_' . COOKIEHASH, $comment['comment_author_openid'], 
					time() + 30000000, COOKIEPATH, COOKIE_DOMAIN);
			}	

			// comment approval
			if ( get_option('oid_enable_approval') ) {
				add_filter('pre_comment_approved', array($this, 'comment_approval'));
			}

			$comment_ID = wp_new_comment( $commentdata );

			$this->set_comment_openid($comment_ID);

			return $comment_ID;
		}


		/**
		 * For comments that were handled by WordPress normally (not our code), check if the author 
		 * registered with OpenID and set comment openid flag if so.
		 *
		 * @action post_comment
		 */
		function check_author_openid($comment_ID) {
			$comment = get_comment($comment_ID);
			if ( $comment->user_id && !$comment->openid && is_user_openid($comment->user_id) ) {
				$this->set_comment_openid($comment_ID);
			}
		}


		/**
		 * Mark the provided comment as an OpenID comment
		 */
		function set_comment_openid($comment_ID) {
			global $wpdb;

			$comments_table = $this->store->comments_table_name;
			$wpdb->query("UPDATE $comments_table SET openid='1' WHERE comment_ID='$comment_ID' LIMIT 1");
		}


		/* These functions are used to store the comment
		 * temporarily while doing an OpenID redirect loop.
		 */
		function set_comment( $content ) {
			$_SESSION['oid_comment'] = $content;
		}

		function clear_comment( ) {
			unset($_SESSION['oid_comment']);
		}

		function get_comment( ) {
			return $_SESSION['oid_comment'];
		}

		/* Called when comment is submitted by get_option('require_name_email') */
		function bypass_option_require_name_email( $value ) {
			global $openid_auth_request;

			if (array_key_exists('openid_url', $_POST)) {
				if( !empty( $_POST['openid_url'] ) ) {
					return false;
				}
			} else {
				if (!empty($_POST['url'])) {
					if ($this->late_bind()) { 
						// check if url is valid OpenID by forming an auth request
						set_error_handler( array($this, 'customer_error_handler'));
						$consumer = $this->getConsumer();
						$openid_auth_request = $consumer->begin( $_POST['url'] );
						restore_error_handler();

						if (null !== $openid_auth_request) {
							return false;
						}
					}
				}
			}

			return $value;
		}
		
		/*
		 * Called when comment is submitted via preprocess_comment hook.
		 * If comment is submitted along with an openid url, store comment, and do authentication.
		 *
		 * regarding comment_type: http://trac.wordpress.org/ticket/2659
		 */
		function comment_tagging( $comment ) {
			global $current_user;

			if (!$this->enabled) return $comment;
			
			$openid_url = (array_key_exists('openid_url', $_POST) ? $_POST['openid_url'] : $_POST['url']);

			if( !empty($openid_url) ) {  // Comment form's OpenID url is filled in.
				$comment['comment_author_openid'] = $openid_url;
				$this->set_comment($comment);
				$this->start_login( $openid_url, '/wp-login.php', 'commentopenid', 
					array(
						'wordpressid' => $comment['comment_post_ID'],
						'redirect_to' => get_permalink( $comment['comment_post_ID'] ), 
					)
				);
				
				// Failure to redirect at all, the URL is malformed or unreachable. 

				// Display an error message only if an explicit OpenID field  was used.  Otherwise, 
				// just ignore the error... it just means the user entered a normal URL.
				if (array_key_exists('openid_url', $_POST)) {
					global $error;
					$error = $this->error;
					$_POST['openid_url'] = '';
					include( ABSPATH . 'wp-login.php' );
					exit();
				}
			}
			
			return $comment;
		}


		/**
		 * This filter callback is only set when a new OpenID comment is made.  
		 * For now it just approves all OpenID comments, but later it could do 
		 * more complicated logic like whitelists.
		 **/
		function comment_approval($approved) {
			return 1;
		}


		/**
		 * Get any additional comments awaiting moderation by this user.  WordPress
		 * core has been udpated to grab most, but we still do one last check for
		 * OpenID comments that have a URL match with the current user.
		 */
		function comments_awaiting_moderation(&$comments, $post_id) {
			global $wpdb, $user_ID;

			$commenter = wp_get_current_commenter();
			extract($commenter);

			$author_db = $wpdb->escape($comment_author);
			$email_db  = $wpdb->escape($comment_author_email);
			$url_db  = $wpdb->escape($comment_author_url);

			if ($url_db) {
				$store =& $this->getStore();
				$comments_table = $store->comments_table_name;
				$additional = $wpdb->get_results(
					"SELECT * FROM $comments_table"
					. " WHERE comment_post_ID = '$post_id'"
					. " AND openid = '1'"             // get OpenID comments
					. " AND comment_author_url = '$url_db'"      // where only the URL matches
					. ($user_ID ? " AND user_id != '$user_ID'" : '')
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
		 *
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


	} // end class definition
} // end if-class-exists test

?>
