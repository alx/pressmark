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

<h3><a href="<?php $url = post_custom("pressmark-url"); echo $url; ?>"><?php echo $post->post_title ?></a></h3>
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
	<?php

	// Youtube embed
	if(preg_match("/http:\/\/([a-zA-Z0-9\-\_]+\.|)youtube\.com\/watch(\?v\=|\/v\/)([a-zA-Z0-9\-\_]{11})([^<\s]*)/", $url, $match)){
		echo '<p><span class="youtube" style="height:310px;"><object type="application/x-shockwave-flash" width="380" height="308" data="'.htmlspecialchars('http://www.youtube.com/v/'.$match[3].'&rel=1&fs=1&ap=%2526fmt%3D18', ENT_QUOTES).'"><param name="movie" value="'.htmlspecialchars('http://www.youtube.com/v/'.$match[3].'&rel=1&fs=1&ap=%2526fmt%3D18', ENT_QUOTES).'"></param><param name="allowFullScreen" value="true"></param><param name="wmode" value="transparent" /></object></span></p>';
	}

	// Vimeo embed
	if(preg_match("/http:\/\/([a-zA-Z0-9\-\_]+\.|)vimeo\.com\/(\d+)([^<\s]*)/", $url, $match)){
		echo '<p><span class="vimeo" style="height:285px;"><object width="380" height="283"><param name="allowfullscreen" value="true" /><param name="allowscriptaccess" value="always" /><param name="movie" value="http://vimeo.com/moogaloop.swf?clip_id='.$match[2].'&amp;server=vimeo.com&amp;show_title=1&amp;show_byline=1&amp;show_portrait=0&amp;color=&amp;fullscreen=1" /><embed src="http://vimeo.com/moogaloop.swf?clip_id='.$match[2].'&amp;server=vimeo.com&amp;show_title=1&amp;show_byline=1&amp;show_portrait=0&amp;color=&amp;fullscreen=1" type="application/x-shockwave-flash" allowfullscreen="true" allowscriptaccess="always" width="380" height="283"></embed></object></span></p>';
	}
	
	// Flickr embed
	if(function_exists('flickr_embed') && preg_match("/http:\/\/([a-zA-Z0-9\-\_]+\.|)flickr\.com\/photos\/(.+)\/(\d+)([^<\s]*)/", $url, $match)){
		flickr_embed($match[3]);
	}

	// mp3 embed
	if(class_exists('AudioPlayer') and preg_match("/http:\/\/.*\.mp3$/", $url)){
		global $AudioPlayer;
		echo $AudioPlayer->getPlayer($url);
	}
	?>
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
