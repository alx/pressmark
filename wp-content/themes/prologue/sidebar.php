<div id="sidebar">
	<ul>
		<li><?php wp_tag_cloud('smallest=4&largest=14&number=500'); ?></li>
		
		<li>
			<?php user_cloud(); ?>
			<br clear='left'>
		</li>
		
		<li>
			<?php wp_widget_recent_comments(array()); ?>
		</li>
		
		<li class="credits">
			<p>
				<?php
				if ( ! is_user_logged_in() )
					echo '<a href="' . get_option('siteurl') . '/wp-login.php?redirect_to=' . urlencode(get_option('siteurl')) . '">' . __('Log in') . '</a>';
				else
					echo '<a href="' . get_option('siteurl') . '/wp-login.php?action=logout">' . __('Log out') . '</a>';
				?>
			</p>
		</li>
		
		<?php if ( is_user_logged_in() ) { ?>
		<li class="bookmarlet">
			<p>Copy this link in your Bookmarks Toolbar to add bookmark in 2 clicks!<br>
			<a href="javascript:Q='';if(navigator.userAgent.indexOf('Safari')>=0){Q=getSelection();}else{Q=document.selection?document.selection.createRange().text:document.getSelection();}location.href='<?php echo get_option('siteurl');?>/index.php?posttext='+encodeURIComponent(Q)+'&posturl='+encodeURIComponent(location.href)+'&posttitle='+encodeURIComponent(document.title);">
			Press it</a>
			Ubiquity: <b>press</b><link rel="commands" href="<?php bloginfo('template_url'); ?>/ubiquity.js" name="press" />
			</p>

		</li>
		<?php } ?>
	</ul>
</div> <!-- // sidebar -->
