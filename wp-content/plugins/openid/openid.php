<?php
/*
 Plugin Name: OpenID
 Plugin URI: http://wordpress.org/extend/plugins/openid
 Description: Allows the use of OpenID for account registration, authentication, and commenting.  Also includes an OpenID provider which can turn WordPress author URLs into OpenIDs.
 Author: DiSo Development Team
 Author URI: http://diso-project.org/
 Version: 3.0
 License: Dual GPL (http://www.fsf.org/licensing/licenses/info/GPLv2.html) and Modified BSD (http://www.fsf.org/licensing/licenses/index_html#ModifiedBSD)
 */

define ( 'OPENID_PLUGIN_REVISION', preg_replace( '/\$Rev: (.+) \$/', 'svn-\\1',
	'$Rev: 518 $') ); // this needs to be on a separate line so that svn:keywords can work its magic

// last plugin revision that required database schema changes
define ( 'OPENID_DB_REVISION', 24426);


$openid_include_path = dirname(__FILE__);
if (file_exists(dirname(__FILE__) . '/openid')) {
	// for WPMU mu-plugins folder
	$openid_include_path .= PATH_SEPARATOR . dirname(__FILE__) . '/openid';
}

set_include_path( $openid_include_path . PATH_SEPARATOR . get_include_path() );
require_once 'common.php';
require_once 'compatibility.php';
require_once 'admin_panels.php';
require_once 'comments.php';
require_once 'wp-login.php';
require_once 'server.php';
require_once 'store.php';
restore_include_path();


// -- public functions

/**
 * Check if the user has any OpenIDs.
 *
 * @param mixed $user the username or ID.  If not provided, the current user will be used.
 * @return bool true if the user has any OpenIDs
 * @since 1.0
 */
function is_user_openid($user = null) {
	$urls = get_user_openids($user);
	return (!empty($urls));
}


/**
 * Check if the current comment was submitted using an OpenID. Useful for 
 * <pre><?php echo ( is_comment_openid() ? 'Submitted with OpenID' : '' ); ?></pre>
 *
 * @param int $id comment ID to check for.  If not provided, the current comment will be used.
 * @return bool true if the comment was submitted using an OpenID
 * @access public
 * @since 1.0
 */
function is_comment_openid($id = null) {
	if (is_numeric($id)) {
		$comment = get_comment($id);
	} else {
		global $comment;
	}

	$openid_comments = get_post_meta($comment->comment_post_ID, 'openid_comments', true);
	if (!is_array($openid_comments)) return false;
	return (in_array($comment->comment_ID, $openid_comments));
}


/**
 * Get the OpenID identities for the specified user.
 *
 * @param mixed $id_or_name the username or ID.  If not provided, the current user will be used.
 * @return array array of user's OpenID identities
 * @access public
 * @since 3.0
 */
function get_user_openids($id_or_name = null) {
	$user = get_userdata_by_various($id_or_name);
	return _get_user_openids($user->ID);
}


/**
 * Get the user associated with the specified OpenID.
 *
 * @param string $openid identifier to match
 * @return int|null ID of associated user, or null if no associated user
 * @access public
 * @since 3.0
 */
function get_user_by_openid($url) {
	global $wpdb;
	return $wpdb->get_var( wpdb_prepare('SELECT user_id FROM '.openid_identity_table().' WHERE url = %s', $url) );
}


/**
 * Get a simple OpenID input field.
 *
 * @access public
 * @since 2.0
 */
function openid_input() {
	return '<input type="text" id="openid_identifier" name="openid_identifier" />';
}


/**
 * Convenience method to get user data by ID, username, or from current user.
 *
 * @param mixed $id_or_name the username or ID.  If not provided, the current user will be used.
 * @return bool|object False on failure, User DB row object
 * @access public
 * @since 3.0
 */
if (!function_exists('get_userdata_by_various')) :
function get_userdata_by_various($id_or_name = null) {
	if ($id_or_name === null) {
		$user = wp_get_current_user();
		if ($user == null) return false;
		return $user->data;
	} else if (is_numeric($id_or_name)) {
		return get_userdata($id_or_name);
	} else {
		return get_userdatabylogin($id_or_name);
	}
}
endif;

?>
