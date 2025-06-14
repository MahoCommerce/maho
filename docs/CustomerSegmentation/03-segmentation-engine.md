# Maho Customer Segmentation - Segmentation Engine Implementation Plan

## Overview

The segmentation engine is the core component responsible for evaluating conditions, matching customers to segments, and maintaining segment membership. This document details the implementation strategy for a high-performance, extensible segmentation engine.

## Architecture Components

### 1. Condition System

#### Base Condition Interface
```php
interface Maho_CustomerSegmentation_Model_Segment_Condition_Interface
{
    public function getMatchingCustomerIds(): array;
    public function validateCustomer(Mage_Customer_Model_Customer $customer): bool;
    public function asArray(): array;
    public function loadArray(array $arr): self;
    public function getConditionsSql(Varien_Db_Select $select): void;
}
```

#### Condition Types Hierarchy
```
Combine (AND/OR logic)
├── Customer Conditions
│   ├── Attributes (name, email, gender, DOB)
│   ├── Address (country, region, city, postcode)
│   ├── Account (created_at, group, website)
│   └── Activity (last_login, visit_count)
├── Order Conditions
│   ├── History (first_order, last_order, order_count)
│   ├── Value (total_spent, average_order, lifetime_value)
│   ├── Products (purchased_products, product_categories)
│   └── Methods (payment_method, shipping_method)
├── Cart Conditions
│   ├── Current (cart_total, item_count, coupon_code)
│   ├── Abandoned (days_abandoned, abandonment_count)
│   └── Items (specific_products, product_attributes)
└── Behavior Conditions
    ├── Product Views (viewed_products, view_count)
    ├── Wishlist (wishlist_items, wishlist_value)
    └── Reviews (review_count, average_rating)
```

### 2. Evaluation Engine

#### Customer Matcher
```php
class Maho_CustomerSegmentation_Model_Segment_Matcher
{
    /**
     * Evaluate all segments for a customer
     */
    public function evaluateCustomer($customerId): array
    {
        // Load customer with all necessary data
        // Evaluate each active segment
        // Return matched segment IDs
    }
    
    /**
     * Evaluate a single segment for all customers
     */
    public function evaluateSegment($segmentId): array
    {
        // Load segment conditions
        // Build optimized SQL query
        // Return matching customer IDs
    }
    
    /**
     * Real-time evaluation for a single customer-segment pair
     */
    public function isCustomerInSegment($customerId, $segmentId): bool
    {
        // Quick evaluation with caching
    }
}
```

#### SQL Query Builder
```php
class Maho_CustomerSegmentation_Model_Resource_Segment_QueryBuilder
{
    /**
     * Build optimized query for segment conditions
     */
    public function buildQuery(array $conditions): Varien_Db_Select
    {
        $select = $this->getBaseSelect();
        
        foreach ($conditions as $condition) {
            $this->applyCondition($select, $condition);
        }
        
        return $this->optimizeQuery($select);
    }
    
    /**
     * Apply specific condition to query
     */
    protected function applyCondition($select, $condition): void
    {
        switch ($condition['type']) {
            case 'customer_attribute':
                $this->applyCustomerAttributeCondition($select, $condition);
                break;
            case 'order_history':
                $this->applyOrderHistoryCondition($select, $condition);
                break;
            // ... other condition types
        }
    }
}
```

### 3. Performance Optimization

#### Caching Strategy
```php
class Maho_CustomerSegmentation_Model_Cache
{
    const CACHE_TAG = 'CUSTOMER_SEGMENT';
    const CACHE_LIFETIME = 3600; // 1 hour
    
    /**
     * Cache segment membership
     */
    public function cacheCustomerSegments($customerId, array $segmentIds): void
    {
        $cacheKey = $this->getCustomerCacheKey($customerId);
        Mage::app()->getCache()->save(
            serialize($segmentIds),
            $cacheKey,
            [self::CACHE_TAG],
            self::CACHE_LIFETIME
        );
    }
    
    /**
     * Get cached segments for customer
     */
    public function getCustomerSegments($customerId): ?array
    {
        $cacheKey = $this->getCustomerCacheKey($customerId);
        $cached = Mage::app()->getCache()->load($cacheKey);
        return $cached ? unserialize($cached) : null;
    }
    
    /**
     * Invalidate cache for specific events
     */
    public function invalidate($tags = []): void
    {
        $tags[] = self::CACHE_TAG;
        Mage::app()->getCache()->clean($tags);
    }
}
```

