---
description: Developer guide to Maho customer segment email automation, covering segment triggers, sequence scheduling, the newsletter queue, database schema and extension points.
---

# Customer Segment Email Automation - Developer Guide <span class="version-badge">v25.11+</span>

This guide covers the technical implementation details of Maho's Customer Segment Email Automation system, including database schema, architecture, code examples, and extension points.

## Architecture Overview

The email automation system is built on three core subsystems:

```
┌─────────────────────┐
│  Customer Segments  │  (Segment matching & membership)
└──────────┬──────────┘
           │ triggers
           ↓
┌─────────────────────┐
│  Email Automation   │  (Sequence scheduling & progression)
└──────────┬──────────┘
           │ generates
           ↓
┌─────────────────────┐
│ Newsletter System   │  (Email delivery via queue)
└─────────────────────┘
```

### Key Components

| Component | Location | Purpose |
|-----------|----------|---------|
| **Segment Model** | `Maho_CustomerSegmentation_Model_Segment` | Manages segment logic and customer matching |
| **Email Automation Observer** | `Maho_CustomerSegmentation_Model_Observer_EmailAutomation` | Handles segment change events and email sending |
| **Email Sequence Model** | `Maho_CustomerSegmentation_Model_EmailSequence` | Represents individual sequence steps |
| **Sequence Progress Model** | `Maho_CustomerSegmentation_Model_SequenceProgress` | Tracks customer progress through sequences |
| **Coupon Helper** | `Maho_CustomerSegmentation_Helper_Coupon` | Generates and manages automation coupons |
| **Cron Model** | `Maho_CustomerSegmentation_Model_Cron` | Executes scheduled tasks |

## Database Schema

### customer_segment (Extended)

Fields added for email automation:

```php
ALTER TABLE customer_segment ADD COLUMN (
    auto_email_active SMALLINT NOT NULL DEFAULT 0,            -- Master on/off switch
    allow_overlapping_sequences SMALLINT NOT NULL DEFAULT 0   -- Allow >1 active sequence per customer
);
```

The `allow_overlapping_sequences` flag controls whether a customer can have more than one active sequence in the segment at once (consumed by `startEmailSequence()` in the Segment model).

!!! note "Trigger Type Configuration"
    Trigger type ('enter' or 'exit') is configured **per-sequence** using the `trigger_event` field in the `customer_segment_email_sequence` table. This allows one segment to have both enter AND exit sequences.

### customer_segment_email_sequence

Stores email sequence configuration:

```sql
CREATE TABLE customer_segment_email_sequence (
    sequence_id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    segment_id INT UNSIGNED NOT NULL,
    trigger_event VARCHAR(10) NOT NULL DEFAULT 'enter',  -- 'enter' or 'exit'
    template_id INT UNSIGNED NOT NULL,          -- Newsletter template
    step_number INT UNSIGNED NOT NULL,
    delay_minutes INT UNSIGNED NOT NULL DEFAULT 0,
    is_active SMALLINT NOT NULL DEFAULT 1,
    max_sends INT UNSIGNED NOT NULL DEFAULT 1,
    generate_coupon SMALLINT NOT NULL DEFAULT 0,
    coupon_sales_rule_id INT UNSIGNED NULL,
    coupon_prefix VARCHAR(50) NULL,
    coupon_expires_days INT UNSIGNED NOT NULL DEFAULT 30,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_segment_trigger_step (segment_id, trigger_event, step_number),
    FOREIGN KEY (segment_id) REFERENCES customer_segment(segment_id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES newsletter_template(template_id),
    FOREIGN KEY (coupon_sales_rule_id) REFERENCES salesrule(rule_id) ON DELETE SET NULL
);
```

**Key Field: `trigger_event`**
- Values: `'enter'` or `'exit'`
- Determines when this sequence is triggered
- Allows independent enter and exit sequences for the same segment
- Step numbers are unique per (segment_id, trigger_event) combination

