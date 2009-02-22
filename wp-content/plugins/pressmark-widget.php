<?php
/**
 * @package Pressmark_Widget
 * @author Alexandre Girard
 * @version 1.0
 */
/*
Plugin Name: Pressmark Widget
Plugin URI: http://github.com/alx/pressmark/#
Description: A simple widget to list the links from a Pressmark RSS Feed.
Author: Alexandre Girard
Version: 1.0
Author URI: http://alexgirard.com/
*/


/**
 * Display Pressmarj widget.
 *
 * Allows for multiple widgets to be displayed.
 *
 * @since 2.2.0
 *
 * @param array $args Widget arguments.
 * @param int $number Widget number.
 */
function pressmark_widget() {
	extract($args, EXTR_SKIP);
	if ( is_numeric($widget_args) )
		$widget_args = array( 'number' => $widget_args );
	$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
	extract($widget_args, EXTR_SKIP);

	$options = get_option('pressmark_widget');

	if ( !isset($options[$number]) )
		return;

	if ( isset($options[$number]['error']) && $options[$number]['error'] )
		return;

	$url = $options[$number]['url'];
	while ( strstr($url, 'http') != $url )
		$url = substr($url, 1);
	if ( empty($url) )
		return;

	require_once(ABSPATH . WPINC . '/rss.php');

	$rss = fetch_rss($url);
	$link = clean_url(strip_tags($rss->channel['link']));
	while ( strstr($link, 'http') != $link )
		$link = substr($link, 1);
	$desc = attribute_escape(strip_tags(html_entity_decode($rss->channel['description'], ENT_QUOTES)));
	$title = $options[$number]['title'];
	if ( empty($title) )
		$title = htmlentities(strip_tags($rss->channel['title']));
	if ( empty($title) )
		$title = $desc;
	if ( empty($title) )
		$title = __('Unknown Feed');
	$title = apply_filters('widget_title', $title );
	$url = clean_url(strip_tags($url));

	echo $before_widget;
	echo $before_title . $title . $after_title;

	pressmark_widget_output( $rss, $options[$number] );

	echo $after_widget;
}

/**
 * Display the RSS entries in a list.
 *
 * @since 2.5.0
 *
 * @param string|array|object $rss RSS url.
 * @param array $args Widget arguments.
 */
