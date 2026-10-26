---
description: Protect Maho admin accounts and customer accounts with TOTP two-factor authentication and passkeys, and make 2FA mandatory for every backend user.
---

# Two-Factor Authentication

Maho has native two-factor authentication (2FA). No extension is needed. It covers both sides of the
store:

| Feature | Who it protects | Available since |
|---------|-----------------|-----------------|
| TOTP two-factor authentication | Admin users | <span class="version-badge">v25.1+</span> |
| Passkey authentication (FIDO2/WebAuthn) | Admin users | <span class="version-badge">v25.3+</span> |
| TOTP two-factor authentication | Customers | <span class="version-badge">v26.7+</span> |
| Mandatory 2FA for all admin users | Admin users | <span class="version-badge">v26.9+</span> |

TOTP works with any standard authenticator application, for example Google Authenticator, Aegis, 1Password,
or Bitwarden. Maho generates the QR code as an inline SVG, so no temporary image file is written to disk.

Every 2FA secret is stored encrypted in the database.

## Admin users

### Enroll in TOTP

Each admin user enrolls from their own account page:

1. Open **System > My Account**.
2. Set **Two-Factor Authentication** to **Yes**.
3. Scan the QR code with your authenticator application.
4. Type the current 6-digit code in **2FA Verification Code**.
5. Save the account.

The verification code is mandatory when you turn 2FA on. Maho rejects the save without a valid code, so a
user cannot lock themselves out with a secret they never scanned.

### Sign in with TOTP

The admin login is a two-step form. You send the username and the password first. If the account has 2FA,
the form reveals the **2FA verification code** field and asks for the current code.

### Passkeys

A passkey is an alternative to the password. It uses the FIDO2/WebAuthn standard, so the user signs in with
a biometric sensor or a hardware security key. A passkey is phishing-resistant.

Register a passkey from **System > My Account**, in the **Passkey Authentication** fieldset:

- **Register Passkey** creates the credential in your browser or security key.
- **Remove Passkey** deletes it.

Registering a passkey turns off password authentication for that user. You can turn the password back on,
but Maho recommends that you leave it off.

A passkey sign-in does not ask for a TOTP code, because the passkey already proves user verification.

### Make 2FA mandatory <span class="version-badge">v26.9+</span>

Per-user 2FA is opt-in, so a store owner cannot be sure that every backend account has it. Two settings in
**System > Configuration > Advanced > Admin > Security** change that:

| Setting | Config path | Description | Default |
|---------|-------------|-------------|---------|
| **Require 2FA for All Admin Users** | `admin/security/twofa_required` | Turns the rule on for every admin user | No |
| **Require 2FA From** | `admin/security/twofa_required_from` | The cutover date of the rule | Empty |

Both settings apply to the default scope only.

The rule puts each admin user in one of these states:

- **Compliant.** The user has TOTP enabled, or a registered passkey. Nothing changes for them.
- **Warning.** The cutover date is in the future and the user is not enrolled. The user keeps full access
  and sees one reminder per session, with the number of days that are left.
- **Blocked.** The cutover date has arrived, or the date is empty, and the user is not enrolled. Maho sends
  the user to **System > My Account** and shows an error. Only the account page, its save action, the
  passkey registration call, and the logout stay reachable.

!!! warning "Check the My Account permission first"
    A blocked user must be able to open **System > My Account**. If their role does not allow
    `system/myaccount`, they cannot enroll and an administrator must grant that permission. Maho shows a
    specific error message in this case.

!!! tip "Give your team a deadline"
    Set the date some weeks in the future before you turn the rule on. Your users then get the reminder at
    each sign-in and enroll at their own pace. An empty date blocks everybody at once.

### Recover a locked-out admin user

If a user loses the device with their authenticator, remove their 2FA from the command line:

```bash
./maho admin:user:twofa-reset
```

The command asks for the username. It clears the 2FA flag and the secret. The user must then enroll again
from **System > My Account**.

If the mandatory rule locks out every administrator, turn it off from the command line:

```bash
./maho config:set admin/security/twofa_required 0 --scope default --scope-id 0
```

See the [CLI tool](../developer/cli-tool.md) page for more commands.

## Customers <span class="version-badge">v26.7+</span>

Customers can protect their own account with the same TOTP mechanism.

### Configuration

Go to **System > Configuration > Customers > Customer Configuration > Password Options**:

| Setting | Config path | Description | Default |
|---------|-------------|-------------|---------|
| **Allow Two-Factor Authentication** | `customer/password/allow_2fa` | Lets customers enable 2FA on their account | Yes |
| **Require Two-Factor Authentication** | `customer/password/require_2fa` | Forces every customer to enroll | No |

You can set both per website and per store view. **Require Two-Factor Authentication** is only visible when
**Allow Two-Factor Authentication** is **Yes**.

### How a customer enrolls

The customer opens the two-factor authentication page in their account, scans the QR code, then types the
current password and the current 6-digit code. Maho asks for the password again, because this is a
sensitive change.

To turn 2FA off, the customer types the current password. Maho refuses this when **Require Two-Factor
Authentication** is **Yes**.

### How the rule applies

When 2FA is mandatory, Maho only gates the sensitive parts of the storefront: the customer account and the
checkout. The catalog, the CMS pages, and the search stay open, so a visitor who does not enroll can still
browse the store.

Inside the gated area, the enrollment page, its POST handler, and the logout stay reachable.

### Login flows

- **Standard login.** The login form detects 2FA and reveals the code field, like the admin form.
- **Magic link and account confirmation.** These passwordless paths send the customer to a TOTP challenge
  page before the session starts. See [Magic Link Authentication](magic-link-authentication.md).
- **Social login.** The customer completes the 2FA challenge before the session starts. See
  [Social Login](social-login.md).
- **API login.** The customer token endpoint accepts a `twofaCode` field. A customer with 2FA must send it
  together with the email and the password. See [API authentication](../api/v2/authentication.md).

## Migration from Magento

The migration tool re-encrypts admin two-factor secrets with your new Maho encryption key. Read the
[migrate to Maho](../about/migrate-to-maho.md) page before you start.
