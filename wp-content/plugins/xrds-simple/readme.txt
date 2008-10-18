=== XRDS-Simple ===
Contributors: singpolyma, wnorris
Tags: xrds, xrds-simple, discovery
Requires at least: 2.1
Tested up to: 2.6.0
Stable tag: 1.0

Provides framework for other plugins to advertise services via XRDS.


== Description ==

[XRDS-Simple][] is a profile of XRDS, a service discovery protocol which used
in the [OpenID][] authentication specification as well as [OAuth][].  This
plugin provides a generic framework to allow other plugins to contribute their
own service endpoints to be included in the XRDS service document for the
domain.

[XRDS-Simple]: http://xrds-simple.net/
[OpenID]: http://openid.net/
[OAuth]: http://oauth.net/


== Installation ==

This plugin follows the [standard WordPress installation method][]:

1. Upload the `xrds-simple` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

[standard WordPress installation method]: http://codex.wordpress.org/Managing_Plugins#Installing_Plugins


== Frequently Asked Questions ==

= How do I contribute services to the XRDS document =

Implement the filter 'xrds_simple', and see the public functions at the top of
the file.


== Changelog ==

= version 1.0 =
 - initial public release