### customer_segment_sequence_progress

Tracks individual customer progress:

```sql
CREATE TABLE customer_segment_sequence_progress (
    progress_id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    segment_id INT UNSIGNED NOT NULL,
    sequence_id INT UNSIGNED NOT NULL,
    queue_id INT UNSIGNED NULL,                 -- Links to newsletter_queue
    step_number INT UNSIGNED NOT NULL,
    trigger_type VARCHAR(10) NOT NULL,          -- 'enter' or 'exit'
    scheduled_at TIMESTAMP NULL,                -- When to send
    sent_at TIMESTAMP NULL,                     -- When sent
    status VARCHAR(20) NOT NULL DEFAULT 'scheduled',  -- 'scheduled', 'sent', 'failed', 'skipped'
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_customer_segment (customer_id, segment_id),
    INDEX idx_scheduled (scheduled_at, status),
    FOREIGN KEY (customer_id) REFERENCES customer_entity(entity_id) ON DELETE CASCADE,
    FOREIGN KEY (segment_id) REFERENCES customer_segment(segment_id) ON DELETE CASCADE,
    FOREIGN KEY (sequence_id) REFERENCES customer_segment_email_sequence(sequence_id) ON DELETE CASCADE,
    FOREIGN KEY (queue_id) REFERENCES newsletter_queue(queue_id) ON DELETE SET NULL
);
```

### newsletter_queue (Extended)

New fields for automation tracking:

```php
ALTER TABLE newsletter_queue ADD COLUMN (
    customer_segment_ids VARCHAR(255) DEFAULT NULL,  -- Customer Segment IDs (comma-separated)
    automation_source VARCHAR(50) DEFAULT NULL,      -- 'customer_segmentation'
    automation_source_id INT UNSIGNED DEFAULT NULL   -- segment_id
);
```

## Event System

### Events Dispatched

#### customer_segment_refresh_after

Fired after a segment refreshes its customer membership.

```php
Mage::dispatchEvent('customer_segment_refresh_after', [
    'segment' => $segment,                       // Maho_CustomerSegmentation_Model_Segment
    'matched_customers' => $customerIds,         // array of currently matched customer IDs
    'previous_customers' => $previousCustomerIds, // array of customer IDs before this refresh
]);
```

The `previous_customers` payload is required to determine who entered (matched minus previous) and who exited (previous minus matched).

**Use case**: Start email sequences when customers enter/exit segments

#### customer_segmentation_process_scheduled_emails

Fired by cron to process ready-to-send emails.

```php
Mage::dispatchEvent('customer_segmentation_process_scheduled_emails');
```

**Use case**: Trigger email queue processing

#### newsletter_subscriber_save_after

Standard Maho event, observed to stop sequences on unsubscribe.

```php
Mage::dispatchEvent('newsletter_subscriber_save_after', [
    'subscriber' => $subscriber,  // Mage_Newsletter_Model_Subscriber
]);
```

**Use case**: Cleanup sequences when customer unsubscribes

#### customer_delete_after

Standard Maho event, observed to cleanup sequence data.

```php
Mage::dispatchEvent('customer_delete_after', [
    'customer' => $customer,  // Mage_Customer_Model_Customer
]);
```

**Use case**: Remove orphaned sequence progress records

### Observer Configuration

Observers are registered with the `#[Maho\Config\Observer]` PHP attribute on each observer method, not via XML `<events>`. The email-automation observer subscribes to its events like this:

```php
class Maho_CustomerSegmentation_Model_Observer_EmailAutomation
{
    #[Maho\Config\Observer('customer_segment_refresh_after')]
    public function onSegmentRefreshAfter(Maho\Event\Observer $observer): void { /* ... */ }

    #[Maho\Config\Observer('newsletter_subscriber_save_after')]
    public function onNewsletterSubscriberSaveAfter(Maho\Event\Observer $observer): void { /* ... */ }

    #[Maho\Config\Observer('customer_delete_after')]
    public function onCustomerDeleteAfter(Maho\Event\Observer $observer): void { /* ... */ }

    #[Maho\Config\Observer('customer_segmentation_process_scheduled_emails')]
    public function processScheduledEmails(Maho\Event\Observer $observer): void { /* ... */ }
}
```

