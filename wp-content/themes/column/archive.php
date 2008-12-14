<?php get_header(); ?>

<?php if( have_posts( ) ) { ?>
	
	  <?php $post = $posts[0]; // Hack. Set $post so that the_date() works. ?>
	  <?php /* If this is a category archive */ if (is_category()) { ?>
		<p id="blurb">Archive for the &#8216;<?php single_cat_title(); ?>&#8217; Category</p>
	  <?php /* If this is a tag archive */ } elseif( is_tag() ) { ?>
		<p id="blurb">Posts Tagged &#8216;<?php single_tag_title(); ?>&#8217;</p>
	  <?php /* If this is a daily archive */ } elseif (is_day()) { ?>
		<p id="blurb">Archive for <?php the_time('F jS, Y'); ?></p>
	  <?php /* If this is a monthly archive */ } elseif (is_month()) { ?>
		<p id="blurb">Archive for <?php the_time('F, Y'); ?></p>
	  <?php /* If this is a yearly archive */ } elseif (is_year()) { ?>
		<p id="blurb">Archive for <?php the_time('Y'); ?></p>
	  <?php /* If this is an author archive */ } elseif (is_author()) { ?>
		<p id="blurb">Author Archive</p>
	  <?php /* If this is a paged archive */ } elseif (isset($_GET['paged']) && !empty($_GET['paged'])) { ?>
		<p id="blurb">Blog Archives</p>
	  <?php } ?>

	$previous_user = "";
	while( have_posts( ) ) {
		the_post();
		$url = post_custom("pressmark-url");
		
		the_date('', '<h2 style="margin-bottom: 0; color: #000; text-decoration: none;">', '</h2><div style="text-align: right; font-family: Verdana; font-size: 11px; margin: 0 0 10px 0;"></div>', true);
?>
	
	<div class="article_bar" id="article_bar_<?php the_ID(); ?>">
		<span style="display:none;" id="urltitle<?php the_ID(); ?>"><?php the_title(); ?></span>
		<a id="article_link_<?php the_ID(); ?>" class="content_link" href="<?php echo $url; ?>"><?php the_title(); ?></a>
		
		<div style="padding-top: 0.5em;">
			<blockquote>
			<?php the_content(); ?>
			</blockquote>

			<?php

			// Youtube embed
			if(preg_match("/http:\/\/([a-zA-Z0-9\-\_]+\.|)youtube\.com\/watch(\?v\=|\/v\/)([a-zA-Z0-9\-\_]{11})([^<\s]*)/", $url, $match)){
				echo '<div class="youtube" style="height:310px;text-align:center;"><object type="application/x-shockwave-flash" width="380" height="308" data="'.htmlspecialchars('http://www.youtube.com/v/'.$match[3].'&rel=1&fs=1&ap=%2526fmt%3D18', ENT_QUOTES).'"><param name="movie" value="'.htmlspecialchars('http://www.youtube.com/v/'.$match[3].'&rel=1&fs=1&ap=%2526fmt%3D18', ENT_QUOTES).'"></param><param name="allowFullScreen" value="true"></param><param name="wmode" value="transparent" /></object></div>';
			}

			// Vimeo embed
			if(preg_match("/http:\/\/([a-zA-Z0-9\-\_]+\.|)vimeo\.com\/(\d+)([^<\s]*)/", $url, $match)){
				echo '<div class="vimeo" style="height:285px;text-align:center;"><object width="380" height="283"><param name="allowfullscreen" value="true" /><param name="allowscriptaccess" value="always" /><param name="movie" value="http://vimeo.com/moogaloop.swf?clip_id='.$match[2].'&amp;server=vimeo.com&amp;show_title=1&amp;show_byline=1&amp;show_portrait=0&amp;color=&amp;fullscreen=1" /><embed src="http://vimeo.com/moogaloop.swf?clip_id='.$match[2].'&amp;server=vimeo.com&amp;show_title=1&amp;show_byline=1&amp;show_portrait=0&amp;color=&amp;fullscreen=1" type="application/x-shockwave-flash" allowfullscreen="true" allowscriptaccess="always" width="380" height="283"></embed></object></div>';
			}

			// mp3 embed
			if(class_exists('AudioPlayer') and preg_match("/http:\/\/.*\.mp3$/", $url)){
				global $AudioPlayer;
				echo $AudioPlayer->getPlayer($url);
			}
			?>
		</div>

		<div style="clear: both; color: #777; font-family: Verdana; font-size: 11px; text-align: right;">
			<span class="host">
				<script type="text/javascript"><!--
					document.write(parse_host(<?php the_ID(); ?>));
				//--></script>
				&bull;
				<a style="text-decoration: none; font-size: 12px; font-family: Georgia, Times, serif;" href="<?php the_permalink(); ?>">&#8734; permalink</a>
			</span>
		</div>
	</div>
<?php
	} // end while( have_posts( ) )
} // end if( have_posts( ) )
?>
    <div style="text-align: center; margin-top: 40px;">
		<?php posts_nav_link('&nbsp;&nbsp;', __('&#171; Previous'), __('Next &#187;')); ?>
    </div>

<?php
get_footer();