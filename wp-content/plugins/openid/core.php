<?php
/*
 Plugin Name: WP-OpenID
 Plugin URI: http://wordpress.org/extend/plugins/openid
 Description: Allows the use of OpenID for account registration, authentication, and commenting.  <em>By <a href="http://verselogic.net">Alan Castonguay</a>.</em>
 Author: Will Norris
 Author URI: http://willnorris.com/
 Version: 2.2.2
 License: Dual GPL (http://www.fsf.org/licensing/licenses/info/GPLv2.html) and Modified BSD (http://www.fsf.org/licensing/licenses/index_html#ModifiedBSD)
 */

define ( 'WPOPENID_PLUGIN_REVISION', preg_replace( '/\$Rev: (.+) \$/', 'svn-\\1',
	'$Rev: 58444 $') ); // this needs to be on a separate line so that svn:keywords can work its magic

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

if  (!class_exists('WordPressOpenID')):
class WordPressOpenID {
	var $store;
	var $consumer;

	var $log;
	var $status = array();

	var $message;	  // Message to be displayed to the user.
	var $action;	  // Internal action tag. 'success', 'warning', 'error', 'redirect'.

	var $response;

	var $enabled = true;

	var $bind_done = false;

	
	function WordPressOpenID() {
		$this->log = &Log::singleton('error_log', PEAR_LOG_TYPE_SYSTEM, 'OpenID');
		//$this->log = &Log::singleton('file', ABSPATH . get_option('upload_path') . '/php.log', 'WPOpenID');

		// Set the log level
		$wpopenid_log_level = constant('PEAR_LOG_' . strtoupper(WPOPENID_LOG_LEVEL));
		$this->log->setMask(Log::UPTO($wpopenid_log_level));
	}


	/**
	 * Set Status.
	 **/
	function setStatus($slug, $state, $message) {
		$this->status[$slug] = array('state'=>$state,'message'=>$message);
	}


	function textdomain() {
		$lang_folder = PLUGINDIR . '/openid/lang';
		load_plugin_textdomain('openid', $lang_folder);
	}

	function table_prefix() {
		global $wpdb;
		return isset($wpdb->base_prefix) ? $wpdb->base_prefix : $wpdb->prefix;
	}

	function associations_table_name() { return WordPressOpenID::table_prefix() . 'openid_associations'; }
	function nonces_table_name() { return WordPressOpenID::table_prefix() . 'openid_nonces'; }
	function identity_table_name() { return WordPressOpenID::table_prefix() . 'openid_identities'; }
	function comments_table_name() { return WordPressOpenID::table_prefix() . 'comments'; }
	function usermeta_table_name() { return WordPressOpenID::table_prefix() . 'usermeta'; }
}
endif;

if (!function_exists('openid_init')):
function openid_init() {
	if ($GLOBALS['openid'] && is_a($GLOBALS['openid'], 'WordPressOpenID')) {
		return;
	}
	
	$GLOBALS['openid'] = new WordPressOpenID();
}
endif;

// -- Register actions and filters -- //

register_activation_hook('openid/core.php', array('WordPressOpenID_Logic', 'activate_plugin'));
register_deactivation_hook('openid/core.php', array('WordPressOpenID_Logic', 'deactivate_plugin'));

add_action( 'admin_menu', array( 'WordPressOpenID_Interface', 'add_admin_panels' ) );

// Add hooks to handle actions in WordPress
add_action( 'wp_authenticate', array( 'WordPressOpenID_Logic', 'wp_authenticate' ) ); // openid loop start
add_action( 'init', array( 'WordPressOpenID_Logic', 'wp_login_openid' ) ); // openid loop done
add_action( 'init', array( 'WordPressOpenID', 'textdomain' ) ); // load textdomain


