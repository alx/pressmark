<?php
/**
 * WordPress AJAX Process Execution.
 *
 * @package WordPress
 * @subpackage Administration
 */

/**
 * Executing AJAX process.
 *
 * @since unknown
 */
define('DOING_AJAX', true);
define('WP_ADMIN', true);

require_once('../wp-load.php');
require_once('includes/admin.php');
@header('Content-Type: text/html; charset=' . get_option('blog_charset'));

do_action('admin_init');

if ( ! is_user_logged_in() ) {

	if ( $_POST['action'] == 'autosave' ) {
		$id = isset($_POST['post_ID'])? (int) $_POST['post_ID'] : 0;

		if ( ! $id )
			die('-1');

		$message = sprintf( __('<strong>ALERT: You are logged out!</strong> Could not save draft. <a href="%s" target="blank">Please log in again.</a>'), wp_login_url() );
			$x = new WP_Ajax_Response( array(
				'what' => 'autosave',
				'id' => $id,
				'data' => $message
			) );
			$x->send();
	}

	if ( !empty( $_REQUEST['action']) )
		do_action( 'wp_ajax_nopriv_' . $_REQUEST['action'] );

	die('-1');
}

if ( isset( $_GET['action'] ) ) :
switch ( $action = $_GET['action'] ) :
case 'ajax-tag-search' :
	if ( !current_user_can( 'edit_posts' ) )
		die('-1');

	$s = $_GET['q']; // is this slashed already?

	if ( isset($_GET['tax']) )
		$taxonomy = sanitize_title($_GET['tax']);
	else
		die('0');

	if ( false !== strpos( $s, ',' ) ) {
		$s = explode( ',', $s );
		$s = $s[count( $s ) - 1];
	}
	$s = trim( $s );
	if ( strlen( $s ) < 2 )
		die; // require 2 chars for matching

	$results = $wpdb->get_col( "SELECT t.name FROM $wpdb->term_taxonomy AS tt INNER JOIN $wpdb->terms AS t ON tt.term_id = t.term_id WHERE tt.taxonomy = '$taxonomy' AND t.name LIKE ('%" . $s . "%')" );

	echo join( $results, "\n" );
	die;
	break;
