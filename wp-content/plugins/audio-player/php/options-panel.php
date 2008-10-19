<div class="wrap">
	<h2>Audio Player</h2>

	<p>
		<?php printf(__('Settings for the Audio Player plugin. Visit <a href="%s">1 Pixel Out</a> for usage information and project news.', $this->textDomain), $this->docURL) ?>
	</p>
	<p><?php _e('Current version', $this->textDomain) ?>: <strong><?php echo $this->version ?></strong></p>

	<form method="post" id="ap_option-form">
	<?php
	if ( function_exists('wp_nonce_field') )
		wp_nonce_field('audio-player-action');
	?>
	<ul id="ap_tabs">
		<li id="ap_tab-general"><a href="#ap_panel-general"><?php _e('General', $this->textDomain) ?></a></li>
		<li id="ap_tab-colour"><a href="#ap_panel-colour"><?php _e('Display', $this->textDomain) ?></a></li>
		<li id="ap_tab-feed"><a href="#ap_panel-feed"><?php _e('Feed options', $this->textDomain) ?></a></li>
		<li id="ap_tab-podcasting"><a href="#ap_panel-podcasting"><?php _e('Podcasting', $this->textDomain) ?></a></li>
		<li id="ap_tab-advanced" class="last"><a href="#ap_panel-advanced"><?php _e('Advanced', $this->textDomain) ?></a></li>
	</ul>
	
	<div class="ap_panel" id="ap_panel-general">
		<h3><?php _e('How do you want to use the audio player?', $this->textDomain) ?></h3>
		<p><?php _e('This set of options allows you to customize when your audio players appear.', $this->textDomain) ?></p>
		<ul class="ap_optionlist">
			<li>
				<label for="ap_behaviour-default">
				<input type="checkbox" name="ap_behaviour[]" id="ap_behaviour-default" value="default"<?php if(in_array("default", $this->options["behaviour"])) echo ' checked="checked"'; ?> />

				<strong><?php _e('Replace [audio] syntax', $this->textDomain) ?></strong></label><br />
				<?php _e('This is the default behaviour and is the only way to apply options to a player instance. Use this option if you want to have more than one audio player per posting.', $this->textDomain) ?>
			</li>
			<li>
				<label for="ap_behaviour-links">
				<input type="checkbox" name="ap_behaviour[]" id="ap_behaviour-links" value="links"<?php if(in_array("links", $this->options["behaviour"])) echo ' checked="checked"'; ?> />
				<strong><?php _e('Replace all links to mp3 files', $this->textDomain) ?></strong></label><br />
				<?php _e('When selected, this option will replace all your links to mp3 files with a player instance. Be aware that this could produce odd results when links are in the middle of paragraphs.', $this->textDomain) ?>
			</li>
			<li>
				<label for="ap_behaviour-comments">
				<input type="checkbox" name="ap_behaviour[]" id="ap_behaviour-comments" value="comments"<?php if(in_array("comments", $this->options["behaviour"])) echo ' checked="checked"'; ?> />
				<strong><?php _e('Enable in comments', $this->textDomain) ?></strong></label><br />
				<?php _e('When selected, Audio Player will be enabled for all comments on your blog.', $this->textDomain) ?>
			</li>
			<li>
				<label for="ap_behaviour-enclosure">
				<input type="checkbox" name="ap_behaviour[]" id="ap_behaviour-enclosure" value="enclosure"<?php if(in_array("enclosure", $this->options["behaviour"])) echo ' checked="checked"'; ?> />
				<strong><?php _e('Enclosure integration', $this->textDomain) ?></strong></label><br />
				<?php _e('Ideal for podcasting. If you set your enclosures manually, this option will automatically insert a player at the end of posts with an mp3 enclosure. The player will appear at the bottom of your posting.', $this->textDomain) ?>
			</li>
			<li>
				<label for="ap_enclosure-at-top">
				<input type="checkbox" name="ap_enclosuresAtTop" id="ap_enclosure-at-top" value="true"<?php if(!in_array("enclosure", $this->options["behaviour"])) echo 'disabled="disabled"'; ?><?php if($this->options["enclosuresAtTop"]) echo ' checked="checked"'; ?> />
				<strong><?php _e('Move enclosures to the beginning of posts', $this->textDomain) ?></strong></label><br />
				<?php _e('When selected, players will be inserted at the beginning of the post when the enclosure integration option is selected.', $this->textDomain) ?>
			</li>
		</ul>
		
		<h3><?php _e('Default audio folder location', $this->textDomain) ?></h3>
		<p>
			<select name="ap_audiowebpath_iscustom" id="ap_audiowebpath_iscustom">
				<option value="false"<?php if (!$this->isCustomAudioRoot) echo(' selected="selected"') ?>><?php echo get_settings('siteurl') ?> </option>
				<option value="true"<?php if ($this->isCustomAudioRoot) echo(' selected="selected"') ?>>Custom</option>
			</select>
			<input type="text" id="ap_audiowebpath" name="ap_audiowebpath" size="40" value="<?php echo $this->options["audioFolder"] ?>" />
		</p>

		<div id="ap_audiofolder-check" class="submit">
			<input type="button" id="ap_check-button" class="submit" value="<?php _e('Verify', $this->textDomain) ?>" />
			<span id="ap_info-message"><?php _e('Click this button to verify that the audio folder is correctly configured', $this->textDomain) ?></span>
			<span id="ap_disabled-message"><?php _e('Custom audio folder locations cannot be verified', $this->textDomain) ?></span>
			<span id="ap_checking-message"><?php _e('Checking...', $this->textDomain) ?></span>
			<span id="ap_success-message"><?php _e('Audio folder location verified', $this->textDomain) ?></span>
			<span id="ap_failure-message"><?php _e('Audio folder location not found. Please check that the following folder exists on your server:', $this->textDomain) ?> <strong>&nbsp;</strong></span>
		</div>

		<p>
			<?php _e('This is the default location for your audio files. When you use the [audio] syntax and don\'t provide an absolute URL for the mp3 file (the full URL including "http://") Audio Player will automatically look for the file in this location. You can set this to a folder located inside your blog folder structure or, alternatively, if you wish to store your audio files outside your blog (maybe even on a different server), choose "Custom" from the drop down and enter the absolute URL to that location.', $this->textDomain); ?>
		</p>
	</div>
	
	<div class="ap_panel" id="ap_panel-colour">
		<h3><?php _e('Player width', $this->textDomain) ?></h3>
		<p>
			<label for="ap_player_width"><?php _e('Player width', $this->textDomain) ?></label>
			<input type="text" id="ap_player_width" name="ap_player_width" value="<?php echo $this->options["playerWidth"] ?>" size="10" />
			<?php _e('You can enter a value in pixels (e.g. 200) or as a percentage (e.g. 100%)', $this->textDomain) ?>
		</p>
		<h3><?php _e('Colour scheme', $this->textDomain) ?></h3>
		<div id="ap_colorscheme">
			<div id="ap_colorselector">
				<input type="hidden" name="ap_bgcolor" id="ap_bgcolor" value="#<?php echo( $this->options["colorScheme"]["bg"] ) ?>" />
				<input type="hidden" name="ap_leftbgcolor" id="ap_leftbgcolor" value="#<?php echo( $this->options["colorScheme"]["leftbg"] ) ?>" />
				<input type="hidden" name="ap_rightbgcolor" id="ap_rightbgcolor" value="#<?php echo( $this->options["colorScheme"]["rightbg"] ) ?>" />
				<input type="hidden" name="ap_rightbghovercolor" id="ap_rightbghovercolor" value="#<?php echo( $this->options["colorScheme"]["rightbghover"] ) ?>" />
				<input type="hidden" name="ap_lefticoncolor" id="ap_lefticoncolor" value="#<?php echo( $this->options["colorScheme"]["lefticon"] ) ?>" />
				<input type="hidden" name="ap_righticoncolor" id="ap_righticoncolor" value="#<?php echo( $this->options["colorScheme"]["righticon"] ) ?>" />
				<input type="hidden" name="ap_righticonhovercolor" id="ap_righticonhovercolor" value="#<?php echo( $this->options["colorScheme"]["righticonhover"] ) ?>" />
				<input type="hidden" name="ap_skipcolor" id="ap_skipcolor" value="#<?php echo( $this->options["colorScheme"]["skip"] ) ?>" />
				<input type="hidden" name="ap_textcolor" id="ap_textcolor" value="#<?php echo( $this->options["colorScheme"]["text"] ) ?>" />
				<input type="hidden" name="ap_loadercolor" id="ap_loadercolor" value="#<?php echo( $this->options["colorScheme"]["loader"] ) ?>" />
				<input type="hidden" name="ap_trackcolor" id="ap_trackcolor" value="#<?php echo( $this->options["colorScheme"]["track"] ) ?>" />
				<input type="hidden" name="ap_bordercolor" id="ap_bordercolor" value="#<?php echo( $this->options["colorScheme"]["border"] ) ?>" />
				<input type="hidden" name="ap_trackercolor" id="ap_trackercolor" value="#<?php echo( $this->options["colorScheme"]["tracker"] ) ?>" />
				<input type="hidden" name="ap_voltrackcolor" id="ap_voltrackcolor" value="#<?php echo( $this->options["colorScheme"]["voltrack"] ) ?>" />
				<input type="hidden" name="ap_volslidercolor" id="ap_volslidercolor" value="#<?php echo( $this->options["colorScheme"]["volslider"] ) ?>" />
				<select id="ap_fieldselector">
				  <option value="bg" selected="selected"><?php _e('Background', $this->textDomain) ?></option>
				  <option value="leftbg"><?php _e('Left background', $this->textDomain) ?></option>
				  <option value="lefticon"><?php _e('Left icon', $this->textDomain) ?></option>
				  <option value="voltrack"><?php _e('Volume control track', $this->textDomain) ?></option>
				  <option value="volslider"><?php _e('Volume control slider', $this->textDomain) ?></option>
				  <option value="rightbg"><?php _e('Right background', $this->textDomain) ?></option>
				  <option value="rightbghover"><?php _e('Right background (hover)', $this->textDomain) ?></option>
				  <option value="righticon"><?php _e('Right icon', $this->textDomain) ?></option>
				  <option value="righticonhover"><?php _e('Right icon (hover)', $this->textDomain) ?></option>
				  <option value="text"><?php _e('Text', $this->textDomain) ?></option>
				  <option value="tracker"><?php _e('Progress bar', $this->textDomain) ?></option>
				  <option value="track"><?php _e('Progress bar track', $this->textDomain) ?></option>
				  <option value="border"><?php _e('Progress bar border', $this->textDomain) ?></option>
				  <option value="loader"><?php _e('Loading bar', $this->textDomain) ?></option>
				  <option value="skip"><?php _e('Next/Previous buttons', $this->textDomain) ?></option>
				</select>
				<input name="ap_colorvalue" type="text" id="ap_colorvalue" size="15" maxlength="7" />
				<span id="ap_colorsample"></span>
				<span id="ap_picker-btn"><?php _e('Pick', $this->textDomain) ?></span>
				<?php if (count($this->getThemeColors())) { ?>
				<span id="ap_themecolor-btn"><?php _e('From your theme', $this->textDomain) ?></span>
				<div id="ap_themecolor">
					<span><?php _e('Theme colors', $this->textDomain) ?></span>
					<ul>
						<?php foreach($this->getThemeColors() as $themeColor) { ?>
						<li style="background:#<?php echo $themeColor ?>" title="#<?php echo $themeColor ?>">#<?php echo $themeColor ?></li>
						<?php } ?>
					</ul>
				</div>
				<?php } ?>
			</div>
			<div id="ap_audioplayer-wrapper"<?php if (!$this->options["colorScheme"]["transparentpagebg"]) echo ' style="background-color:#' . $this->options["colorScheme"]["pagebg"] . '"' ?>>
				<div id="ap_demoplayer">
					Audio Player
				</div>
			</div>
			<script type="text/javascript">
			AudioPlayer.embed("ap_demoplayer", {demomode:"yes"});
			</script>
		</div>
		
		<p style="clear:both">
			<?php _e('Here, you can set the page background of the player. In most cases, simply select "transparent" and it will match the background of your page. In some rare cases, the player will stop working in Firefox if you use the transparent option. If this happens, untick the transparent box and enter the color of your page background in the box below (in the vast majority of cases, it will be white: #FFFFFF).', $this->textDomain) ?>
		</p>
		<p>
			<label for="ap_pagebgcolor"><strong><?php _e('Page background color', $this->textDomain) ?>:</strong></label>
			<input type="text" id="ap_pagebgcolor" name="ap_pagebgcolor" maxlength="7" size="20" value="#<?php echo $this->options["colorScheme"]["pagebg"]; ?>"<?php if( $this->options["colorScheme"]["transparentpagebg"] ) echo ' disabled="disabled" style="color:#999999"'; ?> />
			<label for="ap_transparentpagebg">
				<input type="checkbox" name="ap_transparentpagebg" id="ap_transparentpagebg" value="true"<?php if( $this->options["colorScheme"]["transparentpagebg"] ) echo ' checked="checked"'; ?> />
				<?php _e('Transparent', $this->textDomain) ?>
			</label>
		</p>
		
		<p class="submit" id="ap_reset-color">
			<input type="hidden" name="AudioPlayerReset" id="ap_reset" value="0" />
			<input type="button" class="submit" id="ap_resetcolor" value="<?php _e('Reset colour scheme', $this->textDomain) ?>" />
		</p>
		
		<h3><?php _e('Options', $this->textDomain) ?></h3>
		<ul class="ap_optionlist">
			<li>
				<label for="ap_disableAnimation">
				<input type="checkbox" name="ap_disableAnimation" id="ap_disableAnimation" value="true"<?php if(!$this->options["enableAnimation"]) echo ' checked="checked"'; ?> />
				<strong><?php _e('Disable animation', $this->textDomain) ?></strong></label><br />
				<?php _e('If you don\'t like the open/close animation, you can disable it here.', $this->textDomain) ?>
			</li>
			<li>
				<label for="ap_showRemaining">
				<input type="checkbox" name="ap_showRemaining" id="ap_showRemaining" value="true"<?php if($this->options["showRemaining"]) echo ' checked="checked"'; ?> />
				<strong><?php _e('Show remaining time', $this->textDomain) ?></strong></label><br />
				<?php _e('This will make the time display count down rather than up.', $this->textDomain) ?>
			</li>
			<li>
				<label for="ap_disableTrackInformation">
				<input type="checkbox" name="ap_disableTrackInformation" id="ap_disableTrackInformation" value="true"<?php if($this->options["noInfo"]) echo ' checked="checked"'; ?> />
				<strong><?php _e('Disable track information', $this->textDomain) ?></strong></label><br />
				<?php _e('Select this if you wish to disable track information display (the player won\'t show titles or artist names even if they are available.)', $this->textDomain) ?>
			</li>
			<li>
				<label for="ap_rtlMode">
				<input type="checkbox" name="ap_rtlMode" id="ap_rtlMode" value="true"<?php if($this->options["rtl"]) echo ' checked="checked"'; ?> />
				<strong><?php _e('Switch to RTL layout', $this->textDomain) ?></strong></label><br />
				<?php _e('Select this to switch the player layout to RTL mode (right to left) for Arabic and Hebrew language blogs.', $this->textDomain) ?>
			</li>
		</ul>
	</div>
	
	<div class="ap_panel" id="ap_panel-feed">
		<h3><?php _e('Feed options', $this->textDomain) ?></h3>
		<p>
			<?php _e('The following options determine what is included in your feeds. The plugin doesn\'t place a player instance in the feed. Instead, you can choose what the plugin inserts. You have three choices:', $this->textDomain) ?>
		</p>
		<ul>
			<li><strong><?php _e('Download link', $this->textDomain) ?></strong>: <?php _e('Choose this if you are OK with subscribers downloading the file.', $this->textDomain) ?></li>
			<li><strong><?php _e('Nothing', $this->textDomain) ?></strong>: <?php _e('Choose this if you feel that your feed shouldn\'t contain any reference to the audio file.', $this->textDomain) ?></li>
			<li><strong><?php _e('Custom', $this->textDomain) ?></strong>: <?php _e('Choose this to use your own alternative content for all player instances. You can use this option to tell subscribers that they can listen to the audio file if they read the post on your blog.', $this->textDomain) ?></li>
		</ul>
		<p>
			<label for="ap_rssalternate"><?php _e('Alternate content for  feeds', $this->textDomain) ?>:</label>
			<select id="ap_rssalternate" name="ap_rssalternate">
				<option value="download"<?php if( $this->options["rssAlternate"] == 'download' ) echo( 'selected="selected"') ?>><?php _e('Download link', $this->textDomain) ?></option>
				<option value="nothing"<?php if( $this->options["rssAlternate"] == 'nothing' ) echo( 'selected="selected"') ?>><?php _e('Nothing', $this->textDomain) ?></option>
				<option value="custom"<?php if( $this->options["rssAlternate"] == 'custom' ) echo( 'selected="selected"') ?>><?php _e('Custom', $this->textDomain) ?></option>
			</select>
		</p>
		<p>
			<label for="ap_rsscustomalternate"><?php _e('Custom  alternate content', $this->textDomain) ?>:</label>
			<input type="text" id="ap_rsscustomalternate" name="ap_rsscustomalternate" size="60" value="<?php echo( $this->options["rssCustomAlternate"] ) ?>" />
		</p>
	</div>
	
	<div class="ap_panel" id="ap_panel-podcasting">
		<h3><?php _e('Pre and Post appended audio clips', $this->textDomain) ?></h3>
		<p>
			<?php _e('You may wish to pre-append or post-append audio clips into your players. The pre-appended audio will be played before the main audio, and the post-appended will come after. A typical podcasting use-case for this feature is adding a sponsorship message or simple instructions that help casual listeners become subscribers. <strong>This will apply to all audio players on your site</strong>. Your chosen audio clips should be substantially shorter than your main feature.', $this->textDomain) ?>
		</p>
		<p>
			<label for="ap_audioprefixwebpath"><?php _e('Pre-appended audio clip URL', $this->textDomain) ?>:</label>
			<input type="text" id="ap_audioprefixwebpath" name="ap_audioprefixwebpath" size="60" value="<?php echo $this->options["prefixClip"]; ?>" /><br />
			<em><?php _e('Leave this value blank for no pre-appended audio', $this->textDomain) ?></em>
		</p>
		<p>
			<label for="ap_audiopostfixwebpath"><?php _e('Post-appended audio clip URL', $this->textDomain) ?>:</label>
			<input type="text" id="ap_audiopostfixwebpath" name="ap_audiopostfixwebpath" size="60" value="<?php echo $this->options["postfixClip"]; ?>" /><br />
			<em><?php _e('Leave this value blank for no post-appended audio', $this->textDomain) ?></em>
		</p>
	</div>
	
	<div class="ap_panel" id="ap_panel-advanced">
		<h3><?php _e('Alternate content for excerpts', $this->textDomain) ?></h3>
		<p>
			<?php _e('WordPress automatically creates excerpts (summaries) for your posts. These are used by some themes to show on archive pages instead of the full post. By default, WordPress strips all HTML from these excerpts. Here you can choose what Audio Player inserts in excerpts in place of the player.', $this->textDomain) ?>
		</p>
		<p>
			<label for="ap_excerptalternate"><?php _e('Alternate content for excerpts', $this->textDomain) ?>:</label>
			<input type="text" id="ap_excerptalternate" name="ap_excerptalternate" size="60" value="<?php echo( $this->options["excerptAlternate"] ) ?>" />
		</p>

		<h3><?php _e('Initial volume', $this->textDomain) ?></h3>
		<p>
			<?php _e('This is the volume at which the player defaults to (0 is off, 100 is full volume)', $this->textDomain) ?>
		</p>
		<p>
			<label for="ap_volume"><?php _e('Initial volume', $this->textDomain) ?></label>
			<input type="text" id="ap_volume" name="ap_initial_volume" value="<?php echo $this->options["initialVolume"]; ?>" size="5" />
		</p>

		<h3><?php _e('Buffer time', $this->textDomain) ?></h3>
		<p>
			<?php _e('If you think your target audience is likely to have a slow internet connection, you can increase the player\'s buffering time (for standard broadband connections, 5 seconds is enough)', $this->textDomain) ?>
		</p>
		<p>
			<label for="ap_buffertime"><?php _e('Buffer time (in seconds)', $this->textDomain) ?></label>
			<input type="text" id="ap_buffertime" name="ap_buffertime" value="<?php echo $this->options["bufferTime"]; ?>" size="5" />
		</p>

		<h3><?php _e('Check for policy file', $this->textDomain) ?></h3>
		<p>
			<?php _e('Enable this to tell Audio Player to check for a policy file on the server. This allows Flash to read ID3 tags on remote servers. Only enable this if all your mp3 files are located on a server with a policy file.', $this->textDomain) ?>
		</p>
		<ul class="ap_optionlist">
			<li>
				<label for="ap_checkPolicy">
				<input type="checkbox" name="ap_checkPolicy" id="ap_checkPolicy" value="true"<?php if ($this->options["checkPolicy"]) echo ' checked="checked"'; ?> />
				<strong><?php _e('Check for policy file', $this->textDomain) ?></strong></label>
			</li>
		</ul>
		
		<h3><?php _e('Encoding', $this->textDomain) ?></h3>
		<p>
			<?php _e('Enable this to encode the URLs to your mp3 files. This is the only protection possible against people downloading the mp3 file to their computers.', $this->textDomain) ?>
		</p>
		<ul class="ap_optionlist">
			<li>
				<label for="ap_encodeSource">
				<input type="checkbox" name="ap_encodeSource" id="ap_encodeSource" value="true"<?php if ($this->options["encodeSource"]) echo ' checked="checked"'; ?> />
				<strong><?php _e('Encode mp3 URLs', $this->textDomain) ?></strong></label>
			</li>
		</ul>
	</div>

	<p class="submit">
		<input name="AudioPlayerSubmit" value="<?php _e('Save Changes') ?>" type="submit" />
	</p>
	</form>
</div>