function pressmark_widget_output( $rss, $args = array() ) {
	if ( is_string( $rss ) ) {
		require_once(ABSPATH . WPINC . '/rss.php');
		if ( !$rss = fetch_rss($rss) )
			return;
	} elseif ( is_array($rss) && isset($rss['url']) ) {
		require_once(ABSPATH . WPINC . '/rss.php');
		$args = $rss;
		if ( !$rss = fetch_rss($rss['url']) )
			return;
	} elseif ( !is_object($rss) ) {
		return;
	}

	$default_args = array( 'show_author' => 0, 'show_date' => 0, 'show_summary' => 0 );
	$args = wp_parse_args( $args, $default_args );
	extract( $args, EXTR_SKIP );

	$items = (int) $items;
	if ( $items < 1 || 20 < $items )
		$items = 10;
	$show_summary  = (int) $show_summary;
	$show_author   = (int) $show_author;
	$show_date     = (int) $show_date;

	if ( is_array( $rss->items ) && !empty( $rss->items ) ) {
		$rss->items = array_slice($rss->items, 0, $items);
		echo '<ul>';
		foreach ( (array) $rss->items as $item ) {
			while ( strstr($item['link'], 'http') != $item['link'] )
				$item['link'] = substr($item['link'], 1);
			$link = clean_url(strip_tags($item['source']['url']));
			$title = attribute_escape(strip_tags($item['title']));
			if ( empty($title) )
				$title = __('Untitled');
			$desc = '';
			if ( isset( $item['description'] ) && is_string( $item['description'] ) )
				$desc = str_replace(array("\n", "\r"), ' ', attribute_escape(strip_tags(html_entity_decode($item['description'], ENT_QUOTES))));
			elseif ( isset( $item['summary'] ) && is_string( $item['summary'] ) )
				$desc = str_replace(array("\n", "\r"), ' ', attribute_escape(strip_tags(html_entity_decode($item['summary'], ENT_QUOTES))));
			if ( 360 < strlen( $desc ) )
				$desc = wp_html_excerpt( $desc, 360 ) . ' [&hellip;]';
			$summary = $desc;

			if ( $show_summary ) {
				$desc = '';
				$summary = wp_specialchars( $summary );
				$summary = "<div class='pressmarkSummary'>$summary</div>";
			} else {
				$summary = '';
			}

			$date = '';
			if ( $show_date ) {
				if ( isset($item['pubdate']) )
					$date = $item['pubdate'];
				elseif ( isset($item['published']) )
					$date = $item['published'];

				if ( $date ) {
					if ( $date_stamp = strtotime( $date ) )
						$date = ' <span class="pressmark-date">' . date_i18n( get_option( 'date_format' ), $date_stamp ) . '</span>';
					else
						$date = '';
				}
			}

			$author = '';
			if ( $show_author ) {
				if ( isset($item['dc']['creator']) )
					$author = ' <cite>' . wp_specialchars( strip_tags( $item['dc']['creator'] ) ) . '</cite>';
				elseif ( isset($item['author_name']) )
					$author = ' <cite>' . wp_specialchars( strip_tags( $item['author_name'] ) ) . '</cite>';
			}

			if ( $link == '' ) {
				echo "<li>$title{$date}{$summary}{$author}</li>";
			} else {
				echo "<li><a class='pressmarkwidget' href='$link' title='$desc'>$title</a>{$date}{$summary}{$author}</li>";
			}
}
		echo '</ul>';
	} else {
		echo '<ul><li>' . __( 'An error has occurred; the feed is probably down. Try again later.' ) . '</li></ul>';
	}
}

/**
 * Display and process Pressmark widget control form.
 *
 * @since 2.2.0
 *
 * @param int $widget_args Widget number.
 */
function pressmark_widget_control($widget_args) {
	global $wp_registered_widgets;
	static $updated = false;

	if ( is_numeric($widget_args) )
		$widget_args = array( 'number' => $widget_args );
	$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
	extract($widget_args, EXTR_SKIP);

	$options = get_option('pressmark_widget');
	if ( !is_array($options) )
		$options = array();

	$urls = array();
	foreach ( (array) $options as $option )
		if ( isset($option['url']) )
			$urls[$option['url']] = true;

	if ( !$updated && 'POST' == $_SERVER['REQUEST_METHOD'] && !empty($_POST['sidebar']) ) {
		$sidebar = (string) $_POST['sidebar'];

		$sidebars_widgets = wp_get_sidebars_widgets();
		if ( isset($sidebars_widgets[$sidebar]) )
			$this_sidebar =& $sidebars_widgets[$sidebar];
		else
			$this_sidebar = array();

		foreach ( (array) $this_sidebar as $_widget_id ) {
			if ( 'pressmark_widget' == $wp_registered_widgets[$_widget_id]['callback'] && isset($wp_registered_widgets[$_widget_id]['params'][0]['number']) ) {
				$widget_number = $wp_registered_widgets[$_widget_id]['params'][0]['number'];
				if ( !in_array( "pressmark-$widget_number", $_POST['pressmark-id'] ) ) // the widget has been removed.
					unset($options[$widget_number]);
			}
		}

		foreach( (array) $_POST['pressmark-widget'] as $widget_number => $pressmark_widget ) {
			if ( !isset($pressmark_widget['url']) && isset($options[$widget_number]) ) // user clicked cancel
				continue;
			$pressmark_widget = stripslashes_deep( $pressmark_widget );
			$url = sanitize_url(strip_tags($pressmark_widget['url']));
			$options[$widget_number] = pressmark_widget_process( $pressmark_widget, !isset($urls[$url]) );
		}

		update_option('pressmark_widget', $options);
		$updated = true;
	}

	if ( -1 == $number ) {
		$title = '';
		$url = '';
		$items = 10;
		$error = false;
		$number = '%i%';
		$show_summary = 0;
		$show_author = 0;
		$show_date = 0;
	} else {
		extract( (array) $options[$number] );
	}

	pressmark_widget_form( compact( 'number', 'title', 'url', 'items', 'error', 'show_summary', 'show_author', 'show_date' ) );
}

