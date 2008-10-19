<?php 
if( 'POST' == $_SERVER['REQUEST_METHOD'] && !empty( $_POST['action'] ) && $_POST['action'] == 'post' ) {
	
	if( !current_user_can( 'publish_posts' ) ) {
		wp_redirect( get_bloginfo( 'url' ) );
		exit;
	}
	
	check_admin_referer( 'new-post' );

	$user_id		= $current_user->user_id;
	$post_title		= urldecode($_POST['posttitle']);
	$post_url		= urldecode($_POST['posturl']);
	$post_content	= urldecode($_POST['posttext']);
	$tags			= urldecode($_POST['tags']);
	$status			= urldecode($_POST['status']);

	// $char_limit		= 40;
	// $post_title		= strip_tags( $post_content );
	// if( strlen( $post_title ) > $char_limit ) {
	// 	$post_title = substr( $post_title, 0, $char_limit ) . ' ... ';
	// }


	
	global $wpdb;
	
	// Search existing post with this pressmark-url
	$existing_post_id = $wpdb->get_var("SELECT post_id FROM $wpdb->postmeta 
									WHERE meta_key = 'pressmark-url' AND meta_value = '$post_url'" );
	if($existing_post_id){
		// If exists, add coauthor
		add_post_meta($existing_post_id, 'coauthor', (int)$user_id, false);
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
			'post_author'	=> $user_id,
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

	wp_redirect( get_bloginfo( 'url' ) . '/' );
	exit;
}

get_header( ); 

if( current_user_can( 'publish_posts' ) ) {
	require_once dirname( __FILE__ ) . '/post-form.php';
}
?>

<div id="main">
	<h2>Latest Bookmarks <a class="rss" href="<?php bloginfo( 'rss2_url' ); ?>">RSS</a></h2>
	<ul>

<?php
if( have_posts( ) ) {

	$previous_user_id = 0;
	while( have_posts( ) ) {
		the_post( );
?>

<li id="prologue-<?php the_ID(); ?>" class="user_id_<?php the_author_ID( ); ?>">

<?php
		// Don't show the avatar if the previous post was by the same user
		$current_author = get_the_author( );
		if( $previous_user_id !== $current_author->ID ) {
			echo '<a href="' . get_author_posts_url($current_author->ID, $current_author->user_nicename) . '" title="' . sprintf(__("Posts by %s"), attribute_escape($current_author->display_name)) . '">' . prologue_get_avatar($current_author->ID, $current_author->user_email, 48 ) . '</a>';
		}
		$previous_user_id = $current_author->ID;
		
		$url = post_custom("pressmark-url");
?>
	<h3><a href="<?php $url ?>"><?php echo $post->post_title ?></a></h3>
	<h4>
		<span class="meta">
			<?php the_time( "h:i:s a" ); ?> on <?php the_time( "F j, Y" ); ?> |
			<?php comments_popup_link( __( '0' ), __( '1' ), __( '%' ) ); ?> |
			<?php edit_post_link( __( 'e' ) ); ?>
			<br />
			<?php the_author_posts_link( ); ?><?php the_tags( __( ' | Tags: ' ), ', ', ' ' ); ?>
			<?php if($post->post_status == 'private') echo " | <span class='private'>private</span>"?>
		</span>
	</h4>
	<div class="postcontent">
		<?php
		// Insert youtube video
		if(preg_match("/http:\/\/([a-zA-Z0-9\-\_]+\.|)youtube\.com\/watch(\?v\=|\/v\/)([a-zA-Z0-9\-\_]{11})([^<\s]*)/", $url, $match)){
			echo '<p><span class="youtube"><object type="application/x-shockwave-flash" width="400" height="324" data="'.htmlspecialchars('http://www.youtube.com/v/'.$match[3].'&rel=1&fs=1&ap=%2526fmt%3D18', ENT_QUOTES).'"><param name="movie" value="'.htmlspecialchars('http://www.youtube.com/v/'.$match[3].'&rel=1&fs=1&ap=%2526fmt%3D18', ENT_QUOTES).'"></param><param name="allowFullScreen" value="true"></param><param name="wmode" value="transparent" /></object></span></p>';
		}
		
		?>
		<?php the_content( __( '(More ...)' ) ); ?>
	</div> <!-- // postcontent -->
	<div class="bottom_of_entry">&nbsp;</div>
</li>

<?php
	} // while have_posts

} // if have_posts
?>

	</ul>

	<div class="navigation"><p><?php posts_nav_link(); ?></p></div>

</div> <!-- // main -->

<?php
get_footer( );
