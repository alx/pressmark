<?php
/**
 * @package Author_Limit
 * @author Alexandre Girard
 * @version 1.0
 */
/*
Plugin Name: Author Limit
Plugin URI: http://github.com/alx/pressmark/tree/getwebhost
Description: Verify if the specified author has reached a limited number of post during the current day. Admin is not concerned by this limit
Author: Alexandre Girard
Version: 1.0
Author URI: http://alexgirard.com
*/

function is_author_limited($author_id, $limit = 10) {
	
	global $wpdb;
	
	// return false if admin
	if($author_id == 1)
		return false;
		
	//Get number of posts of the author during the day
	$userid = (int) $author_id;
	$count = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM $wpdb->posts WHERE datediff(day,getdate(),post_date)=0 AND post_date <= getdate() AND post_author = %d AND post_type = 'post' AND ", $userid) . get_private_posts_cap_sql('post'));
	
	if($count >= $limit):
		// number of post has been reached
		return true;
	else:
		// user can continue to post
		return false;
	endif;
}

?>
