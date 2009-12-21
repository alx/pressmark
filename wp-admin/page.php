<?php
/**
 * Edit page administration panel.
 *
 * Manage edit page: post, edit, delete, etc.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once('admin.php');

$parent_file = 'edit-pages.php';
$submenu_file = 'edit-pages.php';

wp_reset_vars(array('action'));

/**
 * Redirect to previous page.
 *
 * @param int $page_ID Page ID.
 */
function redirect_page($page_ID) {
	global $action;

	$referredby = '';
	if ( !empty($_POST['referredby']) ) {
		$referredby = preg_replace('|https?://[^/]+|i', '', $_POST['referredby']);
		$referredby = remove_query_arg('_wp_original_http_referer', $referredby);
	}
	$referer = preg_replace('|https?://[^/]+|i', '', wp_get_referer());

	if ( 'post' == $_POST['originalaction'] && !empty($_POST['mode']) && 'sidebar' == $_POST['mode'] ) {
		$location = 'sidebar.php?a=b';
	} elseif ( isset($_POST['save']) || isset($_POST['publish']) ) {
		$status = get_post_status( $page_ID );

		if ( isset( $_POST['publish'] ) ) {
			switch ( $status ) {
				case 'pending':
					$message = 6;
					break;
				case 'future':
					$message = 7;
					break;
				default:
					$message = 4;
			}
		} else {
				$message = 'draft' == $status ? 8 : 1;
		}

		$location = add_query_arg( 'message', $message, get_edit_post_link( $page_ID, 'url' ) );
	} elseif ( isset($_POST['addmeta']) ) {
		$location = add_query_arg( 'message', 2, wp_get_referer() );
		$location = explode('#', $location);
		$location = $location[0] . '#postcustom';
	} elseif ( isset($_POST['deletemeta']) ) {
		$location = add_query_arg( 'message', 3, wp_get_referer() );
		$location = explode('#', $location);
		$location = $location[0] . '#postcustom';
	} else {
		$location = add_query_arg( 'message', 1, get_edit_post_link( $page_ID, 'url' ) );
	}

	wp_redirect( apply_filters( 'redirect_page_location', $location, $page_ID ) );
}

if (isset($_POST['deletepost']))
	$action = "delete";
elseif ( isset($_POST['wp-preview']) && 'dopreview' == $_POST['wp-preview'] )
	$action = 'preview';

$sendback = wp_get_referer();
if ( strpos($sendback, 'page.php') !== false || strpos($sendback, 'page-new.php') !== false )
	$sendback = admin_url('edit-pages.php');
else
	$sendback = remove_query_arg( array('trashed', 'untrashed', 'deleted', 'ids'), $sendback );

switch($action) {
case 'post':
	check_admin_referer('add-page');
	$page_ID = write_post();

	redirect_page($page_ID);

	exit();
	break;

case 'edit':
	$title = __('Edit Page');
	$editing = true;
	$page_ID = $post_ID = $p = (int) $_GET['post'];
	$post = get_post_to_edit($page_ID);

	if ( empty($post->ID) )
		wp_die( __('You attempted to edit a page that doesn&#8217;t exist. Perhaps it was deleted?') );

	if ( !current_user_can('edit_page', $page_ID) )
		wp_die( __('You are not allowed to edit this page.') );

	if ( 'trash' == $post->post_status )
		wp_die( __('You can&#8217;t edit this page because it is in the Trash. Please move it out of the Trash and try again.') );

	if ( 'page' != $post->post_type ) {
		wp_redirect( get_edit_post_link( $post_ID, 'url' ) );
		exit();
	}

	wp_enqueue_script('post');
	if ( user_can_richedit() )
		wp_enqueue_script('editor');
	add_thickbox();
	wp_enqueue_script('media-upload');
	wp_enqueue_script('word-count');

	if ( $last = wp_check_post_lock( $post->ID ) ) {
		add_action('admin_notices', '_admin_notice_post_locked' );
	} else {
		wp_set_post_lock( $post->ID );
		wp_enqueue_script('autosave');
	}

	include('edit-page-form.php');
	break;

case 'editattachment':
	$page_id = $post_ID = (int) $_POST['post_ID'];
	check_admin_referer('update-attachment_' . $page_id);

	// Don't let these be changed
	unset($_POST['guid']);
	$_POST['post_type'] = 'attachment';

	// Update the thumbnail filename
	$newmeta = wp_get_attachment_metadata( $page_id, true );
	$newmeta['thumb'] = $_POST['thumb'];

	wp_update_attachment_metadata( $newmeta );

case 'editpost':
	$page_ID = (int) $_POST['post_ID'];
	check_admin_referer('update-page_' . $page_ID);

	$page_ID = edit_post();

	redirect_page($page_ID);

	exit();
	break;

case 'trash':
	$post_id = isset($_GET['post']) ? intval($_GET['post']) : intval($_POST['post_ID']);
	check_admin_referer('trash-page_' . $post_id);

	$post = & get_post($post_id);

	if ( !current_user_can('delete_page', $post_id) )
		wp_die( __('You are not allowed to move this page to the trash.') );

	if ( !wp_trash_post($post_id) )
		wp_die( __('Error in moving to trash...') );

	wp_redirect( add_query_arg( array('trashed' => 1, 'ids' => $post_id), $sendback ) );
	exit();
	break;

case 'untrash':
	$post_id = isset($_GET['post']) ? intval($_GET['post']) : intval($_POST['post_ID']);
	check_admin_referer('untrash-page_' . $post_id);

	$post = & get_post($post_id);

	if ( !current_user_can('delete_page', $post_id) )
		wp_die( __('You are not allowed to move this page out of the trash.') );

	if ( !wp_untrash_post($post_id) )
		wp_die( __('Error in restoring from trash...') );

	wp_redirect( add_query_arg('untrashed', 1, $sendback) );
	exit();
	break;

case 'delete':
	$page_id = isset($_GET['post']) ? intval($_GET['post']) : intval($_POST['post_ID']);
	check_admin_referer('delete-page_' .  $page_id);

	$page = & get_post($page_id);

	if ( !current_user_can('delete_page', $page_id) )
		wp_die( __('You are not allowed to delete this page.') );

	if ( $page->post_type == 'attachment' ) {
		if ( ! wp_delete_attachment($page_id) )
			wp_die( __('Error in deleting...') );
	} else {
		if ( !wp_delete_post($page_id) )
			wp_die( __('Error in deleting...') );
	}

	wp_redirect( add_query_arg('deleted', 1, $sendback) );
	exit();
	break;

case 'preview':
	check_admin_referer( 'autosave', 'autosavenonce' );

	$url = post_preview();

	wp_redirect($url);
	exit();
	break;

default:
	wp_redirect('edit-pages.php');
	exit();
	break;
} // end switch
include('admin-footer.php');
?>
