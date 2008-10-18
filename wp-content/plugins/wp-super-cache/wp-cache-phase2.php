<?php

function wp_cache_phase2() {
	global $cache_filename, $cache_acceptable_files, $wp_cache_meta_object, $wp_cache_gzip_encoding, $super_cache_enabled, $cache_rebuild_files;

	wp_cache_mutex_init();
	if(function_exists('add_action') && ( !defined( 'WPLOCKDOWN' ) || ( defined( 'WPLOCKDOWN' ) && constant( 'WPLOCKDOWN' ) == '0' ) ) ) {
		// Post ID is received
		add_action('publish_post', 'wp_cache_post_edit', 0);
		add_action('edit_post', 'wp_cache_post_change', 0); // leaving a comment called edit_post
		add_action('delete_post', 'wp_cache_post_edit', 0);
		add_action('publish_phone', 'wp_cache_post_edit', 0);
		// Coment ID is received
		add_action('trackback_post', 'wp_cache_get_postid_from_comment', 0);
		add_action('pingback_post', 'wp_cache_get_postid_from_comment', 0);
		add_action('comment_post', 'wp_cache_get_postid_from_comment', 0);
		add_action('edit_comment', 'wp_cache_get_postid_from_comment', 0);
		add_action('wp_set_comment_status', 'wp_cache_get_postid_from_comment', 0);
		// No post_id is available
		add_action('delete_comment', 'wp_cache_no_postid', 0);
		add_action('switch_theme', 'wp_cache_no_postid', 0); 

		add_action('wp_cache_gc','wp_cache_gc_cron');

		do_cacheaction( 'add_cacheaction' );
	}
	if( $_SERVER["REQUEST_METHOD"] == 'POST' || get_option('gzipcompression')) 
		return;
	$script = basename($_SERVER['PHP_SELF']);
	if (!in_array($script, $cache_acceptable_files) && 
			wp_cache_is_rejected($_SERVER["REQUEST_URI"]))
		return;
	if (wp_cache_user_agent_is_rejected()) return;
	$wp_cache_meta_object = new CacheMeta;
	if($wp_cache_gzip_encoding)
		header('Vary: Accept-Encoding, Cookie');
	else
		header('Vary: Cookie');
	ob_start('wp_cache_ob_callback'); 

	// restore old supercache file temporarily
	if( $super_cache_enabled && $cache_rebuild_files ) {
		$user_info = wp_cache_get_cookies_values();
		$do_cache = apply_filters( 'do_createsupercache', $user_info );
		if( $user_info == '' || $do_cache === true ) {
			$dir = get_current_url_supercache_dir();
			$files_to_check = array( $dir . 'index.html', $dir . 'index.html.gz' );
			foreach( $files_to_check as $cache_file ) {
				if( !file_exists( $cache_file . '.needs-rebuild' ) )
					continue;
				$mtime = @filemtime($cache_file . '.needs-rebuild');
				if( $mtime && (time() - $mtime) < 30 ) {
					@rename( $cache_file . '.needs-rebuild', $cache_file );
				}
				// cleanup old files or if rename fails
				if( @file_exists( $cache_file . '.needs-rebuild' ) ) {
					@unlink( $cache_file . '.needs-rebuild' );
				}
			}
		}
	}
	register_shutdown_function('wp_cache_shutdown_callback');
}

function wp_cache_get_response_headers() {
	if(function_exists('apache_response_headers')) {
		flush();
		$headers = apache_response_headers();
	} else if(function_exists('headers_list')) {
		$headers = array();
		foreach(headers_list() as $hdr) {
			list($header_name, $header_value) = explode(': ', $hdr, 2);
			$headers[$header_name] = $header_value;
		}
	} else
		$headers = null;

	return $headers;
}

function wp_cache_is_rejected($uri) {
	global $cache_rejected_uri;

	if (strstr($uri, '/wp-admin/'))
		return true; // we don't allow caching of wp-admin for security reasons
	foreach ($cache_rejected_uri as $expr) {
		if( preg_match( "~$expr~", $uri ) )
			return true;
	}
	return false;
}

