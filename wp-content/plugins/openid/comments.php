<?php
/**
 * All the code required for handling OpenID comments.  These functions should not be considered public, 
 * and may change without notice.
 */


// -- WordPress Hooks
add_action( 'parse_request', 'openid_parse_comment_request');
add_action( 'preprocess_comment', 'openid_process_comment', -99 );
add_action( 'comment_post', 'update_comment_openid', 5 );
add_filter( 'option_require_name_email', 'openid_option_require_name_email' );
add_action( 'sanitize_comment_cookies', 'openid_sanitize_comment_cookies', 15);
add_action( 'openid_finish_auth', 'openid_finish_comment' );
if( get_option('openid_enable_approval') ) {
	add_filter('pre_comment_approved', 'openid_comment_approval');
}
add_filter( 'get_comment_author_link', 'openid_comment_author_link');
if( get_option('openid_enable_commentform') ) {
	add_action( 'wp_head', 'openid_js_setup', 9);
	add_action( 'wp_footer', 'openid_comment_profilelink', 10);
	add_action( 'wp_footer', 'openid_comment_form', 10);
}
add_filter( 'openid_user_data', 'openid_get_user_data_form', 10, 2);
add_filter( 'openid_consumer_return_urls', 'openid_comment_return_url' );
add_action( 'delete_comment', 'unset_comment_openid' );


/**
 * Intercept comment submission and check if it includes a valid OpenID.  If it does, save the entire POST
 * array and begin the OpenID authentication process.
 *
 * regarding comment_type: http://trac.wordpress.org/ticket/2659
 *
 * @param array $comment comment data
 * @return array comment data
 */
function openid_process_comment( $comment ) {
	@session_start();

	if ($_REQUEST['openid_skip']) return $comment;
		
	$openid_url = (array_key_exists('openid_identifier', $_POST) ? $_POST['openid_identifier'] : $_POST['url']);

	if( !empty($openid_url) ) {  // Comment form's OpenID url is filled in.
		$_SESSION['openid_comment_post'] = $_POST;
		$_SESSION['openid_comment_post']['comment_author_openid'] = $openid_url;
		$_SESSION['openid_comment_post']['openid_skip'] = 1;

		openid_start_login( $openid_url, 'comment');

		// Failure to redirect at all, the URL is malformed or unreachable.

		// Display an error message only if an explicit OpenID field was used.  Otherwise,
		// just ignore the error... it just means the user entered a normal URL.
		if (array_key_exists('openid_identifier', $_POST)) {
			openid_repost_comment_anonymously($_SESSION['openid_comment_post']);
		}
	}

	return $comment;
}


/**
 * This filter callback simply approves all OpenID comments, but later it could do more complicated logic
 * like whitelists.
 *
 * @param string $approved comment approval status
 * @return string new comment approval status
 */
function openid_comment_approval($approved) {
	return ($_SESSION['openid_posted_comment'] ? 1 : $approved);
}


/**
 * If the comment contains a valid OpenID, skip the check for requiring a name and email address.  Even if
 * this data isn't provided in the form, we may get it through other methods, so we don't want to bail out
 * prematurely.  After OpenID authentication has completed (and $_REQUEST['openid_skip'] is set), we don't
 * interfere so that this data can be required if desired.
 *
 * @param boolean $value existing value of flag, whether to require name and email
 * @return boolean new value of flag, whether to require name and email
 * @see get_user_data
 */
function openid_option_require_name_email( $value ) {
		
	$comment_page = (defined('OPENID_COMMENTS_POST_PAGE') ? OPENID_COMMENTS_POST_PAGE : 'wp-comments-post.php');

	if ($GLOBALS['pagenow'] != $comment_page) {
		return $value;
	}

	if ($_REQUEST['openid_skip']) {
		return get_option('openid_no_require_name') ? false : $value;
	}

	if (array_key_exists('openid_identifier', $_POST)) {
		if( !empty( $_POST['openid_identifier'] ) ) {
			return false;
		}
	} else {
		if (!empty($_POST['url'])) {
			// check if url is valid OpenID by forming an auth request
			$auth_request = openid_begin_consumer($_POST['url']);

			if (null !== $auth_request) {
				return false;
			}
		}
	}

	return $value;
}


/**
 * Make sure that a user's OpenID is stored and retrieved properly.  This is important because the OpenID
 * may be an i-name, but WordPress is expecting the comment URL cookie to be a valid URL.
 *
 * @wordpress-action sanitize_comment_cookies
 */
function openid_sanitize_comment_cookies() {
	if ( isset($_COOKIE['comment_author_openid_'.COOKIEHASH]) ) {

		// this might be an i-name, so we don't want to run clean_url()
		remove_filter('pre_comment_author_url', 'clean_url');

		$comment_author_url = apply_filters('pre_comment_author_url',
		$_COOKIE['comment_author_openid_'.COOKIEHASH]);
		$comment_author_url = stripslashes($comment_author_url);
		$_COOKIE['comment_author_url_'.COOKIEHASH] = $comment_author_url;
	}
}


/**
 * Add OpenID class to author link.
 *
 * @filter: get_comment_author_link
 **/
function openid_comment_author_link( $html ) {
	if( is_comment_openid() ) {
		if (preg_match('/<a[^>]* class=[^>]+>/', $html)) {
			return preg_replace( '/(<a[^>]* class=[\'"]?)/', '\\1openid_link ' , $html );
		} else {
			return preg_replace( '/(<a[^>]*)/', '\\1 class="openid_link"' , $html );
		}
	}
	return $html;
}


/**
 * Check if the comment was posted with OpenID, either directly or by an author registered with OpenID.  Update the comment accordingly.
 *
 * @action post_comment
 */