/**
 * Display Pressmark widget options form.
 *
 * The options for what fields are displayed for the Pressmark form are all booleans
 * and are as follows: 'url', 'title', 'items', 'show_summary', 'show_author',
 * 'show_date'.
 *
 * @since 2.5.0
 *
 * @param array|string $args Values for input fields.
 * @param array $inputs Override default display options.
 */
function pressmark_widget_form( $args, $inputs = null ) {

	$default_inputs = array( 'url' => true, 'title' => true, 'items' => true, 'show_summary' => true, 'show_author' => true, 'show_date' => true );
	$inputs = wp_parse_args( $inputs, $default_inputs );
	extract( $args );
	extract( $inputs, EXTR_SKIP);

	$number = attribute_escape( $number );
	$title  = attribute_escape( $title );
	$url    = attribute_escape( $url );
	$items  = (int) $items;
	if ( $items < 1 || 20 < $items )
		$items  = 10;
	$show_summary   = (int) $show_summary;
	$show_author    = (int) $show_author;
	$show_date      = (int) $show_date;

	if ( $inputs['url'] ) :
?>
	<p>
		<label for="pressmark-url-<?php echo $number; ?>"><?php _e('Enter the Pressmark feed URL here:'); ?>
			<input class="widefat" id="pressmark-url-<?php echo $number; ?>" name="pressmark-widget[<?php echo $number; ?>][url]" type="text" value="<?php echo $url; ?>" />
		</label>
	</p>
<?php endif; if ( $inputs['title'] ) : ?>
	<p>
		<label for="pressmark-title-<?php echo $number; ?>"><?php _e('Give the Pressmark a title (optional):'); ?>
			<input class="widefat" id="pressmark-title-<?php echo $number; ?>" name="pressmark-widget[<?php echo $number; ?>][title]" type="text" value="<?php echo $title; ?>" />
		</label>
	</p>
<?php endif; if ( $inputs['items'] ) : ?>
	<p>
		<label for="pressmark-items-<?php echo $number; ?>"><?php _e('How many items would you like to display?'); ?>
			<select id="pressmark-items-<?php echo $number; ?>" name="pressmark-widget[<?php echo $number; ?>][items]">
				<?php
					for ( $i = 1; $i <= 20; ++$i )
						echo "<option value='$i' " . ( $items == $i ? "selected='selected'" : '' ) . ">$i</option>";
				?>
			</select>
		</label>
	</p>
<?php endif; if ( $inputs['show_summary'] ) : ?>
	<p>
		<label for="pressmark-show-summary-<?php echo $number; ?>">
			<input id="pressmark-show-summary-<?php echo $number; ?>" name="pressmark-widget[<?php echo $number; ?>][show_summary]" type="checkbox" value="1" <?php if ( $show_summary ) echo 'checked="checked"'; ?>/>
			<?php _e('Display item content?'); ?>
		</label>
	</p>
<?php endif; if ( $inputs['show_author'] ) : ?>
	<p>
		<label for="pressmark-show-author-<?php echo $number; ?>">
			<input id="pressmark-show-author-<?php echo $number; ?>" name="pressmark-widget[<?php echo $number; ?>][show_author]" type="checkbox" value="1" <?php if ( $show_author ) echo 'checked="checked"'; ?>/>
			<?php _e('Display item author if available?'); ?>
		</label>
	</p>
<?php endif; if ( $inputs['show_date'] ) : ?>
	<p>
		<label for="pressmark-show-date-<?php echo $number; ?>">
			<input id="pressmark-show-date-<?php echo $number; ?>" name="pressmark-widget[<?php echo $number; ?>][show_date]" type="checkbox" value="1" <?php if ( $show_date ) echo 'checked="checked"'; ?>/>
			<?php _e('Display item date?'); ?>
		</label>
	</p>
	<input type="hidden" name="pressmark-widget[<?php echo $number; ?>][submit]" value="1" />
<?php
	endif;
	foreach ( array_keys($default_inputs) as $input ) :
		if ( 'hidden' === $inputs[$input] ) :
			$id = str_replace( '_', '-', $input );
?>
	<input type="hidden" id="pressmark-<?php echo $id; ?>-<?php echo $number; ?>" name="pressmark-widget[<?php echo $number; ?>][<?php echo $input; ?>]" value="<?php echo $$input; ?>" />
<?php
		endif;
	endforeach;
}

