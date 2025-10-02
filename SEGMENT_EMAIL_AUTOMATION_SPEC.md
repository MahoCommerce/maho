# Customer Segment Email Automation - Implementation Specification

## Overview

This specification outlines the implementation of automated email sequences when customers enter or exit customer segments. The feature supports multi-step email campaigns (e.g., cart abandonment sequences, welcome series, win-back campaigns) and extends the existing Customer Segmentation and Newsletter modules without creating new core infrastructure.

## Use Cases & Business Applications

### 1. **Cart Abandonment Recovery**
**Segment Condition**:
- Shopping Cart: Cart Status equals "Active"
- Shopping Cart: Cart Updated Date was 1 or more hours ago
- Newsletter subscription status is "Subscribed"
**Trigger**: Enter segment
**Email Sequence**:
- Step 1 (1 hour): "You forgot something!" - Gentle reminder with cart contents
- Step 2 (24 hours): "Still thinking it over?" - Social proof + urgency
- Step 3 (7 days): "Last chance" - 10% discount code + free shipping

**Expected Results**: 15-25% recovery rate, significant revenue recovery

### 2. **Welcome Series for New Customers**
**Segment Condition**:
- Customers registered in last 7 days
- Number of orders equals 0
- Newsletter subscription status is "Subscribed"
**Trigger**: Enter segment
**Email Sequence**:
- Step 1 (30 minutes): "Welcome!" - Brand story + first purchase discount
- Step 2 (3 days): "Getting Started Guide" - How to navigate, top products
- Step 3 (7 days): "Customer favorites" - Best-selling products in their interest category
- Step 4 (14 days): "Join our community" - Social media, reviews, loyalty program

**Expected Results**: Increased engagement, higher lifetime value, reduced churn

### 3. **Post-Purchase Follow-up**
**Segment Condition**: Customers who purchased in last 30 days
**Trigger**: Enter segment
**Email Sequence**:
- Step 1 (1 day): "Thank you!" - Order confirmation, shipping info
- Step 2 (7 days): "How's it going?" - Usage tips, tutorial videos
- Step 3 (21 days): "Rate & Review" - Review request + loyalty points
- Step 4 (30 days): "You might also like..." - Cross-sell recommendations

**Expected Results**: Higher customer satisfaction, more reviews, increased repeat purchases

### 4. **VIP Customer Nurturing**
**Segment Condition**: Customers with >$1000 lifetime value
**Trigger**: Enter segment
**Email Sequence**:
- Step 1 (immediate): "Welcome to VIP!" - Exclusive perks explanation
- Step 2 (7 days): "VIP early access" - New collection preview
- Step 3 (monthly): "Your VIP benefits" - Points balance, exclusive offers

**Expected Results**: Increased loyalty, higher average order value, brand advocacy

### 5. **Win-back Campaigns**
**Segment Condition**:
- Order: Days Since Last Order is greater than 90
- Order: Total Orders is greater than 1
- Newsletter subscription status is "Subscribed"
**Trigger**: Enter segment
**Email Sequence**:
- Step 1 (immediate): "We miss you!" - Soft re-engagement, what's new
- Step 2 (7 days): "Come back with 15% off" - Discount incentive
- Step 3 (14 days): "Final offer" - Bigger discount + free shipping
- Step 4 (30 days): Exit sequence (move to different segment or pause)

**Expected Results**: 5-10% reactivation rate, prevented churn

### 6. **Birthday & Anniversary Marketing**
**Segment Condition**:
- Customer: Days Until Birthday equals 0
- Newsletter subscription status is "Subscribed"
**Trigger**: Enter segment
**Email Sequence**:
- Step 1 (immediate): "Happy Birthday!" - Special birthday discount
- Step 2 (3 days): "Birthday week continues" - Extended offer reminder
- Step 3 (7 days): "Last chance for birthday savings" - Urgency message

**Expected Results**: Emotional connection, increased purchase likelihood