## Code Examples

### Programmatically Start a Sequence

```php
// Load segment
$segment = Mage::getModel('customersegmentation/segment')->load($segmentId);

// Start sequence for a customer
$customerId = 123;
$triggerType = Maho_CustomerSegmentation_Model_EmailSequence::TRIGGER_ENTER;
// or: Maho_CustomerSegmentation_Model_EmailSequence::TRIGGER_EXIT

$segment->startEmailSequence($customerId, $triggerType);
```

### Check if Customer is in an Active Sequence

```php
$segment = Mage::getModel('customersegmentation/segment')->load($segmentId);
$customerId = 123;

// Check specific trigger type
if ($segment->hasActiveSequence($customerId, 'enter')) {
    // Customer has active enter sequence
}

// Check any trigger type
if ($segment->hasAnyActiveSequence($customerId)) {
    // Customer has any active sequence
}
```

### Get Sequence Progress for a Customer

```php
$collection = Mage::getResourceModel('customersegmentation/sequenceProgress_collection')
    ->addCustomerFilter($customerId)
    ->addSegmentFilter($segmentId)
    ->addStatusFilter('scheduled');  // or 'sent', 'failed', 'skipped'

foreach ($collection as $progress) {
    /** @var Maho_CustomerSegmentation_Model_SequenceProgress $progress */
    echo "Step {$progress->getStepNumber()}: {$progress->getStatus()}\n";
    echo "Scheduled: {$progress->getScheduledAt()}\n";

    if ($progress->getSentAt()) {
        echo "Sent: {$progress->getSentAt()}\n";
    }
}
```

### Manually Process Scheduled Emails

```php
// The cron job itself does NOT call the observer directly. It checks the
// enabled flag and dispatches the event, which the observer is subscribed to:
//   Maho_CustomerSegmentation_Model_Cron::processScheduledEmails()
//     -> Mage::dispatchEvent('customer_segmentation_process_scheduled_emails')
// To process the queue manually you can dispatch the same event:
Mage::dispatchEvent('customer_segmentation_process_scheduled_emails');

// Or invoke the observer method directly:
$observer = Mage::getModel('customersegmentation/observer_emailAutomation');
$observer->processScheduledEmails(new Varien_Event_Observer());
```

### Generate a Coupon

```php
$helper = Mage::helper('customersegmentation/coupon');

$couponCode = $helper->generateCustomerCoupon(
    $customerId,      // int
    $salesRuleId,     // int
    'CART',           // string prefix
    30                // int days until expiration
);

if ($couponCode) {
    echo "Generated: {$couponCode}\n";
}
```

### Get Coupon Template Variables

```php
$helper = Mage::helper('customersegmentation/coupon');
$rule = Mage::getModel('salesrule/rule')->load($ruleId);

$variables = $helper->getCouponTemplateVariables(
    'CART123ABC',           // coupon code
    '2025-11-20',          // expiration date (Y-m-d)
    $rule                   // Mage_SalesRule_Model_Rule
);

print_r($variables);
/*
Array(
    [coupon_code] => CART123ABC
    [coupon_expires_date] => 2025-11-20
    [coupon_expires_formatted] => Nov 20, 2025
    [coupon_discount_amount] => 15
    [coupon_discount_text] => 15% off
    [coupon_description] => Cart abandonment discount
)
*/
```

### Validate a Sales Rule for Coupon Generation

```php
$helper = Mage::helper('customersegmentation/coupon');
$errors = $helper->validateSalesRule($ruleId);

if (!empty($errors)) {
    foreach ($errors as $error) {
        echo "Error: {$error}\n";
    }
}
```

