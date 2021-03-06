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
	</head>
<body>

<div id="wrapper">

<h1><a href="<?php bloginfo( 'url' ); ?>/"><?php bloginfo( 'name' ); ?></a></h1>

<?php
$image = get_header_image( );
if( preg_match( '|there-is-no-image.jpg$|', $image ) !== 1 ) {
?>

<div id="header_img">
<img src="<?php echo $image; ?>" width="726" height="150" alt="" />
</div>

<?php
} // if header image
?>