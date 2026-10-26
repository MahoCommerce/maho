---
description: Send targeted, multi-step email campaigns in Maho when customers enter or exit segments, with configurable delays and dynamic coupon generation.
---

# Customer Segment Email Automation <span class="version-badge">v25.11+</span>

Maho's Customer Segment Email Automation enables you to send targeted, multi-step email campaigns automatically when customers enter or exit specific segments. Transform your customer engagement with personalized email sequences, dynamic coupon generation, and intelligent timing.

## Overview

Email automation in Maho combines the power of customer segmentation with sophisticated email sequencing to create highly targeted marketing campaigns:

- **Trigger-based sequences**: Automatically send emails when customers enter or exit segments
- **Multi-step campaigns**: Create complex email journeys with configurable delays
- **Dynamic coupon generation**: Generate unique, personalized coupons for each customer
- **Newsletter integration**: Respects customer subscription preferences
- **Progress tracking**: Monitor delivery status and campaign performance

## Use Cases

### 1. Cart Abandonment Recovery

**Scenario**: Customer adds items to cart but doesn't complete purchase

**Segment Conditions**:
```
- Shopping Cart: Status equals "Active"
- Shopping Cart: Updated 1+ hours ago
- Newsletter: Subscribed
```

**Email Sequence**:

| Step | Delay | Template | Coupon |
|------|-------|----------|--------|
| 1 | 1 hour | "You forgot something!" | No |
| 2 | 24 hours | "Still thinking it over?" | 10% off |
| 3 | 7 days | "Last chance" | 15% off + free shipping |

**Expected Results**: 15-25% recovery rate with significant revenue impact

---

### 2. Welcome Series for New Customers

**Scenario**: New customer registers but hasn't made their first purchase

**Segment Conditions**:
```
- Customer: Registered in last 7 days
- Customer: Number of orders equals 0
- Newsletter: Subscribed
```

**Email Sequence**:

| Step | Delay | Template | Coupon |
|------|-------|----------|--------|
| 1 | 30 minutes | "Welcome!" | 15% off first order |
| 2 | 3 days | "Getting Started Guide" | No |
| 3 | 7 days | "Customer favorites" | No |
| 4 | 14 days | "Join our community" | No |

**Expected Results**: Higher engagement, increased lifetime value, reduced churn

---

### 3. Win-Back Campaigns

**Scenario**: Previously active customer hasn't purchased in 90+ days

**Segment Conditions**:
```
- Order: Days since last order > 90
- Order: Total orders > 1
- Newsletter: Subscribed
```

**Email Sequence**:

| Step | Delay | Template | Coupon |
|------|-------|----------|--------|
| 1 | Immediate | "We miss you!" | No |
| 2 | 7 days | "Come back" | 15% off |
| 3 | 14 days | "Final offer" | 20% off + free shipping |

**Expected Results**: 5-10% reactivation rate

---

### 4. Birthday & Anniversary Marketing

**Scenario**: Customer's birthday is today

**Segment Conditions**:
```
- Customer: Days until birthday equals 0
- Newsletter: Subscribed
```

**Email Sequence**:

| Step | Delay | Template | Coupon |
|------|-------|----------|--------|
| 1 | Immediate | "Happy Birthday!" | 20% birthday discount |
| 2 | 3 days | "Birthday week continues" | No |
| 3 | 7 days | "Last chance" | No |

**Expected Results**: Emotional connection, increased purchase likelihood

---

### 5. VIP Customer Nurturing

**Scenario**: Customer reaches high-value tier

**Segment Conditions**:
```
- Customer: Lifetime sales > $1000
- Customer: Number of orders > 5
```

**Email Sequence**:

| Step | Delay | Template | Coupon |
|------|-------|----------|--------|
| 1 | Immediate | "Welcome to VIP!" | No |
| 2 | 7 days | "VIP early access" | No |
| 3 | 30 days | "Your VIP benefits" | 10% VIP exclusive |

**Expected Results**: Increased loyalty, higher average order value

