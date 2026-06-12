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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Reads revocation requests for the owning customer and for admins.
 *
 * Customer-facing operations are scoped to the authenticated account email
 * (the declaration's natural key); admins see every request and the
 * internal-only fields (admin note, IP, user agent).
 */
final class RevocationRequestProvider extends Provider
{
    protected ?string $modelAlias = 'revocation/request';
    protected array $defaultSort = ['received_at' => 'DESC'];

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        StoreContext::ensureStore();

        $name = $operation->getName();

        if ($name === 'my_revocation_requests' || $name === 'myRevocationRequests') {
            return $this->provideMyCollection($context);
        }

        if ($operation instanceof CollectionOperationInterface) {
            $this->requireAdminView();
            return $this->provideAdminCollection($context);
        }

        return $this->provideSingle((int) ($uriVariables['id'] ?? $context['args']['id'] ?? 0));
    }

    private function provideMyCollection(array $context): TraversablePaginator
    {
        $email = $this->requireCustomerEmail();

        $collection = \Mage::getModel($this->modelAlias)->getCollection()
            ->addFieldToFilter('email', $email);

        return $this->paginate($collection, $context, adminView: false);
    }

    private function provideAdminCollection(array $context): TraversablePaginator
    {
        $collection = \Mage::getModel($this->modelAlias)->getCollection();

        $filters = $context['filters'] ?? [];
        if (!empty($filters['processedStatus'])) {
            $collection->addFieldToFilter('processed_status', $filters['processedStatus']);
        }
        if (!empty($filters['storeId'])) {
            $collection->addFieldToFilter('store_id', (int) $filters['storeId']);
        }
        if (!empty($filters['email'])) {
            $collection->addFieldToFilter('email', $filters['email']);
        }
        if (!empty($filters['orderId'])) {
            $collection->addFieldToFilter('order_id', (int) $filters['orderId']);
        }

        return $this->paginate($collection, $context, adminView: true);
    }

    private function provideSingle(int $id): ?RevocationRequest
    {
        if ($id <= 0) {
            return null;
        }

        $model = \Mage::getModel($this->modelAlias)->load($id);
        if (!$model->getId()) {
            return null;
        }

        if ($this->isAdminView()) {
            return RevocationRequest::fromModel($model, adminView: true);
        }

        // Customer path: only the owner (matched by account email) may read it.
        $email = $this->requireCustomerEmail();
        if (strcasecmp((string) $model->getEmail(), $email) !== 0) {
            throw new NotFoundHttpException('Revocation request not found');
        }

        return RevocationRequest::fromModel($model, adminView: false);
    }

    private function paginate(object $collection, array $context, bool $adminView): TraversablePaginator
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
            $items[] = RevocationRequest::fromModel($model, $adminView);
        }

        return new TraversablePaginator(new \ArrayIterator($items), $page, $pageSize, $total);
    }

    /** Admins and API users see every request and the internal-only fields. */
    private function isAdminView(): bool
    {
        return $this->isAdmin() || $this->isApiUser();
    }

    private function requireAdminView(): void
    {
        if (!$this->isAdminView()) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Admin access required');
        }
    }

    private function requireCustomerEmail(): string
    {
        $customerId = $this->requireAuthentication();
        $customer = \Mage::getModel('customer/customer')->load($customerId);
        $email = (string) $customer->getEmail();
        if ($email === '') {
            throw new NotFoundHttpException('Customer not found');
        }
        return $email;
    }
}