#### Batch Processing
```php
class Maho_CustomerSegmentation_Model_Segment_Processor
{
    const BATCH_SIZE = 1000;
    
    /**
     * Process segment refresh in batches
     */
    public function refreshSegment($segmentId): void
    {
        $segment = Mage::getModel('customersegmentation/segment')->load($segmentId);
        $totalCustomers = $this->getTotalCustomers($segment);
        
        for ($offset = 0; $offset < $totalCustomers; $offset += self::BATCH_SIZE) {
            $this->processBatch($segment, $offset, self::BATCH_SIZE);
            
            // Free memory
            Mage::dispatchEvent('customer_segment_batch_processed', [
                'segment' => $segment,
                'offset' => $offset
            ]);
        }
    }
    
    /**
     * Process a batch of customers
     */
    protected function processBatch($segment, $offset, $limit): void
    {
        $customerIds = $this->getCustomerIdsBatch($offset, $limit);
        $matchedIds = $this->evaluateCustomers($segment, $customerIds);
        
        $this->updateSegmentMembership($segment->getId(), $matchedIds);
    }
}
```

### 4. Real-time Updates

#### Event Observer System
```php
class Maho_CustomerSegmentation_Model_Observer
{
    /**
     * Customer-related events
     */
    public function onCustomerSaveAfter($observer): void
    {
        $customer = $observer->getCustomer();
        $this->scheduleCustomerEvaluation($customer->getId());
    }
    
    /**
     * Order-related events
     */
    public function onOrderPlaceAfter($observer): void
    {
        $order = $observer->getOrder();
        if ($customerId = $order->getCustomerId()) {
            $this->triggerOrderConditions($customerId, $order);
        }
    }
    
    /**
     * Cart-related events
     */
    public function onQuoteUpdateAfter($observer): void
    {
        $quote = $observer->getQuote();
        if ($customerId = $quote->getCustomerId()) {
            $this->triggerCartConditions($customerId, $quote);
        }
    }
    
    /**
     * Schedule asynchronous evaluation
     */
    protected function scheduleCustomerEvaluation($customerId): void
    {
        Mage::getModel('customersegmentation/event')
            ->setCustomerId($customerId)
            ->setEventType('customer_update')
            ->setEventData(json_encode(['timestamp' => time()]))
            ->save();
    }
}
```

### 5. Condition Evaluation Examples

#### Customer Attribute Condition
```php
class Maho_CustomerSegmentation_Model_Segment_Condition_Customer_Attributes
    extends Maho_CustomerSegmentation_Model_Segment_Condition_Abstract
{
    public function getConditionsSql(Varien_Db_Select $select): void
    {
        $attribute = $this->getAttribute();
        $operator = $this->getOperator();
        $value = $this->getValue();
        
        switch ($attribute) {
            case 'email':
                $this->addEmailCondition($select, $operator, $value);
                break;
            case 'gender':
                $this->addAttributeCondition($select, 'gender', $operator, $value);
                break;
            case 'dob':
                $this->addDateCondition($select, 'dob', $operator, $value);
                break;
            // ... other attributes
        }
    }
    
    protected function addEmailCondition($select, $operator, $value): void
    {
        $condition = $this->getOperatorCondition('email', $operator, $value);
        $select->where($condition);
    }
}
```

