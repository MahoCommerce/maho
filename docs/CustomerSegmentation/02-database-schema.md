# Maho Customer Segmentation - Database Schema Design

## Overview

This document outlines the database schema for the Maho_CustomerSegmentation module. The schema is designed to support efficient segmentation operations, scalable to millions of customers, with optimized query performance.

## Core Tables

### 1. `customer_segment`
Main table storing segment definitions.

```sql
CREATE TABLE `customer_segment` (
    `segment_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Segment ID',
    `name` varchar(255) NOT NULL COMMENT 'Segment Name',
    `description` text COMMENT 'Segment Description',
    `is_active` smallint(5) unsigned NOT NULL DEFAULT '1' COMMENT 'Is Active',
    `conditions_serialized` text COMMENT 'Serialized Segment Conditions',
    `website_ids` text COMMENT 'Website IDs (comma-separated)',
    `customer_group_ids` text COMMENT 'Customer Group IDs (comma-separated)',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created At',
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated At',
    `matched_customers_count` int(10) unsigned DEFAULT '0' COMMENT 'Cached Count of Matched Customers',
    `last_refresh_at` timestamp NULL DEFAULT NULL COMMENT 'Last Refresh Time',
    `refresh_status` varchar(20) DEFAULT 'pending' COMMENT 'Refresh Status: pending, processing, completed, error',
    `refresh_mode` varchar(20) DEFAULT 'auto' COMMENT 'Refresh Mode: auto, manual',
    `priority` int(10) unsigned DEFAULT '0' COMMENT 'Segment Priority for Ordering',
    PRIMARY KEY (`segment_id`),
    KEY `IDX_IS_ACTIVE` (`is_active`),
    KEY `IDX_REFRESH_STATUS` (`refresh_status`),
    KEY `IDX_PRIORITY` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Customer Segments';
