=== Manny Wenas Redirect Manager ===
Contributors: mannywenas
Tags: redirects, 301 redirect, 404 monitor, url migration
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage 301 and 302 redirects, catch broken URLs, and import redirect rules from CSV.

== Description ==

Manny Wenas Redirect Manager helps you maintain old links when article URLs change. It works on its own. If Manny Wenas SEO is active, the Redirects, 404 Monitor, and Import screens appear under the Manny Wenas SEO menu.

Features:

* Create exact or trailing-wildcard 301 and 302 redirects.
* See redirect hit counts and warnings about redirect chains.
* Review a bounded 404 log and turn a missing URL into a redirect.
* Get a prompt to add a redirect after changing a post or page slug.
* Import compatible CSV redirect lists. Unsupported regex and query-string rules are skipped.

The plugin stores redirects and 404 records in your WordPress database. Uninstalling the plugin deletes these records.

== Installation ==

1. Install and activate the plugin through the WordPress Plugins screen.
2. Open Redirects in the admin menu. If Manny Wenas SEO is active, look under Manny Wenas SEO.
3. Add a source path and a destination URL, then choose 301 or 302.

== Frequently Asked Questions ==

= Does this require Manny Wenas SEO? =

No. Redirect Manager works independently and shows its own menu when Manny Wenas SEO is not active.

= Can I import existing redirect rules? =

Yes. The Import screen accepts CSV files with source, target, and optional status-code columns. It recognizes compatible exports from several redirect plugins. Review skipped rows after the import; regex and query-string rules are not supported.

= Does uninstalling remove my data? =

Yes. Uninstalling removes the redirect and 404 log tables. Keep a separate copy of your redirect rules before uninstalling.

== Changelog ==

= 1.0.2 =

* Removed the repeated Manny Wenas SEO detection notice.
* Placed Redirect Manager screens under Manny Wenas SEO when its menu is present; standalone navigation remains available otherwise.

= 1.0.1 =

* Added CSV import for compatible redirect lists.

= 1.0.0 =

* Initial release.
