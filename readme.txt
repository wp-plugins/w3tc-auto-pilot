=== W3TC Auto Pilot ===
Contributors: Cybr
Tags: cache, control, w3, total, automatic, flush, update, multisite, mapping, hide
Requires at least: 3.6.0
Tested up to: 4.2.2
Stable tag: 4.2.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Put W3 Total Cache on auto pilot. This plugin allows you to control W3 Total Cache by simply updating your website. So your cache is always up to date.

== Description ==

= This plugin puts your W3 Total Cache configuration on auto pilot. =

It's especially handy when you have users that don't have access to W3 Total Cache control but still need to purge the cache.

It's also brilliant when you have created a blog for a customer, this way they won't even know it's there: All cache is purged automatically.

It's absolutely great on MultiSite installations, especially when you allow untrusted users to create a blog. 

**What this plugin does:**

***If not admin (single)/super admin (network):***

* No more purge from cache button on pages and posts edit screens. 
* No more admin menu in the admin bar. 
* No more admin menu in the dashboard. 
* No more admin notices in the dashboard after settings change or on error.
* No more admin script on front end.

***On the front end:***

* No more W3 Total Cache comments in the HTML output

***Behind the screens (pun not intended):*** 

* Purge cache each time a post is updated. 
* Purge cache each time the user changes a theme. 
* Purge cache each time a widget is updated. 
* Purge cache each time a sidebar is updated. 
* Purge cache each time the user finishes editing the theme in: 
 * Customizer. 
 * or switches theme. 

== Installation ==

1. Install Advanced W3TC either via the WordPress.org plugin directory, or by uploading the files to your server. 
1. Activate this plugin either through Network Activation or per site. 
1. That's it! There are currently no options available. 

== Changelog ==

= 1.0.5 =
* Made sure the admin bar was removed. It's only removed if you're not admin (single) or super-admin (multisite)

= 1.0.4 =
* Removed popup admin script if user isn't allowed to control W3TC

= 1.0.3 = 
* Fine tuned the purging of page cache to only when a domain is actually mapped.

= 1.0.2 = 
* Added forced page cache purging on each post save when Domain Mapping (by WPMUdev) is active. This will fix a bug with Domain Mapping.

= 1.0.1 =
* Removed admin notices and errors for non-super-admins (MultiSite) / non-admins (single)

= 1.0.0 =
* Initial Release 

== Developer Notes ==

If you wish to edit this plugin, all you need to do is uncomment stuff in function wap_w3tc_init(), every line is documented there.