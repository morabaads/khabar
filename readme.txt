=== Khabar – خبرم کن ===
Contributors: khabar
Tags: woocommerce, back in stock, price alert, waitlist, sms
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.7.3
License: GPLv2 or later

Variation-exact back-in-stock, price-drop/rise and combined-rule alerts for WooCommerce via SMS (Kavenegar, Melipayamak, IPPanel, SMS.ir, custom), email, on-site, web push, Telegram and Bale.

== Description ==

See README.md for full documentation (Persian).

== Changelog ==

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