### Create Email Sequence Programmatically

```php
$sequence = Mage::getModel('customersegmentation/emailSequence');
$sequence->setSegmentId($segmentId)
         ->setTemplateId($templateId)
         ->setStepNumber(1)
         ->setDelayMinutes(60)  // 1 hour delay
         ->setIsActive(1)
         ->setGenerateCoupon(1)
         ->setCouponSalesRuleId($ruleId)
         ->setCouponPrefix('CART')
         ->setCouponExpiresDays(7)
         ->save();
```

### Get Automation Statistics

```php
$segment = Mage::getModel('customersegmentation/segment')->load($segmentId);
$stats = $segment->getEmailAutomationStats();

print_r($stats);
/*
Array(
    [total] => 150              // total progress records for the segment
    [scheduled] => 45
    [sent] => 100
    [failed] => 5
    [skipped] => 0
    [unique_customers] => 120
)
*/
```

!!! note
    `getEmailAutomationStats()` returns progress-record counts only (from `getSegmentStats()`). There is no built-in open/click/conversion-rate tracking.

## Extending the System

### Add Custom Template Variables

Hook into the email sending process to add custom variables:

```php
class My_Module_Model_Observer
{
    public function addCustomTemplateVariables(Varien_Event_Observer $observer)
    {
        // You would need to create this event in the core
        // This is a conceptual example
        $variables = $observer->getVariables();
        $customer = $observer->getCustomer();

        // Add custom data
        $variables['customer_loyalty_points'] = $this->getLoyaltyPoints($customer);
        $variables['customer_tier'] = $this->getCustomerTier($customer);
        $variables['recommended_products'] = $this->getRecommendations($customer);
    }
}
```

### Create Custom Trigger Types

While the core supports 'enter' and 'exit', you could extend this:

```php
// In your module's observer
public function onCustomEvent(Varien_Event_Observer $observer)
{
    $customer = $observer->getCustomer();
    $segment = $this->determineSegment($customer);

    if ($segment && $segment->hasEmailAutomation()) {
        // Start custom sequence
        $segment->startEmailSequence(
            $customer->getId(),
            'custom_trigger'  // Your custom trigger
        );
    }
}
```

### Implement Sequence Callbacks

Add logic after specific sequence steps:

```php
class My_Module_Model_Observer
{
    public function afterSequenceEmailSent(Varien_Event_Observer $observer)
    {
        $progress = $observer->getProgress();
        $customer = $observer->getCustomer();

        // Custom logic based on step
        if ($progress->getStepNumber() === 3) {
            // Tag customer in CRM
            $this->tagInCrm($customer, 'abandoned_cart_step_3');
        }
    }
}
```

## Performance Optimization

### Batching Email Processing

The system processes emails in batches:

```php
// In Maho_CustomerSegmentation_Model_Resource_SequenceProgress
public function getReadyToSendSequences(int $limit = 100): array
{
    // Limits query to prevent memory issues
    // Default: 100 emails per cron run
}
```

To adjust batch size, override this method in your module.

### Index Optimization

Critical indexes for performance:

```sql
-- Progress lookup
CREATE INDEX idx_customer_segment ON customer_segment_sequence_progress(customer_id, segment_id);

-- Ready-to-send query
CREATE INDEX idx_scheduled ON customer_segment_sequence_progress(scheduled_at, status);

-- Segment lookup
CREATE INDEX idx_segment_active ON customer_segment(is_active, auto_email_active);
```

### Caching Strategies

```php
// Cache segment email sequences
$cacheKey = "segment_sequences_{$segmentId}";
$sequences = Mage::app()->getCache()->load($cacheKey);

if (!$sequences) {
    $sequences = $segment->getEmailSequences();
    Mage::app()->getCache()->save(
        serialize($sequences),
        $cacheKey,
        [Maho_CustomerSegmentation_Model_Segment::CACHE_TAG],
        3600  // 1 hour
    );
}
```

