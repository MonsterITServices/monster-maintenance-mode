=== Monster Maintenance Mode ===
Contributors: monsteritservices
Tags: maintenance mode, coming soon, under construction, landing page, countdown
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Full-featured maintenance & coming soon plugin with 6 visual templates, External IP Bypass Manager, direct file reading controls, and email capture.

== Description ==

**Monster Maintenance Mode** is a lightweight, self-contained Maintenance Mode and Coming Soon plugin for WordPress with zero external CDN dependencies and no paid upsells.

= Key Features =

* **6 Built-In Visual Templates:** Choose between *Modern Glassmorphism*, *Corporate Split-Screen*, *Minimalist Editorial (Serif)*, *Cyber Terminal (Matrix)*, *Neon Synthwave Grid*, and *Bold Industrial Brutalist*.
* **Interactive External IP Bypass Manager:** Whitelist individual external IPv4/IPv6 addresses, wildcards (`192.168.1.*`), or CIDR subnets (`203.0.113.0/24`) with custom labels—or add your current admin IP in one click.
* **Direct File Reading Controls:** Allow visitors and external integrations to read or download specific file extensions (`.pdf`, `.zip`, `.json`, `.csv`, `.jpg`, etc.) and access excluded directories while the site is in maintenance mode.
* **200 OK & 503 HTTP Response Modes:** Use `200 OK` for Coming Soon launch pages or `503 Service Unavailable` with a configurable `Retry-After` header for temporary SEO-safe maintenance.
* **Countdown Timer with Auto-Disable:** Display a live launch countdown and optionally disable maintenance mode automatically when the timer reaches zero.
* **AJAX Email Lead Capture & CSV Export:** Collect subscriber emails directly on the maintenance screen and export your leads to CSV from the WordPress dashboard at any time.
* **Live Admin Template Preview:** Preview any of the 6 templates instantaneously in a new browser tab before activating maintenance mode.
* **Role & Search Bot Bypass:** Grant access to specific WordPress user roles, exclude custom URL paths, optionally allow search engine crawlers, and restrict unauthorized WP REST API requests.

== Installation ==

1. Upload the `monster-maintenance-mode` folder to the `/wp-content/plugins/` directory, or upload `monster-maintenance-mode.zip` via **Plugins > Add New > Upload Plugin**.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **Settings > Maintenance Mode** to select your template, configure IP bypass rules, and activate your maintenance or coming soon page.

== Frequently Asked Questions ==

= How can I preview templates before enabling Maintenance Mode? =
Go to **Settings > Maintenance Mode** and click the **Quick Preview** button on any of the 6 template cards, or click **Preview Active Template** at the top of the settings page.

= Can I allow clients to view the site without logging in? =
Yes. In the **External IP Bypass Manager**, add your client's IP address, wildcard range, or CIDR subnet along with a label. They will see the live site normally while everyone else sees the maintenance page.

= Can direct links to PDFs, ZIPs, or media files still work during maintenance? =
Yes. When **Allow Direct File Reading** is enabled, any direct request matching your configured extensions list or excluded folder paths will bypass the maintenance screen.

= What is the difference between 200 OK and 503 Service Unavailable? =
Use **200 OK** when launching a new site ("Coming Soon") so proxies, CDNs, and browsers treat the page as normal content. Use **503 Service Unavailable** during short-term maintenance on an established site so search engines know to come back later without indexing the maintenance message.

== Screenshots ==

1. Admin Dashboard — Status, HTTP Header Mode, and External IP Bypass Manager.
2. Template Selector — 6 built-in visual templates with one-click live preview.
3. Modern Glassmorphism & Cyber Terminal frontend templates.

== Changelog ==

= 2.2.0 =
* Prepared release for WordPress.org Plugin Directory compliance.
* Added interactive External IP Bypass Manager with CIDR subnet, wildcard, and one-click current IP support.
* Added Direct File Reading extension controls and attachment bypass options.
* Added 6 selectable visual templates with live admin preview and custom color overrides.