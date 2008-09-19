<?php
if( 'comments.php' == basename($_SERVER['SCRIPT_FILENAME'] ) )
	die( 'Please do not load this page directly. Thanks!' );

if( !empty( $post->post_password ) ) { // if there's a password
	if( $_COOKIE['wp-postpass_' . COOKIEHASH] != $post->post_password ) { 
?>

<p class="nocomments">This post is password protected. Enter the password to view comments.</p>

<?php
		return;
	} // if cookie
} // if post_password

if( $comments ) {
	echo "<h3>Comments</h3>\n";
	echo "<ul id=\"comments\" class=\"commentlist\">\n";

	foreach( $comments as $comment ) {
?>

<li id="comment-<?php comment_ID( ); ?>">
	<?php echo prologue_get_avatar( $comment->user_id, $comment->comment_author_email, 32 ); ?>
	<h4>
		<?php comment_author_link( ); ?>
		<span class="meta"><?php comment_time( ); ?> on <?php comment_date( ); ?> | <a href="#comment-<?php comment_ID( ); ?>">#</a><?php edit_comment_link( __( 'e' ), '&nbsp;|&nbsp;',''); ?></span>
	</h4>
	<?php comment_text( ); ?>
</li>

<?php
	} // foreach comments

	echo "</ul>\n";
} // if comments

if( 'open' == $post->comment_status ) {
?>

<h3>Leave a Comment</h3>

<?php
if ( get_option('comment_registration') && !$user_ID ) {
?>

<p>You must be <a href="<?php echo get_option('siteurl'); ?>/wp-login.php?redirect_to=<?php the_permalink(); ?>" title="Log in">logged in</a> to post a comment.</p>

<?php
} // if option comment_registration and not user_ID
else {
?>

<form id="commentform" action="<?php echo get_option( 'siteurl' ); ?>/wp-comments-post.php" method="post">

<div class="form"><textarea id="comment" name="comment" cols ="45" rows="8" tabindex="1"></textarea></div>

<?php 
if( $user_ID ) { 
?>

<p>Logged in as <a href="<?php echo get_option( 'siteurl' ); ?>/wp-admin/profile.php"><?php echo $user_identity; ?></a>.  <a href="<?php echo get_option( 'siteurl' ); ?>/wp-login.php?action=logout" title="Log out">Log out &raquo;</a></p>

<?php 
} // if user_ID 
else { 
?>

<table>
	<tr>
		<td>

<label for="author">Name <em>(required)</em></label>
<div class="form"><input id="author" name="author" type="text" value="<?php echo $comment_author; ?>" tabindex="2" /></div>

		</td><td>

<label for="email">Email <em>(required)</em></label>
<div class="form"><input id="email" name="email" type="text" value="<?php echo $comment_author_email; ?>" tabindex="3" /></div>

		</td><td class="last-child">

<label for="url">Web Site</label>
<div class="form"><input id="url" name="url" type="text" value="<?php echo $comment_author_url; ?>" tabindex="4" /></div>

		</td>
	</tr>
</table>
<?php } // else user_ID ?>

<div><input id="submit" name="submit" type="submit" value="Post Comment &raquo;" tabindex="5" /><input type="hidden" name="comment_post_ID" value="<?php echo $id; ?>" /></div>

</form>
<?php
} // else option comment_registration and not user_ID
} // if open comment_status