## Testing

### Unit Testing Email Sequences

```php
class Maho_CustomerSegmentation_Test_Model_SegmentTest extends PHPUnit\Framework\TestCase
{
    public function testStartEmailSequence()
    {
        $segment = Mage::getModel('customersegmentation/segment');
        $segment->setAutoEmailActive(1)
                ->setAutoEmailTrigger('enter')
                ->setId(1);

        $customerId = 999;
        $segment->startEmailSequence($customerId, 'enter');

        // Verify progress record created
        $collection = Mage::getResourceModel('customersegmentation/sequenceProgress_collection')
            ->addCustomerFilter($customerId)
            ->addSegmentFilter(1);

        $this->assertGreaterThan(0, $collection->getSize());
    }
}
```

### Integration Testing

```php
public function testCartAbandonmentSequence()
{
    // Create test customer
    $customer = $this->createTestCustomer();
    $this->subscribeToNewsletter($customer);

    // Create abandoned cart
    $quote = $this->createAbandonedCart($customer);

    // Trigger segment refresh
    $segment = Mage::getModel('customersegmentation/segment')->load(1);
    $segment->refreshCustomers();

    // Verify sequence started
    $progress = Mage::getResourceModel('customersegmentation/sequenceProgress_collection')
        ->addCustomerFilter($customer->getId())
        ->addSegmentFilter(1)
        ->getFirstItem();

    $this->assertEquals('scheduled', $progress->getStatus());

    // Simulate cron run
    $observer = Mage::getModel('customersegmentation/observer_emailAutomation');
    $observer->processScheduledEmails(new Varien_Event_Observer());

    // Verify email sent
    $progress->load($progress->getId());  // Reload
    $this->assertEquals('sent', $progress->getStatus());
    $this->assertNotNull($progress->getQueueId());
}
```

## Debugging

### Enable Detailed Logging

```php
// In app/etc/local.xml or use Mage::log directly
Mage::log(
    'Detailed automation info: ' . print_r($data, true),
    Mage::LOG_DEBUG,
    'customer_segmentation.log'
);
```

### Common Debug Queries

```sql
-- Find stuck sequences
SELECT * FROM customer_segment_sequence_progress
WHERE status = 'scheduled'
AND scheduled_at < DATE_SUB(NOW(), INTERVAL 1 HOUR);

-- Count sequences by status
SELECT status, COUNT(*) as count
FROM customer_segment_sequence_progress
GROUP BY status;

-- Find customers with multiple active sequences
SELECT customer_id, COUNT(*) as sequence_count
FROM customer_segment_sequence_progress
WHERE status = 'scheduled'
GROUP BY customer_id
HAVING sequence_count > 1;

-- Check coupon usage
SELECT
    sc.code,
    sc.times_used,
    sc.usage_limit,
    sc.expiration_date,
    sr.name as rule_name
FROM salesrule_coupon sc
JOIN salesrule sr ON sc.rule_id = sr.rule_id
WHERE sc.type = 3  -- Auto-generated
ORDER BY sc.created_at DESC
LIMIT 100;
```

### Debugging Checklist

When emails aren't sending:

