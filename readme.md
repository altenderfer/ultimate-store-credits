# Ultimate Store Credits for WooCommerce

![Ultimate Store Credits for WooCommerce Screenshot.](https://kylealtenderfer.com/github/ultimate-store-credits-kyle-altenderfer-v1.png)

**Version 1.0.0**  
Author: [Kyle Altenderfer](https://kylealtenderfer.com)

## Description

A flexible yearly store-credit plugin for WooCommerce, suitable for membership sites, digital downloads, internal company stores, and more. Users automatically receive a set credit amount each year, which resets either on a global fixed date or on each user’s personal anniversary date. Includes:

- **Rollover** of unused credits (optional)  
- **Partial usage** via a one-time coupon  
- **Full store-credit** payment gateway  
- **Domain restriction** for registrations  
- **Dark Mode** admin UI with color pickers  
- **Hourly** cleanup of stale partial-credit coupons  
- Tools to **reset** credits (single/all) or **test** rollover scenarios  
- **GitHub-based** plugin updates

## Features

1. **Yearly Credits**: Configurable amount (e.g. \$400/year).  
2. **Fixed or Anniversary** resets.  
3. **Partial Usage**: one-time coupon for partial credit.  
4. **Full Usage Gateway** if credit covers 100%.  
5. **Rollover** with optional max cap.  
6. **Domain Restriction** for new registrations.  
7. **GitHub Updates** integrated with Plugin Update Checker.

## Installation

1. Upload (or clone) this folder to `wp-content/plugins/ultimate-store-credits/`.  
2. Activate in WP Admin.  
3. Go to **WooCommerce → Store Credits** to configure.  
4. If `/my-account/store-credits/` 404s, re-save permalinks.

## Usage & Configuration

- **Credit Amount**: The base amount each user gets.  
- **Reset Method**: Global date vs. user anniversary.  
- **Partial Credits**: A one-time coupon for smaller credit usage.  
- **Full Credits**: Dedicated payment gateway if the user’s balance >= cart total.  
- **Rollover**: Keep leftover credits, up to a maximum if desired.  
- **Domain Restriction**: Force new user emails to match a specific domain.

## Frequently Asked Questions

**Does this require WooCommerce?**  
Yes, WooCommerce must be active.

**How do partial credits work?**  
It auto-generates a coupon like `credits-xyz123`, used once, then cleans stale coupons hourly.

**Can I see a user’s credit in WP Admin?**  
Yes, it appears on their user profile as read-only.

**GitHub-based updates**?  
Yes! We’ve included [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker). Just push new tagged releases to your GitHub repo.

## Changelog

### 1.0.0
- First release under “Ultimate Store Credits”
- Inherited all core features from the earlier concept
- Hourly stale-coupon cleanup
- Test rollover tool
- Dark-mode admin styling

## Author

- **Name**: [Kyle Altenderfer](https://kylealtenderfer.com)  
- **Email**: kyle@kylealtenderfer.com  
- **Support**: support@kylealtenderfer.com
