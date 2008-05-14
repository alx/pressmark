<?php
$user			= get_userdata( $current_user->ID );
$first_name		= attribute_escape( $user->first_name );
?>

<div id="postbox">
	<form id="new_post" name="new_post" method="post" action="<?php bloginfo( 'url' ); ?>/">
		<input type="hidden" name="action" value="post" />
		<?php wp_nonce_field( 'new-post' ); ?>

		<?php echo prologue_get_avatar( $user->ID, $user->user_email, 48 ); ?>

		<label for="posturl">Hi, <?php echo $first_name; ?>. Un nuevo enlace?</label>
		<input type="text" name="posturl" value="" id="posturl">
		
		<label for="posttext">Description:</label>
		<input type="text" name="posttext" value="" id="posttext">
		
		<textarea name="posttext" id="posttext" rows="3" cols="60"></textarea>
	
		<label for="tags">Tag it</label>
		<input type="text" name="tags" id="tags" autocomplete="off" />
	
		<input id="submit" type="submit" value="Post it" />
	</form>
</div> <!-- // postbox -->
