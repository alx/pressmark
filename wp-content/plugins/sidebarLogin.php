<?php
/*
Plugin Name: Sidebar Login
Description: Adds a sidebar widget to let users login
Author: Mike Jolley, jolley_small@tesco.net
Version: 1.51
Author URI: http://blue-anvil.com
*/
function widget_sidebarLogin_init() {

	// ADDED 1st FEB, thanks Anton Fedorov
	if ( empty($_COOKIE[TEST_COOKIE]) ) {
		//Set a cookie now to see if they are supported by the browser.
		setcookie(TEST_COOKIE, 'WP Cookie check', 0, COOKIEPATH, COOKIE_DOMAIN);
		if ( SITECOOKIEPATH != COOKIEPATH )
				setcookie(TEST_COOKIE, 'WP Cookie check', 0, SITECOOKIEPATH, COOKIE_DOMAIN);
	}

	if ( !function_exists('register_sidebar_widget') )
		return;

		function widget_sidebarLogin($args) {
		
			extract($args);
			
			// Get user info
			global $user_level,$user_identity,$user_ID,$user_login;
			get_currentuserinfo();
			
			if ('' != $user_ID) {
				// User is logged in
				echo $before_widget . $before_title . "Welcome ".$user_identity . $after_title;
				echo '
					<ul class="pagenav">
						<li class="page_item"><a href="'.get_bloginfo('wpurl').'/wp-admin">Dashboard</a></li>
						<li class="page_item"><a href="'.get_bloginfo('wpurl').'/wp-admin/profile.php">Profile</a></li>
						<li class="page_item"><a href="'.get_bloginfo('wpurl').'/wp-login.php?action=logout&redirect_to=http://'.$_SERVER["SERVER_NAME"].$_SERVER['REQUEST_URI'].'">Logout</a></li>
					</ul>
				';
			} else {
			// User is NOT logged in!!!
					//echo $before_widget . $before_title . "Login" . $after_title;
					
					// Show any errors
					$sbl_errors=$_POST['sbl_errors'];
					if ( !empty( $sbl_errors ) ) {
						if ( is_array( $sbl_errors ) ) {
							$newerrors = "\n";
							foreach ( $sbl_errors as $error ) $newerrors .= '	' . $error . "<br />\n";
							$sbl_errors = $newerrors;
						}

						echo '<div id="login_error">' . apply_filters('login_errors', $sbl_errors) . "</div>\n";
					}
			
					// login form
					echo "<h3>Do you want to log in?</h3>";
					echo '<form id="login-form" action="';
					// Get url for CURRENT page
					echo "http://".$_SERVER["SERVER_NAME"].$_SERVER['REQUEST_URI'];
					echo  '" method="post" >';
					?>
                    
					<label for="user_login"><?php _e('User:') ?><input name="log" value="<?php echo attribute_escape(stripslashes($_POST['user_login'])); ?>" class="mid" id="user_login" type="text" /></label>
					<label for="user_pass"><?php _e('Pass:') ?><input name="pwd" class="mid" id="user_pass" type="password" /></label>
					<label for="rememberme"><input name="rememberme" class="checkbox" id="rememberme" value="forever" type="checkbox" /> <?php _e('Remember me'); ?></label>
					<input type="submit" name="wp-submit" id="login-submit" value="<?php _e('Let\'s go'); ?>" />
                    <input type="hidden" name="sidebarLogin_posted" value="1" />
					<input type="hidden" name="redirect_to" value="<?php bloginfo('wpurl'); ?>/user_home" />
					</form>
					
					<?php 
					// Output other links
					if (get_option('users_can_register')) : 
					?>
                        <br /><a href="<?php bloginfo('wpurl'); ?>/wp-login.php?action=register"><?php _e('Create new account') ?></a><hr/>
                        <br /><a class='lost-password' href="<?php bloginfo('wpurl'); ?>/wp-login.php?action=lostpassword" title="<?php _e('Password Lost and Found') ?>"><?php _e('Lost your password?') ?></a><hr/>
					<?php else : ?>
                        <br/><a class='lost-password' href="<?php bloginfo('wpurl'); ?>/wp-login.php?action=lostpassword" title="<?php _e('Password Lost and Found') ?>"><?php _e('Lost your password?') ?></a><hr/>
                    <?php endif; ?>
                    <?php
					
			}
		
			// echo widget closing tag
			echo $after_widget;
	}

	// Register widget for use
	register_sidebar_widget(array('Sidebar Login', 'widgets'), 'widget_sidebarLogin');

}

function widget_sidebarLogin_check() {

	// Are we doing a sidebar login action?
	if ($_POST['sidebarLogin_posted']) {
	
		$user_login = '';
		$user_pass = '';
		$using_cookie = FALSE;
		
		if ( $_POST ) {
			$user_login = $_POST['log'];
			$user_login = sanitize_user( $user_login );
			$user_pass  = $_POST['pwd'];
			$rememberme = $_POST['rememberme'];
		} else {
			$cookie_login = wp_get_cookie_login();
			if ( ! empty($cookie_login) ) {
				$using_cookie = true;
				$user_login = $cookie_login['login'];
				$user_pass = $cookie_login['password'];
			}
		}
		
		do_action_ref_array('wp_authenticate', array(&$user_login, &$user_pass));
		
		// If cookies are disabled we can't log in even with a valid user+pass
		if ( $_POST && empty($_COOKIE[TEST_COOKIE]) )
			$errors['test_cookie'] = __('<strong>ERROR</strong>: WordPress requires Cookies but your browser does not support them or they are blocked.');
			
		if ( $user_login && $user_pass && empty( $errors ) ) {
			$user = new WP_User(0, $user_login);
			if ( wp_login($user_login, $user_pass, $using_cookie) ) {
				if ( !$using_cookie )
					wp_setcookie($user_login, $user_pass, false, '', '', $rememberme);
				do_action('wp_login', $user_login);
				wp_safe_redirect("http://".$_SERVER["SERVER_NAME"].$_SERVER['REQUEST_URI']);
				exit();
			} else {
				if ( $using_cookie )
					$errors['expiredsession'] = __('Your session has expired.');
				$errors['expiredsession'] = __('<strong>ERROR</strong>: Invalid user or password.');
			}
		}
		
		if ( $_POST && empty( $user_login ) )
			$errors['user_login'] = __('<strong>ERROR</strong>: The username field is empty.');
		if ( $_POST && empty( $user_pass ) )
			$errors['user_pass'] = __('<strong>ERROR</strong>: The password field is empty.');
			
		$_POST['sbl_errors']=$errors;
		$_POST['user_login']=$user_login;
	
	}

}
	
// Run code and init
add_action('widgets_init', 'widget_sidebarLogin_init');
// Add code to allow login/logout
add_action('init', 'widget_sidebarLogin_check');
?>