### 6b. **Birthday Reminder Campaign**
**Segment Condition**:
- Customer: Days Until Birthday equals 7
- Newsletter subscription status is "Subscribed"
**Trigger**: Enter segment
**Email Sequence**:
- Step 1 (immediate): "Your birthday is coming!" - Anticipation building
- Step 2 (7 days - on birthday): "Happy Birthday!" - Special birthday discount

**Expected Results**: Higher engagement through anticipation

### 7. **Geographic Expansion**
**Segment Condition**: Customers in newly added shipping regions
**Trigger**: Enter segment
**Email Sequence**:
- Step 1 (immediate): "Now shipping to your area!" - Announcement
- Step 2 (3 days): "Free shipping introduction" - Limited-time offer
- Step 3 (14 days): "Local favorites" - Region-specific product recommendations

**Expected Results**: Market penetration, regional awareness

### 8. **Seasonal Category Interest**
**Segment Condition**: Customers who viewed winter clothing but didn't purchase
**Trigger**: Enter segment (during fall season)
**Email Sequence**:
- Step 1 (1 day): "Winter prep checklist" - Style guide, trending items
- Step 2 (7 days): "Temperature dropping" - Weather-based urgency
- Step 3 (14 days): "Stay warm for less" - Discount on winter items

**Expected Results**: Seasonal conversion boost, inventory movement

### 9. **High-Value Cart Abandonment**
**Segment Condition**:
- Shopping Cart: Cart Status equals "Active"
- Shopping Cart: Grand Total is greater than $200
- Shopping Cart: Cart Updated Date was 2 or more hours ago
- Newsletter subscription status is "Subscribed"
**Trigger**: Enter segment
**Email Sequence**:
- Step 1 (2 hours): "Complete your premium selection" - White-glove service offer
- Step 2 (24 hours): "Personal shopping assistance" - Phone consultation offer
- Step 3 (72 hours): "Exclusive VIP pricing" - Special discount for high-value customers

**Expected Results**: Higher recovery rates for premium customers

### 10. **Re-engagement After Support Issues**
**Segment Condition**: Customers who had support tickets resolved in last 7 days
**Trigger**: Enter segment
**Email Sequence**:
- Step 1 (1 day): "How did we do?" - Support satisfaction survey
- Step 2 (7 days): "Thanks for your patience" - Apology discount/credit
- Step 3 (14 days): "Moving forward together" - Feature updates, improvements made

**Expected Results**: Customer retention after issues, trust rebuilding

### 11. **Product Education Series**
**Segment Condition**: Customers who purchased complex products (electronics, appliances)
**Trigger**: Enter segment
**Email Sequence**:
- Step 1 (1 day): "Getting started guide" - Setup instructions, quick wins
- Step 2 (7 days): "Pro tips & tricks" - Advanced features, video tutorials
- Step 3 (30 days): "Maximizing your investment" - Maintenance tips, accessories
- Step 4 (90 days): "Upgrade opportunities" - Related products, trade-in offers

**Expected Results**: Reduced support costs, higher satisfaction, accessory sales

### 12. **Exit Intent Recovery**
**Segment Condition**: Customers who triggered exit-intent popup but didn't convert
**Trigger**: Enter segment
**Email Sequence**:
- Step 1 (30 minutes): "Wait! Here's 10% off" - Quick discount offer
- Step 2 (24 hours): "Still browsing?" - Product recommendations based on viewed items
- Step 3 (72 hours): "One more try" - Free shipping offer

**Expected Results**: Conversion of bounced traffic

### 13. **Loyalty Program Advancement**
**Segment Condition**: Customers close to next loyalty tier (within $50)
**Trigger**: Enter segment
**Email Sequence**:
- Step 1 (immediate): "You're almost there!" - Progress notification
- Step 2 (7 days): "Just $X away from Gold status" - Tier benefits reminder
- Step 3 (14 days): "Easy ways to reach Gold" - Product suggestions to hit tier

**Expected Results**: Accelerated loyalty program engagement

