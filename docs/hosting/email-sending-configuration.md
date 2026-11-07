---
description: Configure Maho's built-in SMTP email sending, choose a transactional provider, set up SPF, DKIM and DMARC, and troubleshoot delivery of order confirmations and notifications.
---

# Email Sending Configuration <span class="version-badge">v25.3+</span>

Maho includes **built-in SMTP support** for reliable email delivery. This modern implementation uses Symfony Mailer to replace legacy email mechanisms, ensuring your store can send transactional emails like order confirmations, password resets, and customer notifications.

**No third-party modules required** - SMTP functionality is included in Maho core.

Most of Maho's outgoing email is sent asynchronously through an email queue that is flushed by cron, so a working cron setup is as important as your SMTP credentials. Both topics are covered below.

## SMTP Configuration

Email settings can be configured through the admin panel under **System > Configuration > System > Mail Sending Settings**:

1. **Enable SMTP**: Toggle SMTP functionality on/off
2. **SMTP Host**: Your email service provider's SMTP server
3. **SMTP Port**: Usually 587 (TLS) or 465 (SSL)
4. **Authentication**: Username and password for your email account
5. **Encryption**: Automatically detected
6. **Region Support**: Configure region-specific settings for providers like AWS SES

Your provider gives you the host, port, and credentials to enter here. The username and password are the SMTP credentials issued by that provider, which are usually distinct from the login you use for their web dashboard. After saving, confirm the values are live with `./maho email:config:show` and send yourself a test message before relying on the store to deliver real mail (see [Testing and troubleshooting](#testing-and-troubleshooting)).

Make sure the **sender email address** configured for your store uses a domain you control, since the deliverability records described below have to be published on that domain.

## Choosing a provider

Maho works with **any SMTP-capable service**, so the practical decision is where your mail actually leaves from.

- **A transactional email provider** (such as Maileroo, AWS SES, Postmark, Mailgun, or SendGrid) is a service built specifically for application-generated mail. They maintain reputable sending IPs, handle bounce and complaint processing, and publish the infrastructure that inbox providers trust. You point Maho at their SMTP endpoint and they take care of getting the message delivered.
- **Sending from your own server** (a local `sendmail`/Postfix, or SMTP on the same box that runs the store) is possible but rarely a good idea. Fresh server IPs have no sending reputation, are frequently on shared hosting ranges that are pre-emptively blocklisted, and give you no bounce handling or deliverability tooling. Order confirmations that silently land in spam are worse than none at all.

For almost every store, a dedicated transactional provider is the right call: it is the single biggest factor in whether your emails reach the inbox, and the free tiers cover the volume of a small shop comfortably.

## Do you need a trusted SMTP service?

While Maho supports every possible SMTP/EmailAPI provider, our recommended provider is **[Maileroo](https://maileroo.com/?r=maho)**,
particularly for European stores due to GDPR compliance with servers in Germany and the Netherlands.

**[Maileroo](https://maileroo.com/?r=maho)** provides the usual features for **transactional emails and marketing campaigns**,
with great pricing and a **free plan up to 3,000 emails per month**.

!!! info
    We are affiliates of **[Maileroo](https://maileroo.com/?r=maho)**, which means when you use our referral link,
    it won't cost you anything extra, but it will help us finance the development of Maho.

## Deliverability: SPF, DKIM and DMARC

Choosing a good provider gets your mail sent; **authenticating your domain** gets it delivered. Modern inbox providers check three DNS records to decide whether a message is genuinely from you. Whichever provider you use, you must publish records on your sending domain that **authorize that provider** to send on your behalf - your provider's dashboard will give you the exact values to use.

All three are published as DNS `TXT` records. The examples below are **templates to adapt**: replace the domains, selectors, and policy values with the ones your provider documents.

### SPF (Sender Policy Framework)

SPF lists which servers are allowed to send mail for your domain. Receivers reject or flag mail from servers not on the list.

```dns
; Example only - use the include value your provider gives you
example.com.  IN  TXT  "v=spf1 include:spf.yourprovider.com -all"
```

Publish a **single** SPF record per domain. If you already have one, add your provider's `include:` to it rather than creating a second record.

### DKIM (DomainKeys Identified Mail)

DKIM adds a cryptographic signature to every message so the receiver can verify it was not altered in transit and really came from your domain. Your provider generates a key pair and gives you a public key to publish under a named **selector**.

```dns
; Example only - your provider supplies the selector and key
selector1._domainkey.example.com.  IN  TXT  "v=DKIM1; k=rsa; p=MIGfMA0GCSq...your-public-key...AQAB"
```

### DMARC (Domain-based Message Authentication)

DMARC tells receivers what to do when a message fails SPF or DKIM, and where to send aggregate reports so you can monitor your domain.

```dns
; Example only - start with p=none while you verify, then tighten
_dmarc.example.com.  IN  TXT  "v=DMARC1; p=none; rua=mailto:dmarc-reports@example.com"
```

Start with `p=none` to observe reports without affecting delivery, then move to `p=quarantine` and eventually `p=reject` once you have confirmed all your legitimate mail passes.

!!! note
    DNS changes can take some time to propagate. After publishing these records, send a test email to an address at a major inbox provider and inspect the message headers (or use a mail-tester service) to confirm SPF, DKIM, and DMARC all pass before going live.

## Testing and troubleshooting

Maho ships several CLI commands to test and manage email. See the [CLI tool](../developer/cli-tool.md) page for the full command reference.

```bash
# Show current email configuration
./maho email:config:show

# Send a test email directly
./maho email:test:send

# Send a test email via the queue system
./maho email:test:queue

# Process the email queue manually
./maho email:queue:process

# Clear emails from the queue
./maho email:queue:clear
```

### Emails are not sending

1. **Verify the configuration** with `./maho email:config:show`, then try a direct send with `./maho email:test:send`. If the direct send works but real store emails never arrive, the problem is the queue or cron, not your SMTP settings.
2. **Check cron.** Store emails are queued, not sent inline, so if cron is not running the queue simply fills up and nothing is delivered. Confirm your cron setup is in place, described on the [cron jobs](cron.md) page.
    - <span class="version-badge">v26.9+</span> emails go through the [message queue](../developer/message-queue.md), consumed by a background worker that the `queue_process` cron job keeps alive.
    - <span class="version-badge">&lt;v26.9</span> emails were flushed directly by the `core_email_queue_send_all` cron job.
3. **Process the queue manually** with `./maho email:queue:process` to confirm queued messages can be delivered. If this sends the backlog, your SMTP is fine and cron is the missing piece.
4. **Look for failures.** <span class="version-badge">v26.9+</span> Run `./maho queue:list`, or open **System > Tools > Message Queue** in the admin, to see whether messages are piling up as failed and what error they carry. Failed messages can be retried from there.

### Emails land in spam

If mail is being sent and accepted but ends up in the spam folder, this is almost always a **domain authentication** problem rather than a Maho configuration issue. Re-check your [SPF, DKIM, and DMARC](#deliverability-spf-dkim-and-dmarc) records and confirm they authorize your current sending provider. A dedicated transactional provider with a warmed-up sending reputation also makes a large difference here.

### Verifying a change

After adjusting SMTP settings or DNS records, run `./maho email:test:send` to send a message to yourself and inspect the received headers. It is the quickest way to confirm that a change took effect and that your authentication records pass.
