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
		<?php echo get_avatar( get_the_author_ID( ), 48 ); ?>
		<?php the_author_posts_link( ); ?>
	</h2>
	<ul>
		<li>
			<h3>
				<a href="<?php $url = post_custom("pressmark-url"); echo $url; ?>"><?php echo $post->post_title ?></a>
				<?php
				// PDF to Scribd
				if(preg_match("/http:\/\/.*\.pdf$/", $url)){ echo ' - [<a href="http://www.scribd.com/vacuum?url='.$url.'">Scribd</a>]';}
				?>
			</h3>
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

				// mp3 embed
				if(class_exists('AudioPlayer') and preg_match("/http:\/\/.*\.mp3$/", $url)){
					global $AudioPlayer;
					echo $AudioPlayer->getPlayer($url);
				}
				?>
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
