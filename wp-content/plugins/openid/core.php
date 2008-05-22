<?php
/*
Plugin Name: WP-OpenID
Plugin URI: http://wordpress.org/extend/plugins/openid
Description: Allows the use of OpenID for account registration, authentication, and commenting.  <em>By <a href="http://verselogic.net">Alan Castonguay</a>.</em>
Author: Will Norris
Author URI: http://willnorris.com/
Version: 2.1.9
License: Dual GPL (http://www.fsf.org/licensing/licenses/info/GPLv2.html) and Modified BSD (http://www.fsf.org/licensing/licenses/index_html#ModifiedBSD)
*/

define ( 'WPOPENID_PLUGIN_REVISION', preg_replace( '/\$Rev: (.+) \$/', 'svn-\\1', 
	'$Rev: 47054 $') ); // this needs to be on a separate line so that svn:keywords can work its magic

define ( 'WPOPENID_DB_REVISION', 24426);      // last plugin revision that required database schema changes


define ( 'WPOPENID_LOG_LEVEL', 'warning');     // valid values are debug, info, notice, warning, err, crit, alert, emerg

set_include_path( dirname(__FILE__) . PATH_SEPARATOR . get_include_path() );   // Add plugin directory to include path temporarily

require_once('logic.php');
require_once('interface.php');


@include_once('Log.php');                   // Try loading PEAR_Log from normal include_path.  
if (!class_exists('Log')) {                 // If we can't find it, include the copy of 
	require_once('OpenIDLog.php');          // PEAR_Log bundled with the plugin
}

restore_include_path();

@session_start();

if  ( !class_exists('WordpressOpenID') ) {
	class WordpressOpenID {
		var $path;
		var $fullpath;

		var $logic;
		var $interface;

		var $log;
		var $status = array();

		function WordpressOpenID($log) {
			$this->set_path();
			$this->fullpath = get_option('siteurl').$this->path;

			$this->log =& $log;

			$this->logic = new WordpressOpenIDLogic($this);
			$this->interface = new WordpressOpenIDInterface($this);
		}

		/**
		 * This is the main bootstrap method that gets things started.
		 */
		function startup() {
			$this->log->debug("Status: userinterface hooks: " . ($this->logic->enabled? 'Enabled':'Disabled' ) 
				. ' (finished including and instantiating, passing control back to WordPress)' );

			// -- register actions and filters -- //
			
			add_action( 'admin_menu', array( $this->interface, 'add_admin_panels' ) );

			// Kickstart
			register_activation_hook( $this->path.'/core.php', array( $this->logic, 'activate_plugin' ) );
			register_deactivation_hook( $this->path.'/core.php', array( $this->logic, 'deactivate_plugin' ) );

			// Add hooks to handle actions in WordPress
			add_action( 'wp_authenticate', array( $this->logic, 'wp_authenticate' ) ); // openid loop start
			add_action( 'init', array( $this->logic, 'finish_login' ) ); // openid loop done

			// Comment filtering
			add_action( 'preprocess_comment', array( $this->logic, 'comment_tagging' ), -99999 );
			add_action( 'comment_post', array( $this->logic, 'check_author_openid' ), 5 );
			add_filter( 'option_require_name_email', array( $this->logic, 'bypass_option_require_name_email') );
			add_filter( 'comments_array', array( $this->logic, 'comments_awaiting_moderation'), 10, 2);
			add_action( 'sanitize_comment_cookies', array( $this->logic, 'sanitize_comment_cookies'), 15);
			
			// If user is dropped from database, remove their identities too.
			$this->logic->late_bind();
			add_action( 'delete_user', array( $this->logic->store, 'drop_all_identities_for_user' ) );	

			// include internal stylesheet
			add_action( 'wp_head', array( $this->interface, 'style'));
			add_action( 'login_head', array( $this->interface, 'style'));

			add_filter( 'get_comment_author_link', array( $this->interface, 'comment_author_link'));

			if( get_option('oid_enable_commentform') ) {
				add_action( 'wp_head', array( $this->interface, 'js_setup'), 9);
				add_action( 'comment_form', array( $this->interface, 'comment_profilelink'));
				add_action( 'comment_form', array( $this->interface, 'comment_form'));
			}

			// add OpenID input field to wp-login.php
			add_action( 'login_form', array( $this->interface, 'login_form'));
			add_action( 'register_form', array( $this->interface, 'register_form'));
			add_filter( 'login_errors', array( $this->interface, 'login_form_hide_username_password_errors'));

			// Add custom OpenID options
			add_option( 'oid_enable_commentform', true );
			add_option( 'oid_plugin_enabled', true );
			add_option( 'oid_plugin_revision', 0 );
			add_option( 'oid_db_revision', 0 );
			add_option( 'oid_enable_approval', false );
		}

		/** 
		 * Set the path for the plugin. This should allow users to rename the plugin directory 
		 * if they choose to.  If unable to determine the directory (often due to symlinks), 
		 * default to 'openid'
		 **/
		function set_path() {
			$plugin = 'openid';

			$base = plugin_basename(__FILE__);
			if ($base != __FILE__) {
				$plugin = dirname($base);
			}

			$this->path = '/wp-content/plugins/'.$plugin;
		}


		/**
		 * Set Status.
		 **/
		function setStatus($slug, $state, $message) {
			$this->status[$slug] = array('state'=>$state,'message'=>$message);
			if( $state === true ) { 
				$_state = 'ok'; 
			}
			elseif( $state === false ) { 
				$_state = 'fail'; 
			}
			else { 
				$_state = ''.($state); 
			}

			$this->log->debug('Status: ' . strip_tags($slug) . " [$_state]" . ( ($_state==='ok') ? '': strip_tags(str_replace('<br/>'," ", ': ' . $message))  ) );
		}
	}
}

// The variable in use here should probably be something other than $log. Too great a chance of collision. Probably causing http://willnorris.com/2007/10/plugin-updates#comment-13625
if (isset($wp_version)) {
	#$wpopenid_log = &Log::singleton('error_log', PEAR_LOG_TYPE_SYSTEM, 'WPOpenID');
	$wpopenid_log = &Log::singleton('file', ABSPATH . get_option('upload_path') . '/php.log', 'WPOpenID');

	// Set the log level
	$wpopenid_log_level = constant('PEAR_LOG_' . strtoupper(WPOPENID_LOG_LEVEL));
	$wpopenid_log->setMask(Log::UPTO($wpopenid_log_level));

	$openid = new WordpressOpenID($wpopenid_log);
	$openid->startup();
}



// ---------------------------------------------------------------------
// Exposed functions designed for use in templates, specifically inside 
//   `foreach ($comments as $comment)` in comments.php
// ---------------------------------------------------------------------

/**
 * Get a simple OpenID input field, used for disabling unobtrusive mode.
 */
if( !function_exists( 'openid_input' ) ) {
	function openid_input() {
		return '<input type="text" id="openid_url" name="openid_url" />';
	}
}

/**
 * If the current comment was submitted with OpenID, return true
 * useful for  <?php echo ( is_comment_openid() ? 'Submitted with OpenID' : '' ); ?>
 */
if( !function_exists( 'is_comment_openid' ) ) {
	function is_comment_openid() {
		global $comment;
		return ( $comment->openid == 1 );
	}
}

/**
 * If the current user registered with OpenID, return true
 */
if( !function_exists('is_user_openid') ) {
	function is_user_openid($id = null) {
		global $current_user;

		if ($id === null && $current_user !== null) {
			$id = $current_user->ID;
		}

		return $id === null ? false : get_usermeta($id, 'has_openid');
	}
}

?>
