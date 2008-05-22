
<div id="sidebar">
	<ul>
		<li><?php wp_tag_cloud('smallest=4&largest=14'); ?></li>

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
			<p>NOT WORKING YET - Copy this link in your Firefox Bookmarks Toolbar!<br/>
			Then when you get a link, simply press the "Press it" bookmark to post it on <?php bloginfo( 'name' ); ?>.<br/>
			<a href="javascript:Q='';if(navigator.userAgent.indexOf('Safari')>=0){Q=getSelection();}else{Q=document.selection?document.selection.createRange().text:document.getSelection();}location.href='<?php echo get_option('siteurl');?>index.php?posttext='+encodeURIComponent(Q)+'&posturl='+encodeURIComponent(location.href)+'&posttitle='+encodeURIComponent(document.title);">
			Press it</a>
			</p>
		</li>
		<?php } ?>
	</ul>
</div> <!-- // sidebar -->