function wp_cache_user_agent_is_rejected() {
	global $cache_rejected_user_agent;

	if (!function_exists('apache_request_headers')) return false;
	$headers = apache_request_headers();
	if (!isset($headers["User-Agent"])) return false;
	foreach ($cache_rejected_user_agent as $expr) {
		if (strlen($expr) > 0 && stristr($headers["User-Agent"], $expr))
			return true;
	}
	return false;
}


function wp_cache_mutex_init() {
	global $use_flock, $mutex, $cache_path, $mutex_filename, $sem_id;

	if(!is_bool($use_flock)) {
		if(function_exists('sem_get')) 
			$use_flock = false;
		else
			$use_flock = true;
	}

	$mutex = false;
	if ($use_flock) 
		$mutex = @fopen($cache_path . $mutex_filename, 'w');
	else
		$mutex = @sem_get($sem_id, 1, 0644 | IPC_CREAT, 1);
}

function wp_cache_writers_entry() {
	global $use_flock, $mutex, $cache_path, $mutex_filename;

	if( !$mutex )
		return false;

	if ($use_flock)
		flock($mutex,  LOCK_EX);
	else
		sem_acquire($mutex);

	return true;
}

function wp_cache_writers_exit() {
	global $use_flock, $mutex, $cache_path, $mutex_filename;

	if( !$mutex )
		return false;

	if ($use_flock)
		flock($mutex,  LOCK_UN);
	else
		sem_release($mutex);
}

function get_current_url_supercache_dir() {
	global $cached_direct_pages, $cache_path;
	$uri = preg_replace('/[ <>\'\"\r\n\t\(\)]/', '', str_replace( '/index.php', '/', str_replace( '..', '', preg_replace("/(\?.*)?$/", '', $_SERVER['REQUEST_URI'] ) ) ) );
	$dir = strtolower(preg_replace('/:.*$/', '',  $_SERVER["HTTP_HOST"])) . $uri; // To avoid XSS attacs
	$dir = trailingslashit( $cache_path . 'supercache/' . $dir );
	if( is_array( $cached_direct_pages ) && in_array( $_SERVER[ 'REQUEST_URI' ], $cached_direct_pages ) ) {
		$dir = trailingslashit( ABSPATH . $uri );
	}
	$dir = str_replace( '//', '/', $dir );
	return $dir;
}