### 14. **Inventory Clearance Targeted**
**Segment Condition**: Customers who viewed products now on clearance
**Trigger**: Enter segment (when items go on sale)
**Email Sequence**:
- Step 1 (immediate): "Price drop alert!" - Items they viewed are now on sale
- Step 2 (3 days): "Limited stock remaining" - Scarcity messaging
- Step 3 (7 days): "Final hours" - Last chance before removal

**Expected Results**: Faster inventory turnover, recovered browse abandonment

### 15. **Referral Program Activation**
**Segment Condition**: Satisfied customers (5-star reviews, repeat purchasers)
**Trigger**: Enter segment
**Email Sequence**:
- Step 1 (immediate): "Love us? Share us!" - Referral program introduction
- Step 2 (7 days): "Your friends will thank you" - Easy sharing tools
- Step 3 (21 days): "Give $10, Get $10" - Incentive details and tracking

**Expected Results**: Organic customer acquisition, viral growth

## Segment Configuration Examples

### Cart Abandonment Setup
```
Segment Name: "Cart Abandoned 1+ Hours"
Conditions:
- Shopping Cart: Cart Status equals "Active"
- Shopping Cart: Cart Updated Date was 1 or more hours ago
- Newsletter: Subscription Status equals "Subscribed"
Auto Email: Enabled
Trigger: Enter Segment
```

### Welcome Series Setup
```
Segment Name: "New Customers - Subscribed"
Conditions:
- Customer: Days Since Registration is less than 8
- Customer: Number of Orders equals 0
- Newsletter: Subscription Status equals "Subscribed"
Auto Email: Enabled
Trigger: Enter Segment
```

### High-Value Customer Setup
```
Segment Name: "VIP Customers"
Conditions:
- Customer: Lifetime Sales Amount is greater than $1000
- Customer: Number of Orders is greater than 5
- Newsletter: Subscription Status equals "Subscribed"
Auto Email: Enabled
Trigger: Enter Segment
```

### Win-back Setup
```
Segment Name: "Inactive 90+ Days"
Conditions:
- Order: Days Since Last Order is greater than 90
- Order: Total Orders is greater than 1
- Newsletter: Subscription Status equals "Subscribed"
Auto Email: Enabled
Trigger: Enter Segment
```

These use cases demonstrate the versatility and business value of segment-based email automation, covering the entire customer lifecycle from acquisition through retention and win-back.

## Coupon Generation Examples

### Cart Abandonment with Progressive Discounts
```
Sequence Step 1 (1 hour):
- Template: "You forgot something!"
- Generate Coupon: No
- Content: Gentle reminder with cart contents

Sequence Step 2 (24 hours):
- Template: "Still thinking it over?"
- Generate Coupon: Yes
- Base Sales Rule: "Cart Abandonment 10% Off"
- Coupon Prefix: "CART"
- Expires: 7 days
- Content: "Complete your purchase with {{var coupon_code}} for {{var coupon_discount_text}}!"

Sequence Step 3 (7 days):
- Template: "Last chance!"
- Generate Coupon: Yes
- Base Sales Rule: "Cart Abandonment 15% Off + Free Shipping"
- Coupon Prefix: "SAVE"
- Expires: 3 days
- Content: "Final offer: {{var coupon_code}} expires {{var coupon_expires_formatted}}!"
```

### Birthday Campaign with Personal Coupon
```
Sequence Step 1 (on birthday):
- Template: "Happy Birthday!"
- Generate Coupon: Yes
- Base Sales Rule: "Birthday 20% Off"
- Coupon Prefix: "BDAY"
- Expires: 30 days
- Content: "Happy Birthday! Celebrate with {{var coupon_code}} for {{var coupon_discount_text}} - valid until {{var coupon_expires_formatted}}"
```

