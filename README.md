# awesoMux Version Sync

A small WordPress plugin that checks the latest stable release of [awesoMux](https://github.com/Interactive-Buffoonery/awesomux) daily and displays its version with `[awesomux_version]`.

## Install

Requires WordPress 6.6+ and PHP 7.4+. Run `npm run plugin-zip`, then upload `dist/awesomux-version-sync.zip` through **Plugins → Add New Plugin → Upload Plugin**, and activate it. No npm dependencies or PHP build step. Packaging requires Python 3.

Activation makes the first check immediately. No GitHub account or token is needed. Activate individually per site on multisite; network activation is not supported.

## Use

Replace each hardcoded version in your page content with the shortcode:

```html
<p>Terminal → awesoMux [awesomux_version]</p>
<p>==&gt; Pouring awesomux-[awesomux_version]… honk.</p>
```

For a first-install fallback if GitHub is unavailable:

```text
[awesomux_version fallback="0.16.2"]
```

The stored version takes precedence over the fallback. Without either, output is empty. Output is escaped plain text, without the tag's leading `v`, so it inherits your existing styling. Use a Shortcode block for a standalone value. Inline shortcodes work in ordinary post/page content, including Paragraph and Custom HTML blocks processed through `the_content`. Template parts and page builders may require their own shortcode support; check the rendered page after inserting. No existing page content is edited automatically.

## Updates and failures

WordPress checks once daily using WP-Cron. Low traffic, disabled cron, or a host's cron configuration can delay execution. For strict timing, configure your host's scheduler to run WordPress cron. Rendering the shortcode only reads the stored value; it never contacts GitHub.

Only stable `X.Y.Z` or `vX.Y.Z` tags are accepted. Network errors, rate limits, invalid responses, drafts, and prereleases leave the last successful value intact until a later successful check. Deactivation stops checks but keeps the saved version; deleting the plugin removes its data.

**Full-page/CDN caching:** cached HTML can keep showing the old version until that cache expires or is purged. Set an appropriate cache lifetime (for example, no more than 24 hours), or connect your host's purge API to `awesomux_version_sync_updated`, which receives the new and previous versions. No automatic page-cache purge is included. With daily polling plus a 24-hour page cache, visible updates can take roughly 48 hours, longer if cron is delayed.

For an immediate manual check with WP-CLI:

```sh
wp cron event run awesomux_version_sync_refresh
wp option get awesomux_version_sync_version
```

## Development

```sh
npm run lint
npm test
npm run plugin-zip
```

Fast checks exercise the real plugin functions using WordPress API doubles. They do not replace testing activation, cron, content rendering, and caching in WordPress. No hosted workflows are included.

## External service

The server contacts `https://api.github.com/repos/Interactive-Buffoonery/awesomux/releases/latest` on activation and daily while active. Requests send normal WordPress HTTP headers and the server's IP address to GitHub; no visitor data, credentials, or content is sent. See [GitHub's terms](https://docs.github.com/en/site-policy/github-terms/github-terms-of-service) and [privacy statement](https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement).
