=== OpenID ===
Contributors: wnorris, factoryjoe
Tags: openid, authentication
Requires at least: 2.2
Tested up to: 2.6.2
Stable tag: 3.1

Allows WordPress to provide and consumer OpenIDs for authentication of users and comments.

== Description ==

**Upgrade Notes:** If you are upgrading to version 3.0 from a previous version,
it is extremely important that you backup your blog before doing so.  This
release includes database changes that, though they have been thoroughly
tested, may cause problems.  You will also need to deactivate and reactivate
the plugin after upgrading.

OpenID is an [open standard][] that allows users to authenticate to websites
without having to create a new password.  This plugin allows users to login to
their local WordPress account using an OpenID, as well as enabling commenters
to leave authenticated comments with OpenID.  Version 3.0 includes an OpenID
provider as well, enabling users to login to OpenID-enabled sites using their
own personal WordPress account. [XRDS-Simple][] is required for the OpenID
Provider.

Developer documention, which includes all of the public methods and hooks for
integrating with and extending the plugin, can be found [here][dev-doc].

[open standard]: http://openid.net/
[XRDS-Simple]: http://wordpress.org/extend/plugins/xrds-simple/
[dev-doc]: http://wiki.diso-project.org/WordPress-OpenID

== Installation ==

This plugin follows the [standard WordPress installation method][]:

1. Upload the `openid` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Configure the plugin through the 'OpenID' section of the 'Options' menu

[standard WordPress installation method]: http://codex.wordpress.org/Managing_Plugins#Installing_Plugins


== Frequently Asked Questions ==

= Why do I get blank screens when I activate the plugin? =

In some cases the plugin may have problems if not enough memory has been
allocated to PHP.  Try ensuring that the PHP memory_limit is at least 8MB
(limits of 64MB are not uncommon).

= Why don't `https` OpenIDs work? =

SSL certificate problems creep up when working with some OpenID providers
(namely MyOpenID).  This is typically due to an outdated CA cert bundle being
used by libcurl.  An explanation of the problem and a couple of solutions 
can be found [here][libcurl].

[libcurl]: http://lists.openidenabled.com/pipermail/dev/2007-August/000784.html

= How do I add an OpenID field to my comment form? =

The easiest way to display the fact that your blog accepts OpenIDs is to enable
the "Comment Form" option for the plugin.  This will allow the normal website
field to be used for OpenIDs as well.  If this doesn't display properly for
your particular theme or you simply prefer to have a separate OpenID field, you
can modify your comments.php template to include an "openid_identifier" text field as
part of your comment form.  For the default theme, this might look like:

	<p><input type="text" name="openid_identifier" id="openid_identifier" />
	<label for="openid_identifier"><small>OpenID URL</small></label></p>

The input element MUST have the name "openid\_identifier".  Additionally, using
"openid\_identifier" for the id causes the field to be styled with an OpenID logo.  To
remove this, you can override the stylesheet or simply change the element id.

= How do I get help if I have a problem? =

Please direct support questions to the "Plugins and Hacks" section of the
[WordPress.org Support Forum][].  Just make sure and include the tag 'openid'
so that I'll see your post.  Additionally, you can file a bug
report at <http://code.google.com/p/diso/issues/list>.  

[WordPress.org Support Forum]: http://wordpress.org/support/


== Screenshots ==

1. Commentors can use their OpenID when leaving a comment.
2. For users with wordpress accounts, their OpenID associations are managed through the admin panel.
3. Users can login with their OpenID in place of a traditional username and password.


== Changelog ==

= version 3.1 =
 - added hidden constant to set custom comments post page (OPENID_COMMENTS_POST_PAGE)
 - additional option to skip name and email check for OpenID comments
 - use preferred username (from SREG) if possible when creating new account
 - truncate long URLs when used as display_name for comments
 - numerous bug fixes, including bug with registration form

= version 3.0 =
 - includes OpenID Provider
 - supports OpenID delegation
 - add experimental support for Email Address to URL Transformation
 - many new hooks for extension and integration
 - major code refactoring

= version 2.2.2 =
 - fix bug with "unauthorized return_to URL" (only known problem with [openid.pl][])
 - fix bug with comments containing non-latin characters
 - respect CUSTOM_USER_META_TABLE constant if present (also added CUSTOM_OPENID_IDENTITY_TABLE constant)
 - add experimental support for Identity in the Browser

= version 2.2.1 =
 - fixed EAUT handling code
 - fixed bug that broke comments containing double quotes (")

= version 2.2.0 =
 - use POST replay for comments (fixes compatibility with other comment plugins)
 - only build openid object when needed (much better memory usage)
 - support for Email Address to URL Transformation (see eaut.org)
 - fixed bug when using suhosin (hardened php)
 - use hooks for gathering user data (more extensible)
 - fixed openid spoofing vulnerability (http://plugins.trac.wordpress.org/ticket/702)
 - lots code refactoring and UI cleanup

= version 2.1.9 =
 - fix javascript loading issues
 - fix various bugs when creating new account with OpenID
 - fix error message, and add new warning prompt when removing last OpenID for account

= version 2.1.8 =
 - fix UI issue with wp-login.php page in WP2.5
 - fix bug printing supported curl protocols (http://wordpress.org/support/topic/159062)
 - fix jquery bug while adding category in  WP2.5  (http://wordpress.org/support/topic/164305)

= version 2.1.7 =
 - remove php5 dependency bug... AGAIN!
 - also remove some other custom changes to php-openid I forgot were in there.  This may actually re-introduce some edge-case
   bugs, but I'd rather expose them so that we can get the appropriate patches pushed upstream if they really are necessary.

= version 2.1.6 =
 - update php-openid library to latest.  Now properly supports Yahoo's OpenID provider.

= version 2.1.5 =
 - add support for wordpress v2.5

= version 2.1.4 =
 - fix php5 dependency bug
 - improve jQuery code to reduce problems with other js libraries

= version 2.1.3 =
 - address security bug mentioned [here](http://www.gnucitizen.org/blog/hijacking-openid-enabled-accounts).  Props: Sam Alexander

= version 2.1.2 =
 - minor typo in profile data code

= version 2.1.1 =
 - minor bug where profile data is being overwritten

= version 2.1 =
 - added FAQ items for plugin updater and adding an OpenID field to a comment form
 - better tracking of which users have OpenIDs linked to their local WP account
 - better automatic username generation
 - fixed bug where non-OpenID websites had problems (bug [729])
 - upgrade to version 2.0 of JanRain OpenID library
 - admin option to rebuild tables

= version 2.0 =
 - simplified admin interface by using reasonable defaults.  Default behaviors include:
  - "unobtrusive mode"
  - always add openid to wp-login.php
  - always use WP option 'home' for the trust root
 - new features
  - hook for trust engine, with very simple implementation included
  - supports OpenID 2.0 (draft 12) as well as OpenID 1.1 and SReg 1.0
 - normal collection of bug fixes

= version 1.0.1 =
 - added wordpress.org style readme.txt
 
= version 1.0 (also known as r13) =

Full SVN logs are available at <http://dev.wp-plugins.org/log/openid/>.

[729]: http://dev.wp-plugins.org/ticket/729
[openid.pl]: http://openid.pl/
