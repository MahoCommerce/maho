<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CustomerSegmentation
 */

declare(strict_types=1);

class Maho_CustomerSegmentation_Model_Observer_PriceRule
{
    #[Maho\Config\Observer('salesrule_rule_condition_combine')]
    public function addSegmentConditionToSalesRule(\Maho\Event\Observer $observer): void
    {
        $additional = $observer->getAdditional();
        $conditions = $additional->getConditions();

        if (!is_array($conditions)) {
            $conditions = [];
        }

        $conditions[] = [
            'label' => Mage::helper('customersegmentation')->__('Customer Segment'),
            'value' => 'customersegmentation/rule_condition_segment',
        ];

        $additional->setConditions($conditions);
    }
}
