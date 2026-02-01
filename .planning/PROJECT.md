# Mail Testing & Queue Control

## What This Is

Admin tools for testing and controlling email sending in Maho. Adds a test email button to verify SMTP configuration directly from admin settings, and a queue bypass option for immediate email delivery during development.

## Core Value

Developers can verify email configuration works without triggering real system emails or using CLI.

## Requirements

### Validated

- ✓ Maho mail system with queue support — existing
- ✓ Admin mail settings at System > Configuration > Advanced > System > Mail Sending Settings — existing
- ✓ SMTP configuration options — existing

### Active

- [ ] Test email button in admin mail settings
- [ ] Test email recipient defaults to current admin user's email with option to override
- [ ] Global queue bypass toggle (enable/disable mail queue)
- [ ] Automatic queue bypass when Maho is in developer mode

### Out of Scope

- Per-template queue bypass control — adds complexity, global toggle sufficient for v1
- Email preview/rendering — different feature, not part of this issue
- Email logging/history viewer — separate concern

## Context

GitHub Issue: MahoCommerce/maho#530

Similar functionality exists in Magento 1 extension `Aschroder_SMTPPro`:
- Queue bypass: https://github.com/aschroder/Magento-SMTP-Pro-Email-Extension/blob/06863e2525ae9106f0c1ba1475d16d3bb91acca4/app/code/local/Aschroder/SMTPPro/etc/system.xml#L255
- Test button: https://github.com/aschroder/Magento-SMTP-Pro-Email-Extension/blob/06863e2525ae9106f0c1ba1475d16d3bb91acca4/app/code/local/Aschroder/SMTPPro/etc/system.xml#L340

Primary use case is local development and testing environments where:
- Developers need quick feedback on SMTP configuration
- Queue adds unnecessary delay during testing
- CLI access may not always be available

## Constraints

- **Location**: Add to existing Mail Sending Settings section (no new admin sections)
- **Compatibility**: Must work with existing Maho mail infrastructure
- **PHP**: Use PHP 8.3+ patterns per Maho standards

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Add to existing settings vs new section | Keeps mail config consolidated, less navigation | — Pending |
| Global queue toggle + dev mode auto-bypass | Simpler than per-template, covers main use case | — Pending |
| Admin email as default recipient | Convenient for quick tests, override for flexibility | — Pending |

---
*Last updated: 2025-02-01 after initialization*
