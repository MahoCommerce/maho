<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Revocation
 */

declare(strict_types=1);

namespace Maho\Revocation\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\Provider;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * Reads revocation requests for the owning customer and for admins.
 *
 * Item reads are ownership-gated by the operation's `is_owner(object, 'email')`
 * expression (the declaration's natural key); the customer collection is scoped
 * to the authenticated account email at the query. Internal-only fields (admin
 * note, IP, user agent) are gated per-property by `has_backend_access()`.
 */
final class RevocationRequestProvider extends Provider
{
    #[\Override]
    protected ?string $modelAlias = 'revocation/request';
    #[\Override]
    protected array $defaultSort = ['received_at' => 'DESC'];

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        StoreContext::ensureStore();

        $name = $operation->getName();

        if ($name === 'my_revocation_requests' || $name === 'my') {
            return $this->provideMyCollection($context);
        }

        if ($operation instanceof CollectionOperationInterface) {
            return $this->provideAdminCollection($context);
        }

        return $this->provideSingle((int) ($uriVariables['id'] ?? $context['args']['id'] ?? 0));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function provideMyCollection(array $context): TraversablePaginator
    {
        $email = $this->requireCustomerEmail();

        $collection = \Mage::getResourceModel('revocation/request_collection')
            ->addFieldToFilter('email', $email);

        return $this->paginate($collection, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function provideAdminCollection(array $context): TraversablePaginator
    {
        $collection = \Mage::getResourceModel('revocation/request_collection');

        $filters = $context['filters'] ?? [];
        $processedStatus = $this->stringFilter($filters, 'processedStatus');
        if ($processedStatus !== null) {
            $collection->addFieldToFilter('processed_status', $processedStatus);
        }
        $storeId = $this->intFilter($filters, 'storeId');
        if ($storeId) {
            $collection->addFieldToFilter('store_id', $storeId);
        }
        $email = $this->stringFilter($filters, 'email');
        if ($email !== null) {
            $collection->addFieldToFilter('email', $email);
        }
        $orderId = $this->intFilter($filters, 'orderId');
        if ($orderId) {
            $collection->addFieldToFilter('order_id', $orderId);
        }

        return $this->paginate($collection, $context);
    }

    private function provideSingle(int $id): ?RevocationRequest
    {
        if ($id <= 0) {
            return null;
        }

        $model = \Mage::getModel('revocation/request')->load($id);
        if (!$model->getId()) {
            return null;
        }

        // Ownership is enforced by the operation's `is_owner(object, 'email')`
        // expression post-read; a denial maps to 404 / null.
        return RevocationRequest::fromModel($model);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function paginate(object $collection, array $context): TraversablePaginator
    {
        foreach ($this->defaultSort as $field => $dir) {
            $collection->setOrder($field, $dir);
        }

        ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination(
            $context,
            $this->defaultPageSize,
            $this->maxPageSize,
        );
        $collection->setPageSize($pageSize);
        $collection->setCurPage($page);

        $total = (int) $collection->getSize();

        $items = [];
        foreach ($collection as $model) {
            $items[] = RevocationRequest::fromModel($model);
        }

        return new TraversablePaginator(new \ArrayIterator($items), $page, $pageSize, $total);
    }

    private function requireCustomerEmail(): string
    {
        $email = (string) \Mage::getService('customer/customer')->getById($this->requireCustomerId())->getEmail();
        if ($email === '') {
            throw new \Mage_Core_Exception_NoSuchEntity('Customer not found');
        }
        return $email;
    }
}
