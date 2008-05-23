<?php 
get_header( ); 

if( have_posts( ) ) {
	$first_post = true;

	while( have_posts( ) ) {
		the_post( );

		$email_md5		= md5( get_the_author_email( ) );
		$default_img	= urlencode( 'http://use.perl.org/images/pix.gif' );
?>

<div id="postpage">
<div id="main">
	<h2>
		<?php echo prologue_get_avatar( get_the_author_ID( ), get_the_author_email( ), 48 ); ?>
		<?php the_author_posts_link( ); ?>
	</h2>
	<ul>
		<li>
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
	</ul>

<?php
		comments_template( );

	} // while have_posts
} // if have_posts
?>

</div> <!-- // main -->
</div> <!-- // postpage -->

<?php
get_footer( );