function wp_cache_ob_callback($buffer) {
	global $cache_path, $cache_filename, $meta_file, $wp_start_time, $supercachedir;
	global $new_cache, $wp_cache_meta_object, $file_expired, $blog_id, $cache_compression;
	global $wp_cache_gzip_encoding, $super_cache_enabled, $cached_direct_pages;
	global $wp_cache_404;

	$new_cache = true;

	/* Mode paranoic, check for closing tags 
	 * we avoid caching incomplete files */
	if( $wp_cache_404 ) {
		$new_cache = false;
		$buffer .= "\n<!-- Page not cached by WP Super Cache. 404. -->\n";
	}

	if (!preg_match('/(<\/html>|<\/rss>|<\/feed>)/i',$buffer) ) {
		$new_cache = false;
		$buffer .= "\n<!-- Page not cached by WP Super Cache. No closing HTML tag. Check your theme. -->\n";
	}
	
	if( !$new_cache )
		return $buffer;

	$duration = wp_cache_microtime_diff($wp_start_time, microtime());
	$duration = sprintf("%0.3f", $duration);
	$buffer .= "\n<!-- Dynamic Page Served (once) in $duration seconds -->\n";

	if( !wp_cache_writers_entry() ) {
		$buffer .= "\n<!-- Page not cached by WP Super Cache. Could not get mutex lock. -->\n";
		return $buffer;
	}

	$mtime = @filemtime($cache_path . $cache_filename);
	/* Return if:
		the file didn't exist before but it does exist now (another connection created)
		OR
		the file was expired and its mtime is less than 5 seconds
	*/
	if( !((!$file_expired && $mtime) || ($mtime && $file_expired && (time() - $mtime) < 5)) ) {
		$dir = get_current_url_supercache_dir();
		$supercachedir = $cache_path . 'supercache/' . preg_replace('/:.*$/', '',  $_SERVER["HTTP_HOST"]);
		if( !empty( $_GET ) || is_feed() || ( $super_cache_enabled == true && is_dir( substr( $supercachedir, 0, -1 ) . '.disabled' ) ) )
			$super_cache_enabled = false;

		$fr = @fopen($cache_path . $cache_filename, 'w');
		if (!$fr) {
			$buffer .= "<!-- File not cached! Super Cache Couldn't write to: " . str_replace( ABSPATH, '', $cache_path ) . $cache_filename . " -->\n";
			return $buffer;
		}
		if( $super_cache_enabled ) {
			if( @is_dir( $dir ) == false )
				@wp_mkdir_p( $dir );

			$user_info = wp_cache_get_cookies_values();
			$do_cache = apply_filters( 'do_createsupercache', $user_info );
			if( $user_info == '' || $do_cache === true ) {
				$cache_fname = "{$dir}index.html";
				$tmp_cache_filename = tempnam( $dir, "wpsupercache");
				$fr2 = @fopen( $tmp_cache_filename, 'w' );
				if( $cache_compression )
					$gz = @fopen( $tmp_cache_filename . ".gz", 'w');
			}
		}

		if (preg_match('/<!--mclude|<!--mfunc/', $buffer)) { //Dynamic content
			$store = preg_replace('|<!--mclude (.*?)-->(.*?)<!--/mclude-->|is', 
					"<!--mclude-->\n<?php include_once('" . ABSPATH . "$1'); ?>\n<!--/mclude-->", $buffer);
			$store = preg_replace('|<!--mfunc (.*?)-->(.*?)<!--/mfunc-->|is', 
					"<!--mfunc-->\n<?php $1 ;?>\n<!--/mfunc-->", $store);
			$wp_cache_meta_object->dynamic = true;
			/* Clean function calls in tag */
			$buffer = preg_replace('|<!--mclude (.*?)-->|is', '<!--mclude-->', $buffer);
			$buffer = preg_replace('|<!--mfunc (.*?)-->|is', '<!--mfunc-->', $buffer);
			fputs($fr, $store);
			if( $fr2 )
				fputs($fr2, $store . '<!-- super cache -->' );
			if( $gz )
				fputs($gz, gzencode( $store . '<!-- super cache gz -->', 1, FORCE_GZIP ) );
		} else {
			$log = "<!-- Cached page served by WP-Super-Cache -->\n";

			if( $gz || $wp_cache_gzip_encoding ) {
				$gzdata = gzencode( $buffer . $log . "<!-- Compression = gzip -->", 1, FORCE_GZIP );
				$gzsize = strlen($gzdata);
			}
			if ($wp_cache_gzip_encoding) {
				array_push($wp_cache_meta_object->headers, 'Content-Encoding: ' . $wp_cache_gzip_encoding);
				array_push($wp_cache_meta_object->headers, 'Vary: Accept-Encoding, Cookie');
				array_push($wp_cache_meta_object->headers, 'Content-Length: ' . strlen($gzdata));
				// Return uncompressed data & store compressed for later use
				fputs($fr, $gzdata);
			}else{ // no compression
				array_push($wp_cache_meta_object->headers, 'Vary: Cookie');
				fputs($fr, $buffer.$log);
			}
			if( $fr2 )
				fputs($fr2, $buffer . '<!-- super cache -->' );
			if( $gz )
				fwrite($gz, $gzdata );
		}
		$new_cache = true;
		fclose($fr);
		if( $fr2 ) {
			fclose($fr2);
			@chmod( $tmp_cache_filename, 0666 & ~umask());
			if( !@rename( $tmp_cache_filename, $cache_fname ) ) {
				unlink( $cache_fname );
				rename( $tmp_cache_filename, $cache_fname );
			}
		}
		if( $gz ) {
			fclose($gz);
			if( !@rename( $tmp_cache_filename . '.gz', $cache_fname . '.gz' ) ) {
				unlink( $cache_fname . '.gz' );
				rename( $tmp_cache_filename . '.gz', $cache_fname . '.gz' );
			}
		}
	}
	wp_cache_writers_exit();
	return $buffer;
}

