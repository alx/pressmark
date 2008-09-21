<?php
$user			= get_userdata( $current_user->ID );
$first_name		= attribute_escape( $user->first_name );
?>

<div id="postbox">
	<form id="new_post" name="new_post" method="post" action="<?php bloginfo( 'url' ); ?>/">
		<input type="hidden" name="action" value="post" />
		<?php wp_nonce_field( 'new-post' ); ?>

		<?php echo get_avatar( $user->ID, 48 ); ?>

		<label for="posttitle">Title:</label>
		<input type="text" name="posttitle" value="<?php echo $_GET['posttitle']; ?>" id="posttitle" class="text"/>
		
		<label for="posturl">Link:</label>
		<input type="text" id="posturl" name="posturl" class="text" value="<?php echo $_GET['posturl']; ?>"/>
		
		<label for="posttext">Description:</label>
		<textarea name="posttext" id="posttext" rows="3" cols="60"><?php echo $_GET['posttext']; ?></textarea>
	
		<label for="tags">Tags</label>
		<input type="text" name="tags" id="tags" autocomplete="off" />
		
		<input type="radio" name="status" value="publish" checked="checked"> Public
		<input type="radio" name="status" value="private"> Private
		
		<input id="submit" type="submit" value="Post it" />
	</form>
</div> <!-- // postbox -->
