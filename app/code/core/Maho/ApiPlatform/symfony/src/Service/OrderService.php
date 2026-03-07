<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\Service;

/**
 * Order Service - Business logic for checkout and order operations
 */
class OrderService
{
    /**
     * Place order from quote
     *
     * @param \Mage_Sales_Model_Quote $quote Quote to convert to order
     * @param string|null $guestEmail Guest email (for guest checkout)
     * @param string|null $orderNote Order notes
     * @param float|null $cashTendered Cash tendered (for POS)
     * @param int|null $employeeId Employee ID (for POS)
     * @return array [order, changeAmount]
     */
    public function placeOrder(
        \Mage_Sales_Model_Quote $quote,
        ?string $guestEmail = null,
        ?string $orderNote = null,
        ?float $cashTendered = null,
        ?int $employeeId = null,
    ): array {
        // Validate quote
        if (!$quote->getId() || !$quote->getIsActive()) {
            throw new \RuntimeException('Cart is not active or does not exist');
        }

        // Validate quote has items
        if ($quote->getItemsCount() == 0) {
            throw new \RuntimeException('Cart is empty');
        }

        // Validate addresses
        if (!$quote->isVirtual()) {
            $shippingAddress = $quote->getShippingAddress();
            if (!$shippingAddress->getShippingMethod()) {
                throw new \RuntimeException('Shipping method is not set');
            }
        }

        $billingAddress = $quote->getBillingAddress();
        if (!$billingAddress->getFirstname()) {
            throw new \RuntimeException('Billing address is not set');
        }

        // Validate payment method
        $payment = $quote->getPayment();
        if (!$payment->getMethod()) {
            throw new \RuntimeException('Payment method is not set');
        }

        // Set guest email if provided
        if ($guestEmail && $quote->getCustomerIsGuest()) {
            $quote->setCustomerEmail($guestEmail);
        }

        // Set employee ID for POS orders
        if ($employeeId) {
            $quote->setData('employee_id', $employeeId);
        }

        // Collect totals one final time
        $quote->collectTotals();

        try {
            // Convert quote to order
            $service = \Mage::getModel('sales/service_quote', $quote);
            $service->submitAll();

            $order = $service->getOrder();

            if (!$order || !$order->getId()) {
                throw new \RuntimeException('Failed to create order');
            }

            // Generate access token for guest orders
            $accessToken = null;
            if (!$order->getCustomerId() || $quote->getCustomerIsGuest()) {
                $accessToken = $this->generateAccessToken();
                $order->setData('guest_access_token', $accessToken);
                $order->save();
            }

            // Add order note if provided
            if ($orderNote) {
                $order->addStatusHistoryComment($orderNote, false)
                    ->setIsCustomerNotified(false)
                    ->save();
            }

            // Calculate change for cash payments
            $changeAmount = null;
            if ($cashTendered !== null && $payment->getMethod() === 'cashondelivery') {
                $changeAmount = $cashTendered - $order->getGrandTotal();
                if ($changeAmount < 0) {
                    throw new \RuntimeException('Insufficient cash tendered');
                }

                // Store cash tendered amount
                $payment->setAdditionalInformation('cash_tendered', $cashTendered);
                $payment->setAdditionalInformation('change_amount', $changeAmount);
                $payment->save();
            }

            // Deactivate quote
            $quote->setIsActive(0);
            $quote->save();

            return [
                'order' => $order,
                'accessToken' => $accessToken,
                'changeAmount' => $changeAmount,
            ];
        } catch (\Exception $e) {
            \Mage::logException($e);
            throw new \RuntimeException('Failed to place order');
        }
    }

    /**
     * Get order by ID or increment ID (authenticated customers only)
     *
     * @param int|null $orderId Order ID
     * @param string|null $incrementId Increment ID
     */
    public function getOrder(?int $orderId = null, ?string $incrementId = null): ?\Mage_Sales_Model_Order
    {
        $order = \Mage::getModel('sales/order');

        if ($orderId) {
            $order->load($orderId);
        } elseif ($incrementId) {
            $order->loadByIncrementId($incrementId);
        } else {
            return null;
        }

        if (!$order->getId()) {
            return null;
        }

        return $order;
    }

    /**
     * Get guest order by increment ID and access token
     *
     * @param string $incrementId Order increment ID
     * @param string $accessToken Guest access token
     */
    public function getGuestOrder(string $incrementId, string $accessToken): ?\Mage_Sales_Model_Order
    {
        $order = \Mage::getModel('sales/order')->loadByIncrementId($incrementId);

        if (!$order->getId()) {
            return null;
        }

        // Verify access token matches
        $storedToken = $order->getData('guest_access_token');
        if (!$storedToken || !hash_equals($storedToken, $accessToken)) {
            return null;
        }

        return $order;
    }