```php
// 1. Check segment is active
$segment = Mage::getModel('customersegmentation/segment')->load($segmentId);
echo "Active: " . $segment->getIsActive() . "\n";
echo "Auto Email Active: " . $segment->getAutoEmailActive() . "\n";
echo "Trigger: " . $segment->getAutoEmailTrigger() . "\n";

// 2. Check customer is in segment
$inSegment = Mage::getResourceModel('customersegmentation/segment')
    ->isCustomerInSegment($segmentId, $customerId, $websiteId);
echo "In segment: " . ($inSegment ? 'YES' : 'NO') . "\n";

// 3. Check newsletter subscription
$subscriber = Mage::getModel('newsletter/subscriber')
    ->loadByCustomerId($customerId);
echo "Subscribed: " . ($subscriber->getSubscriberStatus() == 1 ? 'YES' : 'NO') . "\n";

// 4. Check sequence configuration
$sequences = $segment->getEmailSequences();
echo "Sequences: " . $sequences->getSize() . "\n";

// 5. Check progress records
$progress = Mage::getResourceModel('customersegmentation/sequenceProgress_collection')
    ->addCustomerFilter($customerId)
    ->addSegmentFilter($segmentId);
foreach ($progress as $p) {
    echo "Step {$p->getStepNumber()}: {$p->getStatus()}\n";
}

// 6. Check newsletter queue
if ($progress->getQueueId()) {
    $queue = Mage::getModel('newsletter/queue')->load($progress->getQueueId());
    echo "Queue status: " . $queue->getQueueStatus() . "\n";
}
```

## Security Considerations

### Newsletter Subscription Validation

**Critical**: Always verify newsletter subscription status:

```php
protected function isCustomerSubscribed(int $customerId): bool
{
    $collection = Mage::getResourceModel('newsletter/subscriber_collection');
    $collection->addFieldToFilter('customer_id', $customerId);
    $subscriber = $collection->getFirstItem();

    return $subscriber->getId() &&
           (int) $subscriber->getSubscriberStatus() === Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED;
}
```

### Coupon Security

Unique coupons prevent sharing:

```php
$coupon->setUsageLimit(1)              // Total uses
       ->setUsagePerCustomer(1);       // Uses per customer
```

### Rate Limiting

Prevent email spam:

```php
// In your custom observer
protected function checkEmailRateLimit(int $customerId): bool
{
    // Max 5 automation emails per day per customer
    $cutoff = date('Y-m-d H:i:s', strtotime('-24 hours'));

    $count = Mage::getResourceModel('customersegmentation/sequenceProgress_collection')
        ->addCustomerFilter($customerId)
        ->addFieldToFilter('sent_at', ['gteq' => $cutoff])
        ->getSize();

    return $count < 5;
}
```

## Migration & Upgrades

### Adding New Fields

```php
// In sql/maho_customersegmentation_setup/upgrade-X.X.X-Y.Y.Y.php

$installer = $this;
$installer->startSetup();

$installer->getConnection()->addColumn(
    $installer->getTable('customer_segment_email_sequence'),
    'new_field_name',
    [
        'type' => Maho\Db\Ddl\Table::TYPE_VARCHAR,
        'length' => 255,
        'nullable' => true,
        'comment' => 'New Field Description',
    ]
);

$installer->endSetup();
```

### Data Migration

```php
// Migrate from custom implementation
$oldSequences = $connection->fetchAll("SELECT * FROM old_email_sequences");

foreach ($oldSequences as $old) {
    $sequence = Mage::getModel('customersegmentation/emailSequence');
    $sequence->setSegmentId($old['segment_id'])
             ->setTemplateId($old['template_id'])
             ->setStepNumber($old['step'])
             ->setDelayMinutes($old['delay_hours'] * 60)
             ->save();
}
```

## Best Practices for Developers

### 1. Use Resource Models

Always use resource models for database operations:

```php
// Good
$resource = Mage::getResourceSingleton('customersegmentation/sequenceProgress');
$readySequences = $resource->getReadyToSendSequences(100);

// Avoid
$connection = Mage::getSingleton('core/resource')->getConnection('core_read');
$result = $connection->fetchAll("SELECT * FROM customer_segment_sequence_progress...");
```

### 2. Leverage Collections

```php
$collection = Mage::getResourceModel('customersegmentation/emailSequence_collection')
    ->addSegmentFilter($segmentId)
    ->addActiveFilter()
    ->addStepNumberOrder('ASC');
```

### 3. Handle Exceptions Gracefully

