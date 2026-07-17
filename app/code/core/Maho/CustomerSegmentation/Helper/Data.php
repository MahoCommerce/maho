<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CustomerSegmentation
 */

declare(strict_types=1);

class Maho_CustomerSegmentation_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function getCustomerSegmentIds(int $customerId, ?int $websiteId = null): array
    {
        $resource = Mage::getResourceModel('customersegmentation/segment');
        return $resource->getCustomerSegmentIds($customerId, $websiteId);
    }
}