case 'wp-compression-test' :
	if ( !current_user_can( 'manage_options' ) )
		die('-1');

	if ( ini_get('zlib.output_compression') || 'ob_gzhandler' == ini_get('output_handler') ) {
		update_site_option('can_compress_scripts', 0);
		die('0');
	}

	if ( isset($_GET['test']) ) {
		header( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-cache, must-revalidate, max-age=0' );
		header( 'Pragma: no-cache' );
		header('Content-Type: application/x-javascript; charset=UTF-8');
		$force_gzip = ( defined('ENFORCE_GZIP') && ENFORCE_GZIP );
		$test_str = '"wpCompressionTest Lorem ipsum dolor sit amet consectetuer mollis sapien urna ut a. Eu nonummy condimentum fringilla tempor pretium platea vel nibh netus Maecenas. Hac molestie amet justo quis pellentesque est ultrices interdum nibh Morbi. Cras mattis pretium Phasellus ante ipsum ipsum ut sociis Suspendisse Lorem. Ante et non molestie. Porta urna Vestibulum egestas id congue nibh eu risus gravida sit. Ac augue auctor Ut et non a elit massa id sodales. Elit eu Nulla at nibh adipiscing mattis lacus mauris at tempus. Netus nibh quis suscipit nec feugiat eget sed lorem et urna. Pellentesque lacus at ut massa consectetuer ligula ut auctor semper Pellentesque. Ut metus massa nibh quam Curabitur molestie nec mauris congue. Volutpat molestie elit justo facilisis neque ac risus Ut nascetur tristique. Vitae sit lorem tellus et quis Phasellus lacus tincidunt nunc Fusce. Pharetra wisi Suspendisse mus sagittis libero lacinia Integer consequat ac Phasellus. Et urna ac cursus tortor aliquam Aliquam amet tellus volutpat Vestibulum. Justo interdum condimentum In augue congue tellus sollicitudin Quisque quis nibh."';

		 if ( 1 == $_GET['test'] ) {
		 	echo $test_str;
		 	die;
		 } elseif ( 2 == $_GET['test'] ) {
			if ( !isset($_SERVER['HTTP_ACCEPT_ENCODING']) )
				die('-1');
			if ( false !== strpos( strtolower($_SERVER['HTTP_ACCEPT_ENCODING']), 'deflate') && function_exists('gzdeflate') && ! $force_gzip ) {
				header('Content-Encoding: deflate');
				$out = gzdeflate( $test_str, 1 );
			} elseif ( false !== strpos( strtolower($_SERVER['HTTP_ACCEPT_ENCODING']), 'gzip') && function_exists('gzencode') ) {
				header('Content-Encoding: gzip');
				$out = gzencode( $test_str, 1 );
			} else {
				die('-1');
			}
			echo $out;
			die;
		} elseif ( 'no' == $_GET['test'] ) {
			update_site_option('can_compress_scripts', 0);
		} elseif ( 'yes' == $_GET['test'] ) {
			update_site_option('can_compress_scripts', 1);
		}
	}

	die('0');
	break;
case 'imgedit-preview' :
	$post_id = intval($_GET['postid']);
	if ( empty($post_id) || !current_user_can('edit_post', $post_id) )
		die('-1');

	check_ajax_referer( "image_editor-$post_id" );

	include_once( ABSPATH . 'wp-admin/includes/image-edit.php' );
	if ( !stream_preview_image($post_id) )
		die('-1');

	die();
	break;
case 'oembed-cache' :
	$return = ( $wp_embed->cache_oembed( $_GET['post'] ) ) ? '1' : '0';
	die( $return );
	break;
default :
	do_action( 'wp_ajax_' . $_GET['action'] );
	die('0');
	break;
endswitch;
endif;

/**
 * Sends back current comment total and new page links if they need to be updated.
 *
 * Contrary to normal success AJAX response ("1"), die with time() on success.
 *
 * @since 2.7
 *
 * @param int $comment_id
 * @return die
 */
function _wp_ajax_delete_comment_response( $comment_id ) {
	$total = (int) @$_POST['_total'];
	$per_page = (int) @$_POST['_per_page'];
	$page = (int) @$_POST['_page'];
	$url = esc_url_raw( @$_POST['_url'] );
	// JS didn't send us everything we need to know. Just die with success message
	if ( !$total || !$per_page || !$page || !$url )
		die( (string) time() );

	if ( --$total < 0 ) // Take the total from POST and decrement it (since we just deleted one)
		$total = 0;

	if ( 0 != $total % $per_page && 1 != mt_rand( 1, $per_page ) ) // Only do the expensive stuff on a page-break, and about 1 other time per page
		die( (string) time() );

	$post_id = 0;
	$status = 'total_comments'; // What type of comment count are we looking for?
	$parsed = parse_url( $url );
	if ( isset( $parsed['query'] ) ) {
		parse_str( $parsed['query'], $query_vars );
		if ( !empty( $query_vars['comment_status'] ) )
			$status = $query_vars['comment_status'];
		if ( !empty( $query_vars['p'] ) )
			$post_id = (int) $query_vars['p'];
	}

	$comment_count = wp_count_comments($post_id);
	$time = time(); // The time since the last comment count

	if ( isset( $comment_count->$status ) ) // We're looking for a known type of comment count
		$total = $comment_count->$status;
	// else use the decremented value from above

	$page_links = paginate_links( array(
		'base' => add_query_arg( 'apage', '%#%', $url ),
		'format' => '',
		'prev_text' => __('&laquo;'),
		'next_text' => __('&raquo;'),
		'total' => ceil($total / $per_page),
		'current' => $page
	) );
	$x = new WP_Ajax_Response( array(
		'what' => 'comment',
		'id' => $comment_id, // here for completeness - not used
		'supplemental' => array(
			'pageLinks' => $page_links,
			'total' => $total,
			'time' => $time
		)
	) );
	$x->send();
}

$id = isset($_POST['id'])? (int) $_POST['id'] : 0;
switch ( $action = $_POST['action'] ) :
case 'delete-comment' : // On success, die with time() instead of 1
	if ( !$comment = get_comment( $id ) )
		die( (string) time() );
	if ( !current_user_can( 'edit_post', $comment->comment_post_ID ) )
		die('-1');

	check_ajax_referer( "delete-comment_$id" );
	$status = wp_get_comment_status( $comment->comment_ID );

	if ( isset($_POST['trash']) && 1 == $_POST['trash'] ) {
		if ( 'trash' == $status )
			die( (string) time() );
		$r = wp_trash_comment( $comment->comment_ID );
	} elseif ( isset($_POST['untrash']) && 1 == $_POST['untrash'] ) {
		if ( 'trash' != $status )
			die( (string) time() );
		$r = wp_untrash_comment( $comment->comment_ID );
	} elseif ( isset($_POST['spam']) && 1 == $_POST['spam'] ) {
		if ( 'spam' == $status )
			die( (string) time() );
		$r = wp_spam_comment( $comment->comment_ID );
	} elseif ( isset($_POST['unspam']) && 1 == $_POST['unspam'] ) {
		if ( 'spam' != $status )
			die( (string) time() );
		$r = wp_unspam_comment( $comment->comment_ID );
	} elseif ( isset($_POST['delete']) && 1 == $_POST['delete'] ) {
		$r = wp_delete_comment( $comment->comment_ID );
	} else {
		die('-1');
	}

	if ( $r ) // Decide if we need to send back '1' or a more complicated response including page links and comment counts
		_wp_ajax_delete_comment_response( $comment->comment_ID );
	die( '0' );
	break;
case 'delete-cat' :
	check_ajax_referer( "delete-category_$id" );
	if ( !current_user_can( 'manage_categories' ) )
		die('-1');

	$cat = get_category( $id );
	if ( !$cat || is_wp_error( $cat ) )
		die('1');

	if ( wp_delete_category( $id ) )
		die('1');
	else
		die('0');
	break;
case 'delete-tag' :
	$tag_id = (int) $_POST['tag_ID'];
	check_ajax_referer( "delete-tag_$tag_id" );
	if ( !current_user_can( 'manage_categories' ) )
		die('-1');

	$taxonomy = !empty($_POST['taxonomy']) ? $_POST['taxonomy'] : 'post_tag';

	$tag = get_term( $tag_id, $taxonomy );
	if ( !$tag || is_wp_error( $tag ) )
		die('1');

	if ( wp_delete_term($tag_id, $taxonomy))
		die('1');
	else
		die('0');
	break;
case 'delete-link-cat' :
	check_ajax_referer( "delete-link-category_$id" );
	if ( !current_user_can( 'manage_categories' ) )
		die('-1');

	$cat = get_term( $id, 'link_category' );
	if ( !$cat || is_wp_error( $cat ) )
		die('1');

	$cat_name = get_term_field('name', $id, 'link_category');

	$default = get_option('default_link_category');

	// Don't delete the default cats.
	if ( $id == $default ) {
		$x = new WP_AJAX_Response( array(
			'what' => 'link-cat',
			'id' => $id,
			'data' => new WP_Error( 'default-link-cat', sprintf(__("Can&#8217;t delete the <strong>%s</strong> category: this is the default one"), $cat_name) )
		) );
		$x->send();
	}

	$r = wp_delete_term($id, 'link_category', array('default' => $default));
	if ( !$r )
		die('0');
	if ( is_wp_error($r) ) {
		$x = new WP_AJAX_Response( array(
			'what' => 'link-cat',
			'id' => $id,
			'data' => $r
		) );
		$x->send();
	}
	die('1');
	break;
case 'delete-link' :
	check_ajax_referer( "delete-bookmark_$id" );
	if ( !current_user_can( 'manage_links' ) )
		die('-1');

	$link = get_bookmark( $id );
	if ( !$link || is_wp_error( $link ) )
		die('1');

	if ( wp_delete_link( $id ) )
		die('1');
	else
		die('0');
	break;
case 'delete-meta' :
	check_ajax_referer( "delete-meta_$id" );
	if ( !$meta = get_post_meta_by_id( $id ) )
		die('1');

	if ( !current_user_can( 'edit_post', $meta->post_id ) )
		die('-1');
	if ( delete_meta( $meta->meta_id ) )
		die('1');
	die('0');
	break;
case 'delete-post' :
	check_ajax_referer( "{$action}_$id" );
	if ( !current_user_can( 'delete_post', $id ) )
		die('-1');

	if ( !get_post( $id ) )
		die('1');

	if ( wp_delete_post( $id ) )
		die('1');
	else
		die('0');
	break;
case 'trash-post' :
case 'untrash-post' :
	check_ajax_referer( "{$action}_$id" );
	if ( !current_user_can( 'delete_post', $id ) )
		die('-1');

	if ( !get_post( $id ) )
		die('1');

	if ( 'trash-post' == $action )
		$done = wp_trash_post( $id );
	else
		$done = wp_untrash_post( $id );

	if ( $done )
		die('1');

	die('0');
	break;
case 'delete-page' :
	check_ajax_referer( "{$action}_$id" );
	if ( !current_user_can( 'delete_page', $id ) )
		die('-1');

	if ( !get_page( $id ) )
		die('1');

	if ( wp_delete_post( $id ) )
		die('1');
	else
		die('0');
	break;
case 'dim-comment' : // On success, die with time() instead of 1

	if ( !$comment = get_comment( $id ) ) {
		$x = new WP_Ajax_Response( array(
			'what' => 'comment',
			'id' => new WP_Error('invalid_comment', sprintf(__('Comment %d does not exist'), $id))
		) );
		$x->send();
	}

	if ( !current_user_can( 'edit_post', $comment->comment_post_ID ) && !current_user_can( 'moderate_comments' ) )
		die('-1');

	$current = wp_get_comment_status( $comment->comment_ID );
	if ( $_POST['new'] == $current )
		die( (string) time() );

	check_ajax_referer( "approve-comment_$id" );
	if ( in_array( $current, array( 'unapproved', 'spam' ) ) )
		$result = wp_set_comment_status( $comment->comment_ID, 'approve', true );
	else
		$result = wp_set_comment_status( $comment->comment_ID, 'hold', true );

	if ( is_wp_error($result) ) {
		$x = new WP_Ajax_Response( array(
			'what' => 'comment',
			'id' => $result
		) );
		$x->send();
	}

	// Decide if we need to send back '1' or a more complicated response including page links and comment counts
	_wp_ajax_delete_comment_response( $comment->comment_ID );
	die( '0' );
	break;
case 'add-category' : // On the Fly
	check_ajax_referer( $action );
	if ( !current_user_can( 'manage_categories' ) )
		die('-1');
	$names = explode(',', $_POST['newcat']);
	if ( 0 > $parent = (int) $_POST['newcat_parent'] )
		$parent = 0;
	$post_category = isset($_POST['post_category'])? (array) $_POST['post_category'] : array();
	$checked_categories = array_map( 'absint', (array) $post_category );
	$popular_ids = wp_popular_terms_checklist('category', 0, 10, false);

	foreach ( $names as $cat_name ) {
		$cat_name = trim($cat_name);
		$category_nicename = sanitize_title($cat_name);
		if ( '' === $category_nicename )
			continue;
		$cat_id = wp_create_category( $cat_name, $parent );
		$checked_categories[] = $cat_id;
		if ( $parent ) // Do these all at once in a second
			continue;
		$category = get_category( $cat_id );
		ob_start();
			wp_category_checklist( 0, $cat_id, $checked_categories, $popular_ids );
		$data = ob_get_contents();
		ob_end_clean();
		$add = array(
			'what' => 'category',
			'id' => $cat_id,
			'data' => str_replace( array("\n", "\t"), '', $data),
			'position' => -1
		);
	}
	if ( $parent ) { // Foncy - replace the parent and all its children
		$parent = get_category( $parent );
		$term_id = $parent->term_id;

		while ( $parent->parent ) { // get the top parent
			$parent = &get_category( $parent->parent );
			if ( is_wp_error( $parent ) )
				break;
			$term_id = $parent->term_id;
		}

		ob_start();
			wp_category_checklist( 0, $term_id, $checked_categories, $popular_ids, null, false );
		$data = ob_get_contents();
		ob_end_clean();
		$add = array(
			'what' => 'category',
			'id' => $term_id,
			'data' => str_replace( array("\n", "\t"), '', $data),
			'position' => -1
		);
	}

	ob_start();
		wp_dropdown_categories( array( 'hide_empty' => 0, 'name' => 'newcat_parent', 'orderby' => 'name', 'hierarchical' => 1, 'show_option_none' => __('Parent category') ) );
	$sup = ob_get_contents();
	ob_end_clean();
	$add['supplemental'] = array( 'newcat_parent' => $sup );

	$x = new WP_Ajax_Response( $add );
	$x->send();
	break;
case 'add-link-category' : // On the Fly
	check_ajax_referer( $action );
	if ( !current_user_can( 'manage_categories' ) )
		die('-1');
	$names = explode(',', $_POST['newcat']);
	$x = new WP_Ajax_Response();
	foreach ( $names as $cat_name ) {
		$cat_name = trim($cat_name);
		$slug = sanitize_title($cat_name);
		if ( '' === $slug )
			continue;
		if ( !$cat_id = is_term( $cat_name, 'link_category' ) ) {
			$cat_id = wp_insert_term( $cat_name, 'link_category' );
		}
		$cat_id = $cat_id['term_id'];
		$cat_name = esc_html(stripslashes($cat_name));
		$x->add( array(
			'what' => 'link-category',
			'id' => $cat_id,
			'data' => "<li id='link-category-$cat_id'><label for='in-link-category-$cat_id' class='selectit'><input value='" . esc_attr($cat_id) . "' type='checkbox' checked='checked' name='link_category[]' id='in-link-category-$cat_id'/> $cat_name</label></li>",
			'position' => -1
		) );
	}
	$x->send();
	break;
case 'add-cat' : // From Manage->Categories
	check_ajax_referer( 'add-category' );
	if ( !current_user_can( 'manage_categories' ) )
		die('-1');

	if ( '' === trim($_POST['cat_name']) ) {
		$x = new WP_Ajax_Response( array(
			'what' => 'cat',
			'id' => new WP_Error( 'cat_name', __('You did not enter a category name.') )
		) );
		$x->send();
	}

	if ( category_exists( trim( $_POST['cat_name'] ), $_POST['category_parent'] ) ) {
		$x = new WP_Ajax_Response( array(
			'what' => 'cat',
			'id' => new WP_Error( 'cat_exists', __('The category you are trying to create already exists.'), array( 'form-field' => 'cat_name' ) ),
		) );
		$x->send();
	}

	$cat = wp_insert_category( $_POST, true );

	if ( is_wp_error($cat) ) {
		$x = new WP_Ajax_Response( array(
			'what' => 'cat',
			'id' => $cat
		) );
		$x->send();
	}

	if ( !$cat || (!$cat = get_category( $cat )) )
		die('0');

	$level = 0;
	$cat_full_name = $cat->name;
	$_cat = $cat;
	while ( $_cat->parent ) {
		$_cat = get_category( $_cat->parent );
		$cat_full_name = $_cat->name . ' &#8212; ' . $cat_full_name;
		$level++;
	}
	$cat_full_name = esc_attr($cat_full_name);

	$x = new WP_Ajax_Response( array(
		'what' => 'cat',
		'id' => $cat->term_id,
		'position' => -1,
		'data' => _cat_row( $cat, $level, $cat_full_name ),
		'supplemental' => array('name' => $cat_full_name, 'show-link' => sprintf(__( 'Category <a href="#%s">%s</a> added' ), "cat-$cat->term_id", $cat_full_name))
	) );
	$x->send();
	break;
case 'add-link-cat' : // From Blogroll -> Categories
	check_ajax_referer( 'add-link-category' );
	if ( !current_user_can( 'manage_categories' ) )
		die('-1');

	if ( '' === trim($_POST['name']) ) {
		$x = new WP_Ajax_Response( array(
			'what' => 'link-cat',
			'id' => new WP_Error( 'name', __('You did not enter a category name.') )
		) );
		$x->send();
	}

	$r = wp_insert_term($_POST['name'], 'link_category', $_POST );
	if ( is_wp_error( $r ) ) {
		$x = new WP_AJAX_Response( array(
			'what' => 'link-cat',
			'id' => $r
		) );
		$x->send();
	}

	extract($r, EXTR_SKIP);

	if ( !$link_cat = link_cat_row( $term_id ) )
		die('0');

	$x = new WP_Ajax_Response( array(
		'what' => 'link-cat',
		'id' => $term_id,
		'position' => -1,
		'data' => $link_cat
	) );
	$x->send();
	break;
case 'add-tag' : // From Manage->Tags
	check_ajax_referer( 'add-tag' );
	if ( !current_user_can( 'manage_categories' ) )
		die('-1');

	$taxonomy = !empty($_POST['taxonomy']) ? $_POST['taxonomy'] : 'post_tag';
	$tag = wp_insert_term($_POST['tag-name'], $taxonomy, $_POST );

	if ( !$tag || is_wp_error($tag) || (!$tag = get_term( $tag['term_id'], $taxonomy )) ) {
		echo '<div class="error"><p>' . __('An error has occured. Please reload the page and try again.') . '</p></div>';
		exit;
	}

	echo _tag_row( $tag, '', $taxonomy );
	exit;
	break;
case 'get-tagcloud' :
	if ( !current_user_can( 'edit_posts' ) )
		die('-1');

	if ( isset($_POST['tax']) )
		$taxonomy = sanitize_title($_POST['tax']);
	else
		die('0');

	$tags = get_terms( $taxonomy, array( 'number' => 45, 'orderby' => 'count', 'order' => 'DESC' ) );

	if ( empty( $tags ) )
		die( __('No tags found!') );

	if ( is_wp_error($tags) )
		die($tags->get_error_message());

	foreach ( $tags as $key => $tag ) {
		$tags[ $key ]->link = '#';
		$tags[ $key ]->id = $tag->term_id;
	}

	// We need raw tag names here, so don't filter the output
	$return = wp_generate_tag_cloud( $tags, array('filter' => 0) );

	if ( empty($return) )
		die('0');

	echo $return;

	exit;
	break;
case 'add-comment' :
	check_ajax_referer( $action );
	if ( !current_user_can( 'edit_posts' ) )
		die('-1');
	$search = isset($_POST['s']) ? $_POST['s'] : false;
	$status = isset($_POST['comment_status']) ? $_POST['comment_status'] : 'all';
	$per_page = isset($_POST['per_page']) ?  (int) $_POST['per_page'] + 8 : 28;
	$start = isset($_POST['page']) ? ( intval($_POST['page']) * $per_page ) -1 : $per_page - 1;
	if ( 1 > $start )
		$start = 27;

	$mode = isset($_POST['mode']) ? $_POST['mode'] : 'detail';
	$p = isset($_POST['p']) ? $_POST['p'] : 0;
	$comment_type = isset($_POST['comment_type']) ? $_POST['comment_type'] : '';
	list($comments, $total) = _wp_get_comment_list( $status, $search, $start, 1, $p, $comment_type );

	if ( get_option('show_avatars') )
		add_filter( 'comment_author', 'floated_admin_avatar' );

	if ( !$comments )
		die('1');
	$x = new WP_Ajax_Response();
	foreach ( (array) $comments as $comment ) {
		get_comment( $comment );
		ob_start();
			_wp_comment_row( $comment->comment_ID, $mode, $status, true, true );
			$comment_list_item = ob_get_contents();
		ob_end_clean();
		$x->add( array(
			'what' => 'comment',
			'id' => $comment->comment_ID,
			'data' => $comment_list_item
		) );
	}
	$x->send();
	break;
case 'get-comments' :
	check_ajax_referer( $action );

	$post_ID = (int) $_POST['post_ID'];
	if ( !current_user_can( 'edit_post', $post_ID ) )
		die('-1');

	$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
	$num = isset($_POST['num']) ? intval($_POST['num']) : 10;

	list($comments, $total) = _wp_get_comment_list( false, false, $start, $num, $post_ID );

	if ( !$comments )
		die('1');

	$comment_list_item = '';
	$x = new WP_Ajax_Response();
	foreach ( (array) $comments as $comment ) {
		get_comment( $comment );
		ob_start();
			_wp_comment_row( $comment->comment_ID, 'single', false, false );
			$comment_list_item .= ob_get_contents();
		ob_end_clean();
	}
	$x->add( array(
		'what' => 'comments',
		'data' => $comment_list_item
	) );
	$x->send();
	break;
case 'replyto-comment' :
	check_ajax_referer( $action );

	$comment_post_ID = (int) $_POST['comment_post_ID'];
	if ( !current_user_can( 'edit_post', $comment_post_ID ) )
		die('-1');

	$status = $wpdb->get_var( $wpdb->prepare("SELECT post_status FROM $wpdb->posts WHERE ID = %d", $comment_post_ID) );

	if ( empty($status) )
		die('1');
	elseif ( in_array($status, array('draft', 'pending', 'trash') ) )
		die( __('Error: you are replying to a comment on a draft post.') );

	$user = wp_get_current_user();
	if ( $user->ID ) {
		$comment_author       = $wpdb->escape($user->display_name);
		$comment_author_email = $wpdb->escape($user->user_email);
		$comment_author_url   = $wpdb->escape($user->user_url);
		$comment_content      = trim($_POST['content']);
		if ( current_user_can('unfiltered_html') ) {
			if ( wp_create_nonce('unfiltered-html-comment_' . $comment_post_ID) != $_POST['_wp_unfiltered_html_comment'] ) {
				kses_remove_filters(); // start with a clean slate
				kses_init_filters(); // set up the filters
			}
		}
	} else {
		die( __('Sorry, you must be logged in to reply to a comment.') );
	}

	if ( '' == $comment_content )
		die( __('Error: please type a comment.') );

	$comment_parent = absint($_POST['comment_ID']);
	$commentdata = compact('comment_post_ID', 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_content', 'comment_type', 'comment_parent', 'user_ID');

	$comment_id = wp_new_comment( $commentdata );
	$comment = get_comment($comment_id);
	if ( ! $comment ) die('1');

	$modes = array( 'single', 'detail', 'dashboard' );
	$mode = isset($_POST['mode']) && in_array( $_POST['mode'], $modes ) ? $_POST['mode'] : 'detail';
	$position = ( isset($_POST['position']) && (int) $_POST['position']) ? (int) $_POST['position'] : '-1';
	$checkbox = ( isset($_POST['checkbox']) && true == $_POST['checkbox'] ) ? 1 : 0;

	if ( get_option('show_avatars') && 'single' != $mode )
		add_filter( 'comment_author', 'floated_admin_avatar' );

	$x = new WP_Ajax_Response();

	ob_start();
		if ( 'dashboard' == $mode ) {
			require_once( ABSPATH . 'wp-admin/includes/dashboard.php' );
			_wp_dashboard_recent_comments_row( $comment, false );
		} else {
			_wp_comment_row( $comment->comment_ID, $mode, false, $checkbox );
		}
		$comment_list_item = ob_get_contents();
	ob_end_clean();

	$x->add( array(
		'what' => 'comment',
		'id' => $comment->comment_ID,
		'data' => $comment_list_item,
		'position' => $position
	));

	$x->send();
	break;
case 'edit-comment' :
	check_ajax_referer( 'replyto-comment' );

	$comment_post_ID = (int) $_POST['comment_post_ID'];
	if ( ! current_user_can( 'edit_post', $comment_post_ID ) )
		die('-1');

	if ( '' == $_POST['content'] )
		die( __('Error: please type a comment.') );

	$comment_id = (int) $_POST['comment_ID'];
	$_POST['comment_status'] = $_POST['status'];
	edit_comment();

	$mode = ( isset($_POST['mode']) && 'single' == $_POST['mode'] ) ? 'single' : 'detail';
	$position = ( isset($_POST['position']) && (int) $_POST['position']) ? (int) $_POST['position'] : '-1';
	$checkbox = ( isset($_POST['checkbox']) && true == $_POST['checkbox'] ) ? 1 : 0;
	$comments_listing = isset($_POST['comments_listing']) ? $_POST['comments_listing'] : '';

	if ( get_option('show_avatars') && 'single' != $mode )
		add_filter( 'comment_author', 'floated_admin_avatar' );

	$x = new WP_Ajax_Response();

	ob_start();
		_wp_comment_row( $comment_id, $mode, $comments_listing, $checkbox );
		$comment_list_item = ob_get_contents();
	ob_end_clean();

	$x->add( array(
		'what' => 'edit_comment',
		'id' => $comment->comment_ID,
		'data' => $comment_list_item,
		'position' => $position
	));

	$x->send();
	break;
case 'add-meta' :
	check_ajax_referer( 'add-meta' );
	$c = 0;
	$pid = (int) $_POST['post_id'];
	if ( isset($_POST['metakeyselect']) || isset($_POST['metakeyinput']) ) {
		if ( !current_user_can( 'edit_post', $pid ) )
			die('-1');
		if ( isset($_POST['metakeyselect']) && '#NONE#' == $_POST['metakeyselect'] && empty($_POST['metakeyinput']) )
			die('1');
		if ( $pid < 0 ) {
			$now = current_time('timestamp', 1);
			if ( $pid = wp_insert_post( array(
				'post_title' => sprintf('Draft created on %s at %s', date(get_option('date_format'), $now), date(get_option('time_format'), $now))
			) ) ) {
				if ( is_wp_error( $pid ) ) {
					$x = new WP_Ajax_Response( array(
						'what' => 'meta',
						'data' => $pid
					) );
					$x->send();
				}
				if ( !$mid = add_meta( $pid ) )
					die(__('Please provide a custom field value.'));
			} else {
				die('0');
			}
		} else if ( !$mid = add_meta( $pid ) ) {
			die(__('Please provide a custom field value.'));
		}

		$meta = get_post_meta_by_id( $mid );
		$pid = (int) $meta->post_id;
		$meta = get_object_vars( $meta );
		$x = new WP_Ajax_Response( array(
			'what' => 'meta',
			'id' => $mid,
			'data' => _list_meta_row( $meta, $c ),
			'position' => 1,
			'supplemental' => array('postid' => $pid)
		) );
	} else {
		$mid = (int) array_pop(array_keys($_POST['meta']));
		$key = $_POST['meta'][$mid]['key'];
		$value = $_POST['meta'][$mid]['value'];
		if ( !$meta = get_post_meta_by_id( $mid ) )
			die('0'); // if meta doesn't exist
		if ( !current_user_can( 'edit_post', $meta->post_id ) )
			die('-1');
		if ( $meta->meta_value != stripslashes($value) ) {
			if ( !$u = update_meta( $mid, $key, $value ) )
				die('0'); // We know meta exists; we also know it's unchanged (or DB error, in which case there are bigger problems).
		}

		$key = stripslashes($key);
		$value = stripslashes($value);
		$x = new WP_Ajax_Response( array(
			'what' => 'meta',
			'id' => $mid, 'old_id' => $mid,
			'data' => _list_meta_row( array(
				'meta_key' => $key,
				'meta_value' => $value,
				'meta_id' => $mid
			), $c ),
			'position' => 0,
			'supplemental' => array('postid' => $meta->post_id)
		) );
	}
	$x->send();
	break;
case 'add-user' :
	check_ajax_referer( $action );
	if ( !current_user_can('create_users') )
		die('-1');
	require_once(ABSPATH . WPINC . '/registration.php');
	if ( !$user_id = add_user() )
		die('0');
	elseif ( is_wp_error( $user_id ) ) {
		$x = new WP_Ajax_Response( array(
			'what' => 'user',
			'id' => $user_id
		) );
		$x->send();
	}
	$user_object = new WP_User( $user_id );

	$x = new WP_Ajax_Response( array(
		'what' => 'user',
		'id' => $user_id,
		'data' => user_row( $user_object, '', $user_object->roles[0] ),
		'supplemental' => array(
			'show-link' => sprintf(__( 'User <a href="#%s">%s</a> added' ), "user-$user_id", $user_object->user_login),
			'role' => $user_object->roles[0]
		)
	) );
	$x->send();
	break;
case 'autosave' : // The name of this action is hardcoded in edit_post()
	define( 'DOING_AUTOSAVE', true );

	$nonce_age = check_ajax_referer( 'autosave', 'autosavenonce' );
	global $current_user;

	$_POST['post_category'] = explode(",", $_POST['catslist']);
	if($_POST['post_type'] == 'page' || empty($_POST['post_category']))
		unset($_POST['post_category']);

	$do_autosave = (bool) $_POST['autosave'];
	$do_lock = true;

	$data = '';
	/* translators: draft saved date format, see http://php.net/date */
	$draft_saved_date_format = __('g:i:s a');
	$message = sprintf( __('Draft Saved at %s.'), date_i18n( $draft_saved_date_format ) );

	$supplemental = array();
	if ( isset($login_grace_period) )
		$supplemental['session_expired'] = add_query_arg( 'interim-login', 1, wp_login_url() );

	$id = $revision_id = 0;
	if($_POST['post_ID'] < 0) {
		$_POST['post_status'] = 'draft';
		$_POST['temp_ID'] = $_POST['post_ID'];
		if ( $do_autosave ) {
			$id = wp_write_post();
			$data = $message;
		}
	} else {
		$post_ID = (int) $_POST['post_ID'];
		$_POST['ID'] = $post_ID;
		$post = get_post($post_ID);

		if ( $last = wp_check_post_lock( $post->ID ) ) {
			$do_autosave = $do_lock = false;

			$last_user = get_userdata( $last );
			$last_user_name = $last_user ? $last_user->display_name : __( 'Someone' );
			$data = new WP_Error( 'locked', sprintf(
				$_POST['post_type'] == 'page' ? __( 'Autosave disabled: %s is currently editing this page.' ) : __( 'Autosave disabled: %s is currently editing this post.' ),
				esc_html( $last_user_name )
			) );

			$supplemental['disable_autosave'] = 'disable';
		}

		if ( 'page' == $post->post_type ) {
			if ( !current_user_can('edit_page', $post_ID) )
				die(__('You are not allowed to edit this page.'));
		} else {
			if ( !current_user_can('edit_post', $post_ID) )
				die(__('You are not allowed to edit this post.'));
		}

		if ( $do_autosave ) {
			// Drafts are just overwritten by autosave
			if ( 'draft' == $post->post_status ) {
				$id = edit_post();
			} else { // Non drafts are not overwritten.  The autosave is stored in a special post revision.
				$revision_id = wp_create_post_autosave( $post->ID );
				if ( is_wp_error($revision_id) )
					$id = $revision_id;
				else
					$id = $post->ID;
			}
			$data = $message;
		} else {
			$id = $post->ID;
		}
	}

	if ( $do_lock && $id && is_numeric($id) )
		wp_set_post_lock( $id );

	if ( $nonce_age == 2 ) {
		$supplemental['replace-autosavenonce'] = wp_create_nonce('autosave');
		$supplemental['replace-getpermalinknonce'] = wp_create_nonce('getpermalink');
		$supplemental['replace-samplepermalinknonce'] = wp_create_nonce('samplepermalink');
		$supplemental['replace-closedpostboxesnonce'] = wp_create_nonce('closedpostboxes');
		if ( $id ) {
			if ( $_POST['post_type'] == 'post' )
				$supplemental['replace-_wpnonce'] = wp_create_nonce('update-post_' . $id);
			elseif ( $_POST['post_type'] == 'page' )
				$supplemental['replace-_wpnonce'] = wp_create_nonce('update-page_' . $id);
		}
	}

	$x = new WP_Ajax_Response( array(
		'what' => 'autosave',
		'id' => $id,
		'data' => $id ? $data : '',
		'supplemental' => $supplemental
	) );
	$x->send();
	break;
case 'autosave-generate-nonces' :
	check_ajax_referer( 'autosave', 'autosavenonce' );
	$ID = (int) $_POST['post_ID'];
	$post_type = ( 'page' == $_POST['post_type'] ) ? 'page' : 'post';
	if ( current_user_can( "edit_{$post_type}", $ID ) )
		die( json_encode( array( 'updateNonce' => wp_create_nonce( "update-{$post_type}_{$ID}" ), 'deleteURL' => str_replace( '&amp;', '&', wp_nonce_url( admin_url( $post_type . '.php?action=trash&post=' . $ID ), "trash-{$post_type}_{$ID}" ) ) ) ) );
	do_action('autosave_generate_nonces');
	die('0');
break;
case 'closed-postboxes' :
	check_ajax_referer( 'closedpostboxes', 'closedpostboxesnonce' );
	$closed = isset( $_POST['closed'] ) ? $_POST['closed'] : '';
	$closed = explode( ',', $_POST['closed'] );
	$hidden = isset( $_POST['hidden'] ) ? $_POST['hidden'] : '';
	$hidden = explode( ',', $_POST['hidden'] );
	$page = isset( $_POST['page'] ) ? $_POST['page'] : '';

	if ( !preg_match( '/^[a-z_-]+$/', $page ) )
		die('-1');

	if ( ! $user = wp_get_current_user() )
		die('-1');

	if ( is_array($closed) )
		update_usermeta($user->ID, 'closedpostboxes_'.$page, $closed);

	if ( is_array($hidden) ) {
		$hidden = array_diff( $hidden, array('submitdiv', 'linksubmitdiv') ); // postboxes that are always shown
		update_usermeta($user->ID, 'meta-box-hidden_'.$page, $hidden);
	}

	die('1');
	break;
case 'hidden-columns' :
	check_ajax_referer( 'screen-options-nonce', 'screenoptionnonce' );
	$hidden = isset( $_POST['hidden'] ) ? $_POST['hidden'] : '';
	$hidden = explode( ',', $_POST['hidden'] );
	$page = isset( $_POST['page'] ) ? $_POST['page'] : '';

	if ( !preg_match( '/^[a-z_-]+$/', $page ) )
		die('-1');

	if ( ! $user = wp_get_current_user() )
		die('-1');

	if ( is_array($hidden) )
		update_usermeta($user->ID, "manage-$page-columns-hidden", $hidden);

	die('1');
	break;
case 'meta-box-order':
	check_ajax_referer( 'meta-box-order' );
	$order = isset( $_POST['order'] ) ? (array) $_POST['order'] : false;
	$page_columns = isset( $_POST['page_columns'] ) ? (int) $_POST['page_columns'] : 0;
	$page = isset( $_POST['page'] ) ? $_POST['page'] : '';

	if ( !preg_match( '/^[a-z_-]+$/', $page ) )
		die('-1');

	if ( ! $user = wp_get_current_user() )
		die('-1');

	if ( $order )
		update_user_option($user->ID, "meta-box-order_$page", $order);

	if ( $page_columns )
		update_usermeta($user->ID, "screen_layout_$page", $page_columns);

	die('1');
	break;
case 'get-permalink':
	check_ajax_referer( 'getpermalink', 'getpermalinknonce' );
	$post_id = isset($_POST['post_id'])? intval($_POST['post_id']) : 0;
	die(add_query_arg(array('preview' => 'true'), get_permalink($post_id)));
break;
case 'sample-permalink':
	check_ajax_referer( 'samplepermalink', 'samplepermalinknonce' );
	$post_id = isset($_POST['post_id'])? intval($_POST['post_id']) : 0;
	$title = isset($_POST['new_title'])? $_POST['new_title'] : '';
	$slug = isset($_POST['new_slug'])? $_POST['new_slug'] : '';
	die(get_sample_permalink_html($post_id, $title, $slug));
break;
case 'inline-save':
	check_ajax_referer( 'inlineeditnonce', '_inline_edit' );

	if ( ! isset($_POST['post_ID']) || ! ( $post_ID = (int) $_POST['post_ID'] ) )
		exit;

	if ( 'page' == $_POST['post_type'] ) {
		if ( ! current_user_can( 'edit_page', $post_ID ) )
			die( __('You are not allowed to edit this page.') );
	} else {
		if ( ! current_user_can( 'edit_post', $post_ID ) )
			die( __('You are not allowed to edit this post.') );
	}

	if ( $last = wp_check_post_lock( $post_ID ) ) {
		$last_user = get_userdata( $last );
		$last_user_name = $last_user ? $last_user->display_name : __( 'Someone' );
		printf( $_POST['post_type'] == 'page' ? __( 'Saving is disabled: %s is currently editing this page.' ) : __( 'Saving is disabled: %s is currently editing this post.' ),	esc_html( $last_user_name ) );
		exit;
	}

	$data = &$_POST;

	$post = get_post( $post_ID, ARRAY_A );
	$post = add_magic_quotes($post); //since it is from db

	$data['content'] = $post['post_content'];
	$data['excerpt'] = $post['post_excerpt'];

	// rename
	$data['user_ID'] = $GLOBALS['user_ID'];

	if ( isset($data['post_parent']) )
		$data['parent_id'] = $data['post_parent'];

	// status
	if ( isset($data['keep_private']) && 'private' == $data['keep_private'] )
		$data['post_status'] = 'private';
	else
		$data['post_status'] = $data['_status'];

	if ( empty($data['comment_status']) )
		$data['comment_status'] = 'closed';
	if ( empty($data['ping_status']) )
		$data['ping_status'] = 'closed';

	// update the post
	edit_post();

	$post = array();
	if ( 'page' == $_POST['post_type'] ) {
		$post[] = get_post($_POST['post_ID']);
		page_rows($post);
	} elseif ( 'post' == $_POST['post_type'] ) {
		$mode = $_POST['post_view'];
		$post[] = get_post($_POST['post_ID']);
		post_rows($post);
	}

	exit;
	break;
case 'inline-save-tax':
	check_ajax_referer( 'taxinlineeditnonce', '_inline_edit' );

	if ( ! current_user_can('manage_categories') )
		die( __('Cheatin&#8217; uh?') );

	if ( ! isset($_POST['tax_ID']) || ! ( $id = (int) $_POST['tax_ID'] ) )
		die(-1);

	switch ($_POST['tax_type']) {
		case 'cat' :
			$data = array();
			$data['cat_ID'] = $id;
			$data['cat_name'] = $_POST['name'];
			$data['category_nicename'] = $_POST['slug'];
			if ( isset($_POST['parent']) && (int) $_POST['parent'] > 0 )
				$data['category_parent'] = $_POST['parent'];

			$cat = get_category($id, ARRAY_A);
			$data['category_description'] = $cat['category_description'];

			$updated = wp_update_category($data);

			if ( $updated && !is_wp_error($updated) )
				echo _cat_row( $updated, 0 );
			else
				die( __('Category not updated.') );

			break;
		case 'link-cat' :
			$updated = wp_update_term($id, 'link_category', $_POST);

			if ( $updated && !is_wp_error($updated) )
				echo link_cat_row($updated['term_id']);
			else
				die( __('Category not updated.') );

			break;
		case 'tag' :
			$taxonomy = !empty($_POST['taxonomy']) ? $_POST['taxonomy'] : 'post_tag';

			$tag = get_term( $id, $taxonomy );
			$_POST['description'] = $tag->description;

			$updated = wp_update_term($id, $taxonomy, $_POST);
			if ( $updated && !is_wp_error($updated) ) {
				$tag = get_term( $updated['term_id'], $taxonomy );
				if ( !$tag || is_wp_error( $tag ) )
					die( __('Tag not updated.') );

				echo _tag_row($tag, '', $taxonomy);
			} else {
				die( __('Tag not updated.') );
			}

			break;
	}

	exit;
	break;
case 'find_posts':
	check_ajax_referer( 'find-posts' );

	if ( empty($_POST['ps']) )
		exit;

	$what = isset($_POST['pages']) ? 'page' : 'post';
	$s = stripslashes($_POST['ps']);
	preg_match_all('/".*?("|$)|((?<=[\\s",+])|^)[^\\s",+]+/', $s, $matches);
	$search_terms = array_map('_search_terms_tidy', $matches[0]);

	$searchand = $search = '';
	foreach ( (array) $search_terms as $term ) {
		$term = addslashes_gpc($term);
		$search .= "{$searchand}(($wpdb->posts.post_title LIKE '%{$term}%') OR ($wpdb->posts.post_content LIKE '%{$term}%'))";
		$searchand = ' AND ';
	}
	$term = $wpdb->escape($s);
	if ( count($search_terms) > 1 && $search_terms[0] != $s )
		$search .= " OR ($wpdb->posts.post_title LIKE '%{$term}%') OR ($wpdb->posts.post_content LIKE '%{$term}%')";

	$posts = $wpdb->get_results( "SELECT ID, post_title, post_status, post_date FROM $wpdb->posts WHERE post_type = '$what' AND post_status IN ('draft', 'publish') AND ($search) ORDER BY post_date_gmt DESC LIMIT 50" );

	if ( ! $posts )
		exit( __('No posts found.') );

	$html = '<table class="widefat" cellspacing="0"><thead><tr><th class="found-radio"><br /></th><th>'.__('Title').'</th><th>'.__('Date').'</th><th>'.__('Status').'</th></tr></thead><tbody>';
	foreach ( $posts as $post ) {

		switch ( $post->post_status ) {
			case 'publish' :
			case 'private' :
				$stat = __('Published');
				break;
			case 'future' :
				$stat = __('Scheduled');
				break;
			case 'pending' :
				$stat = __('Pending Review');
				break;
			case 'draft' :
				$stat = __('Draft');
				break;
		}

		if ( '0000-00-00 00:00:00' == $post->post_date ) {
			$time = '';
		} else {
			/* translators: date format in table columns, see http://php.net/date */
			$time = mysql2date(__('Y/m/d'), $post->post_date);
		}

		$html .= '<tr class="found-posts"><td class="found-radio"><input type="radio" id="found-'.$post->ID.'" name="found_post_id" value="' . esc_attr($post->ID) . '"></td>';
		$html .= '<td><label for="found-'.$post->ID.'">'.esc_html( $post->post_title ).'</label></td><td>'.esc_html( $time ).'</td><td>'.esc_html( $stat ).'</td></tr>'."\n\n";
	}
	$html .= '</tbody></table>';

	$x = new WP_Ajax_Response();
	$x->add( array(
		'what' => $what,
		'data' => $html
	));
	$x->send();

	break;
case 'lj-importer' :
	check_ajax_referer( 'lj-api-import' );
	if ( !current_user_can( 'publish_posts' ) )
		die('-1');
	if ( empty( $_POST['step'] ) )
		die( '-1' );
	define('WP_IMPORTING', true);
	include( ABSPATH . 'wp-admin/import/livejournal.php' );
	$result = $lj_api_import->{ 'step' . ( (int) $_POST['step'] ) }();
	if ( is_wp_error( $result ) )
		echo $result->get_error_message();
	die;
	break;
case 'widgets-order' :
	check_ajax_referer( 'save-sidebar-widgets', 'savewidgets' );

	if ( !current_user_can('switch_themes') )
		die('-1');

	unset( $_POST['savewidgets'], $_POST['action'] );

	// save widgets order for all sidebars
	if ( is_array($_POST['sidebars']) ) {
		$sidebars = array();
		foreach ( $_POST['sidebars'] as $key => $val ) {
			$sb = array();
			if ( !empty($val) ) {
				$val = explode(',', $val);
				foreach ( $val as $k => $v ) {
					if ( strpos($v, 'widget-') === false )
						continue;

					$sb[$k] = substr($v, strpos($v, '_') + 1);
				}
			}
			$sidebars[$key] = $sb;
		}
		wp_set_sidebars_widgets($sidebars);
		die('1');
	}

	die('-1');
	break;
case 'save-widget' :
	check_ajax_referer( 'save-sidebar-widgets', 'savewidgets' );

	if ( !current_user_can('switch_themes') || !isset($_POST['id_base']) )
		die('-1');

	unset( $_POST['savewidgets'], $_POST['action'] );

	do_action('load-widgets.php');
	do_action('widgets.php');
	do_action('sidebar_admin_setup');

	$id_base = $_POST['id_base'];
	$widget_id = $_POST['widget-id'];
	$sidebar_id = $_POST['sidebar'];
	$multi_number = !empty($_POST['multi_number']) ? (int) $_POST['multi_number'] : 0;
	$settings = isset($_POST['widget-' . $id_base]) && is_array($_POST['widget-' . $id_base]) ? $_POST['widget-' . $id_base] : false;
	$error = '<p>' . __('An error has occured. Please reload the page and try again.') . '</p>';

	$sidebars = wp_get_sidebars_widgets();
	$sidebar = isset($sidebars[$sidebar_id]) ? $sidebars[$sidebar_id] : array();

	// delete
	if ( isset($_POST['delete_widget']) && $_POST['delete_widget'] ) {

		if ( !isset($wp_registered_widgets[$widget_id]) )
			die($error);

		$sidebar = array_diff( $sidebar, array($widget_id) );
		$_POST = array('sidebar' => $sidebar_id, 'widget-' . $id_base => array(), 'the-widget-id' => $widget_id, 'delete_widget' => '1');
	} elseif ( $settings && preg_match( '/__i__|%i%/', key($settings) ) ) {
		if ( !$multi_number )
			die($error);

		$_POST['widget-' . $id_base] = array( $multi_number => array_shift($settings) );
		$widget_id = $id_base . '-' . $multi_number;
		$sidebar[] = $widget_id;
	}
	$_POST['widget-id'] = $sidebar;

	foreach ( (array) $wp_registered_widget_updates as $name => $control ) {

		if ( $name == $id_base ) {
			if ( !is_callable( $control['callback'] ) )
				continue;

			ob_start();
				call_user_func_array( $control['callback'], $control['params'] );
			ob_end_clean();
			break;
		}
	}

	if ( isset($_POST['delete_widget']) && $_POST['delete_widget'] ) {
		$sidebars[$sidebar_id] = $sidebar;
		wp_set_sidebars_widgets($sidebars);
		echo "deleted:$widget_id";
		die();
	}

	if ( !empty($_POST['add_new']) )
		die();

	if ( $form = $wp_registered_widget_controls[$widget_id] )
		call_user_func_array( $form['callback'], $form['params'] );

	die();
	break;
case 'image-editor':
	$attachment_id = intval($_POST['postid']);
	if ( empty($attachment_id) || !current_user_can('edit_post', $attachment_id) )
		die('-1');

	check_ajax_referer( "image_editor-$attachment_id" );
	include_once( ABSPATH . 'wp-admin/includes/image-edit.php' );

	$msg = false;
	switch ( $_POST['do'] ) {
		case 'save' :
			$msg = wp_save_image($attachment_id);
			$msg = json_encode($msg);
			die($msg);
			break;
		case 'scale' :
			$msg = wp_save_image($attachment_id);
			break;
		case 'restore' :
			$msg = wp_restore_image($attachment_id);
			break;
	}

	wp_image_editor($attachment_id, $msg);
	die();
	break;
case 'set-post-thumbnail':
	$post_id = intval( $_POST['post_id'] );
	if ( !current_user_can( 'edit_post', $post_id ) )
		die( '-1' );
	$thumbnail_id = intval( $_POST['thumbnail_id'] );

	if ( $thumbnail_id == '-1' ) {
		delete_post_meta( $post_id, '_thumbnail_id' );
		die( _wp_post_thumbnail_html() );
	}

	if ( $thumbnail_id && get_post( $thumbnail_id ) ) {
		$thumbnail_html = wp_get_attachment_image( $thumbnail_id, 'thumbnail' );
		if ( !empty( $thumbnail_html ) ) {
			update_post_meta( $post_id, '_thumbnail_id', $thumbnail_id );
			die( _wp_post_thumbnail_html( $thumbnail_id ) );
		}
	}
	die( '0' );
default :
	do_action( 'wp_ajax_' . $_POST['action'] );
	die('0');
	break;
endswitch;
?>
