---
description: A first-day guide for new Maho store owners - log into admin, configure your store, add your first product, and cover the essentials before you go live.
---

# Your First Days with Maho

Welcome to Maho. This page is a practical starting point for merchants and store owners who have just installed Maho, or are still evaluating it, and want to know where to begin. It walks you through your first day in the admin panel and points you at the deeper guides for everything that follows.

If you are still exploring, spin up the [live demo](../about/demo.md) to click around a working store first. If you have not installed yet, start with the [getting started guide](../about/getting-started.md), then come back here once you can log in.

## Your first day

Work through these in order. Most of the configuration lives under **System > Configuration** in the admin panel, so you will get comfortable with that screen quickly.

1. **Log into the admin panel.** Your admin URL is the store address followed by the admin path you chose during installation (for example `https://yourstore.com/admin`). Sign in with the administrator account created at install time.

2. **Pick a theme.** Maho ships [eleven storefront themes](../about/themes.md), one for each kind of shop, each in light and dark. Choose the one closest to your business and restyle it from the admin, with no code and no build.

3. **Fill in your store information.** Under **System > Configuration**, set your store name, contact details, base currency, and time zone. Confirm your secure and unsecure base URLs point at the right domain, since these underpin every link and asset on your site.

4. **Set up taxes.** Configure your tax classes, rates, and rules so prices and checkout totals are correct for the regions you sell to. See [Tax in the Cart and in the Order](tax-calculation.md) for the address that the store uses for the tax.

5. **Add shipping and payment methods.** Enable the shipping options your store offers and the payment methods you want to accept, both under **System > Configuration**.

6. **Create your first category and product.** From the **Catalog** menu, add a category to organize your catalog, then create a product and assign it to that category. See [Product Types](product-types.md) for a tour of the product types Maho supports.

7. **Configure transactional email.** Order confirmations, password resets, and customer notifications all rely on proper email delivery. Set up your SMTP details following the [email sending configuration](../hosting/email-sending-configuration.md) guide.

8. **Turn on anti-spam protection.** Maho ships a built-in, self-hosted, cookieless [captcha](captcha.md) that shields your storefront and admin forms from bots without any third-party scripts or GDPR headaches. Enable it before you expose your forms to the public.

!!! tip "Take your time with configuration"
    You do not have to get everything perfect on day one. Store info, tax, shipping, and payment can all be revisited as your store grows.

## Before you launch

A few operational essentials keep your store fast, indexable, and maintainable once it is live.

- **Set up cron jobs.** Many features (reindexing, sitemaps, email automation, scheduled tasks) depend on a running scheduler. Follow the [cron guide](../hosting/cron.md) to configure it.
- **Enable Redis caching.** [Redis](../hosting/redis.md) gives you a fast, production-grade cache and session backend, and it is well worth setting up before you take real traffic.
- **Review your robots.txt.** Control what search engines crawl with a proper [robots.txt](../hosting/robotstxt.md) file.
- **Cover the SEO basics.** Maho has broad built-in [SEO tools](seo.md): page metadata, canonical tags, clean URLs, XML sitemaps, and more. Set these up so you launch already indexable.
- **Protect your admin accounts.** Turn on [two-factor authentication](two-factor-authentication.md) for every backend user, or register a passkey. From v26.9 you can make 2FA mandatory with a cutover date.
- **Know how to use maintenance mode.** When you need to do planned work, [maintenance mode](../hosting/maintenance-mode.md) takes the storefront offline cleanly.

## Grow your store

Once the basics are in place, these are some of the more powerful features documented in this User Guide, each covered in depth on its own page:

- **[Customer Segments](customer-segments.md)** - build dynamic customer groups from real-time conditions on behavior, orders, and demographics.
- **[Email Automation](email-automation.md)** - send targeted, multi-step campaigns when customers enter or leave a segment, with delays and dynamic coupons.
- **[Gift Cards](gift-cards.md)** - sell and redeem gift cards with email delivery, QR codes, and partial redemption at checkout.
- **[Product Relationship Rules](product-relationship-rules.md)** - generate related products, up-sells, and cross-sells automatically with condition-based rules.
- **[Feed Manager](feed-manager.md)** - export your catalog to Google Shopping, Facebook, Pinterest, and more with visual mapping and scheduling.
- **[Social Login](social-login.md)** and **[Magic Link Authentication](magic-link-authentication.md)** - let customers sign in with Google, Apple, or a time-limited email link instead of a password.

Every page in this User Guide expands on one piece of the store. Use this page as your map, and dive into the individual guides when you are ready for the detail.
