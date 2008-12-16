<?php

function pressmark() {
	if( current_user_can( 'publish_posts' ) ) {

		// User has submitted a new post
		if( bookmark_submitted() ) {
			add_new_bookmark();
		}

		// Display form
		display_bookmark_form();
	}
}

function display_bookmark_form() {
	?>
	<div id="postbox">
		<form id="new_post" name="new_post" method="post" action="<?php bloginfo( 'url' ); ?>/">
			<input type="hidden" name="action" value="post" />
			<?php wp_nonce_field( 'new-post' ); ?>

			<p>
			<label for="posttitle">Title:</label><br>
			<input type="text" name="posttitle" value="<?php echo $_GET['posttitle']; ?>" id="posttitle" class="text"  size="50"/>
			</p>

			<p>
			<label for="posturl">Link:</label><br>
			<input type="text" id="posturl" name="posturl" class="text" value="<?php echo $_GET['posturl']; ?>"  size="50"/>
			</p>

			<p>
			<label for="posttext">Description:</label><br>
			<textarea name="posttext" id="posttext" rows="3" cols="50"><?php if(isset($_GET['posttext'])) echo $_GET['posttext']; ?></textarea>
			</p>

			<p>
			<label for="tags">Tags</label><br>
			<input type="text" name="tags" id="tags" autocomplete="off" size="50"/>
			</p>

			<p>
			<input type="radio" name="status" value="publish" checked="checked"> Public
			<input type="radio" name="status" value="private"> Private
			</p>

			<input id="submit" type="submit" value="Post it" />
		</form>
	</div> <!-- // postbox -->
	<?php
}

function bookmark_submitted() {
	return ('POST' == $_SERVER['REQUEST_METHOD'] && !empty( $_POST['action'] ) && $_POST['action'] == 'post');
}

function add_new_bookmark() {
	if( !current_user_can( 'publish_posts' ) ) {
		wp_redirect( get_bloginfo( 'url' ) );
		exit;
	}
	
	check_admin_referer( 'new-post' );
	
	$user			= get_userdata( $current_user->ID );
	$post_title		= urldecode($_POST['posttitle']);
	$post_url		= urldecode($_POST['posturl']);
	$post_content	= urldecode($_POST['posttext']);
	$tags			= urldecode($_POST['tags']);
	$status			= urldecode($_POST['status']);
	
	global $wpdb;
	
	// Search existing post with this pressmark-url
	$existing_post_id = $wpdb->get_var("SELECT post_id FROM $wpdb->postmeta 
									WHERE meta_key = 'pressmark-url' AND meta_value = '$post_url'" );
	if($existing_post_id){
		// If exists, add coauthor
		add_post_meta($existing_post_id, 'coauthor', (int)$user->ID, false);
		// Place post on top
		$post_modified     = current_time( 'mysql' );
		$post_modified_gmt = current_time( 'mysql', 1 );
		$wpdb->query("UPDATE $wpdb->posts 
					SET post_date = $post_modified 
					SET post_date_gmt = $post_modified_gmt
					WHERE ID = '$existing_post_id'");
	}
	else {
		// If not exists, insert new post
		$post_id = wp_insert_post( array(
			'post_author'	=> $user->ID,
			'post_title'	=> $post_title,
			'post_content'	=> $post_content,
			'tags_input'	=> $tags,
			'post_status'	=> $status
		) );

		$wpdb->query( "
				INSERT INTO $wpdb->postmeta
				(post_id,meta_key,meta_value )
				VALUES ('$post_id','pressmark-url','$post_url' )
			" );
	}
}

?>