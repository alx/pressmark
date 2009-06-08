<?php
if(!defined( 'ABSPATH') && !defined('WP_UNINSTALL_PLUGIN')) {
	exit();
} else {
	global $wpdb, $table_prefix;
	// get name of subscribe2 table
	$public = $table_prefix . "subscribe2";
	// delete entry from wp_options table
	delete_option('subscribe2_options');
	// delete legacy entry from wp-options table
	delete_option('s2_future_posts');
	// remove and scheduled events
	wp_clear_scheduled_hook('s2_digest_cron');
	// delete usermeta data for registered users
	$users = $wpdb->get_col("SELECT ID FROM $wpdb->users");
	if (!empty($users)) {
		foreach ($users as $user) {
			$cats = explode(',', get_usermeta($user, 's2_subscribed'));
			if ($cats) {
				foreach ($cats as $cat) {
					delete_usermeta($user, "s2_cat" . $cat);
				}
			}
			delete_usermeta($user, 's2_subscribed');
		}
	}
	// drop the subscribe2 table
	$sql = "DROP TABLE IF EXISTS `" . $public . "`";
	mysql_query($sql);
}
?>