function update_comment_openid($comment_ID) {
	if ($_SESSION['openid_posted_comment']) {
		set_comment_openid($comment_ID);
		unset($_SESSION['openid_posted_comment']);
	} else {
		$comment = get_comment($comment_ID);

		if ( is_user_openid($comment->user_id) ) {
			set_comment_openid($comment_ID);
		}
	}

}


/**
 * Print jQuery call for slylizing profile link.
 *
 * @action: comment_form
 **/
function openid_comment_profilelink() {
	global $wp_scripts;
	if ((is_single() || is_comments_popup()) && is_user_openid() && $wp_scripts->query('openid')) {
		echo '<script type="text/javascript">stylize_profilelink()</script>';
	}
}


/**
 * Print jQuery call to modify comment form.
 *
 * @action: comment_form
 **/
function openid_comment_form() {
	global $wp_scripts;
	if (!is_user_logged_in() && (is_single() || is_comments_popup()) && isset($wp_scripts) && $wp_scripts->query('openid')) {
		echo '<script type="text/javascript">add_openid_to_comment_form()</script>';
	}
}


function openid_repost_comment_anonymously($post) {
	$comment_page = (defined('OPENID_COMMENTS_POST_PAGE') ? OPENID_COMMENTS_POST_PAGE : 'wp-comments-post.php');

	$html = '
	<h1>'.__('OpenID Authentication Error', 'openid').'</h1>
	<p id="error">'.__('We were unable to authenticate your claimed OpenID, however you '
	. 'can continue to post your comment without OpenID:', 'openid').'</p>

	<form action="' . site_url("/$comment_page") . '" method="post">
		<p>Name: <input name="author" value="'.$post['author'].'" /></p>
		<p>Email: <input name="email" value="'.$post['email'].'" /></p>
		<p>URL: <input name="url" value="'.$post['url'].'" /></p>
		<textarea name="comment" cols="80%" rows="10">'.stripslashes($post['comment']).'</textarea>
		<input type="submit" name="submit" value="'.__('Submit Comment').'" />
		<input type="hidden" name="openid_skip" value="1" />';
	foreach ($post as $name => $value) {
		if (!in_array($name, array('author', 'email', 'url', 'comment', 'submit'))) {
			$html .= '
		<input type="hidden" name="'.$name.'" value="'.$value.'" />';
		}
	}
	
	$html .= '</form>';
	openid_page($html, __('OpenID Authentication Error', 'openid'));
}


/**
 * Action method for completing the 'comment' action.  This action is used when leaving a comment.
 *
 * @param string $identity_url verified OpenID URL
 */
function openid_finish_comment($identity_url) {
	if ($_REQUEST['action'] != 'comment') return;

	if (empty($identity_url)) {
		openid_repost_comment_anonymously($_SESSION['openid_comment_post']);
	}
		
	openid_set_current_user($identity_url);
		
	if (is_user_logged_in()) {
		// simulate an authenticated comment submission
		$_SESSION['openid_comment_post']['author'] = null;
		$_SESSION['openid_comment_post']['email'] = null;
		$_SESSION['openid_comment_post']['url'] = null;
	} else {
		// try to get user data from the verified OpenID
		$user_data =& openid_get_user_data($identity_url);

		if (!empty($user_data['display_name'])) {
			$_SESSION['openid_comment_post']['author'] = $user_data['display_name'];
		}
		if (!empty($user_data['user_email'])) {
			$_SESSION['openid_comment_post']['email'] = $user_data['user_email'];
		}
		$_SESSION['openid_comment_post']['url'] = $identity_url;
	}
		
	// record that we're about to post an OpenID authenticated comment.
	// We can't actually record it in the database until after the repost below.
	$_SESSION['openid_posted_comment'] = true;

	$comment_page = (defined('OPENID_COMMENTS_POST_PAGE') ? OPENID_COMMENTS_POST_PAGE : 'wp-comments-post.php');

	openid_repost(site_url("/$comment_page"), array_filter($_SESSION['openid_comment_post']));
}


/**
 * Mark the specified comment as an OpenID comment.
 *
 * @param int $id id of comment to set as OpenID
 */
function set_comment_openid($id) {
	$comment = get_comment($id);
	$openid_comments = get_post_meta($comment->comment_post_ID, 'openid_comments', true);
	if (!is_array($openid_comments)) {
		$openid_comments = array();
	}
	$openid_comments[] = $id;
	update_post_meta($comment->comment_post_ID, 'openid_comments', array_unique($openid_comments));
}


/**
 * Unmark the specified comment as an OpenID comment
 *
 * @param int $id id of comment to set as OpenID
 */
function unset_comment_openid($id) {
	$comment = get_comment($id);
	$openid_comments = get_post_meta($comment->comment_post_ID, 'openid_comments', true);

	if (is_array($openid_comments) && in_array($id, $openid_comments)) {
		$new = array();
		foreach($openid_comments as $c) {
			if ($c == $id) continue;
			$new[] = $c;
		}
		update_post_meta($comment->comment_post_ID, 'openid_comments', array_unique($new));
	}
}


/**
 * Retrieve user data from comment form.
 *
 * @param string $identity_url OpenID to get user data about
 * @param reference $data reference to user data array
 * @see get_user_data
 */
function openid_get_user_data_form($data, $identity_url) {
	$comment = $_SESSION['openid_comment_post'];

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
 * Parse the WordPress request.  If the pagename is 'openid_consumer', then the request
 * is an OpenID response and should be handled accordingly.
 *
 * @param WP $wp WP instance for the current request
 */
function openid_parse_comment_request($wp) {
	if (array_key_exists('openid_consumer', $_REQUEST) && $_REQUEST['action']) {
		finish_openid($_REQUEST['action']);
	}
}


function openid_comment_return_url($urls) {
	$urls[] = get_option('home');
	return $urls;
}


?>
