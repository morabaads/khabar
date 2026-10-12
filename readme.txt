=== Yadam Biar – یادم بیار ===
Contributors: yadambiar
Tags: woocommerce, back in stock, price alert, waitlist, sms
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later

Variation-exact back-in-stock, price-drop/rise and combined-rule alerts for WooCommerce via SMS (Kavenegar, Melipayamak, IPPanel, SMS.ir, custom), email, on-site, web push, Telegram and Bale.

== Description ==

See README.md for full documentation (Persian).

== Changelog ==

= 2.0.0 =
* Renamed to «یادم بیار» (Yadam Biar): slug, text domain, options, tables, hooks, REST namespace (yadambiar/v1), shortcodes ([yadambiar…]; old [khabar…] still work).
* One-time automatic migration from Khabar: settings, tables, request data, product/order meta and the "my alerts" page; the old plugin is deactivated.
* Modular PSR-4 architecture (src/): composition root with Module contract; split admin (pages, guarded actions, settings renderers), REST (router, controllers, services), SMS (one gateway class per provider), messengers, push, dispatcher, alternatives and price history.
* Security: typed REST args, ownership in permission callbacks, central capability + nonce guard for admin actions, safe HTTP for admin-typed URLs, CSV formula-injection protection, webhook secret checked before the handler.
* Performance: storefront settings computed only where widgets render, cached request counts with write invalidation, variation caches primed, CSV export streamed in batches.
* Uninstall also removes transients, queued actions and the migration flag (and runs on every site of a network).

= 1.7.4 =
* The same alert for the same item is not sent to the same person again within 72 hours (setting in Advanced; 0 = off), even across different requests or days. Skipped duplicates appear in the send log; each log row shows its request number.

= 1.7.3 =
* Fix: in-stock products/variations without a price were never notified (WooCommerce treats them as not purchasable). Empty price shows as 'استعلام در سایت' in messages.
* After each plugin update, all waiting requests are re-checked right away.

= 1.7.2 =
* New 'Help' section in the admin: quick start plus a short guide to each part, with links to the matching settings.

= 1.7.1 =
* WhatsApp channel removed (settings, sending, test send). Leftover WhatsApp settings are ignored and dropped on the next save.

= 1.7.0 =
* Bale personal notifications through Bale Safir: sent straight to the customer's phone number, no bot Start/linking needed. Bot linking stays as the fallback when Safir is not configured.

= 1.6.1 =
* Telegram/Bale: channel ids without @ (or pasted t.me / ble.ir links) are normalized; new 'Check bots' diagnostic (token, bot username, webhook registration and last delivery error, linked customers, channel access/admin).

= 1.6.0 =
* WhatsApp: Green API, UltraMsg, WAHA and Evolution API drivers (personal number via QR, no Meta business account), API host override for a reverse proxy (Iranian hosts), custom gateway body and headers.

= 1.1.0 =
* Telegram & Bale personal notifications (bot deep-link linking) and channel broadcasts (Telegram, Bale).
* Automatic single-use discount coupons in notifications, auto-applied from the message link.
* Alternative product suggestions for long-waiting customers.
* Price history tracking with an accessible chart.
* Demand forecast and purchase-order suggestions with CSV export.
* Elementor widgets: notify button, price history, my alerts, bell.

= 1.0.0 =
* Initial release.