### Welcome Series with First Purchase Incentive
```
Sequence Step 1 (30 minutes):
- Template: "Welcome to our store!"
- Generate Coupon: Yes
- Base Sales Rule: "Welcome 15% Off First Order"
- Coupon Prefix: "WELCOME"
- Expires: 14 days
- Content: "Welcome! Start your journey with {{var coupon_code}} for {{var coupon_discount_text}} on your first order"
```

### Available Template Variables for Coupons
When `generate_coupon` is enabled, these variables are available in newsletter templates:

- `{{var coupon_code}}` - The generated coupon code (e.g., "CART12345AB")
- `{{var coupon_expires_date}}` - Expiration date (YYYY-MM-DD format)
- `{{var coupon_expires_formatted}}` - Formatted expiration date (e.g., "Dec 15, 2024")
- `{{var coupon_discount_amount}}` - Discount amount from sales rule
- `{{var coupon_discount_text}}` - Human-readable discount (e.g., "15% off", "$10 off")
- `{{var coupon_description}}` - Sales rule description

## Database Schema Changes ✅ **IMPLEMENTED**

### 1. Extend `customer_segment` table ✅

```sql
ALTER TABLE customer_segment ADD COLUMN (
    auto_email_trigger enum('none', 'enter', 'exit') DEFAULT 'none',
    auto_email_active tinyint(1) DEFAULT 0
);
```

**Field Descriptions:**
- `auto_email_trigger`: When to trigger sequence ('none', 'enter', 'exit')
- `auto_email_active`: Enable/disable automation for this segment

### 2. Create `customer_segment_email_sequence` table ✅

```sql
CREATE TABLE customer_segment_email_sequence (
    sequence_id int PRIMARY KEY AUTO_INCREMENT,
    segment_id int NOT NULL,
    template_id int NOT NULL,
    step_number int NOT NULL,
    delay_minutes int NOT NULL DEFAULT 0,
    is_active tinyint(1) DEFAULT 1,
    max_sends int DEFAULT 1,
    generate_coupon tinyint(1) DEFAULT 0,
    coupon_sales_rule_id int DEFAULT NULL,
    coupon_prefix varchar(50) DEFAULT NULL,
    coupon_expires_days int DEFAULT 30,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_segment_step (segment_id, step_number),
    FOREIGN KEY (segment_id) REFERENCES customer_segment(segment_id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES newsletter_template(template_id),
    FOREIGN KEY (coupon_sales_rule_id) REFERENCES salesrule(rule_id) ON DELETE SET NULL
);
```

**Field Descriptions:**
- `segment_id`: Links to customer segment
- `template_id`: Newsletter template for this step
- `step_number`: Order of email in sequence (1, 2, 3...)
- `delay_minutes`: Delay before sending this step (0 = immediate)
- `is_active`: Enable/disable this step
- `max_sends`: Maximum sends per customer for this step
- `generate_coupon`: Whether to generate unique coupon for this step
- `coupon_sales_rule_id`: Base sales rule to use for coupon generation
- `coupon_prefix`: Prefix for generated coupon codes (e.g., "CART", "BDAY")
- `coupon_expires_days`: Number of days until generated coupon expires

### 3. Extend `newsletter_queue` table ✅

```sql
ALTER TABLE newsletter_queue ADD COLUMN (
    automation_source varchar(50) DEFAULT NULL,
    automation_source_id int DEFAULT NULL
);
```

**Field Descriptions:**
- `automation_source`: Identifies automation source ('customer_segmentation')
- `automation_source_id`: Links back to originating segment

### 4. Create customer progress tracking table ✅

