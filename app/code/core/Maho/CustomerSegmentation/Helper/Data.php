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

        return array_values(array_unique(array_filter(array_map(intval(...), $segmentIds))));
    }

    /**
     * Both things a campaign's segments can be wrong about, from one load of the
     * same rows: ids deleted between form and save, and segments covering none of
     * the selected stores.
     *
     * @return array{unknown: int[], outside: string[]}
     */
    public function getQueueSegmentIssues(array $segmentIds, array $storeIds): array
    {
        if ($segmentIds === []) {
            return ['unknown' => [], 'outside' => []];
        }

        $websiteIds = [];
        foreach ($storeIds as $storeId) {
            $websiteIds[] = (int) Mage::app()->getStore($storeId)->getWebsiteId();
        }

        $collection = Mage::getResourceModel('customersegmentation/segment_collection')
            ->addFieldToFilter('segment_id', ['in' => $segmentIds]);

        $known = [];
        $outside = [];
        foreach ($collection as $segment) {
            $known[] = (int) $segment->getId();

            $segmentWebsiteIds = array_map(intval(...), $segment->getWebsiteIdsArray());
            if ($websiteIds !== [] && array_intersect($segmentWebsiteIds, $websiteIds) === []) {
                $outside[] = $segment->getName();
            }
        }

        return [
            'unknown' => array_values(array_diff($segmentIds, $known)),
            'outside' => $outside,
        ];
    }

    /**
     * Ids that no longer resolve to a segment, deleted between form and save.
     */
    public function getUnknownSegmentIds(array $segmentIds): array
    {
        return $this->getQueueSegmentIssues($segmentIds, [])['unknown'];
    }

    public function getSegmentsOutsideStores(array $segmentIds, array $storeIds): array
    {
        if ($storeIds === []) {
            return [];
        }

        return $this->getQueueSegmentIssues($segmentIds, $storeIds)['outside'];
    }
}
