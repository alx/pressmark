<?php
$user			= get_userdata( $current_user->ID );
$first_name		= attribute_escape( $user->first_name );

// 1. News URL (default and will disappear if News type)
// 2. Title
// 3. Description (Full WYSIWYG editor)
// 4. Category Choices (can select multiple categories)
// 5. Meta Description
// 6. Meta Keywords (comma separated)
// 7. Tags
?>

<div id="postbox">
	<form id="new_post" name="new_post" method="post" action="<?php bloginfo( 'url' ); ?>/">
		<input type="hidden" name="action" value="post" />
		<?php wp_nonce_field( 'new-post' ); ?>

		<input type="radio" name="entry_type" value="type_news" onClick="$('posturl_label').show();$('posturl').show();" checked="checked"> News		
		<input type="radio" name="entry_type" value="type_blog" onClick="$('posturl_label').hide();$('posturl').hide();"> Blog Entry

		<label for="posttitle">Title:</label>
		<input type="text" name="posttitle" value="<?php echo $_GET['posttitle']; ?>" id="posttitle" class="text"/>
		
		<label id="posturl_label" for="posturl">News URL:</label>
		<input type="text" id="posturl" name="posturl" class="text" value="<?php echo $_GET['posturl']; ?>"/>
		
		<label for="posttext">Description:</label>
		<textarea name="posttext" id="posttext" rows="3" cols="60">
			<?php if(isset($_GET['posttext'])):?>
				&#8220;<i><?php echo $_GET['posttext']; ?></i>&#8221;
			<?php endif; ?>
		</textarea>
	
		<label for="tags">Categories:</label>
		<input type="text" name="tags" id="tags" autocomplete="off" />
		
		<label for="tags">Tags:</label>
		<input type="text" name="tags" id="tags" autocomplete="off" />
		
		
		<input id="submit" type="submit" value="Post it" />
	</form>
</div> <!-- // postbox -->