```php
try {
    $segment->startEmailSequence($customerId, $triggerType);
} catch (Exception $e) {
    Mage::logException($e);
    Mage::log(
        "Failed to start sequence for customer {$customerId}: " . $e->getMessage(),
        Mage::LOG_ERROR,
        'customer_segmentation.log'
    );
}
```

### 4. Use Type Hints

```php
public function generateCustomerCoupon(
    int $customerId,
    int $ruleId,
    string $prefix = 'AUTO',
    int $expireDays = 30
): ?string {
    // Implementation
}
```

### 5. Document Template Variables

When adding custom variables, document them:

```php
/**
 * Get template variables for email
 *
 * Available variables:
 * - customer: Full customer object
 * - customer_firstname: First name
 * - customer_lastname: Last name
 * - segment_name: Segment name
 * - step_number: Current step
 *
 * @param Mage_Customer_Model_Customer $customer
 * @param array $sequenceData
 * @return array
 */
protected function getTemplateVariables($customer, $sequenceData): array
```

## API Reference

### Model Methods

#### Maho_CustomerSegmentation_Model_Segment

```php
public function startEmailSequence(int $customerId, string $triggerType): void
public function hasActiveSequence(int $customerId, string $triggerType): bool
public function hasAnyActiveSequence(int $customerId): bool
public function getEmailSequences(): Maho_CustomerSegmentation_Model_Resource_EmailSequence_Collection
public function getEmailAutomationStats(): array
public function hasEmailAutomation(): bool
```

#### Maho_CustomerSegmentation_Model_SequenceProgress

```php
public function markAsSent(?int $queueId = null): self
public function markAsFailed(): self
public function markAsSkipped(): self
```

#### Maho_CustomerSegmentation_Helper_Coupon

```php
public function generateCustomerCoupon(int $customerId, int $ruleId, string $prefix, int $expireDays): ?string
public function getCouponTemplateVariables(string $couponCode, ?string $expirationDate, ?Mage_SalesRule_Model_Rule $rule): array
public function validateSalesRule(int $ruleId): array
public function getAvailableSalesRules(): array
public function getCouponStats(string $couponCode): array
public function cleanupExpiredCoupons(int $daysOld = 30): int
```

### Resource Model Methods

#### Maho_CustomerSegmentation_Model_Resource_SequenceProgress

```php
public function getActiveSequenceCustomers(int $segmentId, string $triggerType): array
public function stopSequencesForCustomers(int $segmentId, array $customerIds, string $triggerType): int
public function getReadyToSendSequences(int $limit = 100): array
public function hasActiveSequence(int $customerId, int $segmentId, string $triggerType): bool
public function hasAnyActiveSequence(int $customerId, int $segmentId): bool
public function createSequenceProgress(int $customerId, int $segmentId, array $sequences, string $triggerType): void
public function cleanupOldProgress(int $daysOld = 90): int
```

## Configuration Reference

### System Configuration Paths

```php
// Enable/disable email automation globally
customer_segmentation/email_automation/enabled  // 1 or 0

// Cleanup settings
customer_segmentation/email_automation/cleanup_days  // Default: 90
customer_segmentation/email_automation/coupon_cleanup_days  // Default: 30
```

### Cron Configuration

Cron jobs are registered with the `#[Maho\Config\CronJob]` PHP attribute on the cron method, not via XML `<crontab>`. The email-processing job runs every 5 minutes:

```php
class Maho_CustomerSegmentation_Model_Cron
{
    #[Maho\Config\CronJob('customersegmentation_process_emails', schedule: '*/5 * * * *')]
    public function processScheduledEmails(): void
    {
        // Checks customer_segmentation/email_automation/enabled, then dispatches
        // the customer_segmentation_process_scheduled_emails event.
    }
}
```

The module also registers `customersegmentation_cleanup_progress` (`0 2 * * *`), `customersegmentation_cleanup_coupons` (`0 3 * * *`), and `customersegmentation_automation_report` (`0 4 * * *`) via the same attribute.