```sql
CREATE TABLE customer_segment_sequence_progress (
    progress_id int PRIMARY KEY AUTO_INCREMENT,
    customer_id int NOT NULL,
    segment_id int NOT NULL,
    sequence_id int NOT NULL,
    queue_id int DEFAULT NULL,
    step_number int NOT NULL,
    trigger_type enum('enter', 'exit') NOT NULL,
    scheduled_at timestamp NULL,
    sent_at timestamp NULL,
    status enum('scheduled', 'sent', 'failed', 'skipped') DEFAULT 'scheduled',
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_customer_segment (customer_id, segment_id),
    INDEX idx_scheduled (scheduled_at, status),
    FOREIGN KEY (customer_id) REFERENCES customer_entity(entity_id) ON DELETE CASCADE,
    FOREIGN KEY (segment_id) REFERENCES customer_segment(segment_id) ON DELETE CASCADE,
    FOREIGN KEY (sequence_id) REFERENCES customer_segment_email_sequence(sequence_id) ON DELETE CASCADE,
    FOREIGN KEY (queue_id) REFERENCES newsletter_queue(queue_id) ON DELETE SET NULL
);
```

**Field Descriptions:**
- `customer_id`: Customer receiving the email
- `segment_id`: Segment that triggered the sequence
- `sequence_id`: Specific sequence step
- `queue_id`: Newsletter queue entry (when scheduled)
- `step_number`: Which step in the sequence
- `trigger_type`: Whether triggered by 'enter' or 'exit'
- `scheduled_at`: When email is scheduled to send
- `sent_at`: When email was actually sent
- `status`: Current status of this sequence step

## Core Implementation ✅ **IMPLEMENTED**

The email automation system consists of the following components:

### 1. Model Layer
- **`Maho_CustomerSegmentation_Model_Segment`** - Extended with email automation methods for starting sequences, checking active sequences, etc.
- **`Maho_CustomerSegmentation_Model_EmailSequence`** - Represents individual steps in an email sequence
- **`Maho_CustomerSegmentation_Model_SequenceProgress`** - Tracks customer progress through email sequences
- **`Maho_CustomerSegmentation_Helper_Coupon`** - Generates and manages unique coupons for automation

### 2. Resource Models
- **`Maho_CustomerSegmentation_Model_Resource_Segment`** - Extended with methods to check active sequences
- **`Maho_CustomerSegmentation_Model_Resource_EmailSequence`** - Handles sequence CRUD operations and validation
- **`Maho_CustomerSegmentation_Model_Resource_SequenceProgress`** - Manages progress records and queries ready-to-send sequences

### 3. Observer & Cron
- **`Maho_CustomerSegmentation_Model_Observer_EmailAutomation`** - Handles segment refresh events and triggers sequences
- **`Maho_CustomerSegmentation_Model_Cron`** - Contains all cron job methods (processScheduledEmails, cleanupOldProgress, cleanupExpiredCoupons, generateAutomationReport)

### 4. Admin Interface
- **`Maho_CustomerSegmentation_Block_Adminhtml_Segment_Edit_Tab_EmailAutomation`** - Admin tab for enabling automation and setting trigger type
- **`Maho_CustomerSegmentation_Block_Adminhtml_Segment_Edit_Tab_EmailSequences`** - Grid showing all sequence steps for a segment
- **`Maho_CustomerSegmentation_Block_Adminhtml_Segment_Sequence_Edit`** - Form for creating/editing individual sequence steps
- **Controller actions** - In `Maho_CustomerSegmentation_Adminhtml_CustomerSegmentation_IndexController` for managing sequences

### Key Implementation Files
```
app/code/core/Maho/CustomerSegmentation/
├── Model/
│   ├── Segment.php (extended with automation methods)
│   ├── EmailSequence.php
│   ├── SequenceProgress.php
│   ├── Cron.php
│   ├── Observer/
│   │   └── EmailAutomation.php
│   └── Resource/
│       ├── EmailSequence.php
│       ├── EmailSequence/
│       │   └── Collection.php
│       ├── SequenceProgress.php
│       └── SequenceProgress/
│           └── Collection.php
├── Helper/
│   └── Coupon.php
├── Block/Adminhtml/Segment/
│   ├── Edit/Tab/
│   │   ├── EmailAutomation.php
│   │   └── EmailSequences.php
│   └── Sequence/
│       ├── Edit.php
│       └── Edit/Form.php
└── controllers/Adminhtml/
    └── CustomerSegmentation_IndexController.php (extended)
```

