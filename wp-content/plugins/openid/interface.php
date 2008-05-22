<?php
/**
 * interface.php
 *
 * User Interface Elements for wp-openid
 * Dual Licence: GPL & Modified BSD
 */
if ( !class_exists('WordpressOpenIDInterface') ) {
class WordpressOpenIDInterface {

	var $logic;  // Hold core logic instance
	var $core;  // Hold core instance

	var $profile_page_name = 'openid';

	/**
	 * Constructor.
	 */
	function WordpressOpenIDInterface($core) {
		$this->core =& $core;
		$this->logic =& $core->logic;
	}

	
	/**
	 * Provide more useful OpenID error message to the user.
	 *
	 * @filter: login_errors
	 **/
	function login_form_hide_username_password_errors($r) {
		if( $_POST['openid_url']
			or $_REQUEST['action'] == 'loginopenid'
			or $_REQUEST['action'] == 'commentopenid' ) return $this->logic->error;
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
		<p>
			<label>Or login using your <a class="<?php echo $link_class; ?>" href="http://openid.net/">OpenID</a> url:<br/>
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
		?><p>For faster registration, just <a href="<?php echo get_option('siteurl'); ?>/wp-login.php">login with <span class="openid_link">OpenID</span>!</a></p><?php
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
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script('jquery.textnode', $this->core->path . '/files/jquery.textnode.js', 
			array('jquery'), WPOPENID_PLUGIN_REVISION);
		wp_enqueue_script('jquery.xpath', $this->core->path . '/files/jquery.xpath.js', 
			array('jquery'), WPOPENID_PLUGIN_REVISION);
		wp_enqueue_script('openid', $this->core->path . '/files/openid.js', 
			array('jquery','jquery.textnode'), WPOPENID_PLUGIN_REVISION);
	}


	/**
	 * Include internal stylesheet.
	 *
	 * @action: wp_head, login_head
	 **/
	function style() {
		$css_path = $this->core->fullpath . '/files/openid.css?ver='.WPOPENID_PLUGIN_REVISION;
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
		global $user_ID;
		if (!$user_ID) {
			echo '<script type="text/javascript">add_openid_to_comment_form()</script>';
		}
	}


	/**
	 * Spam up the admin interface with warnings.
	 **/
	function admin_notices_plugin_problem_warning() {
		?><div class="error"><p><strong>The WordPress OpenID plugin is not active.</strong>
		Check <a href="options-general.php?page=global-openid-options">OpenID Options</a> for
		a full diagnositic report.</p></div><?php
	}
	

	/**
	 * Setup admin menus for OpenID options and ID management.
	 *
	 * @action: admin_menu
	 **/
	function add_admin_panels() {
		$hookname = add_options_page('OpenID options', 'WP-OpenID', 8, 'global-openid-options', 
			array( $this, 'options_page')  );
		add_action("load-$hookname", array( $this, 'js_setup' ));

		if( $this->logic->enabled ) {
			$hookname =	add_submenu_page('profile.php', 'Your Identity URLs', 'Your Identity URLs', 
				'read', $this->profile_page_name, array($this, 'profile_panel') );
			add_action("admin_head-$hookname", array( $this, 'style' ));
			add_action("load-$hookname", array( $this->logic, 'openid_profile_management' ));
		}
	}


	/*
	 * Display and handle updates from the Admin screen options page.
	 *
	 * @options_page
	 */
	function options_page() {
			$this->logic->late_bind();
			$this->core->log->debug("WPOpenID Plugin: " . ($this->logic->enabled? 'Enabled':'Disabled' ) 
				. ' (start of WordPress options page)' );
		
			if ( isset($_REQUEST['action']) ) {
				switch($_REQUEST['action']) {
					case 'rebuild_tables' :
						check_admin_referer('wp-openid-info_rebuild_tables');
						$this->logic->store->destroy_tables();
						$this->logic->store->create_tables();
						echo '<div class="updated"><p><strong>Open ID tables rebuilt.</strong></p></div>';
						break;
				}
			}

			// if we're posted back an update, let's set the values here
			if ( isset($_POST['info_update']) ) {
			
				check_admin_referer('wp-openid-info_update');

				$error = '';
				
				update_option( 'oid_enable_commentform', isset($_POST['enable_commentform']) ? true : false );
				update_option( 'oid_enable_approval', isset($_POST['enable_approval']) ? true : false );

				if ($error !== '') {
					echo '<div class="error"><p><strong>At least one of OpenID options was NOT updated</strong>'.$error.'</p></div>';
				} else {
					echo '<div class="updated"><p><strong>Open ID options updated</strong></p></div>';
				}
				
			}

			$this->printSystemStatus();
			
			// Display the options page form
			$siteurl = get_option('home');
			if( substr( $siteurl, -1, 1 ) !== '/' ) $siteurl .= '/';
			?>
			<div class="wrap">
				<h2>WP-OpenID Registration Options</h2>
				<form method="post">
     				<p class="submit"><input type="submit" name="info_update" value="<?php _e('Update options') ?> &raquo;" /></p>

     				<fieldset class="options">
						<legend>Behavior</legend>
     									
     					<table class="optiontable editform" cellspacing="2" cellpadding="5" width="100%">
						<tr valign="top">
							<th style="width: 33%" scope="row">Automatic Approval:</th>
							<td>
								<p><input type="checkbox" name="enable_approval" id="enable_approval" <?php 
									echo get_option('oid_enable_approval') ? 'checked="checked"' : ''; ?> />
								<label for="enable_approval">Enable OpenID comment auto-approval</label>

								<p>For now this option will cause comments made with OpenIDs to be automatically 
								approved.  Since most spammers haven't started using OpenID yet, this is probably 
								pretty safe.  More importantly however, this could be a foundation on which to build 
								more advanced automatic approval such as whitelists or a third-party trust service.
								</p>
							</td>
						</tr>
						</table>
					</fieldset>

     				<fieldset class="options">
						<legend>Look &amp; Feel</legend>
     									
     					<table class="optiontable editform" cellspacing="2" cellpadding="5" width="100%">
						<tr valign="top">
							<th style="width: 33%" scope="row">Comment Form:</th>
							<td>
								<p><input type="checkbox" name="enable_commentform" id="enable_commentform" <?php
								if( get_option('oid_enable_commentform') ) echo 'checked="checked"'
								?> />
								<label for="enable_commentform">Add OpenID text to the WordPress post 
								comment form.</label></p>

								<p> This will work for most themes derived from Kubrick or Sandbox.
								Template authors can tweak the comment form as described in the
								<a href="<?php echo $this->core->fullpath?>/readme.txt">readme</a>.</p>
								<br />
							</td>
						</tr>

     					</table>
     				</fieldset>

					Occasionally, the WP-OpenID tables don't get setup properly, and it may help to <a href="<?php echo 
					wp_nonce_url(sprintf('?page=%s&action=rebuild_tables', $_REQUEST['page']), 
					'wp-openid-info_rebuild_tables'); ?>">rebuild the tables</a>.  Don't worry, this won't cause you 
					to lose any data... it just rebuilds a couple of tables that hold only temprory data.

					<?php wp_nonce_field('wp-openid-info_update'); ?>
     				<p class="submit"><input type="submit" name="info_update" value="<?php _e('Update options') ?> &raquo;" /></p>
     			</form>

			</div>
    			<?php
	} // end function options_page


	/**
	 * Handle user management of OpenID associations.
	 *
	 * @submenu_page: profile.php
	 **/
	function profile_panel() {
		if( !current_user_can('read') ) {
			return;
		}

		$this->logic->late_bind();

		if( 'success' == $this->logic->action ) {
			echo '<div class="updated"><p><strong>Success: '.$this->logic->error.'</strong></p></div>';
		}
		elseif( 'warning' == $this->logic->action ) {
			echo '<div class="error"><p><strong>Warning:</strong> '.$this->logic->error.'</p></div>';
		}
		elseif( $this->logic->error ) {
			echo '<div class="error"><p><strong>Error: '.$this->logic->error.'</strong></p></div>';
		}

		?>

		<div class="wrap">
			<h2>Your Identity URLs</h2>

			<p>The following Identity URLs <a title="What is OpenID?" href="http://openid.net/">?</a> 
			are tied to this user account. You can login with equivalent permissions using any of the 
			following identities.</p>

		<?php
		
		$urls = $this->logic->store->get_my_identities();

		if( count($urls) ) : ?>
			<p>There are <?php echo count($urls); ?> identities associated with this WordPress user.</p>

			<table class="widefat">
			<thead>
				<tr>
					<th scope="col" style="text-align: center">ID</th>
					<th scope="col">Identity Url</th>
					<th scope="col" style="text-align: center">Action</th>
				</tr>
			</thead>

			<?php foreach( $urls as $k=>$v ): ?>

				<tr class="alternate">
					<th scope="row" style="text-align: center"><?php echo $v['uurl_id']; ?></td>
					<td><a href="<?php echo $v['url']; ?>"><?php echo $v['url']; ?></a></td>
					<td style="text-align: center"><a class="delete" href="<?php 
					echo wp_nonce_url(sprintf('?page=%s&action=drop_identity&id=%s', $this->profile_page_name, $v['uurl_id']), 
					'wp-openid-drop-identity_'.$v['url']);
					?>">Delete</a></td>
				</tr>

			<?php endforeach; ?>

			</table>

			<?php
		else:
			echo '
			<p class="error">There are no OpenIDs associated with this WordPress user.</p>';
		endif; ?>

		<p>
			<form method="post">Add identity: 
				<?php wp_nonce_field('wp-openid-add_identity'); ?>
				<input id="openid_url" name="openid_url" /> 
				<input type="submit" value="Add" />
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
		$relativeto = dirname( __FILE__ ) . DIRECTORY_SEPARATOR;
		$paths = explode(PATH_SEPARATOR, get_include_path());
		foreach( $paths as $path ) {
			$fullpath = $path . DIRECTORY_SEPARATOR;
			if( $path == '.' ) $fullpath = '';
			if( substr( $path, 0, 1 ) !== '/' ) $fullpath = $relativeto . $fullpath;
			$list_of_paths[] = $fullpath;
		}
		
		$this->core->setStatus( 'Include Path', 'info', implode('<br/>', $list_of_paths ) );
		
		global $wp_version;
		$this->core->setStatus( 'WordPress version', 'info', $wp_version );
		$this->core->setStatus( 'MySQL version', 'info', function_exists('mysql_get_client_info') ? mysql_get_client_info() : 'Mysql client information not available. Very strange, as WordPress requires MySQL.' );

		$this->core->setStatus( 'PHP version', 'info', phpversion() );
		$this->core->setStatus( 'PHP memory limit', 'info', ini_get('memory_limit') );
		
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
		$this->core->setStatus( 'Curl ' . $curl_message, function_exists('curl_version'), function_exists('curl_version') ? $curl_message :
				'This PHP installation does not have support for libcurl. Some functionality, such as fetching https:// URLs, will be missing and performance will slightly impared. See <a href="http://www.php.net/manual/en/ref.curl.php">php.net/manual/en/ref.curl.php</a> about enabling libcurl support for PHP.');

		/* Check for Long Integer math library */
		$this->core->setStatus( 'library: GMP compiled into in PHP', ( extension_loaded('gmp') and @gmp_init(1) ), '<a href="http://www.php.net/gmp">GMP</a> does not appear to be built into PHP. This is highly recommended for performance reasons.' );
		$this->core->setStatus( 'library: BCMath compiled into in PHP', ( extension_loaded('bcmath') and @bcadd(1,1)==2 ), '<a href="http://www.php.net/bc">BCMath</a> does not appear to be built into PHP. GMP is preferred.' );

		if( defined( 'Auth_OpenID_NO_MATH_SUPPORT' ) ) {
			$this->core->setStatus( 'Loaded long integer library', false, 'The OpenID Library is operating Dumb Mode, since it doesn\'t have a big integer library. Recommend installing GMP support.' );
		}
		if( defined( 'Auth_OpenID_RAND_SOURCE' ) ) {
			$this->core->setStatus( 'Cryptographic Randomness Source', (Auth_OpenID_RAND_SOURCE===null) ? false: 'info' ,
				(Auth_OpenID_RAND_SOURCE===null)
				? '/dev/urandom unavailable, using an <a href="http://php.net/mt_rand">insecure random number generator</a>. <a href="http://www.php.net/manual/en/features.safe-mode.php#ini.open-basedir">open_basedir</a> is "' . ini_get('open_basedir') . '"'
				: Auth_OpenID_RAND_SOURCE );
		}

		
		$this->core->setStatus( 'Plugin Revision', 'info', WPOPENID_PLUGIN_REVISION);
		$this->core->setStatus( 'Plugin Database Revision', 'info', 'Plugin database is currently at revision '
			. get_option('oid_db_revision') . '.' );
		
		$this->core->setStatus( '<strong>Overall Plugin Status</strong>', ($this->logic->enabled), 
			'There are problems above that must be dealt with before the plugin can be used.' );


		if( $this->logic->enabled ) {	// Display status information
			?><div id="openid_rollup" class="updated"><p><strong>Status information:</strong> All Systems Nominal <small>(<a href="#" id="openid_rollup_link">Toggle More/Less</a>)</small> </p><?php
		} else {
			?><div class="error"><p><strong>Plugin is currently disabled. Fix the problem, then Deactivate/Reactivate the plugin.</strong></p><?php
		}
		
		?>
		<dl>
		<?php
			foreach( $this->core->status as $k=>$v ) {
				if( $v['state'] === false ) { echo "<dt><span style='color:red;'>[FAIL]</span> $k </dt>"; }
				elseif( $v['state'] === true ) { echo "<dt><span style='color:green;'>[OK]</span> $k </dt>"; }
				else { echo "<dt><span style='color:grey;'>[INFO]</span> $k </dt>"; }
				if( $v['state']!==true and $v['message'] ) echo '<dd>' . $v['message'] . '</dd>';
			}
		?>
		</dl></div>
		<?php
	}

}
}

?>
