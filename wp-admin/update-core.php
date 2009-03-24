<?php
/**
 * Update Core administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once('admin.php');

if ( ! current_user_can('update_plugins') )
	wp_die(__('You do not have sufficient permissions to update plugins for this blog.'));

function list_core_update( $update ) {
	$version_string = 'en_US' == $update->locale ?
			$update->current : sprintf("%s&ndash;<strong>%s</strong>", $update->current, $update->locale);
	$current = false;
	if ( !isset($update->response) || 'latest' == $update->response )
		$current = true;
	$submit = __('Upgrade Automatically');
	$form_action = 'update-core.php?action=do-core-upgrade';
	if ( 'development' == $update->response ) {
		$message = __('You are using a development version of WordPress.  You can upgrade to the latest nightly build automatically or download the nightly build and install it manually:');
		$download = __('Download nightly build');
	} else {
		if ( $current ) {
			$message = sprintf(__('You have the latest version of WordPress. You do not need to upgrade. However, if you want to re-install version %s, you can do so automatically or download the package and re-install manually:'), $version_string);
			$submit = __('Re-install Automatically');
			$form_action = 'update-core.php?action=do-core-reinstall';
		} else {
			$message = 	sprintf(__('You can upgrade to version %s automatically or download the package and install it manually:'), $version_string);
		}
		$download = sprintf(__('Download %s'), $version_string);
	}

	echo '<p>';
	echo $message;
	echo '</p>';
	echo '<form method="post" action="' . $form_action . '" name="upgrade" class="upgrade">';
	wp_nonce_field('upgrade-core');
	echo '<p>';
	echo '<input id="upgrade" class="button" type="submit" value="' . $submit . '" name="upgrade" />&nbsp;';
	echo '<input name="version" value="'.$update->current.'" type="hidden"/>';
	echo '<input name="locale" value="'.$update->locale.'" type="hidden"/>';
	echo '<a href="' . $update->package . '" class="button">' . $download . '</a>&nbsp;';
	if ( 'en_US' != $update->locale )
		if ( !isset( $update->dismissed ) || !$update->dismissed )
			echo '<input id="dismiss" class="button" type="submit" value="' . attribute_escape(__('Hide this update')) . '" name="dismiss" />';
		else
			echo '<input id="undismiss" class="button" type="submit" value="' . attribute_escape(__('Bring back this update')) . '" name="undismiss" />';
	echo '</p>';
	echo '</form>';

}

function dismissed_updates() {
	$dismissed = get_core_updates( array( 'dismissed' => true, 'available' => false ) );
	if ( $dismissed ) {

		$show_text = js_escape(__('Show hidden updates'));
		$hide_text = js_escape(__('Hide hidden updates'));
	?>
	<script type="text/javascript">

		jQuery(function($) {
			$('dismissed-updates').show();
			$('#show-dismissed').toggle(function(){$(this).text('<?php echo $hide_text; ?>');}, function() {$(this).text('<?php echo $show_text; ?>')});
			$('#show-dismissed').click(function() { $('#dismissed-updates').toggle('slow');});
		});
	</script>
	<?php
		echo '<p class="hide-if-no-js"><a id="show-dismissed" href="#">'.__('Show hidden updates').'</a></p>';
		echo '<ul id="dismissed-updates" class="core-updates dismissed">';
		foreach( (array) $dismissed as $update) {
			echo '<li>';
			list_core_update( $update );
			echo '</li>';
		}
		echo '</ul>';
	}
}

/**
 * Display upgrade WordPress for downloading latest or upgrading automatically form.
 *
 * @since 2.7
 *
 * @return null
 */
