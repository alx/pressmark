<?php
/**
 * interface.php
 *
 * User Interface Elements for wp-openid
 * Dual Licence: GPL & Modified BSD
 */
if (!class_exists('WordPressOpenID_Interface')):
class WordPressOpenID_Interface {

	/**
	 * Provide more useful OpenID error message to the user.
	 *
	 * @filter: login_errors
	 **/
	function login_form_hide_username_password_errors($r) {
		global $openid;

		if( $_POST['openid_url']
			or $_REQUEST['action'] == 'login'
			or $_REQUEST['action'] == 'comment' ) return $openid->message;
		return $r;
	}


	/**
	 * Add OpenID input field to wp-login.php
	 *
	 * @action: login_form
	 **/
	function login_form() {
		global $wp_version;

		$link_class = 'openid_link';
		if ($wp_version < '2.5') {
			$link_class .= ' legacy';
		}

		?>
		<hr />
		<p style="margin-top: 1em;">
			<label><?php printf(__('Or login using your %s url:', 'openid'), '<a class="'.$link_class.'" href="http://openid.net/">'.__('OpenID', 'openid').'</a>') ?><br/>
			<input type="text" name="openid_url" id="openid_url" class="input openid_url" value="" size="20" tabindex="25" /></label>
		</p>
		<?php
	}


