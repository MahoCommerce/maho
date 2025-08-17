<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_CustomerSegmentation_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function isEnabled(mixed $store = null): bool
    {
        return Mage::getStoreConfigFlag('customer_segmentation/general/enabled', $store);
    }

    public function getRefreshFrequency(mixed $store = null): int
    {
        return (int) Mage::getStoreConfig('customer_segmentation/general/refresh_frequency', $store);
    }

    public function getCustomerSegmentIds(int $customerId, ?int $websiteId = null): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        $resource = Mage::getResourceModel('customersegmentation/segment');
        return $resource->getCustomerSegmentIds($customerId, $websiteId);
    }

}