function wp_cache_phase2_clean_cache($file_prefix) {
	global $cache_path;

	if( !wp_cache_writers_entry() )
		return false;
	if ( ($handle = opendir( $cache_path )) ) { 
		while ( false !== ($file = readdir($handle))) {
			if ( preg_match("/^$file_prefix/", $file) ) {
				@unlink($cache_path . $file);
			}
		}
		closedir($handle);
	}
	wp_cache_writers_exit();
}

function prune_super_cache($directory, $force = false, $rename = false) {
	global $cache_max_time, $cache_path, $super_cache_enabled, $cache_rebuild_files;

	if( !is_admin() && $super_cache_enabled == 0 )
		return false;

	if( !isset( $cache_max_time ) )
		$cache_max_time = 3600;

	$now = time();

	$protected_directories = array( $cache_path . '.htaccess', $cache_path . 'meta', $cache_path . 'supercache' );

	$oktodelete = false;
	if (is_dir($directory)) {
		$directory = trailingslashit( $directory );
		$entries = glob($directory. '*');
		if( is_array( $entries ) && !empty( $entries ) ) foreach ($entries as $entry) {
			if ($entry != '.' && $entry != '..') {
				prune_super_cache( $entry, $force, $rename );
				if( is_dir( $entry ) && ( $force || @filemtime( $entry ) + $cache_max_time <= $now ) ) {
					$oktodelete = true;
					if( in_array( $entry, $protected_directories ) )
						$oktodelete = false;
					if( $oktodelete && !$rename )
						@rmdir( addslashes( $entry ) );
				}
			}
		}
	} elseif( is_file($directory) && ($force || filemtime( $directory ) + $cache_max_time <= $now ) ) {
		$oktodelete = true;
		if( in_array( $directory, $protected_directories ) )
			$oktodelete = false;
		if( $oktodelete && !$rename ) {
			@unlink( addslashes( $directory ) );
		} elseif( $oktodelete && $rename ) {
			if( $cache_rebuild_files && substr( $directory, -14 ) != '.needs-rebuild' ) {
				if( @rename($directory, $directory . '.needs-rebuild') )
					@touch( $directory . '.needs-rebuild' );
			} else {
				@unlink( $directory );
			}

		}
	}
}

function wp_cache_phase2_clean_expired($file_prefix) {
	global $cache_path, $cache_max_time;

	clearstatcache();
	if( !wp_cache_writers_entry() )
		return false;
	$now = time();
	if ( ($handle = opendir( $cache_path )) ) { 
		while ( false !== ($file = readdir($handle))) {
			if ( preg_match("/^$file_prefix/", $file) && 
				(filemtime($cache_path . $file) + $cache_max_time) <= $now  ) {
				@unlink($cache_path . $file);
				@unlink($cache_path . 'meta/' . str_replace( '.html', '.meta', $file ) );
				continue;
			}
			if($file != '.' && $file != '..') {
				if( is_dir( $cache_path . $file ) == false && (filemtime($cache_path . $file) + $cache_max_time) <= $now  ) {
					if( substr( $file, -9 ) != '.htaccess' )
						@unlink($cache_path . $file);
				}
			}
		}
		closedir($handle);
		prune_super_cache( $cache_path . 'supercache' );
	}

	wp_cache_writers_exit();
}

