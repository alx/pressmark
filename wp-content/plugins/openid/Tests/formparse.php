<?php

$html = <<<HEREDOC
stuffbefore
<form action="http://nemu.local/~alan/wp/wp-comments-post.php" method="post" id="commentform">

<div class="personalinformation">
<p><input type="text" name="author" id="author" value="" size="22" tabindex="1" />
<label for="author"><small>Name </small></label></p>

<p><input type="text" name="captcha" id="captcha" value="cap" size="10" />
<label for="captcha"><small>Type the text in the picture</label></p>

<p><input type="text" name="email" id="email" value="" size="22" tabindex="2" />
<label for="email"><small>Mail (will not be published) </small></label></p>

<p><input name="url" id="url" value="" size="22" tabindex="3" />
<label for="url"><small>Website</small></label></p>
</div>

<!--<p><small><strong>XHTML:</strong> You can use these tags: &lt;a href=&quot;&quot; title=&quot;&quot;&gt; &lt;abbr title=&quot;&quot;&gt; &lt;acronym title=&quot;&quot;&gt; &lt;b&gt; &lt;blockquote cite=&quot;&quot;&gt; &lt;code&gt; &lt;em&gt; &lt;i&gt; &lt;strike&gt; &lt;strong&gt; </small></p>-->

<p><textarea name="comment" id="comment" cols="100%" rows="10" tabindex="4"></textarea></p>

<p><input name="submit" type="submit" id="submit" tabindex="5" value="Submit Comment" />
<input type="hidden" name="comment_post_ID" value="<?php echo $id; ?>" />
</p>

</form>
stuffafter
HEREDOC;


/* Plan:
 1. Find <form ... >, set aside everything before it.  (pre)
 2. Find </form>, set aside everything after it. (post)
 3. Working = <form .... </form>
 4. Find <input ... >, with name="author" / email / url using XML Parser
*/


$matches = array();
$foundform = preg_match( '|(.*)<form([^>]*)>(.*)</form>(.*)|ism', $html, $matches );
$form_pre = $matches[1];
$form_post = $matches[4];
$form_inner = $matches[2];

$work = $matches[3];

function shuffle_comment_form( $work ) {
	$block = array('address','blockquote','dsiv','dl','span',
		'fieldset','h1','h2','h3','h4','h5','h6',
		'p','ul','li', 'dd','dt');

	$fields = array( 'author','url','email' );

	$rinput = '(<input[^>]*?name="([^"]+)"[^>]*?>)';
	$rblock = '<(' . implode('|', $block) . ')( [^>]*)?>';
	$rs = '(.*?)';
	$rblockend = '</\\1>';
	
	$r = '%' . $rblock . $rs . $rinput . $rs . $rblockend . '%ism';
	
	$matches = array();
	$num = preg_match_all( $r, $work, $matches, PREG_OFFSET_CAPTURE );

	$chunks = array();
	foreach( $matches[5] as $k=>$v ) {
		if( in_array( strtolower($v[0]), $fields ) ) {
			$chunks[] = array( 'line' => $matches[0][$k][0],
								'startpos' => $matches[0][$k][1],
								'starttag' => strtolower( $matches[1][$k][0] ),
								'length' => strlen( $matches[0][$k][0] ) );
		}
	}
	// Grab starting position for re-insertion
	$insert_point = $chunks[0]['startpos'];
	$insert_tag = $chunks[0]['starttag'];
	
	// Create OpenID version of the Author line
	$author = $chunks[0]['line'];
	$author_name = trim(strip_tags($author));
	
	$openid = str_replace(  array('name="author"', "$author_name"),
		array('name="openid" class="commentform_openid"', 'Sign in with your OpenID'), $author );
		
	if( preg_match( '/id="[^"]+"/', $openid )) {
		$openid = preg_replace( '/id="[^"]+"/', 'id="commentform_openid"', $openid );
		$openid = preg_replace( '/for="[^"]+"/', 'for="commentform_openid"', $openid );
	} else {
		$openid = preg_replace( '/name="/', 'id="commentform_openid" name="', $openid );
	}


	// Remove the Anonymous chunks from the html source
	$blocklength = 0;
	$anonymous = '';
	$chunks = array_reverse($chunks);
	foreach( $chunks as $k=>$v ) {
		$html = substr_replace( $work, '', $v['startpos'], $v['length'] );
		$blocklength += $v['length'];
		$anonymous .= $v['line']."\n";
	}
	
	//$save_form_elements = substr( $html, $insert_point, $blocklength);

	switch ($insert_tag) {
		case 'li':
			$n = '<li><h4>OpenID</h4></li>' . $openid . '<li><h4>Anonymous</h4></li>' . $anonymous;
			break;
		default:
			$n = '<dl class="commentform_openid_list"><dt>OpenID</dt><dd>' . $openid . '</dd><dt>Anonymous</dt><dd>' . $anonymous . '</dd></dl>';
	}

	$work = substr_replace( $work, $n, $insert_point, 0 );
	return $work;

}


?><pre><?php

$work = shuffle_comment_form($work);

$final = "$form_pre<form$form_inner> $work </form>$form_post";
echo htmlentities($final);
?>