## Configuration ✅ **IMPLEMENTED**

### Event Observers
The system registers the `customer_segment_refresh_after` event observer in `config.xml`:
- Observer: `customersegmentation/observer_emailAutomation::onSegmentRefreshAfter`
- Also observes `newsletter_subscriber_save_after` and `customer_delete_after` events

### Cron Jobs
Four cron jobs are configured in `config.xml` (see Cron Jobs section for details)

## Cron Jobs

The email automation system uses scheduled cron jobs to process email sequences, maintain the database, and generate reports.

### 1. Email Sequence Processor

**Cron Job Name**: `customersegmentation_process_emails`

**Method**: `customersegmentation/cron::processScheduledEmails`

**Schedule**: Every 5 minutes (`*/5 * * * *`)

**What It Does**:
1. Checks if email automation is enabled in system configuration
2. Dispatches event `customer_segmentation_process_scheduled_emails` which triggers the observer
3. Observer queries the `customer_segment_sequence_progress` table for emails scheduled to be sent (where `scheduled_at <= NOW()` and `status = 'scheduled'`)
4. For each ready sequence:
   - Verifies customer is still subscribed to newsletter
   - Loads the email template and sequence configuration
   - Generates unique coupon codes if configured for this step
   - Creates template variables including customer data and coupon information
   - Creates a newsletter queue entry
   - Sends the email immediately via the newsletter system
   - Updates progress record to `status = 'sent'` with timestamp and queue_id
5. Processes up to 100 sequences per run to prevent overload
6. Logs all activity to `var/log/customer_segmentation.log`

**Error Handling**:
- If customer is no longer subscribed → marks progress as `skipped`
- If template is invalid → marks progress as `failed` and logs exception
- If email sending fails → marks progress as `failed` and logs exception
- All errors are logged to `var/log/exception.log` and `var/log/customer_segmentation.log`

**Performance Considerations**:
- Batch size: 100 sequences per run (configurable in code)
- Run frequency: Every 5 minutes ensures timely delivery
- Index on `(scheduled_at, status)` optimizes query performance
- Processes oldest scheduled emails first

**Example Log Output**:
```
2025-09-30 12:00:01: Processing 15 scheduled email sequences
2025-09-30 12:00:03: Generated coupon CART12345AB for customer 42 in sequence 8
2025-09-30 12:00:03: Sent automation email to customer 42, template 5, queue 103
2025-09-30 12:00:05: Email automation cron: processed 15, failed 0 emails in 4.23s
```

---

### 2. Progress Record Cleanup

**Cron Job Name**: `customersegmentation_cleanup_progress`

**Method**: `customersegmentation/cron::cleanupOldProgress`

**Schedule**: Daily at 2:00 AM (`0 2 * * *`)

**What It Does**:
1. Checks if email automation is enabled in system configuration
2. Retrieves cleanup threshold from config (default: 90 days)
3. Deletes sequence progress records older than the threshold that are in final states (`sent`, `failed`, `skipped`)
4. Logs number of deleted records to `var/log/customer_segmentation.log`

**Configuration**:
- `customer_segmentation/email_automation/cleanup_days` - Days to keep old records (default: 90)

**Why This Matters**:
- Prevents the `customer_segment_sequence_progress` table from growing indefinitely
- Keeps database performance optimal
- Old completed records are not needed for operations
- Retains enough history for debugging and analysis

**Example Log Output**:
```
2025-09-30 02:00:05: Cleaned up 1,247 old sequence progress records (older than 90 days)
```

**Manual Execution**:
```bash
./maho cron:run customersegmentation_cleanup_progress
```

---

### 3. Expired Coupon Cleanup

**Cron Job Name**: `customersegmentation_cleanup_coupons`

**Method**: `customersegmentation/cron::cleanupExpiredCoupons`

**Schedule**: Daily at 3:00 AM (`0 3 * * *`)