## Key Features

### Dynamic Coupon Generation

Each customer receives a unique, trackable coupon code:

- **Custom prefixes**: Brand your coupons (e.g., `CART`, `VIP`, `BDAY`)
- **Configurable expiration**: Create urgency with time-limited offers
- **One-time use**: Prevent coupon sharing
- **Sales rule integration**: Leverage existing discount rules

Example coupon codes:
```
CART12345ABC      # Cart abandonment for customer #1
BDAY67890DEF      # Birthday offer for customer #6
VIP11223GHI       # VIP exclusive for customer #11
```

### Intelligent Sequencing

Build sophisticated email journeys:

- **Flexible timing**: Delays from minutes to days
- **Progressive engagement**: Escalate offers over time
- **Exit handling**: Stop sequences when customers leave segments
- **Overlap control**: Prevent sequence conflicts

### Newsletter Integration

Respects customer preferences:

- **Subscription status**: Only sends to subscribed customers
- **Automatic cleanup**: Stops sequences on unsubscribe
- **Template system**: Uses Maho's newsletter template engine
- **Variable support**: Rich personalization options

## Configuration

### 1. Create Your Segment

Navigate to **Customers → Customer Segments → Add Segment**:

1. Set segment conditions (e.g., cart status, customer attributes)
2. Save the segment

### 2. Configure Email Sequences

The segment edit page has **two dedicated tabs** for email automation:

- **Enter Segment** tab - Configure sequences triggered when customers join the segment
- **Exit Segment** tab - Configure sequences triggered when customers leave the segment

This allows you to configure **both** enter and exit sequences for the same segment!

#### Adding Enter Sequences

In the **Enter Segment** tab:

1. Click "Add Email Step"
2. Select newsletter template
3. Set delay (in minutes)
4. Optionally enable coupon generation:
   - Choose sales rule
   - Set coupon prefix
   - Set expiration days
5. Save

Repeat for additional steps.

#### Adding Exit Sequences

In the **Exit Segment** tab:

Follow the same process as Enter sequences. Exit sequences are perfect for:
- Thank you emails when customers complete a purchase
- Win-back campaigns when high-value customers become inactive
- Feedback requests after issue resolution

### 3. Create Newsletter Templates

Create templates at **Newsletter → Newsletter Templates**:

Use available variables:

```html
<p>Hi {{var customer_firstname}},</p>

<p>Your cart is waiting! Complete your purchase with code:</p>
<p style="font-size: 24px; font-weight: bold;">{{var coupon_code}}</p>
<p>Save {{var coupon_discount_text}} - expires {{var coupon_expires_formatted}}</p>
```

