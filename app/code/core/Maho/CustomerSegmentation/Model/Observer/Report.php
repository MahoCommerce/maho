<?php

/**
 * Add the customer segments to the orders and the customers of the report explorer, as a grouping and a filter.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CustomerSegmentation
 */

declare(strict_types=1);

use Maho\Report\Definition\Dataset;
use Maho\Report\Definition\Dimension;
use Maho\Report\Definition\Field;
use Maho\Report\Definition\FieldType;
use Maho\Report\Definition\Join;

class Maho_CustomerSegmentation_Model_Observer_Report
{
    public const string FIELD = 'customer_segment';

    /**
     * A customer can be in several segments, so a grouping by segment counts a record once in each segment of its
     * customer. A segment holds the customers that match it now, not on the date of an order.
     */
    #[Maho\Config\Observer('report_dataset_build')]
    public function addSegments(\Maho\Event\Observer $observer): void
    {
        $dataset = $observer->getEvent()->getData('dataset');
        if (!$dataset instanceof Dataset) {
            return;
        }
        $customer = match ($dataset->code) {
            Mage_Sales_Model_Report_Dataset_Orders::CODE => 'main.customer_id',
            Mage_Customer_Model_Report_Dataset_Customers::CODE => 'main.entity_id',
            default => null,
        };
        $segments = $customer === null ? [] : $this->activeSegments();
        if ($segments === []) {
            return;
        }

        $resource = Mage::getSingleton('core/resource');
        // The condition reads only the main table, because a filter on the segment runs it in an EXISTS subquery
        $website = $dataset->code === Mage_Sales_Model_Report_Dataset_Orders::CODE
            ? '(SELECT segment_store.website_id FROM ' . $resource->getTableName('core/store') . ' segment_store WHERE segment_store.store_id = main.store_id)'
            : 'main.website_id';
        $dataset->addJoin(new Join(
            'segment',
            $resource->getTableName('customersegmentation/segment_customer'),
            "segment.customer_id = {$customer} AND segment.website_id = {$website}"
                . ' AND segment.segment_id IN (' . implode(', ', array_keys($segments)) . ')',
            manyRows: true,
        ));

        $label = Mage::helper('customersegmentation')->__('Customer segment');
        $dataset->addField(new Field(self::FIELD, $label, FieldType::Id, 'segment.segment_id', ['segment'], options: $segments));
        $dataset->addDimension(new Dimension(self::FIELD, $label, self::FIELD, nonAdditive: true));
    }

    /** @return array<int, string> the name of each active segment, by ID */
    private function activeSegments(): array
    {
        $segments = [];
        $collection = Mage::getResourceModel('customersegmentation/segment_collection')
            ->addFieldToFilter('is_active', 1)
            ->setOrder('name', 'ASC');
        foreach ($collection as $segment) {
            $segments[(int) $segment->getId()] = (string) $segment->getName();
        }
        return $segments;
    }
}