    /**
     * Get all orders with billing address joined (no N+1 queries).
     *
     * @param int $page Page number
     * @param int $pageSize Page size
     * @param string|null $status Filter by status
     * @param string|null $email Filter by customer email (exact match, uses index)
     * @param string|null $incrementId Filter by order increment ID (exact match)
     * @param string|null $emailLike Filter by customer email (partial LIKE match, slower on large tables)
     * @param string|null $since Filter by updated_at >= value (ISO datetime)
     * @return array{orders: array, total: int}
     */
    public function getAllOrders(
        int $page = 1,
        int $pageSize = 20,
        ?string $status = null,
        #[\SensitiveParameter]
        ?string $email = null,
        ?string $incrementId = null,
        ?string $emailLike = null,
        ?string $since = null,
    ): array {
        $collection = \Mage::getModel('sales/order')->getCollection()
            ->setOrder('created_at', 'DESC');

        if ($status) {
            $collection->addFieldToFilter('status', $status);
        }

        if ($email) {
            $collection->addFieldToFilter('customer_email', $email);
        } elseif ($emailLike && mb_strlen($emailLike) >= 3) {
            $collection->addFieldToFilter('customer_email', ['like' => '%' . $emailLike . '%']);
        }

        if ($incrementId) {
            $collection->addFieldToFilter('increment_id', $incrementId);
        }

        if ($since) {
            $collection->addFieldToFilter('updated_at', ['gteq' => $since]);
        }

        $total = $collection->getSize();

        $collection->setPageSize($pageSize);
        $collection->setCurPage($page);

        // Join billing address directly to avoid N+1 loads per order.
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

        // Materialize and batch-load order items for all orders on this page.
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

            foreach ($orders as $order) {
                $oid = $order->getId();
                if (isset($itemsByOrder[$oid])) {
                    $order->setData('_preloaded_items', $itemsByOrder[$oid]);
                }
            }
        }

        return [
            'orders' => $orders,
            'total' => $total,
        ];
    }

    /**
     * Get customer orders with billing address joined (no N+1 queries).
     *
     * @param int $customerId Customer ID
     * @param int $page Page number
     * @param int $pageSize Page size
     * @param string|null $status Filter by status
     * @param string|null $since Filter by updated_at >= value (ISO datetime)
     * @return array{orders: array, total: int}
     */
    public function getCustomerOrders(
        int $customerId,
        int $page = 1,
        int $pageSize = 20,
        ?string $status = null,
        ?string $since = null,
    ): array {
        $collection = \Mage::getModel('sales/order')->getCollection()
            ->addFieldToFilter('customer_id', $customerId)
            ->setOrder('created_at', 'DESC');

        if ($status) {
            $collection->addFieldToFilter('status', $status);
        }

        if ($since) {
            $collection->addFieldToFilter('updated_at', ['gteq' => $since]);
        }

        $total = $collection->getSize();

        $collection->setPageSize($pageSize);
        $collection->setCurPage($page);

        // Join billing address directly to avoid N+1 loads per order.
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

        // Materialize and batch-load order items.
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

            foreach ($orders as $order) {
                $oid = $order->getId();
                if (isset($itemsByOrder[$oid])) {
                    $order->setData('_preloaded_items', $itemsByOrder[$oid]);
                }
            }
        }

        return [
            'orders' => $orders,
            'total' => $total,
        ];
    }

    /**
     * Cancel order
     *
     * @param \Mage_Sales_Model_Order $order Order
     * @param string|null $reason Cancellation reason
     */
    public function cancelOrder(\Mage_Sales_Model_Order $order, ?string $reason = null): \Mage_Sales_Model_Order
    {
        if (!$order->canCancel()) {
            throw new \RuntimeException('Order cannot be cancelled');
        }

        try {
            $order->cancel();

            if ($reason) {
                $order->addStatusHistoryComment('Order cancelled: ' . $reason, false)
                    ->setIsCustomerNotified(true);
            }

            $order->save();

            return $order;
        } catch (\Exception $e) {
            \Mage::logException($e);
            throw new \RuntimeException('Failed to cancel order');
        }
    }

    /**
     * Add order note
     *
     * @param \Mage_Sales_Model_Order $order Order
     * @param string $note Note text
     * @param bool $notifyCustomer Notify customer
     * @param bool $visibleOnFront Visible on frontend
     */
    public function addOrderNote(
        \Mage_Sales_Model_Order $order,
        string $note,
        bool $notifyCustomer = false,
        bool $visibleOnFront = false,
    ): \Mage_Sales_Model_Order {
        $order->addStatusHistoryComment($note, false)
            ->setIsCustomerNotified($notifyCustomer)
            ->setIsVisibleOnFront((int) $visibleOnFront);

        $order->save();

        return $order;
    }

    /**
     * Get order status history
     *
     * @param \Mage_Sales_Model_Order $order Order
     * @return array Order notes
     */
    public function getOrderNotes(\Mage_Sales_Model_Order $order): array
    {
        $notes = [];

        foreach ($order->getStatusHistoryCollection() as $status) {
            $notes[] = [
                'note' => $status->getComment(),
                'createdAt' => $status->getCreatedAt(),
                'isCustomerNotified' => (bool) $status->getIsCustomerNotified(),
                'isVisibleOnFront' => (bool) $status->getIsVisibleOnFront(),
            ];
        }

        return $notes;
    }

    /**
     * Generate secure access token for guest orders
     *
     * @return string Cryptographically secure random token
     */
    private function generateAccessToken(): string
    {
        // Generate 32 bytes of random data and convert to hex (64 characters)
        return bin2hex(random_bytes(32));
    }
}