#### Order History Condition
```php
class Maho_CustomerSegmentation_Model_Segment_Condition_Order_History
    extends Maho_CustomerSegmentation_Model_Segment_Condition_Abstract
{
    public function getConditionsSql(Varien_Db_Select $select): void
    {
        $subSelect = $this->getConnection()->select()
            ->from(['o' => $this->getTable('sales/order')], [
                'customer_id',
                'order_count' => 'COUNT(*)',
                'total_amount' => 'SUM(grand_total)',
                'last_order_date' => 'MAX(created_at)'
            ])
            ->where('o.state NOT IN (?)', ['canceled', 'closed'])
            ->group('customer_id');
        
        $select->joinLeft(
            ['order_stats' => $subSelect],
            'order_stats.customer_id = e.entity_id',
            []
        );
        
        $this->applyOrderConditions($select);
    }
}
```

### 6. Guest Visitor Tracking

#### Guest Segment Evaluator
```php
class Maho_CustomerSegmentation_Model_Segment_Guest
{
    /**
     * Track guest visitor
     */
    public function trackVisitor($visitorId, array $data): void
    {
        $guest = Mage::getModel('customersegmentation/guest')
            ->loadByVisitorId($visitorId);
        
        if (!$guest->getId()) {
            $guest->setVisitorId($visitorId)
                ->setFirstVisitAt(now());
        }
        
        $guest->setLastVisitAt(now())
            ->setPageViews($guest->getPageViews() + 1)
            ->setDataSerialized(serialize($data))
            ->save();
        
        $this->evaluateGuestSegments($guest);
    }
    
    /**
     * Evaluate segments for guest
     */
    protected function evaluateGuestSegments($guest): void
    {
        $segments = Mage::getResourceModel('customersegmentation/segment_collection')
            ->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('allow_guests', 1);
        
        foreach ($segments as $segment) {
            if ($this->guestMatchesSegment($guest, $segment)) {
                $this->addGuestToSegment($guest, $segment);
            }
        }
    }
}
```

### 7. Performance Monitoring

#### Metrics Collector
```php
class Maho_CustomerSegmentation_Model_Metrics
{
    /**
     * Track segment evaluation performance
     */
    public function trackEvaluation($segmentId, $startTime, $customerCount): void
    {
        $executionTime = microtime(true) - $startTime;
        $memoryUsage = memory_get_peak_usage(true);
        
        // Log performance metrics to system log
        Mage::log([
            'segment_id' => $segmentId,
            'execution_time' => $executionTime,
            'memory_usage' => $memoryUsage,
            'customers_processed' => $customerCount
        ], null, 'customer_segmentation_performance.log');
    }
    
    /**
     * Get performance statistics from cache
     */
    public function getPerformanceStats($segmentId): array
    {
        $cacheKey = 'segment_performance_' . $segmentId;
        $stats = Mage::app()->getCache()->load($cacheKey);
        
        if (!$stats) {
            $stats = [
                'avg_execution_time' => 0,
                'avg_memory_usage' => 0,
                'last_refresh' => null
            ];
            
            Mage::app()->getCache()->save(
                serialize($stats),
                $cacheKey,
                ['customer_segment_performance'],
                3600
            );
        } else {
            $stats = unserialize($stats);
        }
        
        return $stats;
    }
}
```

## Implementation Best Practices

### 1. Query Optimization
- Use covering indexes for all segment queries
- Minimize JOIN operations where possible
- Implement query result caching
- Use UNION for OR conditions instead of multiple queries

### 2. Memory Management
- Process customers in configurable batches
- Clear object references after processing
- Use iterators instead of loading full collections
- Implement memory limit monitoring

### 3. Scalability Considerations
- Support horizontal scaling with read replicas
- Implement distributed caching (Redis)
- Use message queues for asynchronous processing
- Design for multi-server deployments

### 4. Error Handling
- Graceful degradation on evaluation errors
- Comprehensive logging for debugging
- Automatic retry mechanisms
- Alert system for critical failures

### 5. Testing Strategy
- Unit tests for each condition type
- Integration tests for complex scenarios
- Performance benchmarks
- Load testing with large datasets