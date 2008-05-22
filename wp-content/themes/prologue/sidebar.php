
<div id="sidebar">
	<ul>
		<li><?php wp_tag_cloud(''); ?></li>

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
			<a href="javascript:Q='';if(top.frames.length==0)Q=document.selection.createRange()
			.text;void (btw=window.open ('<?php echo get_option('siteurl'); ?>?postcontent='+escape(Q)+
			'&posturl='+escape (location.href)+'&posttitle='+escape(document.title),'bookmarklet'
			,&'scrollbars=yes,width=600,height=460,left=100,top=150,status=yes'));btw.focus();">
			Press it</a>
			</p>
		</li>
		<?php } ?>
	</ul>
</div> <!-- // sidebar -->
