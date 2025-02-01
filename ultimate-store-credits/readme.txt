=== Ultimate Store Credits for WooCommerce ===
Contributors: kylealtenderfer
Donate link: https://kylealtenderfer.com
Tags: woocommerce, store credits, partial payment, membership
Requires at least: 5.5
Tested up to: 6.3
Stable tag: 1.0.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Ultimate Store Credits for WooCommerce is a robust plugin to provide users with annual store credits (either by fixed date or anniversary), optional rollover, partial usage coupons, and a dedicated store-credit payment gateway.

== Description ==

This plugin automatically grants users a specified store-credit balance yearly. Credits may reset on a global fixed date (e.g. Jan 1) or on each user’s “anniversary date.” Admins can enable a rollover feature and partial-credit usage via on-the-fly coupons. The plugin also includes domain-restricted registration, a dark-mode admin UI, and GitHub-based updates using Plugin Update Checker.

= Key Features =

* Yearly store credits (choose the amount)  
* Rollover unused credits (optional, with a max cap)  
* Partial usage through generated single-use coupons  
* Full-credit gateway if the user’s balance covers the total  
* Domain restriction for new signups  
* Admin tools to reset credits for all or single users  
* Automatic stale-coupon cleanup (hourly cron)  
* Dark mode settings panel with custom brand colors

== Installation ==

1. Download the plugin files and upload to `/wp-content/plugins/ultimate-store-credits/`, or install via your normal plugin workflow.  
2. Activate the plugin in your WordPress Admin.  
3. In **WooCommerce → Store Credits**, configure the credit amount, reset method, rollover, domain restriction, etc.  
4. Navigate to “My Account → Store Credits” to view or test credits as a user.

== Frequently Asked Questions ==

= Does it require WooCommerce? =  
Yes, WooCommerce must be active.

= Can I limit new registrations to a certain domain? =  
Yes, enable domain restriction in the plugin settings and specify the domain.

= How do partial credits work? =  
If a user’s balance is less than their cart total, they can generate a one-time coupon for that balance.

= Where can I see a user’s credits? =  
In WP Admin, edit a user and look under “Store Credits.”  

== Screenshots ==


== Changelog ==

= 1.0.0 =
* Initial public release of the plugin.  
* Yearly credit logic (fixed date or anniversary), partial usage, rollover, domain restriction, etc.  
* Dark-mode admin UI with custom brand colors.  
* GitHub-based updates with Plugin Update Checker.