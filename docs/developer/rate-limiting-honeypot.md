---
description: Throttle public endpoints and trap spam bots in Maho with the shared rate limiter and honeypot toolkit on the core helper, using cache-backed sliding windows.
---

# Rate Limiting & Honeypot <span class="version-badge">v26.7+</span>

Maho ships a single, shared toolkit for throttling public endpoints and trapping spam bots. Both live on the core helper (`Mage_Core_Helper_Data`), so you should never roll a per-feature limiter or hand-build a honeypot field. Use these factories and you get consistent behaviour, install-specific trap fields, and cache-backed counters for free.

The rate limiter is a value object: `\Maho\Security\RateLimiter`, a sliding window of `$maxAttempts` hits per `$windowSeconds`.

## Rate limiting

Core owns request identity. Callers **never** read the client IP or session id themselves; they name a scope and core resolves it. A non-positive `$maxAttempts` disables a limiter (it never blocks and records nothing), so there is no need for a call-site `if ($limit <= 0)` guard.

### Scope by request client

The most common case: throttle a feature per visitor. `rateLimiter()` returns a limiter keyed by the request identity that core resolves from the scope.

```php
use Maho\Security\RateLimitScope;

// namespace, max attempts, window in seconds
$limiter = Mage::helper('core')->rateLimiter('myfeature', 5, 3600);
if (!$limiter->attempt()) {        // check-and-record; false = blocked
    // blocked: surface your own message (keep AJAX/API responses silent)
}
```

The `$namespace` separates one feature's budget from another's, so two features never share a counter.

The scope is an enum (`\Maho\Security\RateLimitScope`):

| Scope     | Identity resolved by core |
|-----------|---------------------------|
| `Client`  | Client IP, falling back to the session id when the IP is unknown. **Default.** |
| `Ip`      | Client IP only |
| `Session` | Session id only |

```php
// Throttle strictly by IP
Mage::helper('core')->rateLimiter('contacts_ip', $ipLimit, 3600, RateLimitScope::Ip);
```

### Scope by a value you already hold

When you want to throttle by a domain value rather than request identity (email address, store id, order reference), use `rateLimiterBy()`. The caller passes a value it already holds; nothing is read from the request.

```php
// Cap auto-reply emails per recipient address, not per visitor
if (!Mage::helper('core')
        ->rateLimiterBy('contacts_recipient', strtolower(trim($email)), 1, 86400)
        ->attempt()) {
    // blocked
}
```

This is how the contact form prevents an attacker from email-bombing a third party: the recipient address is attacker-controllable, so the throttle is keyed on the address, not the sender's IP.

### Check up front, record on failure

`attempt()` does check-and-record in one call. When the check and the record need to happen at different points (for example, only count an attempt when validation fails), drive the object directly.

```php
$limiter = Mage::helper('core')->ipRateLimiter();
if ($limiter?->tooManyAttempts()) {
    // blocked: present a "Too Soon" message
}

// ...later, on a failed attempt only:
$limiter?->hit();
```

`ipRateLimiter()` is the store-config-governed IP limiter wired to `system/rate_limit/*`. It returns `null` when rate limiting is disabled in the admin or the client IP is unknown, and callers treat `null` as "not limited" (note the `?->` null-safe calls above).

### The RateLimiter API

| Method               | Behaviour |
|----------------------|-----------|
| `attempt(): bool`    | Check-and-record. Records a hit unless already over budget. Returns `true` when allowed, `false` when blocked. |
| `tooManyAttempts(): bool` | Pure read. `true` when the key is at or over its budget. Records nothing. |
| `hit(): void`        | Record a single hit explicitly. |
| `attempts(): int`    | Number of hits still inside the window. |
| `remaining(): int`   | Hits left before the next one blocks. |
| `clear(): void`      | Forget every hit for this key. |

### Storage and caveats

Counters are **cache-backed** (cache tag `\Maho\Security\RateLimiter::CACHE_TAG`), so a full cache flush resets every window. The read-modify-write is not atomic, so highly concurrent hits can race and slightly under-count. This is acceptable for abuse mitigation: the limiter is a soft throttle, not a hard guarantee.

!!! warning "Don't use it for must-persist counters"
    For security counters that must survive a cache flush (for example, forgot-password attempts), use durable storage instead. The rate limiter is for blunting automated floods, not enforcing hard limits.

## Honeypot

A honeypot is an invisible form field that humans never see and never fill, but naive spambots do. When the field comes back non-empty, the submission is bot traffic and you drop it silently.

### Rendering the trap field

Echo the ready-made markup inside your `<form>`:

```php
<?= Mage::helper('core')->getHoneypotFieldHtml() ?>
```

This emits a visually-hidden, `aria-hidden`, non-tab-focusable field so it stays invisible and inaccessible to real users while remaining present in the DOM for bots.

### Checking it server-side

```php
if (Mage::helper('core')->isHoneypotTriggered($request->getPost())) {
    // silently drop: show the normal success page so the bot can't detect the trap
}
```

`isHoneypotTriggered()` accepts any array of form data, so it works for web controllers (`$request->getPost()`) and API processors (a decoded JSON body) alike.

### Install-specific field name

The trap field name is derived deterministically from the install's encryption key (`getHoneypotFieldName()`). This means:

- The **same** install always renders the same field name (the frontend can cache it).
- **Different** installs get different names, so a bot that scrapes one Maho store can't blanket-target every Maho store with one hardcoded payload.

!!! note "What honeypots do and don't stop"
    Honeypots defeat random spambot armies cheaply. A targeted attacker who scrapes your rendered form can see and avoid the trap, so for those pair the honeypot with a captcha.

### The on/off toggle is yours

Core deliberately does **not** own the honeypot enable/disable flag. Gate both the render and the check behind your own module's default-on setting so merchants can turn it off per feature:

```php
// In the template
<?php if (Mage::getStoreConfigFlag('mymodule/abuse/honeypot_enabled')): ?>
    <?= Mage::helper('core')->getHoneypotFieldHtml() ?>
<?php endif ?>

// In the controller
if (Mage::getStoreConfigFlag('mymodule/abuse/honeypot_enabled')
    && Mage::helper('core')->isHoneypotTriggered($request->getPost())) {
    // drop
}
```

## Worked example: the contact form

The contact form (`Mage_Contacts_IndexController`) combines all three tools and is the canonical reference implementation:

1. **Honeypot** - a hidden field. Bots that fill it get the normal success page (so they can't detect the trap) and no email is sent.
2. **Per-IP throttle** (`rateLimiter('contacts_ip', ..., RateLimitScope::Ip)`) - blunts automated submission floods.
3. **Per-recipient throttle** (`rateLimiterBy('contacts_recipient', $email, ...)`) - caps how many auto-reply emails a single address can receive, defeating email-bombing of third parties. The merchant notification is still sent; only the attacker-controllable auto-reply is capped, and only when auto-reply is enabled.

These are exposed under **System > Configuration > Contacts > Abuse Protection** with three fields:

| Field | Purpose |
|-------|---------|
| Honeypot Field | Adds the invisible trap field. Submissions that fill it are silently dropped. |
| Submissions per IP per Hour | Per-IP throttle. `0` disables the limit. |
| Auto-Reply Emails per Recipient per Hour | Caps auto-reply emails per address. `0` disables the limit. |

Follow this pattern when adding abuse protection to your own public endpoints: keep the measures invisible to real customers, and don't set limits so low that legitimate visitors get blocked.
