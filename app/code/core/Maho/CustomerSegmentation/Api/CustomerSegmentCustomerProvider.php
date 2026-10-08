<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CustomerSegmentation
 */

declare(strict_types=1);

namespace Maho\CustomerSegmentation\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\Security\AdminAcl;
use Symfony\Bundle\SecurityBundle\Security;

final class CustomerSegmentCustomerProvider extends \Maho\ApiPlatform\Provider
{
    use SegmentApiTrait;

    public function __construct(
        Security $security,
        private readonly CustomerSegmentProvider $segmentProvider,
    ) {
        parent::__construct($security);
    }

    /**
     * @return TraversablePaginator<CustomerSegmentCustomer>
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        AdminAcl::checkPath(CustomerSegment::ACL_MANAGE);
        $user = $this->requireUser();
        $segment = $this->segmentProvider->loadReadableSegment((int) ($uriVariables['segmentId'] ?? 0), $user);

        $resource = \Mage::getSingleton('core/resource');
        /** @var \Mage_Customer_Model_Resource_Customer_Collection $collection */
        $collection = \Mage::getResourceModel('customer/customer_collection');
        $collection->addAttributeToSelect(['email', 'firstname', 'lastname', 'group_id']);
        $collection->getSelect()
            ->joinInner(
                ['segment_customer' => $resource->getTableName('customersegmentation/segment_customer')],
                'segment_customer.customer_id = e.entity_id',
                ['segment_website_id' => 'segment_customer.website_id', 'segment_added_at' => 'segment_customer.added_at'],
            )
            ->where('segment_customer.segment_id = ?', (int) $segment->getId());

        $allowedWebsiteIds = $this->allowedWebsiteIds($user);
        if ($allowedWebsiteIds !== null) {
            $collection->getSelect()->where('segment_customer.website_id IN (?)', $allowedWebsiteIds === [] ? [-1] : $allowedWebsiteIds);
        }
        $collection->setOrder('entity_id', 'ASC');

        ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination($context, $this->defaultPageSize, $this->maxPageSize);
        $collection->setPageSize($pageSize)->setCurPage($page);

        $items = [];
        foreach ($collection->getItems() as $customer) {
            $dto = new CustomerSegmentCustomer();
            $dto->id = (int) $customer->getId();
            $dto->email = $customer->getData('email');
            $dto->firstname = $customer->getData('firstname');
            $dto->lastname = $customer->getData('lastname');
            $dto->groupId = $customer->getData('group_id') === null ? null : (int) $customer->getData('group_id');
            $dto->websiteId = (int) $customer->getData('segment_website_id');
            $dto->addedAt = $customer->getData('segment_added_at');
            $items[] = $dto;
        }

        return new TraversablePaginator(new \ArrayIterator($items), $page, $pageSize, (int) $collection->getSize());
    }
}