function wp_cache_shutdown_callback() {
	global $cache_path, $cache_max_time, $file_expired, $file_prefix, $meta_file, $new_cache, $wp_cache_meta_object, $known_headers, $blog_id, $wp_cache_gc;

	$wp_cache_meta_object->uri = $_SERVER["SERVER_NAME"].preg_replace('/[ <>\'\"\r\n\t\(\)]/', '', $_SERVER['REQUEST_URI']); // To avoid XSS attacs
	$wp_cache_meta_object->blog_id=$blog_id;
	$wp_cache_meta_object->post = wp_cache_post_id();

	$response = wp_cache_get_response_headers();
	foreach ($known_headers as $key) {
		if(isset($response[$key])) {
			array_push($wp_cache_meta_object->headers, "$key: " . $response[$key]);
		}
	}
	/* Not used because it gives problems with some
	 * PHP installations
	if (!$response{'Content-Length'}) {
	// WP does not set content size
		$content_size = ob_get_length();
		@header("Content-Length: $content_size");
		array_push($wp_cache_meta_object->headers, "Content-Length: $content_size");
	}
	*/
	if (!isset( $response['Last-Modified'] )) {
		$value = gmdate('D, d M Y H:i:s') . ' GMT';
		/* Dont send this the first time */
		/* @header('Last-Modified: ' . $value); */
		array_push($wp_cache_meta_object->headers, "Last-Modified: $value");
	}
	if (!$response['Content-Type'] && !$response['Content-type']) {
		// On some systems, headers set by PHP can't be fetched from
		// the output buffer. This is a last ditch effort to set the
		// correct Content-Type header for feeds, if we didn't see
		// it in the response headers already. -- dougal
		if (is_feed()) {
			$type = get_query_var('feed');
			$type = str_replace('/','',$type);
			switch ($type) {
				case 'atom':
					$value = "application/atom+xml";
					break;
				case 'rdf':
					$value = "application/rdf+xml";
					break;
				case 'rss':
				case 'rss2':
				default:
					$value = "application/rss+xml";
			}
		} else { // not a feed
			$value = 'text/html';
		}
		$value .=  "; charset=\"" . get_option('blog_charset')  . "\"";

		@header("Content-Type: $value");
		array_push($wp_cache_meta_object->headers, "Content-Type: $value");
	}

	@ob_end_flush();
	flush(); //Ensure we send data to the client
	if ($new_cache) {
		$serial = serialize($wp_cache_meta_object);
		if( !wp_cache_writers_entry() )
			return false;
		$fr = @fopen($cache_path . 'meta/' . $meta_file, 'w');
		if( !$fr )
			@mkdir( $cache_path . 'meta' );
		$fr = fopen($cache_path . 'meta/' . $meta_file, 'w');
		fputs($fr, $serial);
		fclose($fr);
		wp_cache_writers_exit();
	}

	if( !isset( $wp_cache_gc ) )
		$wp_cache_gc = 1000;
	if( mt_rand( 0, $wp_cache_gc ) != 1 )
		return;

	// we delete expired files, using a wordpress cron event
	// since flush() does not guarantee hand-off to client - problem on Win32 and suPHP
	if(!wp_next_scheduled('wp_cache_gc')) wp_schedule_single_event(time() + 10 , 'wp_cache_gc');
}

function wp_cache_no_postid($id) {
	return wp_cache_post_change(wp_cache_post_id());
}

function wp_cache_get_postid_from_comment($comment_id) {
	global $super_cache_enabled;
	$comment = get_comment($comment_id, ARRAY_A);
	$postid = $comment['comment_post_ID'];
	// Do nothing if comment is not moderated
	// http://ocaoimh.ie/2006/12/05/caching-wordpress-with-wp-cache-in-a-spam-filled-world
	if( !preg_match('/wp-admin\//', $_SERVER['REQUEST_URI']) ) 
		if( $comment['comment_approved'] == 'spam' ) { // changed from 1 to "spam"
			return $post_id;
		} elseif( $comment['comment_approved'] == '0' ) {
			$super_cache_enabled = 0; // don't remove the super cache static file until comment is approved
		}
	// We must check it up again due to WP bugs calling two different actions
	// for delete, for example both wp_set_comment_status and delete_comment 
	// are called when deleting a comment
	if ($postid > 0) 
		return wp_cache_post_change($postid);
	else 
		return wp_cache_post_change(wp_cache_post_id());
}

