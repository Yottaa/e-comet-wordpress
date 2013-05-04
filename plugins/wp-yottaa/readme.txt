=== WordPress Yottaa ===
Contributors: 
Donate link: 
Tags: cache, caching, performance, Yottaa, purge, speed
Requires at least: 2.9.2
* Tested up to: 3.5.1
* Stable tag: 0.1

WordPress Yottaa is a plugin that provides performance optimization and caching service.

== Description ==

This plugin helps to sign up a new Yottaa account or register an existing Yottaa account.
This plugin purges your Yottaa cache when content is added or edited. This includes when a new post is
added, a post is updated or when a comment is posted to your blog.


== Installation ==

This section describes how to install the plugin and get it working.

1. Upload `wp-yottaa/` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= Does this just work? =

Yes.

= My Plugins are seeing the Yottaa server's IP rather than the websurfer IP =

In wp-config.php, near the top, put the following code:

    $temp_ip = explode(',', isset($_SERVER['HTTP_X_FORWARDED_FOR'])
    ? $_SERVER['HTTP_X_FORWARDED_FOR'] :
    (isset($_SERVER['HTTP_CLIENT_IP']) ?
    $_SERVER['HTTP_CLIENT_IP'] : $_SERVER['REMOTE_ADDR']));
    $remote_addr = trim($temp_ip[0]);
    $_SERVER['REMOTE_ADDR'] = preg_replace('/[^0-9.:]/', '', $remote_addr );

The code takes some of the common headers and replaces the REMOTE_ADDR
variable, allowing plugins that use the surfer's IP address to see the
surfer's IP rather than the server's IP.

== Screenshots ==

1. Screenshot of the adminstration interface.

== Changelog ==

= 0.1 =
* Initial release.

== Upgrade Notice ==