	/**
	 * Add information about registration to wp-login.php?action=register 
	 *
	 * @action: register_form
	 **/
	function register_form() {
		echo '<p>';
		printf(__('For faster registration, just %s login with %s.', 'openid'), '<a href="'.get_option('siteurl').'/wp-login.php">', '<span class="openid_link">'.__('OpenID', 'openid').'</span></a>');
		echo '</p>';
	}

	
	/**
	 * Add OpenID class to author link.
	 *
	 * @filter: get_comment_author_link
	 **/
	function comment_author_link( $html ) {
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
	 * Enqueue required javascript libraries.
	 *
	 * @action: init
	 **/
	function js_setup() {
		if (is_single() || is_comments_popup() || is_admin()) {
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script('jquery.textnode', '/' . PLUGINDIR . '/openid/files/jquery.textnode.min.js', 
				array('jquery'), WPOPENID_PLUGIN_REVISION);
			wp_enqueue_script('jquery.xpath', '/' . PLUGINDIR . '/openid/files/jquery.xpath.min.js', 
				array('jquery'), WPOPENID_PLUGIN_REVISION);
			wp_enqueue_script('openid', '/' . PLUGINDIR . '/openid/files/openid.min.js', 
				array('jquery','jquery.textnode'), WPOPENID_PLUGIN_REVISION);
		}
	}


	/**
	 * Include internal stylesheet.
	 *
	 * @action: wp_head, login_head
	 **/
	function style() {
		$css_path = get_option('siteurl') . '/' . PLUGINDIR . '/openid/files/openid.css?ver='.WPOPENID_PLUGIN_REVISION;
		echo '
			<link rel="stylesheet" type="text/css" href="'.$css_path.'" />';
	}


	/**
	 * Print jQuery call for slylizing profile link.
	 *
	 * @action: comment_form
	 **/
	function comment_profilelink() {
		if (is_user_openid()) {
			echo '<script type="text/javascript">stylize_profilelink()</script>';
		}
	}


	/**
	 * Print jQuery call to modify comment form.
	 *
	 * @action: comment_form
	 **/
	function comment_form() {
		if (!is_user_logged_in()) {
			echo '<script type="text/javascript">add_openid_to_comment_form()</script>';
		}
	}


	/**
	 * Spam up the admin interface with warnings.
	 **/
	function admin_notices_plugin_problem_warning() {
		echo'<div class="error"><p><strong>'.__('The WordPress OpenID plugin is not active.', 'openid').'</strong>';
		printf(_('Check %sOpenID Options%s for a full diagnositic report.', 'openid'), '<a href="options-general.php?page=global-openid-options">', '</a>');
		echo '</p></div>';
	}
	

	/**
	 * Setup admin menus for OpenID options and ID management.
	 *
	 * @action: admin_menu
	 **/
	function add_admin_panels() {
		$hookname = add_options_page(__('OpenID options', 'openid'), __('WP-OpenID', 'openid'), 8, 'global-openid-options', 
			array( 'WordPressOpenID_Interface', 'options_page')  );
		add_action("load-$hookname", array( 'WordPressOpenID_Interface', 'js_setup' ));
		add_action("admin_head-$hookname", array( 'WordPressOpenID_Interface', 'style' ));

		$hookname =	add_submenu_page('profile.php', __('Your Identity URLs', 'openid'), __('Your Identity URLs', 'openid'), 
			'read', 'openid', array('WordPressOpenID_Interface', 'profile_panel') );
		add_action("admin_head-$hookname", array( 'WordPressOpenID_Interface', 'style' ));
		add_action("load-$hookname", array( 'WordPressOpenID_Logic', 'openid_profile_management' ));
	}


	/*
	 * Display and handle updates from the Admin screen options page.
	 *
	 * @options_page
	 */
	function options_page() {
		global $wp_version, $openid;

			WordPressOpenID_Logic::late_bind();
		
			if ( isset($_REQUEST['action']) ) {
				switch($_REQUEST['action']) {
					case 'rebuild_tables' :
						check_admin_referer('wp-openid-info_rebuild_tables');
						$openid->store->destroy_tables();
						$openid->store->create_tables();
						echo '<div class="updated"><p><strong>'.__('OpenID tables rebuilt.', 'openid').'</strong></p></div>';
						break;
				}
			}

			// if we're posted back an update, let's set the values here
			if ( isset($_POST['info_update']) ) {
			
				check_admin_referer('wp-openid-info_update');

				$error = '';
				
				update_option( 'oid_enable_commentform', isset($_POST['enable_commentform']) ? true : false );
				update_option( 'oid_enable_approval', isset($_POST['enable_approval']) ? true : false );
				update_option( 'oid_enable_email_mapping', isset($_POST['enable_email_mapping']) ? true : false );

				if ($error !== '') {
					echo '<div class="error"><p><strong>'.__('At least one of OpenID options was NOT updated', 'openid').'</strong>'.$error.'</p></div>';
				} else {
					echo '<div class="updated"><p><strong>'.__('Open ID options updated', 'openid').'</strong></p></div>';
				}
				
			}

			
			// Display the options page form
			$siteurl = get_option('home');
			if( substr( $siteurl, -1, 1 ) !== '/' ) $siteurl .= '/';
			?>
			<div class="wrap">
				<h2><?php _e('WP-OpenID Registration Options', 'openid') ?></h2>

				<?php if ($wp_version >= '2.3') { WordPressOpenID_Interface::printSystemStatus(); } ?>

				<form method="post">

					<?php if ($wp_version < '2.3') { ?>
     				<p class="submit"><input type="submit" name="info_update" value="<?php _e('Update Options') ?> &raquo;" /></p>
					<?php } ?>

     				<table class="form-table optiontable editform" cellspacing="2" cellpadding="5" width="100%">
						<tr valign="top">
							<th style="width: 33%" scope="row"><?php _e('Automatic Approval:', 'openid') ?></th>
							<td>
								<p><input type="checkbox" name="enable_approval" id="enable_approval" <?php 
									echo get_option('oid_enable_approval') ? 'checked="checked"' : ''; ?> />
									<label for="enable_approval"><?php _e('Enable OpenID comment auto-approval', 'openid') ?></label>

								<p><?php _e('For now this option will cause comments made with OpenIDs '
								. 'to be automatically approved.  Since most spammers haven\'t started '
								. 'using OpenID yet, this is probably pretty safe.  More importantly '
								. 'however, this could be a foundation on which to build more advanced '
								. 'automatic approval such as whitelists or a third-party trust service.', 'openid') ?>
								</p>

								<p><?php _e('Note that this option will cause OpenID authenticated comments '
								. 'to appear, even if you have enabled the option, "An administrator must '
								. 'always approve the comment".', 'openid') ?></p>
								
							</td>
						</tr>

						<tr valign="top">
							<th style="width: 33%" scope="row"><?php _e('Comment Form:', 'openid') ?></th>
							<td>
								<p><input type="checkbox" name="enable_commentform" id="enable_commentform" <?php
								if( get_option('oid_enable_commentform') ) echo 'checked="checked"'
								?> />
									<label for="enable_commentform"><?php _e('Add OpenID text to the WordPress post comment form.', 'openid') ?></label></p>

								<p><?php printf(__('This will work for most themes derived from Kubrick or Sandbox.  '
								. 'Template authors can tweak the comment form as described in the %sreadme%s.', 'openid'), 
								'<a href="'. get_option('siteurl') . '/' . PLUGINDIR . '/openid/readme.txt">', '</a>') ?></p>
								<br />
							</td>
						</tr>

						<?php /*
						<tr valign="top">
							<th style="width: 33%" scope="row"><?php _e('Email Mapping:', 'openid') ?></th>
							<td>
								<p><input type="checkbox" name="enable_email_mapping" id="enable_email_mapping" <?php
								if( get_option('oid_enable_email_mapping') ) echo 'checked="checked"'
								?> />
									<label for="enable_email_mapping"><?php _e('Enable email addresses to be mapped to OpenID URLs.', 'openid') ?></label></p>

								<p><?php printf(__('This feature uses the Email-To-URL mapping specification to allow OpenID authentication'
								. ' based on an email address.  If enabled, commentors who do not supply a valid OpenID URL will have their'
								. ' supplied email address mapped to an OpenID.  If their email provider does not currently support email to'
								. ' url mapping, the default provider %s will be used.', 'openid'), '<a href="http://emailtoid.net/" target="_blank">Emailtoid.net</a>') ?></p>
								<br />
							</td>
						</tr>
						*/ ?>

     				</table>

					<p><?php printf(__('Occasionally, the WP-OpenID tables don\'t get setup properly, and it may help '
						. 'to %srebuild the tables%s.  Don\'t worry, this won\'t cause you to lose any data... it just '
						. 'rebuilds a couple of tables that hold only temporary data.', 'openid'), 
					'<a href="'.wp_nonce_url(sprintf('?page=%s&action=rebuild_tables', $_REQUEST['page']), 'wp-openid-info_rebuild_tables').'">', '</a>') ?></p>

					<?php wp_nonce_field('wp-openid-info_update'); ?>
     				<p class="submit"><input type="submit" name="info_update" value="<?php _e('Update Options') ?> &raquo;" /></p>
     			</form>

			</div>
    			<?php
			if ($wp_version < '2.3') {
				echo '<br />';
				WordPressOpenID_Interface::printSystemStatus();
			}
	} // end function options_page


	/**
	 * Handle user management of OpenID associations.
	 *
	 * @submenu_page: profile.php
	 **/
	function profile_panel() {
		global $error, $openid;

		if( !current_user_can('read') ) {
			return;
		}
		$user = wp_get_current_user();

		WordPressOpenID_Logic::late_bind();

		if (!$openid->action && $_SESSION['oid_action']) {
			$openid->action = $_SESSION['oid_action'];
			unset($_SESSION['oid_action']);
		}

		if (!$openid->message && $_SESSION['oid_message']) {
			$openid->message = $_SESSION['oid_message'];
			unset($_SESSION['oid_message']);
		}

		if( 'success' == $openid->action ) {
			echo '<div class="updated"><p><strong>'.__('Success:', 'openid').'</strong> '.$openid->message.'</p></div>';
		}
		elseif( 'warning' == $openid->action ) {
			echo '<div class="error"><p><strong>'.__('Warning:', 'openid').'</strong> '.$openid->message.'</p></div>';
		}
		elseif( 'error' == $openid->action ) {
			echo '<div class="error"><p><strong>'.__('Error:', 'openid').'</strong> '.$openid->message.'</p></div>';
		}

		if (!empty($error)) {
			echo '<div class="error"><p><strong>'.__('Error:', 'openid').'</strong> '.$error.'</p></div>';
			unset($error);
		}


		?>

		<div class="wrap">
			<h2><?php _e('Your Identity URLs', 'openid') ?></h2>

			<p><?php printf(__('The following Identity URLs %s are tied to this user account. You can login '
			. 'with equivalent permissions using any of the following identities.', 'openid'), 
			'<a title="'.__('What is OpenID?', 'openid').'" href="http://openid.net/">'.__('?', 'openid').'</a>') ?>
			</p>
		<?php
		
		$urls = $openid->store->get_identities($user->ID);

		if( count($urls) ) : ?>
			<p>There are <?php echo count($urls); ?> identities associated with this WordPress user.</p>

			<table class="widefat">
			<thead>
				<tr>
					<th scope="col" style="text-align: center"><?php _e('ID', 'openid') ?></th>
					<th scope="col"><?php _e('Identity Url', 'openid') ?></th>
					<th scope="col" style="text-align: center"><?php _e('Action', 'openid') ?></th>
				</tr>
			</thead>

			<?php foreach( $urls as $k=>$v ): ?>

				<tr class="alternate">
					<th scope="row" style="text-align: center"><?php echo $v['uurl_id']; ?></th>
					<td><a href="<?php echo $v['url']; ?>"><?php echo $v['url']; ?></a></td>
					<td style="text-align: center"><a class="delete" href="<?php 
					echo wp_nonce_url(sprintf('?page=%s&action=drop_identity&id=%s', 'openid', $v['uurl_id']), 
					'wp-openid-drop-identity_'.$v['url']);
					?>"><?php _e('Delete', 'openid') ?></a></td>
				</tr>

			<?php endforeach; ?>

			</table>

			<?php
		else:
			echo '
			<p class="error">'.__('There are no OpenIDs associated with this WordPress user.', 'openid').'</p>';
		endif; ?>

		<p>
			<form method="post"><?php _e('Add identity:', 'openid') ?>
				<?php wp_nonce_field('wp-openid-add_identity'); ?>
				<input id="openid_url" name="openid_url" /> 
				<input type="submit" value="<?php _e('Add', 'openid') ?>" />
				<input type="hidden" name="action" value="add_identity" >
			</form>
		</p>
		</div>
		<?php
	}


	/**
	 * Print the status of various system libraries.  This is displayed on the main OpenID options page.
	 **/
	function printSystemStatus() {
		global $wp_version, $wpdb, $openid;

		$paths = explode(PATH_SEPARATOR, get_include_path());
		for($i=0; $i<sizeof($paths); $i++ ) { 
			$paths[$i] = realpath($paths[$i]); 
		}
		
		$openid->setStatus( 'PHP version', 'info', phpversion() );
		$openid->setStatus( 'PHP memory limit', 'info', ini_get('memory_limit') );
		$openid->setStatus( 'Include Path', 'info', $paths );
		
		$openid->setStatus( 'WordPress version', 'info', $wp_version );
		$openid->setStatus( 'MySQL version', 'info', function_exists('mysql_get_client_info') ? mysql_get_client_info() : 'Mysql client information not available. Very strange, as WordPress requires MySQL.' );

		$openid->setStatus('WordPress\' table prefix', 'info', isset($wpdb->base_prefix) ? $wpdb->base_prefix : $wpdb->prefix );
		
		
		if ( extension_loaded('suhosin') ) {
			$openid->setStatus( 'Curl', false, 'Hardened php (suhosin) extension active -- curl version checking skipped.' );
		} else {
			$curl_message = '';
			if( function_exists('curl_version') ) {
				$curl_version = curl_version();
				if(isset($curl_version['version']))  	
					$curl_message .= 'Version ' . $curl_version['version'] . '. ';
				if(isset($curl_version['ssl_version']))	
					$curl_message .= 'SSL: ' . $curl_version['ssl_version'] . '. ';
				if(isset($curl_message['libz_version']))
					$curl_message .= 'zlib: ' . $curl_version['libz_version'] . '. ';
				if(isset($curl_version['protocols'])) {
					if (is_array($curl_version['protocols'])) {
						$curl_message .= 'Supports: ' . implode(', ',$curl_version['protocols']) . '. ';
					} else {
						$curl_message .= 'Supports: ' . $curl_version['protocols'] . '. ';
					}
				}
			}
			$openid->setStatus( 'Curl Support', function_exists('curl_version'), function_exists('curl_version') ? $curl_message :
					'This PHP installation does not have support for libcurl. Some functionality, such as fetching https:// URLs, will be missing and performance will slightly impared. See <a href="http://www.php.net/manual/en/ref.curl.php">php.net/manual/en/ref.curl.php</a> about enabling libcurl support for PHP.');
		}

		if (extension_loaded('gmp') and @gmp_init(1)) {
			$openid->setStatus( 'Big Integer support', true, 'GMP is installed.' );
		} elseif (extension_loaded('bcmath') and @bcadd(1,1)==2) {
			$openid->setStatus( 'Big Integer support', true, 'BCMath is installed (though <a href="http://www.php.net/gmp">GMP</a> is preferred).' );
		} elseif (defined('Auth_OpenID_NO_MATH_SUPPORT')) {
			$openid->setStatus( 'Big Integer support', false, 'The OpenID Library is operating in Dumb Mode. Recommend installing <a href="http://www.php.net/gmp">GMP</a> support.' );
		}

		
		$openid->setStatus( 'Plugin Revision', 'info', WPOPENID_PLUGIN_REVISION);
		$openid->setStatus( 'Plugin Database Revision', 'info', get_option('oid_db_revision'));
		
		$openid->setStatus( '<strong>Overall Plugin Status</strong>', ($openid->enabled), 
			($openid->enabled ? '' : 'There are problems above that must be dealt with before the plugin can be used.') );


			
		if( $openid->enabled ) {	// Display status information
			echo'<div id="openid_rollup" class="updated">
			<p><strong>' . __('Status information:', 'openid') . '</strong> ' . __('All Systems Nominal', 'openid') 
			. '<small> (<a href="#" id="openid_rollup_link">' . __('Toggle More/Less', 'openid') . '</a>)</small> </p>';
		} else {
			echo '<div class="error"><p><strong>' . __('Plugin is currently disabled. Fix the problem, then Deactivate/Reactivate the plugin.', 'openid') 
			. '</strong></p>';
		}
		echo '<div>';
		foreach( $openid->status as $k=>$v ) {
			echo '<div><strong>';
			if( $v['state'] === false ) {
				echo "<span style='color:red;'>[".__('FAIL', 'openid')."]</span> $k";
			} elseif( $v['state'] === true ) {
				echo "<span style='color:green;'>[".__('OK', 'openid')."]</span> $k";
			} else {
				echo "<span style='color:grey;'>[".__('INFO', 'openid')."]</span> $k";
			}
			echo ($v['message'] ? ': ' : '') . '</strong>';
			echo (is_array($v['message']) ? '<ul><li>' . implode('</li><li>', $v['message']) . '</li></ul>' : $v['message']);
			echo '</div>';
		}
		echo '</div></div>';
	}

	function repost($action, $parameters) {
		$html = '
		<noscript><p>Since your browser does not support JavaScript, you must press the Continue button once to proceed.</p></noscript>
		<form action="'.$action.'" method="post">';

		foreach ($parameters as $k => $v) {
			if ($k == 'submit') continue;
			$html .= "\n" . '<input type="hidden" name="'.$k.'" value="' . htmlspecialchars(stripslashes($v), ENT_COMPAT, get_option('blog_charset')) . '" />';
		}
		$html .= '
			<noscript><div><input type="submit" value="Continue" /></div></noscript>
		</form>
		
		<script type="text/javascript">
			document.write("<h2>Please Wait...</h2>"); 
			document.forms[0].submit()
		</script>';

		wp_die($html, 'OpenID Authentication Redirect');
	}
	
	function init_errors() {
		global $error;
		$error = $_SESSION['oid_error'];
		unset($_SESSION['oid_error']);
	}


	function repost_comment_anonymously($post) {
		$html = '
		<p id="error">We were unable to authenticate your claimed OpenID, however you 
		can continue to post your comment without OpenID:</p>

		<form action="' . get_option('siteurl') . '/wp-comments-post.php" method="post">
			<p>Name: <input name="author" value="'.$post['author'].'" /></p>
			<p>Email: <input name="email" value="'.$post['email'].'" /></p>
			<p>URL: <input name="url" value="'.$post['url'].'" /></p>
			<textarea name="comment" cols="80%" rows="10">'.stripslashes($post['comment']).'</textarea>
			<input type="submit" name="submit" value="Submit Comment" />
			<input type="hidden" name="oid_skip" value="1" />';
		foreach ($post as $name => $value) {
			if (!in_array($name, array('author', 'email', 'url', 'comment', 'submit'))) {
				$html .= '
			<input type="hidden" name="'.$name.'" value="'.$value.'" />';
			}
		}
		
		$html .= '</form>';
		wp_die($html, 'OpenID Authentication Error');
	}
	
}
endif;

?>