// Comment filtering
add_action( 'preprocess_comment', array( 'WordPressOpenID_Logic', 'comment_tagging' ), -99 );
add_action( 'comment_post', array( 'WordPressOpenID_Logic', 'check_author_openid' ), 5 );
add_filter( 'option_require_name_email', array( 'WordPressOpenID_Logic', 'bypass_option_require_name_email') );
add_filter( 'comments_array', array( 'WordPressOpenID_Logic', 'comments_awaiting_moderation'), 10, 2);
add_action( 'sanitize_comment_cookies', array( 'WordPressOpenID_Logic', 'sanitize_comment_cookies'), 15);
add_filter( 'comment_post_redirect', array( 'WordPressOpenID_Logic', 'comment_post_redirect'), 0, 2);
if( get_option('oid_enable_approval') ) {
	add_filter( 'pre_comment_approved', array('WordPressOpenID_Logic', 'comment_approval'));
}
	
// include internal stylesheet
add_action( 'wp_head', array( 'WordPressOpenID_Interface', 'style'));
add_action( 'login_head', array( 'WordPressOpenID_Interface', 'style'));
add_filter( 'get_comment_author_link', array( 'WordPressOpenID_Interface', 'comment_author_link'));

if( get_option('oid_enable_commentform') ) {
	add_action( 'wp_head', array( 'WordPressOpenID_Interface', 'js_setup'), 9);
	add_action( 'wp_footer', array( 'WordPressOpenID_Interface', 'comment_profilelink'), 10);
	add_action( 'wp_footer', array( 'WordPressOpenID_Interface', 'comment_form'), 10);
}

// add OpenID input field to wp-login.php
add_action( 'login_form', array( 'WordPressOpenID_Interface', 'login_form'));
add_action( 'register_form', array( 'WordPressOpenID_Interface', 'register_form'));
add_filter( 'login_errors', array( 'WordPressOpenID_Interface', 'login_form_hide_username_password_errors'));
add_filter( 'init', array( 'WordPressOpenID_Interface', 'init_errors'));

// parse request
add_action('parse_request', array('WordPressOpenID_Logic', 'parse_request'));

// Add custom OpenID options
add_option( 'oid_enable_commentform', true );
add_option( 'oid_plugin_enabled', true );
add_option( 'oid_plugin_revision', 0 );
add_option( 'oid_db_revision', 0 );
add_option( 'oid_enable_approval', false );
add_option( 'oid_enable_email_mapping', false );

add_action( 'delete_user', array( 'WordPressOpenID_Logic', 'delete_user' ) );
add_action( 'cleanup_openid', array( 'WordPressOpenID_Logic', 'cleanup_nonces' ) );

add_action( 'personal_options_update', array( 'WordPressOpenID_Logic', 'personal_options_update' ) );

// hooks for getting user data
add_filter( 'openid_user_data', array('WordPressOpenID_Logic', 'get_user_data_form'), 10, 2);
add_filter( 'openid_user_data', array('WordPressOpenID_Logic', 'get_user_data_sreg'), 10, 2);

add_filter('xrds_simple', array('WordPressOpenID_Logic', 'xrds_simple'));

// ---------------------------------------------------------------------
// Exposed functions designed for use in templates, specifically inside
//   `foreach ($comments as $comment)` in comments.php
// ---------------------------------------------------------------------

/**
 * Get a simple OpenID input field, used for disabling unobtrusive mode.
 */
if(!function_exists('openid_input')):
function openid_input() {
	return '<input type="text" id="openid_url" name="openid_url" />';
}
endif;

/**
 * If the current comment was submitted with OpenID, return true
 * useful for  <?php echo ( is_comment_openid() ? 'Submitted with OpenID' : '' ); ?>
 */
if(!function_exists('is_comment_openid')):
function is_comment_openid() {
	global $comment;
	return ( $comment->openid == 1 );
}
endif;

/**
 * If the current user registered with OpenID, return true
 */
if(!function_exists('is_user_openid')):
function is_user_openid($id = null) {
	global $current_user;

	if ($id === null && $current_user !== null) {
		$id = $current_user->ID;
	}

	return $id === null ? false : get_usermeta($id, 'has_openid');
}
endif;

?>
