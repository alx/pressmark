<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" 
 "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
	<head profile="http://gmpg.org/xfn/11">
		<meta http-equiv="Content-Type" content="<?php bloginfo('html_type'); ?>; charset=<?php bloginfo('charset'); ?>" />
		<title><?php wp_title(); ?> <?php bloginfo('name'); ?></title>
		<meta name="generator" content="WordPress.com" /> 
		<link rel="stylesheet" href="<?php bloginfo('stylesheet_url'); ?>" type="text/css" media="screen" />
		<link rel="alternate" type="application/rss+xml" title="<?php bloginfo('name'); ?> RSS Feed" href="<?php bloginfo('rss2_url'); ?>" />
		<link rel="pingback" href="<?php bloginfo('pingback_url'); ?>" />
		<link rel="shortcut icon" href="favicon.ico">
		<?php wp_head(); ?>
		
		<script type="text/javascript">
			function parse_host(post_id)
			{
				var link = document.getElementById('article_link_' + post_id);
				if (! link) return '';

				var parts = link.getAttribute('href').split('/');
				if (parts.length > 2) return parts[2]; else return '';
			}
		</script>
	</head>

	<body style="background-color: #fff;">
		<div id="header">
			<div id="logo"><a href="<?php bloginfo( 'url' ); ?>" class="logo"><?php bloginfo( 'name' ); ?></a></div>

    		<div id="not_navigation">
        		<div style="float: left;">
            		<a href="<?php bloginfo('rss2_url'); ?>">RSS</a>
            		<a href="<?php bloginfo('rss2_url'); ?>"><img style="border: 0; width: 14px; height: 14px; margin-bottom: -2px;" src="<?php bloginfo( 'template_url' ); ?>/images/rss.png" alt="RSS feed" title="RSS feed"/></a>
        		</div>

				<form action="/search" method="get" style="float: right;">
					<input type="text" name="q" value="" style="width: 115px;"/>
					<input type="submit" value="Search"/>
				</form>
    		</div>
		</div>

		<div id="content">
            
<?php

if( current_user_can( 'publish_posts' ) ) require_once dirname( __FILE__ ) . '/post-form.php';