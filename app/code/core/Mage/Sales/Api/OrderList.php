<?php

/**
 * Lists and searches the orders for the API, with the filters of the API query.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

namespace Mage\Sales\Api;

final class OrderList
{
    /**
     * Get all orders with billing address joined (no N+1 queries).
     *
     * @param array<string, string|int|null> $filters Single values, which the transport checks: status, state, storeId, customerId, email,
     *   emailLike, incrementId, search, createdFrom, createdTo, updatedSince (`since` is
     *   accepted as the legacy name for the last)
     * @param int[]|null $allowedStoreIds Token store allowlist; null means unrestricted
     * @return array{orders: array, total: int}
     */
    public function getList(
        int $page = 1,
        int $pageSize = 20,
        #[\SensitiveParameter]
        array $filters = [],
        ?array $allowedStoreIds = null,
    ): array {
        $collection = $this->buildOrderCollection(isset($filters['customerId']) ? (int) $filters['customerId'] : null, $filters);

        if ($allowedStoreIds !== null) {
            $collection->getSelect()->where(
                'main_table.store_id IN (?)',
                $allowedStoreIds === [] ? [-1] : $allowedStoreIds,
            );
        }

        $email = $this->filterValue($filters, 'email');
        $emailLike = $this->filterValue($filters, 'emailLike');
        if ($email !== null) {
            $collection->addFieldToFilter('customer_email', $email);
        } elseif ($emailLike !== null && mb_strlen($emailLike) >= 3) {
            $collection->addFieldToFilter('customer_email', ['like' => '%' . $emailLike . '%']);
        }

        $incrementId = $this->filterValue($filters, 'incrementId');
        if ($incrementId !== null) {
            $collection->addFieldToFilter('increment_id', $incrementId);
        }

        // Free-text search, like the admin order grid: every word must appear in the
        // order number, the email, the customer name, or the billing name (the only
        // name a guest order has). The billing address is joined by buildOrderCollection.
        $words = \Mage::helper('core/string')->splitSearchWords($this->filterValue($filters, 'search'));
        if ($words !== []) {
            $adapter = $collection->getConnection();
            foreach ($words as $word) {
                $like = '%' . $word . '%';
                $collection->getSelect()->where(implode(' OR ', array_map(
                    fn(string $column) => $adapter->prepareSqlCondition($column, ['like' => $like]),
                    ['main_table.increment_id', 'main_table.customer_email', 'main_table.customer_firstname',
                        'main_table.customer_lastname', 'billing_addr.firstname', 'billing_addr.lastname'],
                )), null, \Maho\Db\Select::TYPE_CONDITION);
            }
        }

        return $this->paginateAndPreload($collection, $page, $pageSize);
    }

    /**
     * Get every order of a customer with billing address joined (no N+1 queries), for the admin.
     * getListVisibleToCustomer() gets the orders that the customer sees.
     *
     * @param int $customerId Customer ID
     * @param int $page Page number
     * @param int $pageSize Page size
     * @param string|null $status Filter by status
     * @param string|null $since Filter by updated_at >= value (ISO datetime)
     * @return array{orders: array, total: int}
     */
    public function getListForCustomer(
        int $customerId,
        int $page = 1,
        int $pageSize = 20,
        ?string $status = null,
        ?string $since = null,
    ): array {
        $collection = $this->buildOrderCollection($customerId, ['status' => $status, 'since' => $since]);

        return $this->paginateAndPreload($collection, $page, $pageSize);
    }

    /**
     * Get the orders that the customer $customerId may see, as getListForCustomer() does. See
     * Mage_Sales_Service_Order::isVisibleToCustomer().
     *
     * @return array{orders: array, total: int}
     */
    public function getListVisibleToCustomer(
        int $customerId,
        int $page = 1,
        int $pageSize = 20,
        ?string $status = null,
        ?string $since = null,
    ): array {
        $collection = \Mage::getService('sales/order')->addVisibleToCustomerFilter(
            $this->buildOrderCollection(null, ['status' => $status, 'since' => $since]),
            $customerId,
        );

        return $this->paginateAndPreload($collection, $page, $pageSize);
    }

    /**
     * Recent orders for the admin dashboard, newest first, optionally store-scoped.
     *
     * @return \Mage_Sales_Model_Order[]
     */
    public function getRecent(int $limit, ?int $storeId = null): array
    {
        $collection = $this->buildOrderCollection();
        if ($storeId !== null) {
            $collection->addFieldToFilter('store_id', $storeId);
        }

        return $this->paginateAndPreload($collection, 1, $limit)['orders'];
    }

    /**
     * Multi-field order search (increment id, customer email, first/last name),
     * newest first, optionally store-scoped.
     *
     * @return \Mage_Sales_Model_Order[]
     */
    public function search(string $query, ?int $storeId, int $limit): array
    {
        $collection = $this->buildOrderCollection();
        if ($storeId !== null) {
            $collection->addFieldToFilter('store_id', $storeId);
        }

        $escaped = addcslashes($query, '%_');
        $like = ['like' => "%{$escaped}%"];
        $collection->addFieldToFilter(
            ['increment_id', 'customer_email', 'customer_firstname', 'customer_lastname'],
            [$like, $like, $like, $like],
        );

        return $this->paginateAndPreload($collection, 1, $limit)['orders'];
    }

    /**
     * Return the filter $key as a string, or null when it is absent or empty.
     *
     * @param array<string, string|int|null> $filters
     */
    private function filterValue(array $filters, string $key): ?string
    {
        $value = $filters[$key] ?? null;
        return $value === null || $value === '' ? null : (string) $value;
    }

    /**
     * Build an order collection with billing address joined and common filters applied.
     *
     * @param int|null $customerId When set, restrict to this customer's orders
     * @param array<string, string|int|null> $filters
     */
    private function buildOrderCollection(
        ?int $customerId = null,
        array $filters = [],
    ): \Mage_Sales_Model_Resource_Order_Collection {
        $collection = \Mage::getModel('sales/order')->getCollection()
            ->setOrder('created_at', 'DESC');

        if ($customerId !== null) {
            $collection->addFieldToFilter('customer_id', $customerId);
        }

        $status = $this->filterValue($filters, 'status');
        if ($status !== null) {
            $collection->addFieldToFilter('status', $status);
        }

        // Status is the merchant-visible label and can be renamed per install; state is
        // the fixed lifecycle stage behind it. Both are worth filtering on.
        $state = $this->filterValue($filters, 'state');
        if ($state !== null) {
            $collection->addFieldToFilter('state', $state);
        }

        $storeId = $this->filterValue($filters, 'storeId');
        if ($storeId !== null) {
            $collection->addFieldToFilter('store_id', (int) $storeId);
        }

        // `since` predates the createdFrom/createdTo/updatedSince set and means the same
        // as updatedSince; keep honouring it.
        $filters['updatedSince'] ??= $filters['since'] ?? null;
        $locale = \Mage::app()->getLocale();
        foreach ([
            ['created_at', 'createdFrom', 'gteq'],
            ['created_at', 'createdTo', 'lteq'],
            ['updated_at', 'updatedSince', 'gteq'],
        ] as [$column, $key, $operator]) {
            $value = $this->filterValue($filters, $key);
            if ($value !== null) {
                $collection->addFieldToFilter($column, [
                    $operator => $locale->formatDateBoundaryForDb($value, $operator === 'lteq'),
                ]);
            }
        }

        $resource = \Mage::getSingleton('core/resource');
        $collection->getSelect()->joinLeft(
            ['billing_addr' => $resource->getTableName('sales/order_address')],
            "billing_addr.parent_id = main_table.entity_id AND billing_addr.address_type = 'billing'",
            [
                'billing_addr_id' => 'billing_addr.entity_id',
                'billing_telephone' => 'billing_addr.telephone',
                'billing_firstname' => 'billing_addr.firstname',
                'billing_lastname' => 'billing_addr.lastname',
                'billing_company' => 'billing_addr.company',
                'billing_street' => 'billing_addr.street',
                'billing_city' => 'billing_addr.city',
                'billing_region' => 'billing_addr.region',
                'billing_postcode' => 'billing_addr.postcode',
                'billing_country_id' => 'billing_addr.country_id',
            ],
        );

        return $collection;
    }

    /**
     * Paginate an order collection and batch-preload visible items and
     * payments for the page. Without the payment preload, mapToDto's
     * $order->getPayment() lazy-loads a payment collection per order: one
     * extra query per row on every list response.
     *
     * @return array{orders: array, total: int}
     */
    private function paginateAndPreload(
        \Mage_Sales_Model_Resource_Order_Collection $collection,
        int $page,
        int $pageSize,
    ): array {
        $total = $collection->getSize();

        $collection->setPageSize($pageSize);
        $collection->setCurPage($page);

        $orders = [];
        $orderIds = [];
        foreach ($collection as $order) {
            $orders[] = $order;
            $orderIds[] = $order->getId();
        }

        if (!empty($orderIds)) {
            $itemCollection = \Mage::getModel('sales/order_item')->getCollection()
                ->addFieldToFilter('order_id', ['in' => $orderIds])
                ->addFieldToFilter('parent_item_id', ['null' => true]);

            $itemsByOrder = [];
            foreach ($itemCollection as $item) {
                $itemsByOrder[$item->getOrderId()][] = $item;
            }

            $paymentCollection = \Mage::getModel('sales/order_payment')->getCollection()
                ->addFieldToFilter('parent_id', ['in' => $orderIds]);

            // Keep the first payment per order, mirroring
            // \Mage_Sales_Model_Order::getPayment()'s first-not-deleted pick.
            $paymentsByOrder = [];
            foreach ($paymentCollection as $payment) {
                $paymentsByOrder[$payment->getParentId()] ??= $payment;
            }

            foreach ($orders as $order) {
                $oid = $order->getId();
                if (isset($itemsByOrder[$oid])) {
                    $order->setData('_preloaded_items', $itemsByOrder[$oid]);
                }
                if (isset($paymentsByOrder[$oid])) {
                    $order->setData('_preloaded_payment', $paymentsByOrder[$oid]->setOrder($order));
                }
            }
        }

        return [
            'orders' => $orders,
            'total' => $total,
        ];
    }
}
