<?php
/*
Plugin Name: Sidebar Login
Plugin URI: http://wordpress.org/extend/plugins/sidebar-login/
Description: Adds a sidebar widget to let users login
Version: 2.1.2
Author: Mike Jolley
Author URI: http://blue-anvil.com
*/
function sidebarlogin() {
	$args["before_widget"]="";
	$args["after_widget"]="";
	$args["before_title"]="<h2>";
	$args["after_title"]="</h2>";
	widget_sidebarlogin($args);
}
function widget_sidebarlogin($args) {
	
		extract($args);
		
		global $user_ID;

		if (isset($user_ID)) {
			// User is logged in
			$user_info = get_userdata($user_ID);
			echo $before_widget . $before_title . __("Welcome "). $user_info->user_login . $after_title;
			echo '<ul class="pagenav">
					<li class="page_item"><a href="'.get_bloginfo('wpurl').'/wp-admin/">'.__('Dashboard').'</a></li>
					<li class="page_item"><a href="'.get_bloginfo('wpurl').'/wp-admin/profile.php">'.__('Profile').'</a></li>
					<li class="page_item"><a href="'.current_url('logout').'">'.__('Logout').'</a></li>
				</ul>';
		} else {
			// User is NOT logged in!!!
			echo $before_widget . $before_title . __("Login") . $after_title;
			// Show any errors
			global $myerrors;
			$wp_error = new WP_Error();
			if ( !empty($myerrors) ) {
				$wp_error = $myerrors;
			}
			if ( $wp_error->get_error_code() ) {
				$errors = '';
				$messages = '';
				foreach ( $wp_error->get_error_codes() as $code ) {
					$severity = $wp_error->get_error_data($code);
					foreach ( $wp_error->get_error_messages($code) as $error ) {
						if ( 'message' == $severity )
							$messages .= '	' . $error . "<br />\n";
						else
							$errors .= '	' . $error . "<br />\n";
					}
				}
				if ( !empty($errors) )
					echo '<div id="login_error">' . apply_filters('login_errors', $errors) . "</div>\n";
				if ( !empty($messages) )
					echo '<p class="message">' . apply_filters('login_messages', $messages) . "</p>\n";
			}
			// login form
			echo '<form action="'.current_url().'" method="post" >';
			?>
			<p><label for="user_login"><?php _e('Username:') ?><br/><input name="log" value="<?php echo attribute_escape(stripslashes($_POST['log'])); ?>" class="mid" id="user_login" type="text" /></label></p>
			<p><label for="user_pass"><?php _e('Password:') ?><br/><input name="pwd" class="mid" id="user_pass" type="password" /></label></p>
			<p><label for="rememberme"><input name="rememberme" class="checkbox" id="rememberme" value="forever" type="checkbox" /> <?php _e('Remember me'); ?></label></p>
			<p class="submit"><input type="submit" name="wp-submit" id="wp-submit" value="<?php _e('Login'); ?> &raquo;" />
			<input type="hidden" name="sidebarlogin_posted" value="1" />
			<input type="hidden" name="testcookie" value="1" /></p>
			</form>
			<?php 			
			// Output other links
			echo '<ul class="sidebarlogin_otherlinks">';		
			if (get_option('users_can_register')) { 
				// MU FIX
				global $wpmu_version;
				if (empty($wpmu_version)) {
					?>
						<li><a href="<?php bloginfo('wpurl'); ?>/wp-login.php?action=register"><?php _e('Register') ?></a></li>
					<?php 
				} else {
					?>
						<li><a href="<?php bloginfo('wpurl'); ?>/wp-signup.php"><?php _e('Register') ?></a></li>
					<?php 
				}
			}
			?>
			<li><a href="<?php bloginfo('wpurl'); ?>/wp-login.php?action=lostpassword" title="<?php _e('Password Lost and Found') ?>"><?php _e('Lost your password?') ?></a></li>
			</ul>
			<?php	
		}
		// echo widget closing tag
		echo $after_widget;
}
function widget_sidebarlogin_init() {
	if ( !function_exists('register_sidebar_widget') ) return;
	// Register widget for use
	register_sidebar_widget(array('Sidebar Login', 'widgets'), 'widget_sidebarlogin');
}
function widget_sidebarlogin_check() {
	if ($_POST['sidebarlogin_posted'] || $_GET['logout']) {
		// Includes
		//include_once('wp-settings.php');
		global $myerrors;
		$myerrors = new WP_Error();
		//Set a cookie now to see if they are supported by the browser.
		setcookie(TEST_COOKIE, 'WP Cookie check', 0, COOKIEPATH, COOKIE_DOMAIN);
		if ( SITECOOKIEPATH != COOKIEPATH )
			setcookie(TEST_COOKIE, 'WP Cookie check', 0, SITECOOKIEPATH, COOKIE_DOMAIN);
		// Logout
		if ($_GET['logout']==true) {
			nocache_headers();
			wp_logout();
			wp_redirect(current_url('nologout'));
			exit();
		}
		// Are we doing a sidebar login action?
		if ($_POST['sidebarlogin_posted']) {
		
			if ( is_ssl() && force_ssl_login() && !force_ssl_admin() && ( 0 !== strpos($redirect_to, 'https') ) && ( 0 === strpos($redirect_to, 'http') ) )
				$secure_cookie = false;
			else
				$secure_cookie = '';
		
			$user = wp_signon('', $secure_cookie);
			
			// Error Handling
			if ( is_wp_error($user) ) {
			
				$errors = $user;
	
				// If cookies are disabled we can't log in even with a valid user+pass
				if ( isset($_POST['testcookie']) && empty($_COOKIE[TEST_COOKIE]) )
					$errors->add('test_cookie', __("<strong>ERROR</strong>: Cookies are blocked or not supported by your browser. You must <a href='http://www.google.com/cookies.html'>enable cookies</a> to use WordPress."));
					
				if ( empty($_POST['log']) && empty($_POST['pwd']) ) {
					$errors->add('empty_username', __('<strong>ERROR</strong>: Please enter a username.'));
					$errors->add('empty_password', __('<strong>ERROR</strong>: Please enter your password.'));
				}
					
				$myerrors = $errors;
						
			} else {
				wp_redirect(current_url('nologout'));
				exit;
			}
		}
	}
}
if ( !function_exists('current_url') ) :
function current_url($url = '') {
	$pageURL = 'http';
	if ($_SERVER["HTTPS"] == "on") $pageURL .= "s";
	$pageURL .= "://";
	if ($_SERVER["SERVER_PORT"] != "80") {
		$pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
	} else {
		$pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
	}
	if ($url == "logout" && strstr($pageURL,'logout')==false) {
		if (strstr($pageURL,'?')) {
			$pageURL .='&logout=true';
		} else {
			$pageURL .='?logout=true';
		}
	}
	if ($url == "nologout" && strstr($pageURL,'logout')==true) {
		$pageURL = str_replace('?logout=true','',$pageURL);
		$pageURL = str_replace('&logout=true','',$pageURL);
	}
	//————–added by mick 
	if (!strstr(get_bloginfo('wpurl'),'www.')) $pageURL = str_replace('www.','', $pageURL );
	//——————–
	return $pageURL;
}
endif;
// Run code and init
add_action('init', 'widget_sidebarlogin_check',1);
add_action('widgets_init', 'widget_sidebarlogin_init');
?>