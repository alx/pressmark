<?php 
get_header( ); 
$tag_obj = $wp_query->get_queried_object();
?>

<div id="main">
	<h2>Latest Updates: <?php single_tag_title( ); ?> <a class="rss" href="<?php echo get_tag_feed_link( $tag_obj->term_id ); ?>">RSS</a></h2>
	<ul>

<?php
if( have_posts( ) ) {

	$previous_user_id = 0;
	while( have_posts( ) ) {
		the_post( );
?>

<li>

<?php
		// Don't show the avatar if the previous post was by the same user
		$current_user_id = get_the_author_ID( );
		if( $previous_user_id !== $current_user_id ) {
			echo get_avatar( $current_user_id, 48 );
		}
		$previous_user_id = $current_user_id;
?>

<h3><a href="<?php echo post_custom("pressmark-url"); ?>"><?php echo $post->post_title ?></a></h3>
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
	<?php the_content( __( '(More ...)' ) ); ?>
</div> <!-- // postcontent -->
<div class="bottom_of_entry">&nbsp;</div>
</li>

<?php
	} // while have_posts

	echo '<div class="navigation"><p>' . posts_nav_link() . '</p></div>';
} // if have_posts
?>

	</ul>
</div> <!-- // main -->

<?php
get_footer( );