function wp_cache_post_edit($post_id) {
	global $wp_cache_clear_on_post_edit, $cache_path;
	if( $wp_cache_clear_on_post_edit ) {
		prune_super_cache( $cache_path, true );
	} else {
		wp_cache_post_change( $post_id );
	}
}

function wp_cache_post_change($post_id) {
	global $file_prefix, $cache_path, $blog_id, $blogcacheid, $super_cache_enabled;
	static $last_processed = -1;

	if ($post_id == $last_processed) return $post_id;
	$last_processed = $post_id;
	if( !wp_cache_writers_entry() )
		return $post_id;

	$permalink = trailingslashit( str_replace( get_option( 'siteurl' ), '', post_permalink( $post_id ) ) );
	if( $super_cache_enabled ) {
		$siteurl = trailingslashit( strtolower( preg_replace( '/:.*$/', '', str_replace( 'http://', '', get_option( 'home' ) ) ) ) );
		// make sure the front page has a rebuild file
		prune_super_cache( $cache_path . 'supercache/' . $siteurl . 'index.html', true, true ); 
		prune_super_cache( $cache_path . 'supercache/' . $siteurl . 'index.html.gz', true, true );
		if( $post_id != 0 ) {
			$permalink = trailingslashit( str_replace( get_option( 'home' ), '', post_permalink( $post_id ) ) );
			$dir = $cache_path . 'supercache/' . $siteurl;
			prune_super_cache( $dir . $permalink, true, true );
			@rmdir( $dir . $permalink );
			prune_super_cache( $dir . 'page/', true );
		}
	}

	$meta = new CacheMeta;
	$matches = array();
	if ( ($handle = opendir( $cache_path . 'meta/' )) ) { 
		while ( false !== ($file = readdir($handle))) {
			if ( preg_match("/^({$file_prefix}{$blogcacheid}.*)\.meta/", $file, $matches) ) {
				$meta_pathname = $cache_path . 'meta/' . $file;
				$content_pathname = $cache_path . $matches[1] . ".html";
				$meta = unserialize(@file_get_contents($meta_pathname));
				if ($post_id > 0 && $meta) {
					if ($meta->blog_id == $blog_id  && (!$meta->post || $meta->post == $post_id) ) {
						@unlink($meta_pathname);
						@unlink($content_pathname);
					}
				} elseif ($meta->blog_id == $blog_id) {
					@unlink($meta_pathname);
					@unlink($content_pathname);
				}

			}
		}
		closedir($handle);
	}
	wp_cache_writers_exit();
	return $post_id;
}

function wp_cache_microtime_diff($a, $b) {
	list($a_dec, $a_sec) = explode(' ', $a);
	list($b_dec, $b_sec) = explode(' ', $b);
	return $b_sec - $a_sec + $b_dec - $a_dec;
}

function wp_cache_post_id() {
	global $posts, $comment_post_ID, $post_ID;
	// We try hard all options. More frequent first.
	if ($post_ID > 0 ) return $post_ID;
	if ($comment_post_ID > 0 )  return $comment_post_ID;
	if (is_single() || is_page()) return $posts[0]->ID;
	if (isset( $_GET[ 'p' ] ) && $_GET['p'] > 0) return $_GET['p'];
	if (isset( $_POST[ 'p' ] ) && $_POST['p'] > 0) return $_POST['p'];
	return 0;
}

function wp_cache_gc_cron() {
	global $file_prefix;
	wp_cache_phase2_clean_expired($file_prefix);
}

?>
