=== Subscribe2 ===
Contributors: MattyRob, Skippy, RavanH
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&amp;hosted_button_id=2387904
Tags: posts, subscription, email, subscribe, notify, notification
Requires at least: 2.0.x
Tested up to: 2.9
Stable tag: 5.2

Sends a list of subscribers an email notification when new posts are published to your blog

== Description ==

Subscribe2 provides a comprehensive subscription management and email notification system for WordPress blogs that sends email notifications to a list of subscribers when you publish new content to your blog.

Email Notifications can be sent on a per-post basis or periodically in a Digest email. Additionally, certain categories can be excluded from inclusion in the notification and post can be excluded on an individual basis by setting a custom field of 's2mail' to 'no'. The format of the email can also be customised for per-post notifications, subscribe2 can generate emails for each of the following formats:

* plaintext excerpt
* plaintext full post
* HTML full post

The plugin also handles subscription requests allowing users to publically subscribe by submitting their email address in an easy to use form or to register with your blog which enables greater flexibility over the email content for per-post notifications for the subscriber. Admins are given control over the presentation of the email notifications, can bulk manage subscriptions for users and manually send email notices to subscribers.

Subscribe2 supports two classes of subscribers: the general public, and registered users of the blog.  The general public may subscribe and unsubscribe.  They will receive a limited email notification when new post is made or periodically (unless that post is assigned to one of the excluded categories you defined).  The general public will receive a plaintext email with an excerpt of the post: either the excerpt you created when making the post, the portion of text before a <!--more--> tag (if present), or the first 55 words of the post.

Registered users of the blog can elect to receive email notifications for specific categories (unless Digest email are select, then it is an opt in or out decision).  The Users->Subscription menu item will allow them to select the delivery format (plaintext or HTML), amount of message (excerpt or full post), and the categories to which they want to subscribe.  You, the blog owner, have the option (Options->Subscribe2) to allow registered users to subscribe to your excluded categories or not.

== Installation ==