See the [Email Template Variables reference](#email-template-variables) for all available variables.

### 4. Set Up Cron

Email automation requires cron to be configured:

```bash
# Email processing runs every 5 minutes
*/5 * * * * /path/to/maho cron:run customersegmentation_process_emails
```

Or rely on the main cron job:
```bash
*/5 * * * * /path/to/maho cron:run
```

## Email Template Variables

### Standard Variables (Always Available)

| Variable | Example | Description |
|----------|---------|-------------|
| `{{var customer_firstname}}` | "John" | Customer's first name |
| `{{var customer_lastname}}` | "Doe" | Customer's last name |
| `{{var customer_name}}` | "John Doe" | Full name |
| `{{var customer_email}}` | "john@example.com" | Email address |
| `{{var segment_name}}` | "Cart Abandoned" | Segment name |
| `{{var step_number}}` | 2 | Current sequence step |
| `{{var customer}}` | (object) | Full customer object |
| `{{var store}}` | (object) | Store object |

### Coupon Variables (When Enabled)

| Variable | Example | Description |
|----------|---------|-------------|
| `{{var coupon_code}}` | "CART12345ABC" | Generated coupon code |
| `{{var coupon_discount_text}}` | "15% off" | Human-readable discount |
| `{{var coupon_discount_amount}}` | 15.00 | Discount amount |
| `{{var coupon_description}}` | "Cart recovery" | Sales rule description |
| `{{var coupon_expires_date}}` | "2026-11-20" | Expiration date (Y-m-d) |
| `{{var coupon_expires_formatted}}` | "Nov 20, 2025" | Formatted expiration |

### Example Templates

#### Cart Abandonment (Step 1)
```html
<!DOCTYPE html>
<html>
<head>
    <style>
        .cart-item { border: 1px solid #ddd; padding: 10px; margin: 10px 0; }
        .cta-button { background: #4CAF50; color: white; padding: 15px 32px;
                      text-decoration: none; display: inline-block; }
    </style>
</head>
<body>
    <h2>Hi {{var customer_firstname}},</h2>

    <p>You left something in your cart!</p>

    <p>We noticed you didn't complete your purchase. Your items are still waiting:</p>

    <a href="{{var store.getUrl('checkout/cart')}}" class="cta-button">
        Complete Your Purchase
    </a>

    <p>Need help? Reply to this email or call us at {{var store.getPhone()}}</p>
</body>
</html>
```

#### Cart Abandonment (Step 2 - With Coupon)
```html
<!DOCTYPE html>
<html>
<head>
    <style>
        .coupon-code { background: #f4f4f4; padding: 20px; text-align: center;
                       font-size: 32px; font-weight: bold; letter-spacing: 2px; }
        .expiration { color: #ff0000; font-weight: bold; }
    </style>
</head>
<body>
    <h2>Hi {{var customer_firstname}},</h2>

    <p>Still thinking it over? Here's an exclusive offer just for you!</p>

    <div class="coupon-code">{{var coupon_code}}</div>

    <p>Use this code for <strong>{{var coupon_discount_text}}</strong> on your order</p>

    <p class="expiration">⏰ Expires {{var coupon_expires_formatted}}</p>

    <a href="{{var store.getUrl('checkout/cart')}}" class="cta-button">
        Apply Code & Checkout
    </a>
</body>
</html>
```

#### Welcome Email (With First Purchase Coupon)
```html
<!DOCTYPE html>
<html>
<body>
    <h1>Welcome to {{var store.getName()}}, {{var customer_firstname}}! 🎉</h1>

    <p>We're thrilled to have you join our community of happy customers.</p>

    <p>To get you started, here's an exclusive welcome offer:</p>

    <div class="coupon-code">{{var coupon_code}}</div>

    <p>Enjoy <strong>{{var coupon_discount_text}}</strong> on your first order!</p>

    <p><em>Valid until {{var coupon_expires_formatted}}</em></p>

    <h3>What's Next?</h3>
    <ul>
        <li><a href="{{var store.getUrl('catalog')}}">Browse our catalog</a></li>
        <li><a href="{{var store.getUrl('customer/account')}}">Complete your profile</a></li>
        <li><a href="{{var store.getUrl('about')}}">Learn about us</a></li>
    </ul>
</body>
</html>
```

## Monitoring & Analytics

### View Automation Stats

Each segment shows email automation statistics:

- **Total sequences started**: How many customers entered
- **Emails scheduled**: Pending emails
- **Emails sent**: Successfully delivered
- **Emails failed**: Delivery failures

### Sequence Progress Tracking

Track individual customer progress:

- **Status**: Scheduled, Sent, Failed, Skipped
- **Scheduled time**: When email will/was sent
- **Queue ID**: Link to newsletter queue
- **Step number**: Current position in sequence

### Cron Jobs

The automation system includes several cron jobs:

| Job | Schedule | Purpose |
|-----|----------|---------|
| `customersegmentation_process_emails` | Every 5 min | Process scheduled emails |
| `customersegmentation_refresh_segments` | Daily 5 AM | Refresh segment membership |
| `customersegmentation_cleanup_progress` | Daily 2 AM | Clean old progress records |
| `customersegmentation_cleanup_coupons` | Daily 3 AM | Remove expired unused coupons |
| `customersegmentation_automation_report` | Daily 4 AM | Generate performance stats |

### Logs

All automation activity is logged to `var/log/customer_segmentation.log`:

```
2026-07-10 05:35:00 INFO: Started email sequences for 1 customers entering segment 1
2026-07-10 05:35:00 INFO: Sent automation email to customer 3, template 1, queue 2
2026-07-10 05:35:00 INFO: Generated coupon CART3ABC123 for customer 3 using rule 1
```

## Best Practices

### 1. Segment Design

- **Be specific**: Narrow segments = more relevant emails
- **Test conditions**: Verify segment matches expected customers
- **Monitor size**: Track matched customer counts
- **Use website context**: Ensure segments work for specific websites

### 2. Email Timing

- **Start gentle**: First email should be informative, not salesy
- **Escalate gradually**: Increase incentives over time
- **Respect time zones**: Consider customer timezone (via store view)
- **Test sequences**: Run through the entire sequence yourself

### 3. Coupon Strategy

- **Progressive discounts**: Increase discount in later steps
- **Add scarcity**: Shorter expiration creates urgency
- **Track redemption**: Monitor which steps convert best
- **Set minimum order**: Use sales rule conditions to protect margins

### 4. Template Design

- **Mobile-first**: Most emails are read on mobile
- **Clear CTA**: One primary call-to-action per email
- **Test rendering**: Preview in multiple email clients
- **Include unsubscribe**: Respect customer preferences

### 5. Performance Optimization

- **Limit active sequences**: Don't overwhelm customers
- **Monitor bounce rates**: Clean invalid email addresses
- **A/B test**: Try different templates and timings
- **Regular cleanup**: Remove old progress records

## Sales Rule Configuration

For coupon generation to work, sales rules must be configured correctly:

1. **Coupon Type**: "Specific Coupon"
2. **Auto Generation**: Enabled
3. **Uses per Coupon**: Recommended 1 (one-time use)
4. **Uses per Customer**: Recommended 1
5. **Conditions**: Add any restrictions (minimum order amount, product categories, etc.)

Example sales rule for cart abandonment:
```
Name: Cart Abandonment 10%
Discount: 10% off entire cart
Minimum Cart Amount: $50
Coupon Type: Specific Coupon
Auto Generation: Yes
Uses per Coupon: 1
Uses per Customer: 1
```

## Troubleshooting

### Emails Not Sending

**Check these items**:

1. ✅ Customer is subscribed to newsletter
2. ✅ Segment "Auto Email Active" is enabled
3. ✅ Email sequence steps are active (`is_active = 1`)
4. ✅ Newsletter template exists and is valid
5. ✅ Cron is running (`customersegmentation_process_emails`)
6. ✅ Check `var/log/customer_segmentation.log` for errors

### Coupons Not Generating

**Check these items**:

1. ✅ Sales rule is active
2. ✅ Sales rule has "Auto Generation" enabled
3. ✅ Sales rule coupon type is "Specific Coupon"
4. ✅ Sequence step has `generate_coupon = 1`
5. ✅ Valid sales rule ID is configured
6. ✅ Check logs for generation errors

### Duplicate Sequences

**Possible causes**:

- Segment refreshing too frequently
- Customer re-entering segment
- Overlapping segment logic disabled

**Solutions**:

- Review segment refresh schedule
- Add conditions to prevent re-entry
- Check sequence progress before starting new ones

## Advanced Usage

### Exit Sequences

Trigger emails when customers **leave** a segment:

**Use case**: Customer exits "Active Cart" segment (purchased or cart expired)

```
Trigger: Exit Segment
Step 1 (immediate): "Thank you for your purchase!" (for purchasers)
Step 1 (immediate): "Item sold out" (for cart expirations)
```

### Conditional Templates

Use Maho's template directives for conditional content:

```html
{{depend customer.getGroupId() == 1}}
    <p>As a wholesale customer, you get extra benefits!</p>
{{/depend}}

{{if store.getId() == 2}}
    <p>Free shipping on this store!</p>
{{/if}}
```
