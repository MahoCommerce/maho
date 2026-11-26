<?php

/**
 * Give the privacy request models to API Platform, which maps them to the resource.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

namespace Maho\PrivacyCenter\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\Security\AdminAcl;
use Maho\ApiPlatform\Service\StoreContext;
use Maho_PrivacyCenter_Model_Request as Request;

final class PrivacyRequestProvider extends \Maho\ApiPlatform\Provider
{
    /**
     * @return TraversablePaginator<Request>|Request
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator|Request
    {
        StoreContext::ensureStore();
        $collection = \Mage::getResourceModel('privacycenter/request_collection');

        if ($operation->getName() === 'my_privacy_requests') {
            $collection->addFieldToFilter('customer_id', $this->requireCustomerId());
            return $this->paginateRequests($collection, $context);
        }

        AdminAcl::checkPath(PrivacyRequest::ACL_VIEW);
        $allowedStoreIds = $this->requireUser()->getAllowedStoreIds();
        if (!$operation instanceof CollectionOperationInterface) {
            $request = \Mage::getService('privacycenter/request')->getById((int) ($uriVariables['id'] ?? 0));
            $this->assertStoreAllowed($request->getStoreId(), $this->requireUser(), 'privacy request');
            return $request;
        }

        if ($allowedStoreIds !== null) {
            $collection->addFieldToFilter('store_id', ['in' => $allowedStoreIds ?: [-1]]);
        }
        $filters = $context['filters'] ?? [];
        foreach (['status', 'type', 'email'] as $field) {
            $value = $this->stringFilter($filters, $field);
            if ($value !== null) {
                $collection->addFieldToFilter($field, $value);
            }
        }
        $customerId = $this->intFilter($filters, 'customerId');
        if ($customerId !== null) {
            $collection->addFieldToFilter('customer_id', $customerId);
        }
        return $this->paginateRequests($collection, $context);
    }

    /**
     * @param array<string, mixed> $context
     * @return TraversablePaginator<Request>
     */
    private function paginateRequests(\Maho_PrivacyCenter_Model_Resource_Request_Collection $collection, array $context): TraversablePaginator
    {
        ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination($context, $this->defaultPageSize, $this->maxPageSize);
        $collection->setOrder('created_at', 'DESC')->setOrder('request_id', 'DESC')
            ->setPageSize($pageSize)->setCurPage($page);
        $items = [];
        foreach ($collection->getItems() as $request) {
            if ($request instanceof Request) {
                $items[] = $request;
            }
        }
        return new TraversablePaginator(new \ArrayIterator($items), $page, $pageSize, (int) $collection->getSize());
    }
}