/**
 * Process Pressmark feed widget data and optionally retrieve feed items.
 *
 * The feed widget can not have more than 20 items or it will reset back to the
 * default, which is 10.
 *
 * The resulting array has the feed title, feed url, feed link (from channel),
 * feed items, error (if any), and whether to show summary, author, and date.
 * All respectively in the order of the array elements.
 *
 * @since 2.5.0
 *
 * @param array $widget_rss RSS widget feed data. Expects unescaped data.
 * @param bool $check_feed Optional, default is true. Whether to check feed for errors.
 * @return array
 */
function pressmark_widget_process( $widget_rss, $check_feed = true ) {
	$items = (int) $widget_rss['items'];
	if ( $items < 1 || 20 < $items )
		$items = 10;
	$url           = sanitize_url(strip_tags( $widget_rss['url'] ));
	$title         = trim(strip_tags( $widget_rss['title'] ));
	$show_summary  = (int) $widget_rss['show_summary'];
	$show_author   = (int) $widget_rss['show_author'];
	$show_date     = (int) $widget_rss['show_date'];

	if ( $check_feed ) {
		require_once(ABSPATH . WPINC . '/rss.php');
		$rss = fetch_rss($url);
		$error = false;
		$link = '';
		if ( !is_object($rss) ) {
			$url = wp_specialchars(__('Error: could not find an RSS or ATOM feed at that URL.'), 1);
			$error = sprintf(__('Error in RSS %1$d'), $widget_number );
		} else {
			$link = clean_url(strip_tags($rss->channel['source']['url']));
			while ( strstr($link, 'http') != $link )
				$link = substr($link, 1);
		}
	}

	return compact( 'title', 'url', 'link', 'items', 'error', 'show_summary', 'show_author', 'show_date' );
}

/**
 * Register Pressmark widget to allow multiple widgets on startup.
 *
 * @since 2.2.0
 */
function pressmark_widget_register() {
		if ( !$options = get_option('pressmark_widget') )
			$options = array();
		$widget_ops = array('classname' => 'pressmark_widget', 'description' => __( 'Pressmark Link list' ));
		$control_ops = array('width' => 400, 'height' => 200, 'id_base' => 'pressmark');
		$name = __('Pressmark');

		$id = false;
		foreach ( (array) array_keys($options) as $o ) {
			$id = "pressmark-$o"; // Never never never translate an id
			wp_register_sidebar_widget($id, $name, 'pressmark_widget', $widget_ops, array( 'number' => $o ));
			wp_register_widget_control($id, $name, 'pressmark_widget_control', $control_ops, array( 'number' => $o ));
		}

		// If there are none, we register the widget's existance with a generic template
		if ( !$id ) {
			wp_register_sidebar_widget( 'pressmark-1', $name, 'pressmark_widget', $widget_ops, array( 'number' => -1 ) );
			wp_register_widget_control( 'pressmark-1', $name, 'pressmark_widget_control', $control_ops, array( 'number' => -1 ) );
		}
}

add_action('init', 'pressmark_widget_register');

?>
