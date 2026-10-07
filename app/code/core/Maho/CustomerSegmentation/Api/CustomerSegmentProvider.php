<?php

/**
 * Give the segment models to API Platform, which maps them to the resource.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CustomerSegmentation
 */

declare(strict_types=1);

namespace Maho\CustomerSegmentation\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Service\StoreContext;

final class CustomerSegmentProvider extends \Maho\ApiPlatform\Provider
{
    use SegmentApiTrait;

    /**
     * @return TraversablePaginator<\Maho_CustomerSegmentation_Model_Segment>|\Maho_CustomerSegmentation_Model_Segment
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator|\Maho_CustomerSegmentation_Model_Segment
    {
        StoreContext::ensureStore();
        $this->assertSegmentAcl(CustomerSegment::ACL_MANAGE);

        if ($operation instanceof CollectionOperationInterface) {
            return $this->segments($context);
        }
        if ($operation instanceof Post && !isset($uriVariables['id'])) {
            return $this->segmentService()->newSegment();
        }
        return $this->loadReadableSegment((int) ($uriVariables['id'] ?? 0), $this->requireUser());
    }

    /**
     * Load a segment that the token can read. A segment of another website answers 404, as a missing one does.
     */
    public function loadReadableSegment(int $id, ApiUser $user): \Maho_CustomerSegmentation_Model_Segment
    {
        return $this->segmentService()->getById($id, $this->allowedWebsiteIds($user));
    }

    /**
     * @return TraversablePaginator<\Maho_CustomerSegmentation_Model_Segment>
     */
    private function segments(array $context): TraversablePaginator
    {
        /** @var \Maho_CustomerSegmentation_Model_Resource_Segment_Collection $collection */
        $collection = \Mage::getResourceModel('customersegmentation/segment_collection');
        $allowedWebsiteIds = $this->allowedWebsiteIds($this->requireUser());
        if ($allowedWebsiteIds !== null) {
            if ($allowedWebsiteIds === []) {
                $collection->addFieldToFilter('segment_id', -1);
            } else {
                $collection->addWebsiteFilter($allowedWebsiteIds);
            }
        }
        $collection->setOrder('priority', 'DESC')->setOrder('name', 'ASC')->setOrder('segment_id', 'ASC');

        ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination($context, $this->defaultPageSize, $this->maxPageSize);
        $collection->setPageSize($pageSize)->setCurPage($page);

        $items = [];
        foreach ($collection->getItems() as $segment) {
            if ($segment instanceof \Maho_CustomerSegmentation_Model_Segment) {
                $items[] = $segment;
            }
        }

        return new TraversablePaginator(new \ArrayIterator($items), $page, $pageSize, (int) $collection->getSize());
    }
}
