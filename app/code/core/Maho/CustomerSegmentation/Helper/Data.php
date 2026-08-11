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

    public function getQueueSegmentIds(array|string|null $segmentIds): array
    {
        if (!is_array($segmentIds)) {
            $segmentIds = explode(',', (string) $segmentIds);
        }

        return array_values(array_unique(array_filter(array_map('intval', $segmentIds))));
    }

    public function getSegmentsOutsideStores(array $segmentIds, array $storeIds): array
    {
        if ($segmentIds === [] || $storeIds === []) {
            return [];
        }

        $websiteIds = [];
        foreach ($storeIds as $storeId) {
            $websiteIds[] = (int) Mage::app()->getStore($storeId)->getWebsiteId();
        }

        $collection = Mage::getResourceModel('customersegmentation/segment_collection')
            ->addFieldToFilter('segment_id', ['in' => $segmentIds]);

        $outside = [];
        foreach ($collection as $segment) {
            $segmentWebsiteIds = array_map('intval', $segment->getWebsiteIdsArray());
            if (array_intersect($segmentWebsiteIds, $websiteIds) === []) {
                $outside[] = $segment->getName();
            }
        }

        return $outside;
    }
}
