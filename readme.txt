=== awesoMux Version Sync ===
Contributors: edequalsawesome
Tags: github, version, shortcode
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Show the latest stable awesoMux release anywhere shortcodes are supported.

== Description ==
Checks GitHub on activation and daily with WP-Cron, then stores a last-known-good version. Use [awesomux_version] or [awesomux_version fallback="0.16.2"]. No token, settings screen, or frontend network requests.

WP-Cron depends on traffic or your host's scheduler. Cached pages show the old value until their cache expires or is purged. The awesomux_version_sync_updated action supports host-specific cache integrations; no automatic full-page cache purge is included. Network activation is unsupported; activate per site.

External service: contacts https://api.github.com/repos/Interactive-Buffoonery/awesomux/releases/latest on activation and daily to retrieve public release metadata. GitHub receives the server IP and normal WordPress HTTP headers, not visitor data or page content.
Terms: https://docs.github.com/en/site-policy/github-terms/github-terms-of-service
Privacy: https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement

== Installation ==
1. Upload the plugin ZIP through Plugins > Add New Plugin > Upload Plugin.
2. Activate and replace hardcoded versions in page content with [awesomux_version].
3. Verify the rendered page and configure page-cache expiry or purging as needed.

== Changelog ==
= 1.0.0 =
* Initial release with daily stable release checks and an escaped inline shortcode.
