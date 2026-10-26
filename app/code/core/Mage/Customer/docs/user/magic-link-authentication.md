---
description: Let Maho customers log in without a password using secure, time-limited magic links sent by email, in either hybrid or fully passwordless mode.
---

# Magic Link Authentication <span class="version-badge">v26.1+</span>

Magic Link (passwordless) authentication allows customers to log in using a secure, time-limited link sent to their email instead of entering a password. This feature provides enhanced security and convenience for your customers.

## Overview

The Magic Link feature offers two authentication modes:

- **Require Password (Hybrid)**: Traditional password authentication with an optional magic link login
- **No Password (Passwordless)**: Pure passwordless authentication where customers never set or use passwords

## Key Features

- **Email-based authentication** - Customers receive a secure login link via email
- **Passwordless registration** - Customers can create accounts without setting passwords
- **Time-limited tokens** - Links expire after a configurable time (default: 10 minutes)
- **One-time use** - Each link can only be used once for maximum security
- **Rate limiting** - Prevents abuse with configurable email and IP-based limits
- **Multi-website support** - Can be enabled/disabled per website

## Configuration

Navigate to **System → Configuration → Customers → Customer Configuration → Magic Link (Passwordless Login) Options**

### Settings

| Setting | Description | Default |
|---------|-------------|---------|
| **Enable Magic Link** | Enable/disable the feature per website | Disabled |
| **Token Expiration** | How long login links remain valid (minutes) | 10 |
| **Rate Limit (Email)** | Maximum requests per email per hour | 3 |
| **Rate Limit (IP)** | Maximum requests per IP address per hour | 10 |
| **Email Template** | Template used for magic link emails | `customer_magic_link_email_template` |
| **Email Identity** | Sender identity for magic link emails | General Contact |
| **Registration Password Requirement** | Authentication mode (see below) | No Password |

### Authentication Modes

#### Require Password (Hybrid Mode)

Traditional authentication with an optional magic link:

- Login form shows both password field and "Login with Magic Link" button
- Registration requires password
- Existing password-based authentication continues to work
- Useful for gradual migration or offering user choice

#### No Password (Passwordless Mode)

Pure passwordless authentication:

- Login form only shows email field - no password field
- Registration form hides password fields
- Account edit form hides password management
- New accounts are created with auto-generated secure passwords
- Users receive magic link email to complete registration
- Recommended for maximum simplicity and security

!!! tip
    When you enable Magic Link, **No Password mode is the default**. This provides the best user experience for new deployments. You can switch to Hybrid mode if you need backward compatibility.

## User Experience

### Login Flow

=== "Passwordless Mode"

    1. Customer visits login page
    2. Enters email address in the single field shown
    3. Clicks "Login" button
    4. Receives email with magic link
    5. Clicks link and is automatically logged in
    6. Token is cleared and cannot be reused

=== "Hybrid Mode"

    1. Customer visits login page
    2. Can choose between:
        - Enter email + password and click "Login" button (traditional login)
        - Enter email and click "Login with Magic Link" button (passwordless login)
    3. If using magic link, receives email with login link
    4. Clicks link and is automatically logged in

### Registration Flow

=== "Passwordless Mode"

    1. Customer fills registration form (name, email, address, etc.)
    2. Password fields are automatically hidden
    3. Clicks "Register" button
    4. Account is created with auto-generated secure password
    5. Receives magic link email to complete first login
    6. Clicks link and is logged in - no password to remember!

=== "Hybrid Mode"

    1. Customer fills registration form including password fields
    2. Account is created with chosen password
    3. Can log in using either password or magic link

### Account Management

In **Passwordless Mode**, the account edit page automatically hides all password-related fields:

- No "Current Password" field required
- No "Change Password" checkbox or fields
- Customers can update personal information without password friction

## Security

The Magic Link implementation follows industry best practices:

### Security Features

- **Cryptographic tokens**: 32-character hexadecimal tokens (128-bit entropy)
- **Timing-safe comparison**: Uses `hash_equals()` to prevent timing attacks
- **CSRF protection**: All forms protected against cross-site request forgery
- **Rate limiting**: Count-based limits prevent abuse
- **Email enumeration protection**: Silent failure for non-existent accounts
- **Active account validation**: Only active accounts can receive magic links
- **One-time use enforcement**: Tokens are invalidated after successful login

### Rate Limiting

Two independent rate limits protect against abuse:

1. **Email-based limiting** (default: 3 requests/hour)
    - Prevents spam to specific email addresses
    - Configurable per your security needs

2. **IP-based limiting** (default: 10 requests/hour)
    - Prevents automated attacks from single sources
    - Higher limit allows legitimate users on shared networks

If limits are exceeded, users see a clear error message and must wait before requesting another link.

### Token Security

Magic link tokens are:

- Generated using `Mage::helper('core')->getRandomString(32)` for cryptographic randomness
- Stored unhashed in the database (required for email delivery, same as password reset)
- Valid for a configurable duration (default: 10 minutes)
- Cleared immediately after successful use
- Compared using timing-safe `hash_equals()` function

!!! warning "Token Storage"
    Like password reset tokens, magic link tokens are stored unhashed because they must be sent via email. This is the same security model used by password reset functionality and is industry standard for email-based authentication.

## Email Template

The magic link email uses the template: **customer_magic_link_email_template**

You can customize this template in the admin panel under:
**System → Transactional Emails**

## Technical Details

??? note "Technical details (for developers)"

    Magic Link does not add its own database tables - it reuses the existing customer authentication infrastructure:

    - `customer_entity.rp_token` - stores the magic link token
    - `customer_entity.rp_token_created_at` - token creation timestamp, used for expiration
    - `customer_flowpassword` - tracks requests for rate limiting