function core_upgrade_preamble() {
	$updates = get_core_updates();
?>
	<div class="wrap">
	<?php screen_icon(); ?>
	<h2><?php _e('Upgrade WordPress'); ?></h2>
<?php
	if ( !isset($updates[0]->response) || 'latest' == $updates[0]->response ) {
		echo '<h3>';
		_e('You have the latest version of WordPress. You do not need to upgrade');
		echo '</h3>';
	} else {
		echo '<div class="updated fade"><p>';
		_e('<strong>Important:</strong> before upgrading, please <a href="http://codex.wordpress.org/WordPress_Backups">backup your database and files</a>.');
		echo '</p></div>';

		echo '<h3 class="response">';
		_e( 'There is a new version of WordPress available for upgrade' );
		echo '</h3>';
	}

	echo '<ul class="core-updates">';
	$alternate = true;
	foreach( (array) $updates as $update ) {
		$class = $alternate? ' class="alternate"' : '';
		$alternate = !$alternate;
		echo "<li $class>";
		list_core_update( $update );
		echo '</li>';
	}
	echo '</ul>';
	dismissed_updates();
	echo '</div>';
}


/**
 * Upgrade WordPress core display.
 *
 * @since 2.7
 *
 * @return null
 */
function do_core_upgrade( $reinstall = false ) {
	global $wp_filesystem;

	if ( $reinstall )
		$url = 'update-core.php?action=do-core-reinstall';
	else
		$url = 'update-core.php?action=do-core-upgrade';
	$url = wp_nonce_url($url, 'upgrade-core');
	if ( false === ($credentials = request_filesystem_credentials($url)) )
		return;

	$version = isset( $_POST['version'] )? $_POST['version'] : false;
	$locale = isset( $_POST['locale'] )? $_POST['locale'] : 'en_US';
	$update = find_core_update( $version, $locale );
	if ( !$update )
		return;


	if ( ! WP_Filesystem($credentials) ) {
		request_filesystem_credentials($url, '', true); //Failed to connect, Error and request again
		return;
	}
?>
	<div class="wrap">
	<?php screen_icon(); ?>
	<h2><?php _e('Upgrade WordPress'); ?></h2>
<?php
	if ( $wp_filesystem->errors->get_error_code() ) {
		foreach ( $wp_filesystem->errors->get_error_messages() as $message )
			show_message($message);
		echo '</div>';
		return;
	}

	if ( $reinstall )
		$update->response = 'reinstall';

	$result = wp_update_core($update, 'show_message');

	if ( is_wp_error($result) ) {
		show_message($result);
		if ('up_to_date' != $result->get_error_code() )
			show_message( __('Installation Failed') );
	} else {
		show_message( __('WordPress upgraded successfully') );
	}
	echo '</div>';
}

function do_dismiss_core_update() {
	$version = isset( $_POST['version'] )? $_POST['version'] : false;
	$locale = isset( $_POST['locale'] )? $_POST['locale'] : 'en_US';
	$update = find_core_update( $version, $locale );
	if ( !$update )
		return;
	dismiss_core_update( $update );
	wp_redirect( wp_nonce_url('update-core.php?action=upgrade-core', 'upgrade-core') );
}

function do_undismiss_core_update() {
	$version = isset( $_POST['version'] )? $_POST['version'] : false;
	$locale = isset( $_POST['locale'] )? $_POST['locale'] : 'en_US';
	$update = find_core_update( $version, $locale );
	if ( !$update )
		return;
	undismiss_core_update( $version, $locale );
	wp_redirect( wp_nonce_url('update-core.php?action=upgrade-core', 'upgrade-core') );
}

$action = isset($_GET['action']) ? $_GET['action'] : 'upgrade-core';

if ( 'upgrade-core' == $action ) {
	$title = __('Upgrade WordPress');
	$parent_file = 'tools.php';
	require_once('admin-header.php');
	core_upgrade_preamble();
	include('admin-footer.php');
} elseif ( 'do-core-upgrade' == $action || 'do-core-reinstall' == $action ) {
	check_admin_referer('upgrade-core');
	$title = __('Upgrade WordPress');
	$parent_file = 'tools.php';
	// do the (un)dismiss actions before headers,
	// so that they can redirect
	if ( isset( $_POST['dismiss'] ) )
		do_dismiss_core_update();
	elseif ( isset( $_POST['undismiss'] ) )
	do_undismiss_core_update();
	require_once('admin-header.php');
	if ( 'do-core-reinstall' == $action )
		$reinstall = true;
	else
		$reinstall = false;
	if ( isset( $_POST['upgrade'] ) )
		do_core_upgrade($reinstall);
	include('admin-footer.php');

}?>