**What It Does**:
1. Checks if email automation is enabled in system configuration
2. Retrieves cleanup threshold from config (default: 30 days)
3. Finds automation-generated coupons that:
   - Have expired (past `expiration_date`)
   - Have never been used (`times_used = 0`)
   - Are older than the threshold
4. Deletes these unused expired coupons from the `salesrule_coupon` table
5. Logs number of deleted coupons to `var/log/customer_segmentation.log`

**Configuration**:
- `customer_segmentation/email_automation/coupon_cleanup_days` - Days after expiration to keep unused coupons (default: 30)

**Why This Matters**:
- Email automation can generate thousands of unique coupons
- Most coupons are never redeemed
- Expired unused coupons serve no purpose and waste database space
- Keeps the `salesrule_coupon` table manageable

**Example Log Output**:
```
2025-09-30 03:00:02: Cleaned up 542 expired automation coupons (older than 30 days)
```

**Manual Execution**:
```bash
./maho cron:run customersegmentation_cleanup_coupons
```

---

### 4. Daily Automation Report

**Cron Job Name**: `customersegmentation_automation_report`

**Method**: `customersegmentation/cron::generateAutomationReport`

**Schedule**: Daily at 4:00 AM (`0 4 * * *`)

**What It Does**:
1. Checks if email automation is enabled in system configuration
2. Queries the `customer_segment_sequence_progress` table for last 24 hours
3. Generates statistics:
   - Number of emails sent
   - Number of failed sends
   - Number currently scheduled
   - Total active sequences
4. Logs summary report to `var/log/customer_segmentation.log`

**Why This Matters**:
- Provides daily snapshot of automation system health
- Helps identify trends and issues
- Easy to grep log file for historical performance
- Alerts to sudden changes in send volume or failure rate

**Example Log Output**:
```
2025-09-30 04:00:01: Daily automation report: 3,421 sent, 12 failed, 156 scheduled, 3,589 total active
```

**Monitoring**:
```bash
# View last 30 days of reports
grep "Daily automation report" var/log/customer_segmentation.log | tail -30

# Check for high failure rates
grep "Daily automation report" var/log/customer_segmentation.log | grep -E "failed, [5-9][0-9]|failed, [0-9]{3,}"
```

**Manual Execution**:
```bash
./maho cron:run customersegmentation_automation_report
```

---

## Monitoring All Cron Jobs

### Viewing Cron Status

To check if cron jobs are configured and running:

```bash
# List all cron jobs
./maho cron:list

# List only customer segmentation cron jobs
./maho cron:list | grep customersegmentation

# Check last execution times for all automation cron jobs
./maho db:query "SELECT job_code, status, executed_at, finished_at, messages
                 FROM cron_schedule
                 WHERE job_code LIKE 'customersegmentation_%'
                 ORDER BY executed_at DESC LIMIT 20"

# Check for pending email sequences
./maho db:query "SELECT COUNT(*) as pending_count
                 FROM customer_segment_sequence_progress
                 WHERE status = 'scheduled'
                 AND scheduled_at <= NOW()"

# Check for failed email sequences
./maho db:query "SELECT customer_id, segment_id, step_number, created_at
                 FROM customer_segment_sequence_progress
                 WHERE status = 'failed'
                 ORDER BY created_at DESC LIMIT 20"

# View automation activity log
tail -f var/log/customer_segmentation.log
```

### Manual Cron Execution

To manually trigger any cron job for testing:

```bash
# Run email sequence processor
./maho cron:run customersegmentation_process_emails

# Run progress cleanup
./maho cron:run customersegmentation_cleanup_progress

# Run coupon cleanup
./maho cron:run customersegmentation_cleanup_coupons

# Run daily report
./maho cron:run customersegmentation_automation_report

# Run all customer segmentation cron jobs at once
./maho cron:run customersegmentation_process_emails && \
./maho cron:run customersegmentation_cleanup_progress && \
./maho cron:run customersegmentation_cleanup_coupons && \
./maho cron:run customersegmentation_automation_report
```
