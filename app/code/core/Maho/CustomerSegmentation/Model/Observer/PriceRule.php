<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_CustomerSegmentation_Model_Observer_PriceRule
{
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