1. Copy the entire /subscribe2/ directory into your /wp-content/plugins/ directory.
2. Activate the plugin.
3. Click the "Settings" admin menu link, and select "Subscribe2".
4. Configure the options to taste, including the email template and any categories which should be excluded from notification
5. Click the "Tools" admin menu link, and select "Subscribers".
6. Manually subscribe people as you see fit.
7. Create a [WordPress Page](http://codex.wordpress.org/Pages) to display the subscription form.  When creating the page, you may click the "S2" button on the QuickBar to automatically insert the subscribe2 token.  Or, if you prefer, you may manually insert the subscribe2 token:
     <!--subscribe2-->
     ***Ensure the token is on a line by itself and that it has a blank line above and below.***
This token will automatically be replaced by dynamic subscription information and will display all forms and messages as necessary.
8. In the WordPress "Settings" area for Subscribe2 define the default page ID in the "Appearance" section to the ID of the WordPress page created in step 7.

== Frequently Asked Questions ==

= I want HTML email to be the default email type =

You need to pay for the [Subscribe2 HTML version](http://wpplugins.com/plugin/46/subscribe2-html). 

= Where can I get help? =
So, you've downloaded the plugin an it isn't doing what you expect. First you should read the included documentation. There is a ReadMe.txt file and a PDF startup guide installed with the plugin.

Next you could search in the [Subscribe2 Forum](http://getsatisfaction.com/subscribe2/), the [WordPress forums](http://wordpress.org/support/) or the [Subscribe2 blog FAQs](http://subscribe2.wordpress.com/category/faq/).

No joy there? Well, if you can't find an answer to your question you can get [paid support](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=2387904) by donating at least 20 UK pounds to the plugin author.

= Where can I get more information about the plugin features? =

A comprehensive guide that covers many, if not all, of the Subscribe2 features is available to purchase from the [iAssistant](http://the-iassistant.com/subscribe2-guide/)

= Some or all email notifications fail to send, why?  =
In the first instance ***check this with your hosting provider***, they have access to your server logs and will be able to tell you where and why emails are being blocked.

Some hosting providers place a restriction on the maximum number of recipients in any one email message.  Some hosts simply block all emails on certain low-cost hosting plans.

Subscribe2 provides a facility to work around this restriction by sending batches of emails.  To enable this feature, go to Settings->Subscribe2 and located the setting to restrict the number of recipients per email. If this is set to 30 then each outgoing email notification will only contain addresses for 30 recipients. 

Reminder: because subscribe2 places all recipients in BCC fields, and places the blog admin in the TO field, the blog admin will receive one email per batched delivery.  So if you have 90 subscribers, the blog admin should receive three post notification emails, one for each set of 30 BCC recipients.

Batches will occur for each group of message as described above.  A site on Dreamhost with many public and registered subscribers could conceivably generate a lot of email for your own inbox.

= My host has a limit of X emails per hour / day, can I limit the way Subscribe2 sends emails? =

This is more commonly called 'throttling' or 'choking'. PHP is a scripting language and while it is technically possible to throttle emails using script it is not very efficient. It is much better in terms of speed and server overhead (CPU cycles and RAM) to throttle using a server side application.

So, Subscribe2 does not and never will offer a throtting option. To solve the problem speak to your hosting provider about changing the restrictions, move to a less restriction hosting package or change hosting providers. Alternatively, there may be another WordPress plugin that can provide this functionality.

= Why is my admin address getting emails from Subscribe2? =

This plugin sends emails to your subscribers using the BCC (Blind Cardon Copy) header in email messages. Each email is sent TO: the admin address. There may be emails for a plain text excerpt notification, palin text full text and HTML format emails and additionally if the BCCLIMIT has been set due to hosting restrictions duplicate copies of these emails will be sent to the admin address.

= I can't find my subscribers / the options / something else =

Subscribe2 creates four (4) new admin menus in the back end of WordPress.

* Posts -> Mail Subscribers : Allows users with Publish capabilities to send emails to your current subscribers
* Tools -> Subscribers : Allows you to manually (un)subscribe users by email address, displays lists of currently subscribed users and allows you to bulk subscribe Registered Users
* Users -> Subscriptions : Allows the currently logged in user to manage their own subscriptions
* Settings -> Subscribe2 : Allows administrator level users to control many aspects of the plugins operation. It should be pretty self explanatory from the notes on the screen

= I'm confused, what are all the different types of subscriber? =

There are basically only 2 types of subscriber. Public Subscribers and Registered Subscribers.

Public subscribers have provided their email address for email notification of your new posts. When they enter there address on your site they are sent an email asking them to confirm their request and added to a list of Unconfirmed Subscribers. Once they complete their request by clicking on the link in their email they will become Confirmed Subscribers.

Registered Users have registered with your WorPress blog (provided you have enabled this in the core WordPress settings). Once registered they can choose to subscribe to specific categories and can also control the type of email they get by choosing plain text in an excerpt or full post format or a full post format with HTML.

= Can I put the form elsewhere? (header, footer, sidebar without the widget) =

The simple answer is yes you can but this is not supported so you need to figure out any problems that are caused by doing this on your own. Read <a href="http://subscribe2.wordpress.com/2006/09/19/sidebar-without-a-widget/">here</a> for the basic approach.

= I'd like to be able to collect more information from users when they subscribe, can I? =

Get them to register with your blog rather than using the Subscribe2 form. Additional fields would require much more intensive form processing, checking and entry into the database and since you won't then be able to easily use this information to persoanlise emails there really isn't any point in collecting this data.

= I can't find or insert the Subscribe2 token, help! =

If, for some reason the Subscribe2 button does not appear in your browser window try refreshing your browser and cache (Shift and Reload in Firefox). If this still fails then insert the token manually. In the Rich Text Editor (TinyMCE) make sure you switch to the "code" view and type in <!--subscribe2-->.

= Can I suggest you add X as a feature =

I'm open to suggestions but since the software is written by me for use on my site and then shared for free because others may find it useful as it comes don't expect your suggestion to be implemented unless I'll find it useful.

= I'd like to be able to send my subscribers notifications in HTML =

By default Public Subscribers get plain text emails and only Registered Subscribers can opt to receive email in HTML format. If you really want HTML for all you need to pay for the upgrade. $40US will get you the amended code and updates for 1 year.

= Which version should I be using, I'm on WordPress x.x.x? =
WordPress 2.3 and up require Subscribe2 from the 4.x stable branch. The most recent version is hosted via [Wordpress.org](http://wordpress.org/extend/plugins/subscribe2/).

WordPress 2.1.x and 2.2.x require Subscribe2 from the 3.x stable. The most recent version is [3.8](http://downloads.wordpress.org/plugin/subscribe2.3.8.zip).

WordPress 2.0.x requires Subscribe2 from the 2.x stable branch. The most recent version is [2.22](http://downloads.wordpress.org/plugin/subscribe2.2.22.zip).

= Why doesn't the form appear in my WordPress page? =
This is usually caused by one of two things. Firstly, it is possible that the form is there but because you haven't logged out of WordPress yourself you are seeing a message about managing your profile instead. Log out of WordPress and it will appear as the subscription form you are probably expecting.

Secondly, make sure that the token (<!--subscribe2-->) is correctly entered in your page with a blank line above and below. The easient way to do this is to deactivate the plugin, visit your WordPress page and view the source. The token should be contained in the source code of the page. If it is not there you either have not correctly entered the token or you have another plugin that is stripping the token from the page code.

== Screenshots ==

1. The Posts->Mail Subscribers admin page generated by the plugin.
2. The Tools->Subscribers admin page generated by the plugin.
3. The Users->Subscriptions admin page generated by the plugin.
4. The Options->Subscribe2 admin page generated by the plugin.

== Changelog ==

= Version 5.2 by Matthew Robinson =

* Added screen_icon() to each Subscribe2 admin page
* Improved addition of links to the Plugins admin page
* Improved XHTML validity of admin pages
* Improved display of category hierarchy display in the category form
* Added ability to use TAGS & CATS keywords in digest mails (position is static irrespective of keyword location)
* Use PHP variable for Subscribe2 folder to allow for easier renaming (if needed)
* Fixed a bug in TinyURL encoding introduced when links were click enabled
* Removed BurnURL from the plugin as it appears to be no longer operational
* Added urlencode to email addresses in Tools->Subscribers when editing other user preferences
* Restored several FAQs to the ReadMe file and the [WordPress.org FAQ section](http://wordpress.org/extend/plugins/subscribe2/faq/)

= Version 5.1 by Matthew Robinson =

* Add widget options to add custom text before and a after the dynamic Subscribe2 output - thank to Lee Willis
* Add protection against SQL injection attacks to the data entered into the Subscribe2 table
* Applied a fix for WP_User_Search on PHP4 installations
* Collect IP address of subscribers either at initial submission or at confirmation as required by some hosts to allow relaxation of email restrictions. IP details are in the database or available when the mouse pointer is held over the sign up date in Tools->Subscribers
* Fix for script execution time limit code for sites that have safe mode on or that have disable ini_set()
* Display category slugs when mouse pointer is held over the name in the category form area
* Fixed display of HTML entities in the subject of emails by using html_entity_decode()
* Fixed substitution of the MYNAME keyword in notification emails
* Added option to use BurnURL as an alternative to TinyURL to create shorter link URLs

= Version 5.0 by Matthew Robinson =

* Change version number to reflect change in the on going support of the plugin which is now a searchable forum or a paid service
* Added links to online Subscribe2 resources into the Options->Subsribe2 page
* Fixed Digest Time Dropdown to recall Cron Task scheduled time
* Fixed code using updated [Admin Menu Plugin](http://wordpress.org/extend/plugins/ozh-admin-drop-down-menu/) API
* Fixed foreach() error in widget rename function
* Improved layout of widget control boxes
* Improved identification of Administrator level users on blogs where usermeta table entries for user_level are low or missing
* Removed avatar plugin support on WPMU due to processing overhead
* Improved the layout of the digest email with respect to inclusion of unnecessary white space
* Extended maximum script runtime for servers not using PHP in safe mode

= Version 4.18 by Matthew Robinson =

* Option to sort digest posts in ascending or descending order
* Check that plugin options array exists before calling upgrade functions
* Improved reliability of the Preview function 
* Extended Preview function to digest emails
* Fixed a code glitch that stopped CATS and TAGS from working
* Fixed incorrect sender information is emails are set to come from Post Author
* Simplified email notification format options in Users->Subscriptions for per-post notifications
* Added Bulk Manage option to update email notification format
* Simplified the usermeta database entries from two format variables down to one
* Removed trailing spaces from some strings for improved i18n support
* Improved Bulk Subscribe and Unsubscribe routines to avoid database artefacts
* Moved Select/Deselect All check box to the top of the category list in admin pages
* Fixed small layout glitch in Manage->Subscribers screen
* Added ChangeLog section to ReadMe to support WordPress.org/extend development

= Version 4.17 by Matthew Robinson =

* Tested for compatibility with WordPress 2.8.x
* Added TAGS and CATS keyword for per-post notification templates
* Fixed bug where confirmation emails may have an empty sender field if notifications come from post author
* Fixed a bug in WPMU CSS
* Added option to exclude new categories by default
* Fixed a bug where emails may not be sent to subscribers when a user subscribes or unsubscribes
* Improved accessing of 'Admin' level users on blogs where user_level is set below 10
* Added ability to send email previews to currently logged in user from Settings->Subscribe2
* Styled admin menu form buttons to fit with WordPress theme
* Improved handling of confirmation sending to reduce errors

= Version 4.16 by Matthew Robinson =

* Correct minor layout issue in Settings->Subscribe2
* Allow users to define the div class name for the widget for styling purposes
* Select from a greater number of notification senders via dropdown list in Settings
* Improved efficiency of newly added WordPressMU code
* Added ability to manage across-blog subscriptions when using WordPressMU
* Fixed bug whereby Public Subscribers may not have got notification emails for posts if Private Posts were blocked
* Added ability to define email Subject contents in Settings->Subscribe2
* Sanity checks of email subject and body templates to ensure they are not empty
* Introduced s2_html_email and s2_plain_email filters to allow manipulation of email messages after construction
* Amended handling of database entries to simplify code and database needs
* Improved the layout of the Subscriber drop down menu
* Added bullet points to the TABLE of posts
* Ensure database remains clean when categories are deleted
* Added new option to manage how auto-subscribe handles excluded categories 

= Version 4.15 by Matthew Robinson =

* Fixed E_DEPRECATE warning caused by a variable being passed by reference to the ksort() function
* Fixed called to undefined function caused by typo
* Fixed a syntax error in the SQL code constructors affecting some users

= Version 4.14 by Matthew Robinson =

* Reordered some functions to improve grouping
* Stop s2mail custom variable being added if empty
* Localised 'Send Digest Notification at' string
* Add support for template tags in Post->Mail Subscribers emails
* Improve handling of translation files for more recent version of WordPress
* Implemented <label> tags in the admin pages so text descriptors are click enabled
* Improved subscription preferences for WordPress MU (Huge thanks to Benedikt Forchhammer)
* Added TINYLINK tag to allow TinyURL insertion in place of PERMALINK
* Improved layout of Tools->Subscriber page (Thanks to Anne-Marie Redpath)
* Enhancements to Subscription form layout (Thanks to Anne-Marie Redpath and Andy Steinmark)
* Sender details now uses current user information from Write->Mail Subscribers
* Introduced 's2_template_filter' to allow other plugins to amend the email template on-the-fly

= Version 4.13 by Matthew Robinson =

* Update weekly description
* Improve layout in the Subscribe2 form
* Fixed bug where registering users would not be subscribed to categories if using the checkbox on the registration page
* Improved buttonsnap function checking to reduce PHP notices
* Fixed typo when including CSS information in HTML emails
* Fix 'edit' links in the Tools->Subscribers page for blogs where the WordPress files are not in root
* Improved Tools->Subscribers page layout by hiding some buttons when they are not needed
* Fixed glitch in default options settings file
* Added option to include or exclude a link back to the blog theme CSS information within the HTML emails
* Improve per-post exceptions to sending emails by introducing a separate meta-box rather than relying on a custom field
* Fix for Gallery code in emails sending entire media library
* Updated screen shots

= Version 4.12 by Matthew Robinson =

* Added new option to remove Auto Subscribe option from Users->Your Subscriptions
* New POSTTIME token for digest email notifications
* Preserve mail after sending from Write->Mail Subscribers
* Introduced the Subscriber Counter Widget
* Use Rich Text Editor in Write->Mail Subscribers for the Paid HTML version
* Per User management in Admin
* Added support Uninstall API in WordPress 2.7
* Add support for 'Meta Widget' links
* Subscribers are sorted alphabetically before sending notifications
* Added ability to bulk unsubscribe a list of emails pasted into manage window
* Define number of subscribers in Manage window
* Added options for admin emails when public users subscribe or unsubscribe 
* Fixed bug that prevented sending of Reminder emails from Manage->Subscribers
* Amended confirmation code so that only one email is sent no matter how many times users click on (un)subscribe links

= Version 4.11 by Matthew Robinson =

* Works in WordPress 2.7-almost-beta!
* Fixed a bug in the mail() function that meant emails were not sent to recipients if the BCCLimit setting was greater than the total number of recipients for that mail type
* Ensured that the array of recipients was cast correctly in the reminder function
* Fixed display of html entities in the reminder emails
* Fixed a bug in the SQL statements for WordPress MU installations
* Corrected a typo in the message displayed on the WordPress registration page if subscriptions are automatic
* Several layout and inline comment changes

= Version 4.10 by Matthew Robinson =

* Fixed Registration form action from WordPress registrations
* Repositioned the button to send reminder emails
* Implemented WP_CONTENT_URL and WP_CONTENT_DIR
* Added filter for <a href="http://planetozh.com/blog/2008/08/admin-drop-down-menu-more-goodness-an-api/">Admin Drop Down Menu</a>
* Improve functioning of Process button in Manage admin pane
* Improved form compliance with XHTML
* Fixed bug with cron time being changed every time options are changed

= Version 4.9 by Matthew Robinson =

* Send email direct to recipient if BCC is set as 1
* Fix issue where WordPress shortcodes were not stripped out of emails
* Amended Manage page to resolve issues with IE and Opera not passing form information correctly
* Amended Manage page to allow for bulk management of public users
* Amended WordPress API usage for translation files to 2.6 compatible syntax
* Allow Editor and Author users to send emails from Write->Mail Subscribers
* Post collection for CRON function is more dynamic
* CRON function sanity to checks for post content before sending a notification
* Fixed get_register() function to allow for user_activation field
* Corrected typos in options.php
* Added a search box to the Manage->Subscribers window
* Strip tags and HTML entities from email subjects
* Improved message feedback in Write->Mail
* Added html_entity_decode to sender name fields
* Change Menu string for User menu to make it clearer whose preferences are being edited

= Version 4.8 by Matthew Robinson =

* Removed unnecessary return statement at end of publish() function
* Ensured posts in digest are listed in date order
* Improved compatibility with other plugins by only inserting JavaScript code into Subscribe2's own admin pages
* Added BCCLIMIT and S2PAGE to options page with AJAX editing
* Improved setting of CRON task base time
* Improved handling of option values in the options form
* Full XHTML compliance on all subscribe2 admin pages
* Decode HTML entity codes in notification email subjects
* Added Subscribe2 support for blogging via email
* Work-around fix implemented for WordPress the_title bug

= Version 4.7 by Matthew Robinson =

* Added admin control over default auto subscribe to new category option
* Improved Cron code to reduce the chance of duplicate emails
* Fixed a string that was missed from the translation files
* Improved time variable handling for cron functions, especially when UTC is different from both server time and blog time
* Completed code changes to allow WPMU compatibility
* Fixed some issues with the email headers now that Subscribe2 is using wp_mail() again

= Version 4.6 by Matthew Robinson =

* Fixed mis-reporting of server error when unsubscribing
* Fixed fatal errors involving buttonsnap library
* Improved database entry management for new subscribers
* Fixed issue where Subscribe2 grabbed the first page from the database even if it wasn't published
* Fixed upgrade reporting for Debug and Uninstaller plugins

= Version 4.5 by Matthew Robinson =

* Added Support for WordPress 2.5!
* Fixed HTML typo in admin submission message
* Fixed time display for cron jobs in Options->Subscribe2 when displayed on blogs using a time offset
* Added Debug plugin to the download package
* Improved descriptions of email template keywords in Options->Subscribe2
* Display subscribers in batches of 50 in Manage->Subscribers
* Fixed some XHTML validation errors
* Improved admin menu layout for compliance with WordPress 2.5
* Reverted to using wp_mail instead of mail to ensure proper header encoding
* Improved mail header formatting - thanks to Chris Carlson
* Add ability to skip email notification using a Custom Field (s2mail set as "no")
* Improved CSV export - thanks to Aaron Axelsen
* Added some compatibility for WPMU - thanks to Aaron Axelsen
* Added some error feedback to blog users if mails fail to send
* Moved Buttonsnap due to far to many fatal error complaints
* Added option to send notifications for Private posts
* Improved handling of notification for Password Protected Posts

= Version 4.4 by Matthew Robinson =

* Fixed non-substitution of TABLE keyword
* Fixed bug in usermeta update calls in unsubscribe_registered_users function
* Fixed bug in array handling in cron function that may have stopped emails sending
* Improved array handling in the Digest function
* Added an Un-installer to completely removed Subscribe2 from your WordPress install

= Version 4.3 by Matthew Robinson =

* Fixed bug where digest emails were sent to unsubscribed users - Thanks to Mr Papa
* Stripped slashes from Subject when sending from Write->Mail Subscribers - Thanks to James
* Ensured all admin pages created by Subscribe2 are valid XHTML 1.0 Transitional code
* Added default mail templates and other missed string values to i18n files to allow easier first time translation - thanks to Kjell
* Added option to set the hour for digest email notifications provided the schedule interval is one day or greater
* Moved option variable declaration to ensure better caching
* Fixed bug where cron tasks were not removed when options were reset
* Fixed email notifications for future dated posts
* Fixed QuickTag Icons and mouse-over floating text

= Version 4.2 by Matthew Robinson =

* Added translation capability to user feedback strings - thanks to Lise
* Corrected some other translation strings
* Fixed bug in notification emails to admins when new users subscribe
* Updated default options code

= Version 4.1 by Matthew Robinson =

* Fixed sending of notifications for Pages
* Fixed password protected post bug for Digest email notifications
* Fixed blank email headers if admin data is not at ID 1

= Version 4.0 by Matthew Robinson =

* Compatible with WordPress 2.3
* Widget Code now integrated into the main plugin and added as an option
* More Options for Email Notifications
* Category Lists fixed for WordPress 2.3 and now show empty categories

= Version 3.8 by Matthew Robinson =

* Fixed User Menu Settings when Digests enabled
* Changed Registered Subscribers to Registered Users in drop down to avoid confusion
* Minor code revisions for admin menu layout

= Version 3.7 by Matthew Robinson =

* Change from deprecated get_settings -> get_option
* Fix for confirmation links not working for custom installs
* Abandoned wp_mail due to core bugs
* Added Digest Table feature (untested)
* Added icons to manage window (Thanks to http://www.famfamfam.com/lab/icons/)
* Fixed Bulk Manage bug when using i18n files
* Fixed bug in cron emails if <!--more--> tag present

= Version 3.6 by Matthew Robinson =

* Fixed a typo in Content-Type mail headers
* Fixed Auto Register functions to obey Excluded Categories
* Added option to check WP-Register checkbox by default

= Version 3.5 by Matthew Robinson =

* Fixed a bug in the upgrade function that was messing up the options settings
* Updated the include.php file to preset recently introduced option settings

= Version 3.4 by Matthew Robinson =

* QuickTag button now displays a Marker! (HUGE thanks to Raven!)
* Fix for excluded categories in User Menu
* BCCLIMIT typo corrected in Mail function
* Call to translation files moved to avoid call to undefined function
* Options added to send mails for pages and password protected posts
* Option added to display subscription checkbox in WordPress Register screen
* Small typo and layout amendments

= Version 3.3 by Matthew Robinson =

* QuickTag button added! Works with Visual and Standard Editor. __Look in Code for token addition if using RTE.__
* Current Server time displayed for Cron tasks
* Fixed bug so Registered users now identified correctly
* Upgrade function called via WordPress hook to prevent calls to undefined functions 
* Fixed a bug affecting Registered Users not appearing in the drop down list
* Improved handling of the Subscribe2 option array

= Version 3.2 by Matthew Robinson =

* Fixed a bug affecting Registered Users not appearing in the drop down list
* Improved handling of the Subscribe2 option array

= Version 3.1 by Matthew Robinson =

* Amended code to use core cron functionality for future posts and digest notifications, no longer need WP-Cron
* Improved HTML code generated for admin pages
* Removed sending of emails for WordPress Pages
* Fixed display issues if S2PAGE is not defined

= Version 3.0 by Matthew Robinson =

* Updated for WordPress 2.1 Branch

= Version 2.22 by Matthew Robinson =

* Fixed User Menu Settings when Digests enabled
* Changed Registered Subscribers to Registered Users in drop down to avoid confusion
* Minor code revisions for admin menu layout

= Version 2.21 by Matthew Robinson =

* Change from deprecated get_settings -> get_option
* Fixed bug in cron emails if <!--more--> tag present

= Version 2.20 by Matthew Robinson =

* Fixed a typo in Content-Type mail headers
* Fixed Auto Register functions to obey Excluded Categories

= Version 2.19 by Matthew Robinson =

* Fixed a bug in the upgrade function that was messing up the options settings

= Version 2.18 by Matthew Robinson =

* BCCLIMIT typo corrected in Mail function
* Call to translation files moved to avoid call to undefined function
* Small typo and layout amendments

= Version 2.17 by Matthew Robinson =

* Current Server time displayed for Cron tasks
* Fixed bug so Registered users now identified correctly
* Upgrade function called via WordPress hook to prevent calls to undefined functions 

= Version 2.16 by Matthew Robinson =

* Fixed a bug affecting Registered Users not appearing in the drop down list
* Improved handling of the Subscribe2 option array

= Version 2.15 by Matthew Robinson =

* Improved HTML code generated for admin pages
* Fixed display issues if S2PAGE is not defined

= Version 2.14 by Matthew Robinson =

* Amended DREAMHOST setting to BCCLIMIT as more hosts are limiting emails
* Fixed oversight in upgrade() function

= Version 2.13 by Matthew Robinson =

* Added WordPress nonce functionality to improve admin security

= Version 2.12 by Matthew Robinson =

* Fix for missing Quicktags (probably since version 2.2.10)
* Fix for occasional email issue where excerpts are incomplete

= Version 2.11 by Matthew Robinson =

* Fixed bug that would cause all subscribers to get digest emails
* Added Select All check box to category listing

= Version 2.10 by Matthew Robinson =

* Improved sign up process by double checking email address
* Fix for submenu issues encountered in WP 2.0.6

= Version 2.9 by Matthew Robinson =

* Fixed get_userdata call issue
* Added CSV export
* Reworked options storage routines

= Version 2.8 by Matthew Robinson =

* Fixed missing line return in email headers that was causing failed emails
* Added user feedback messages to profile area
* Added 'Authorname' to the list of message substitutions in email messages
* Fixed name and email substitution in Digest Mails
* Fixed stripslashes issue in email subjects
* Added new 'Action' token for confirmation emails

= Version 2.7 by Matthew Robinson =

* Link to post in HTML emails is now functional
* Fixed bug in Bulk Management so it works when first loaded
* Ability to auto subscribe newly registering users
* Added additional email header information

= Version 2.6 by Matthew Robinson =

* Fixed email headers to comply with RFC2822 standard (after breaking them in the first place)
* Impoved XHTML compliance of user feedback messages and subscription form when presented on a blog
* Tidied up presentation of the code a little
* Cached some additional variables

= Version 2.5 by Matthew Robinson =

* Added functionality to Bulk Manage registered users subscriptions

= Version 2.4 by Matthew Robinson =

* Added functionality to block user specified domains from public subscription

= Version 2.3 by Matthew Robinson =

* Added functionality to allow for Subscribe2 Sidebar Widget
* Added functionality to block public email subscriptins from domains defined under Options
* Added functionality to send an email reminder to all unconfirmed public subscriber
* Added removal of html entities (for example &copy;) from plaintext emails
* Replaced spaces with tabs in Plugin format
* Minor changes to admin layout to match WordPress admin function layout

= Version 2.2 =

* By Scott Merrill, see http://www.skippy.net/blog/category/wordpress/plugins/subscribe2/