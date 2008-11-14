<?php 
get_header( ); 
?>

<div id="userpage">
<div id="main">

<?php
if( have_posts( ) ) {
	$first_post = true;

	while( have_posts( ) ) {
		the_post( );

		$author_feed_url = '';
		if( function_exists( 'get_author_feed_link' ) ) {
			$author_feed_url = get_author_feed_link( get_the_author_ID( ) );
		}
		else {
			$author_feed_url = get_author_rss_link( false, get_the_author_ID( ), get_the_author_nickname( ) );
		}
?>

<?php if( $first_post === true ) { ?>
	<h2>
		<?php echo get_avatar( get_the_author_email( ), 48 ); ?>
		Updates from <?php the_author_posts_link( ); ?>
		<a class="rss" href="<?php echo $author_feed_url; ?>">RSS</a>
	</h2>
<?php } // first_post ?>

	<ul>
		<li>
			<h3>
				<a href="<?php echo post_custom("pressmark-url"); ?>"><?php the_title(); ?></a>
				<?php
				// PDF to Scribd
				if(preg_match("/http:\/\/.*\.mp3$/", $url)){ echo ' - [<a href=http://www.scribd.com/vacuum?url='.$url.'>Scribd</a>]';}
				?>
			</h3>
			<h4>
				<span class="meta">
					<?php the_time( "h:i:s a" ); ?> on <?php the_time( "F j, Y" ); ?> |
					<?php comments_popup_link( __( '0' ), __( '1' ), __( '%' ) ); ?> |
					<?php edit_post_link( __( 'e' ) ); ?>
					<br />
					<?php the_author_posts_link( ); ?><?php the_tags( __( ' | Tags: ' ), ', ', ' ' ); ?>
				</span>
			</h4>
			<div class="postcontent">
				<?php the_content( __( '(More ...)' ) ); ?>
			</div> <!-- // postcontent -->
			<div class="bottom_of_entry">&nbsp;</div>
		</li>
	</ul>

<?php
		$first_post = false;

	} // while have_posts

	echo '<div class="navigation"><p>' . posts_nav_link() . '</p></div>';

} // if have_posts
?>


</div> <!-- // main -->
</div> <!-- // postpage -->

<?php
get_footer( );