```

### 2. `customer_segment_customer`
Junction table linking segments to customers.

```sql
CREATE TABLE `customer_segment_customer` (
    `segment_id` int(10) unsigned NOT NULL COMMENT 'Segment ID',
    `customer_id` int(10) unsigned NOT NULL COMMENT 'Customer ID',
    `website_id` smallint(5) unsigned NOT NULL COMMENT 'Website ID',
    `added_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Added to Segment At',
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated At',
    PRIMARY KEY (`segment_id`,`customer_id`),
    KEY `IDX_CUSTOMER_ID` (`customer_id`),
    KEY `IDX_WEBSITE_ID` (`website_id`),
    KEY `IDX_ADDED_AT` (`added_at`),
    CONSTRAINT `FK_SEGMENT_CUSTOMER_SEGMENT` FOREIGN KEY (`segment_id`) 
        REFERENCES `customer_segment` (`segment_id`) ON DELETE CASCADE,
    CONSTRAINT `FK_SEGMENT_CUSTOMER_CUSTOMER` FOREIGN KEY (`customer_id`) 
        REFERENCES `customer_entity` (`entity_id`) ON DELETE CASCADE,
    CONSTRAINT `FK_SEGMENT_CUSTOMER_WEBSITE` FOREIGN KEY (`website_id`) 
        REFERENCES `core_website` (`website_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Customer Segment Members';
```

### 3. `customer_segment_guest`
Table for tracking guest visitor segments.

```sql
CREATE TABLE `customer_segment_guest` (
    `guest_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Guest ID',
    `visitor_id` varchar(64) NOT NULL COMMENT 'Visitor Session ID',
    `segment_id` int(10) unsigned NOT NULL COMMENT 'Segment ID',
    `website_id` smallint(5) unsigned NOT NULL COMMENT 'Website ID',
    `email` varchar(255) DEFAULT NULL COMMENT 'Guest Email',
    `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP Address',
    `user_agent` text COMMENT 'User Agent',
    `first_visit_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'First Visit',
    `last_visit_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last Visit',
    `page_views` int(10) unsigned DEFAULT '0' COMMENT 'Total Page Views',
    `data_serialized` text COMMENT 'Additional Guest Data',
    PRIMARY KEY (`guest_id`),
    UNIQUE KEY `UNQ_VISITOR_SEGMENT` (`visitor_id`,`segment_id`),
    KEY `IDX_SEGMENT_ID` (`segment_id`),
    KEY `IDX_WEBSITE_ID` (`website_id`),
    KEY `IDX_EMAIL` (`email`),
    KEY `IDX_LAST_VISIT` (`last_visit_at`),
    CONSTRAINT `FK_GUEST_SEGMENT` FOREIGN KEY (`segment_id`) 
        REFERENCES `customer_segment` (`segment_id`) ON DELETE CASCADE,
    CONSTRAINT `FK_GUEST_WEBSITE` FOREIGN KEY (`website_id`) 
        REFERENCES `core_website` (`website_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Guest Visitor Segments';
```

### 4. `customer_segment_event`
Track customer events for real-time segmentation.

```sql
CREATE TABLE `customer_segment_event` (
    `event_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Event ID',
    `customer_id` int(10) unsigned DEFAULT NULL COMMENT 'Customer ID',
    `visitor_id` varchar(64) DEFAULT NULL COMMENT 'Visitor ID for Guests',
    `website_id` smallint(5) unsigned NOT NULL COMMENT 'Website ID',
    `event_type` varchar(50) NOT NULL COMMENT 'Event Type',
    `event_data` text COMMENT 'Event Data (JSON)',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Event Time',
    `processed` smallint(5) unsigned NOT NULL DEFAULT '0' COMMENT 'Is Processed',
    PRIMARY KEY (`event_id`),
    KEY `IDX_CUSTOMER_ID` (`customer_id`),
    KEY `IDX_VISITOR_ID` (`visitor_id`),
    KEY `IDX_EVENT_TYPE` (`event_type`),
    KEY `IDX_CREATED_AT` (`created_at`),
    KEY `IDX_PROCESSED` (`processed`),
    KEY `IDX_COMPOSITE_PROCESSING` (`processed`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Customer Segment Events';
```


## Indexes and Performance Optimization

### Composite Indexes for Common Queries

```sql
-- For efficient customer segmentation queries
ALTER TABLE `customer_entity` 
ADD INDEX `IDX_SEGMENT_QUERY` (`website_id`, `group_id`, `created_at`, `is_active`);

-- For order-based segmentation
ALTER TABLE `sales_flat_order` 
ADD INDEX `IDX_SEGMENT_ORDER` (`customer_id`, `state`, `created_at`, `grand_total`);

-- For cart-based segmentation
ALTER TABLE `sales_flat_quote` 
ADD INDEX `IDX_SEGMENT_QUOTE` (`customer_id`, `is_active`, `updated_at`, `grand_total`);
```

## Data Integrity Constraints

### Foreign Key Relationships
1. All segment-customer relationships cascade on delete
2. Prevent orphaned records in junction tables
3. Maintain referential integrity with core Maho tables

### Data Validation Rules
1. Segment names must be unique per website
2. Conditions must be valid JSON/serialized format
3. Customer can belong to multiple segments
4. Refresh status transitions must be valid

## Migration Considerations

### Initial Setup
```sql
-- Add custom attributes for segmentation
ALTER TABLE `customer_entity` 
ADD COLUMN `segment_data` text COMMENT 'Cached Segment Data (JSON)' AFTER `updated_at`;

ALTER TABLE `customer_entity` 
ADD INDEX `IDX_SEGMENT_UPDATE` (`updated_at`);
```

### Performance Metrics
- Expected query time for 1M customers: < 1 second
- Segment refresh for 100K customers: < 5 minutes
- Export generation for 50K records: < 30 seconds

## Maintenance Operations

### Regular Cleanup
```sql
-- Clean old event records (30 days)
DELETE FROM `customer_segment_event` 
WHERE `created_at` < DATE_SUB(NOW(), INTERVAL 30 DAY) 
AND `processed` = 1;


-- Remove expired guest records (configurable)
DELETE FROM `customer_segment_guest` 
WHERE `last_visit_at` < DATE_SUB(NOW(), INTERVAL 180 DAY);
```

### Optimization Queries
```sql
-- Analyze segment distribution
SELECT 
    s.name,
    COUNT(sc.customer_id) as customer_count,
    s.last_refresh_at
FROM customer_segment s
LEFT JOIN customer_segment_customer sc ON s.segment_id = sc.segment_id
GROUP BY s.segment_id
ORDER BY customer_count DESC;

-- Find segments needing refresh
SELECT * FROM customer_segment 
WHERE last_refresh_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
AND is_active = 1
AND refresh_mode = 'auto';
```

## Future Considerations

### Partitioning Strategy
For installations with millions of customers:
1. Partition `customer_segment_customer` by website_id
2. Partition `customer_segment_event` by created_at (monthly)
3. Consider read replicas for segment queries

### Caching Strategy
1. Redis keys for active segment membership
2. Denormalized segment data in customer records
3. Query result caching for complex conditions