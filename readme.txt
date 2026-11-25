=== Manny Wenas Redirect Manager ===
Contributors: mannywenas
Donate link: https://paypal.me/manfr3dw
Tags: redirects, 301 redirect, 302 redirect, regex, 404 monitor
Requires at least: 6.2
Requires PHP: 8.2
Tested up to: 7.1
Stable tag: 1.0.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage 301 and 302 redirects, catch broken URLs, and import redirect rules from CSV.

== Description ==

Inspired by modularity, Manny Wenas introduces Redirect Manager: a focused WordPress plugin built around the Manny Wenas philosophy of no bloat.

Use it as a standalone redirect manager or alongside Manny Wenas SEO. Choose the tools you need, and keep your setup simple.

Manny Wenas Redirect Manager helps you maintain old links when article URLs change. It works on its own. If Manny Wenas SEO is active, the Redirects, 404 Monitor, and Import screens appear under the Manny Wenas SEO menu.

Features:

* Create exact, trailing-wildcard, or regular-expression 301 and 302 redirects.
* See redirect hit counts and warnings about redirect chains.
* Review a bounded 404 log and turn a missing URL into a redirect.
* Get a prompt to add a redirect after changing a post or page slug.
* Import compatible CSV redirect lists. Regex rules can be added manually; regex and query-string rules in CSV files are skipped.

The plugin stores redirects and 404 records in your WordPress database. Uninstalling the plugin deletes these records.

= What it does not do =

* It does not output SEO metadata or create sitemaps.
* Redirects match the URL path only; query strings are not matched. Exact rules take priority over wildcards, which take priority over regex rules.
* It does not automatically redirect all missing pages to the homepage. Only redirects you create or import are applied.
* It does not prevent loops between two or more different wildcard redirects that send visitors back and forth. The Redirects list flags these as "Limit reached; possible loop" so you can correct them.
* Regex rules that redirect to a URL matching the same rule are skipped. Loops involving multiple regex rules are not detected automatically; test those redirects before using them widely.

= Support development =

If you find Manny Wenas Redirect Manager useful, consider supporting its continued development with a voluntary donation.

[Donate via PayPal](https://paypal.me/manfr3dw)

== Installation ==

1. Install and activate the plugin through the WordPress Plugins screen.
2. Open Redirects in the admin menu. If Manny Wenas SEO is active, look under Manny Wenas SEO.
3. Add a source path and a destination URL, then choose 301 or 302. For regex, choose "Regular expression" and use an anchored path such as `^/old/([0-9]+)$` with a target such as `/new/$1`. The pattern matches paths relative to the site root, without a trailing slash.

== Frequently Asked Questions ==

= Does this require Manny Wenas SEO? =

No. Redirect Manager works independently and shows its own menu when Manny Wenas SEO is not active.

= Can I import existing redirect rules? =

Yes. The Import screen accepts CSV files with source, target, and optional status-code columns. It recognizes compatible exports from several redirect plugins. Review skipped rows after the import; regex and query-string rows are skipped. Add regex rules manually on the Redirects screen.

= Does uninstalling remove my data? =

Yes. Uninstalling removes the redirect and 404 log tables. Keep a separate copy of your redirect rules before uninstalling.

== Changelog ==

= 1.0.4 =

* Added: path-only regex redirects with numbered captures, added manually in the Redirects screen.
* Preserved exact and wildcard precedence and added a per-rule guard against redirecting back into the same regex.
* Raised the minimum PHP version to 8.2.

= 1.0.3 =

* Fixed: a wildcard redirect whose target lies inside its own source (for example /blog/* to /blog/archive/*) no longer redirects endlessly. It redirects once and leaves its own target area alone.
* Fixed: a wildcard that would only point back into its own source (for example /blog/* to /blog/*) can no longer be saved or imported. Such a rule saved by an earlier version no longer redirects and is shown as "Never applies".
* Changed: the redirect check now reports a proven loop (a URL repeats) separately from "Limit reached; possible loop" (still redirecting after 10 steps).
* Known limitation: loops between two different wildcard redirects are not prevented. They are flagged as "Limit reached; possible loop".

= 1.0.2 =

* Fixed: the database tables are now verified and, when missing or incomplete (for example after files were copied without activation), created or completed automatically without losing data. A failure shows an admin notice instead of repeated database errors.
* Removed the repeated Manny Wenas SEO detection notice.
* Placed Redirect Manager screens under Manny Wenas SEO when its menu is present; standalone navigation remains available otherwise.

= 1.0.1 =

* Added CSV import for compatible redirect lists.

= 1.0.0 =

* Initial release.
