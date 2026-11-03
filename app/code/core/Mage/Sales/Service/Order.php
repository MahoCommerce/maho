<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

use Maho\ApiPlatform\Trait\DateRangeFilterTrait;
use Maho\ApiPlatform\Trait\FilterValueTrait;
use Maho\Security\RateLimitScope;
use Mage_Sales_Model_Order as Order;
use Mage_Sales_Model_Order_Creditmemo as Creditmemo;
use Mage_Sales_Model_Order_Invoice as Invoice;
use Mage_Sales_Model_Order_Shipment as Shipment;

/**
 * Places orders from carts, finds orders, and cancels, holds, invoices, ships and refunds them, for the admin,
 * the storefront and the API.
 */
class Mage_Sales_Service_Order
{
    use DateRangeFilterTrait;
    use FilterValueTrait;

    public const XML_PATH_GUEST_LOOKUP_RATE_LIMIT = 'system/rate_limit/guest_order_lookup';
    public const GUEST_LOOKUP_WINDOW = 3600;

    public const CAPTURE_CASES = [Invoice::CAPTURE_ONLINE, Invoice::CAPTURE_OFFLINE, Invoice::NOT_CAPTURE];

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
    public function place(
        \Mage_Sales_Model_Quote $quote,
        ?string $guestEmail = null,
        ?string $orderNote = null,
        ?float $cashTendered = null,
        ?int $employeeId = null,
    ): array {
        // Validate quote
        if (!$quote->getId() || !$quote->getIsActive()) {
            throw new \Mage_Core_Exception('Cart is not active or does not exist');
        }

        // Validate quote has items
        if ($quote->getItemsCount() == 0) {
            throw new \Mage_Core_Exception('Cart is empty');
        }

        // A cart assigned to a customer before its guest flag was cleared still carries it
        $quote->setCustomerIsGuest(!$quote->getCustomerId());
        // The cart keeps the email the account had when the customer was set on it
        $accountEmail = $quote->getCustomerId() ? $quote->getCustomer()->getEmail() : null;
        if ($accountEmail) {
            $quote->setCustomerEmail($accountEmail);
        }

        // Validate addresses
        if (!$quote->isVirtual()) {
            $shippingAddress = $quote->getShippingAddress();
            if (!$shippingAddress->getShippingMethod()) {
                throw new \Mage_Core_Exception('Shipping method is not set');
            }
        }

        $billingAddress = $quote->getBillingAddress();
        if (!$billingAddress->getFirstname()) {
            throw new \Mage_Core_Exception('Billing address is not set');
        }

        // Validate payment method
        $payment = $quote->getPayment();
        if (!$payment->getMethod()) {
            throw new \Mage_Core_Exception('Payment method is not set');
        }

        // Set guest email if provided
        if ($guestEmail && $quote->getCustomerIsGuest()) {
            $quote->setCustomerEmail($guestEmail);
        }

        // Prefer the account name, so an API order matches the storefront and the admin.
        if (!$quote->getCustomerFirstname()) {
            $nameSource = $quote->getCustomer()->getFirstname() ? $quote->getCustomer() : $billingAddress;
            foreach (\Mage::getConfig()->getFieldset('customer_account') as $code => $node) {
                if ($node->is('name') && $node->to_quote) {
                    $targetCode = (string) $node->to_quote;
                    $quote->setDataUsingMethod($targetCode === '*' ? $code : $targetCode, $nameSource->getDataUsingMethod($code));
                }
            }
        }

        // Set employee ID for POS orders
        if ($employeeId) {
            $quote->setData('employee_id', $employeeId);
        }

        // Serialize concurrent placement attempts on the same quote. Without
        // this, two simultaneous POSTs both pass the is_active check above and
        // submit the same cart twice, duplicate orders, double inventory
        // decrement. The lock gives us a per-quote critical section that
        // releases on disconnect, so a crashed worker can't deadlock the cart.
        $resource = \Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $lockName = 'maho_quote_place_order:' . (int) $quote->getId();
        if (!$write->getLock($lockName, 5)) {
            throw new \Mage_Core_Exception_Conflict('Order placement is already in progress for this cart');
        }

        try {
            // Re-read is_active under the lock, another request may have
            // converted this quote while we were waiting.
            $stillActive = (int) $write->fetchOne(
                $write->select()
                    ->from($resource->getTableName('sales/quote'), ['is_active'])
                    ->where('entity_id = ?', (int) $quote->getId()),
            );
            if ($stillActive !== 1) {
                throw new \Mage_Core_Exception_Conflict('Cart is no longer active');
            }

            // Re-validate gift-card balances against the live DB under the lock,
            // immediately before consuming them, a card spent elsewhere since
            // apply time must not discount this order. Collect totals once more
            // so submitAll() converts the freshly revalidated amounts.
            \Mage::getService('checkout/cart')->revalidateGiftcards($quote);
            // Restated together: submitAll() copies the currency off the quote,
            // and the totals were just converted at the live one.
            $quote->collectTotals()->refreshCurrencyStamp();

            // Validate cash tendered covers the total BEFORE creating the order.
            // Checking after submitAll() would place the order and decrement
            // inventory, then throw, leaving an orphaned order that a retry can't
            // recover (the quote is already inactive).
            if ($cashTendered !== null
                && $payment->getMethod() === 'cashondelivery'
                && $cashTendered < (float) $quote->getGrandTotal()
            ) {
                throw new \Mage_Core_Exception('Insufficient cash tendered');
            }

            // Convert quote to order
            $service = \Mage::getModel('sales/service_quote', $quote);
            $service->submitAll();

            $order = $service->getOrder();

            if (!$order || !$order->getId()) {
                throw new \RuntimeException('Failed to create order');
            }

            // Generate access token for guest orders
            $accessToken = null;
            if (!$order->getCustomerId()) {
                $accessToken = $this->generateAccessToken();
                $order->setData('guest_access_token', $accessToken);
                $order->save();
            }

            // Persist the note both as the readable customer_note snapshot and
            // as a status-history entry, so the placement orderNote round-trips
            // through the order read surface.
            if ($orderNote) {
                $order->setCustomerNote($orderNote);
                $order->save();
                $order->addStatusHistoryComment($orderNote, false)
                    ->setIsCustomerNotified(false)
                    ->save();
            }

            // Calculate change and persist cash details on the ORDER payment.
            // $payment above is the quote's payment, which is discarded once the
            // quote is deactivated; receipts read $order->getPayment(). Sufficiency
            // was already validated before submitAll(), so change is never negative.
            $changeAmount = null;
            if ($cashTendered !== null && $payment->getMethod() === 'cashondelivery') {
                $changeAmount = $cashTendered - (float) $order->getGrandTotal();
                $orderPayment = $order->getPayment();
                $orderPayment->setAdditionalInformation('cash_tendered', $cashTendered);
                $orderPayment->setAdditionalInformation('change_amount', $changeAmount);
                $orderPayment->save();
            }

            // Deactivate quote
            $quote->setIsActive(false);
            $quote->save();

            return [
                'order' => $order,
                'accessToken' => $accessToken,
                'changeAmount' => $changeAmount,
            ];
        } finally {
            $write->releaseLock($lockName);
        }
    }

    /**
     * Get an order by its id.
     */
    public function getById(int $orderId): ?\Mage_Sales_Model_Order
    {
        $order = \Mage::getModel('sales/order')->load($orderId);

        return $order->getId() ? $order : null;
    }

    /**
     * Get an order by its increment id.
     */
    public function getByIncrementId(string $incrementId): ?\Mage_Sales_Model_Order
    {
        $order = \Mage::getModel('sales/order')->loadByIncrementId($incrementId);

        return $order->getId() ? $order : null;
    }

    /**
     * Load the order $orderId. With $customerId, only an order of that customer exists for the caller.
     *
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function requireById(int $orderId, ?int $customerId = null): Order
    {
        return $this->requireOwned($orderId > 0 ? $this->getById($orderId) : null, $customerId);
    }

    /**
     * Load the order $incrementId. With $customerId, only an order of that customer exists for the caller.
     *
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function requireByIncrementId(string $incrementId, ?int $customerId = null): Order
    {
        return $this->requireOwned($incrementId !== '' ? $this->getByIncrementId($incrementId) : null, $customerId);
    }

    /**
     * Return true when the customer $customerId may see the order $order on the storefront: the order is theirs, and
     * its state is visible on the storefront.
     */
    public function isVisibleToCustomer(Order $order, ?int $customerId): bool
    {
        return $order->getId() && $this->isOwner($order, $customerId)
            && in_array($order->getState(), Mage::getSingleton('sales/order_config')->getVisibleOnFrontStates(), true);
    }

    /**
     * Load the invoice $invoiceId. With $orderId, only an invoice of that order exists.
     *
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function requireInvoice(int $invoiceId, ?int $orderId = null): Invoice
    {
        return $this->requireDocument(Mage::getModel('sales/order_invoice'), $invoiceId, $orderId, 'Invoice not found');
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function requireShipment(int $shipmentId): Shipment
    {
        return $this->requireDocument(Mage::getModel('sales/order_shipment'), $shipmentId, null, 'Shipment not found');
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function requireCreditmemo(int $creditmemoId): Creditmemo
    {
        return $this->requireDocument(Mage::getModel('sales/order_creditmemo'), $creditmemoId, null, 'Credit memo not found');
    }

    /**
     * Get the guest order $incrementId whose one-time access token is $accessToken. Count each call against the
     * limit of the client.
     *
     * @throws Mage_Core_Exception_TooManyRequests when the client is over the limit
     */
    public function getForGuest(string $incrementId, string $accessToken): ?Order
    {
        $limit = (int) Mage::getStoreConfig(self::XML_PATH_GUEST_LOOKUP_RATE_LIMIT);
        if (!Mage::helper('core')->rateLimiter('guest_order_token', $limit, self::GUEST_LOOKUP_WINDOW, RateLimitScope::Ip)->attempt()) {
            throw new Mage_Core_Exception_TooManyRequests('Too many requests. Please try again later.', self::GUEST_LOOKUP_WINDOW);
        }

        $order = $incrementId !== '' ? $this->getByIncrementId($incrementId) : null;
        $storedToken = (string) $order?->getData('guest_access_token');

        return $storedToken !== '' && hash_equals($storedToken, $accessToken) ? $order : null;
    }

    /**
     * Clear the one-time access token of the guest order $order, so that the token cannot show the order again.
     * Return a token to create an account for the email of the order when no customer of its website has the email.
     */
    public function consumeGuestToken(Order $order): ?string
    {
        $resource = Mage::getSingleton('core/resource');
        $resource->getConnection('core_write')->update(
            $resource->getTableName('sales/order'),
            ['guest_access_token' => null],
            ['entity_id = ?' => $order->getId()],
        );

        $email = (string) $order->getCustomerEmail();
        if ($email === '') {
            return null;
        }
        $customer = Mage::getModel('customer/customer')
            ->setWebsiteId(Mage::app()->getStore($order->getStoreId())->getWebsiteId())
            ->loadByEmail($email);

        return $customer->getId() ? null : Mage::helper('sales/accountToken')->generate((int) $order->getId(), $email);
    }

    /**
     * Find the guest order $incrementId for the guest order form. The billing last name must match, and the email or
     * the billing postcode, whichever is given. Count each failed lookup against the limit of the client.
     *
     * @throws Mage_Core_Exception_TooManyRequests when the client is over the limit
     * @throws Mage_Core_Exception_NoSuchEntity when no guest order matches
     * @throws Mage_Core_Exception_Forbidden when the order belongs to a customer, who must log in to see it
     */
    public function findForGuest(
        string $incrementId,
        string $lastname,
        #[\SensitiveParameter]
        string $email = '',
        string $postcode = '',
    ): Order {
        return $this->lookUpForGuest(function () use ($incrementId, $lastname, $email, $postcode): ?Order {
            $order = $incrementId !== '' && $lastname !== '' && ($email !== '' || $postcode !== '')
                ? $this->getByIncrementId($incrementId)
                : null;
            $billing = $order?->getBillingAddress();
            if (!$billing
                || strtolower($lastname) !== strtolower((string) $billing->getLastname())
                || ($email !== '' && strtolower($email) !== strtolower((string) $order->getCustomerEmail()))
                || ($postcode !== '' && strtolower($postcode) !== strtolower((string) $billing->getPostcode()))
            ) {
                return null;
            }
            return $order;
        });
    }

    /**
     * Find the guest order $incrementId whose protect code is $protectCode, for the cookie of the guest order form.
     *
     * @throws Mage_Core_Exception_TooManyRequests when the client is over the limit
     * @throws Mage_Core_Exception_NoSuchEntity when no guest order matches
     * @throws Mage_Core_Exception_Forbidden when the order belongs to a customer, who must log in to see it
     */
    public function findForGuestByProtectCode(string $incrementId, string $protectCode): Order
    {
        return $this->lookUpForGuest(function () use ($incrementId, $protectCode): ?Order {
            $order = $incrementId !== '' && $protectCode !== '' ? $this->getByIncrementId($incrementId) : null;
            return $order && hash_equals((string) $order->getProtectCode(), $protectCode) ? $order : null;
        });
    }

    /**
     * @param callable(): ?Order $find
     */
    private function lookUpForGuest(callable $find): Order
    {
        $limiter = Mage::helper('core')->rateLimiter(
            'guest_order_lookup',
            (int) Mage::getStoreConfig(self::XML_PATH_GUEST_LOOKUP_RATE_LIMIT),
            self::GUEST_LOOKUP_WINDOW,
            RateLimitScope::Ip,
        );
        if ($limiter->tooManyAttempts()) {
            throw new Mage_Core_Exception_TooManyRequests(
                Mage::helper('sales')->__('Too Soon: You are trying to perform this operation too frequently. Please wait a few seconds and try again.'),
                self::GUEST_LOOKUP_WINDOW,
            );
        }

        $order = $find();
        if ($order && $order->getCustomerId() === null) {
            return $order;
        }
        $limiter->hit();
        throw $order
            ? new Mage_Core_Exception_Forbidden(Mage::helper('sales')->__('Please log in to view your order details.'))
            : new Mage_Core_Exception_NoSuchEntity(Mage::helper('sales')->__('Entered data is incorrect. Please try again.'));
    }

    private function isOwner(Order $order, ?int $customerId): bool
    {
        return $customerId > 0 && (int) $order->getCustomerId() === $customerId;
    }

    private function requireOwned(?Order $order, ?int $customerId): Order
    {
        if (!$order || ($customerId !== null && !$this->isOwner($order, $customerId))) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('sales')->__('Order not found'));
        }
        return $order;
    }

    /**
     * @template T of Mage_Sales_Model_Abstract
     * @param T $document an empty document model
     * @return T
     */
    private function requireDocument(Mage_Sales_Model_Abstract $document, int $id, ?int $orderId, string $notFound): Mage_Sales_Model_Abstract
    {
        if ($id > 0) {
            $document->load($id);
        }
        if (!$document->getId() || ($orderId !== null && (int) $document->getOrderId() !== $orderId)) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('sales')->__($notFound));
        }
        return $document;
    }

    /**
     * Get all orders with billing address joined (no N+1 queries).
     *
     * @param array<string, mixed> $filters status, state, storeId, customerId, email,
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
        $collection = $this->buildOrderCollection($this->intFilter($filters, 'customerId'), $filters);

        if ($allowedStoreIds !== null) {
            $collection->getSelect()->where(
                'main_table.store_id IN (?)',
                $allowedStoreIds === [] ? [-1] : $allowedStoreIds,
            );
        }

        $email = $this->stringFilter($filters, 'email');
        $emailLike = $this->stringFilter($filters, 'emailLike');
        if ($email !== null) {
            $collection->addFieldToFilter('customer_email', $email);
        } elseif ($emailLike !== null && mb_strlen($emailLike) >= 3) {
            $collection->addFieldToFilter('customer_email', ['like' => '%' . $emailLike . '%']);
        }

        $incrementId = $this->stringFilter($filters, 'incrementId');
        if ($incrementId !== null) {
            $collection->addFieldToFilter('increment_id', $incrementId);
        }

        // Free-text search, like the admin order grid: every word must appear in the
        // order number, the email, the customer name, or the billing name (the only
        // name a guest order has). The billing address is joined by buildOrderCollection.
        $words = $this->searchWords($this->stringFilter($filters, 'search'));
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
     * Get customer orders with billing address joined (no N+1 queries).
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
     * Build an order collection with billing address joined and common filters applied.
     *
     * @param int|null $customerId When set, restrict to this customer's orders
     * @param array<string, mixed> $filters
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

        $status = $this->stringFilter($filters, 'status');
        if ($status !== null) {
            $collection->addFieldToFilter('status', $status);
        }

        // Status is the merchant-visible label and can be renamed per install; state is
        // the fixed lifecycle stage behind it. Both are worth filtering on.
        $state = $this->stringFilter($filters, 'state');
        if ($state !== null) {
            $collection->addFieldToFilter('state', $state);
        }

        $storeId = $this->intFilter($filters, 'storeId');
        if ($storeId !== null) {
            $collection->addFieldToFilter('store_id', $storeId);
        }

        // `since` predates the createdFrom/createdTo/updatedSince set and means the same
        // as updatedSince; keep honouring it.
        $filters['updatedSince'] ??= $filters['since'] ?? null;
        $this->applyDateRangeFilters($collection, $filters);

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
     * $order->getPayment() lazy-loads a payment collection per order — one
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
            // Mage_Sales_Model_Order::getPayment()'s first-not-deleted pick.
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

    /**
     * Cancel the order $order. Add $reason to the history of the order.
     *
     * @throws Mage_Core_Exception_Conflict when the order cannot be canceled, or another request holds the order lock
     */
    public function cancel(Order $order, ?string $reason = null): Order
    {
        return $this->changeState($order, 'cancel', $reason);
    }

    /**
     * Put the order $order on hold. Add $reason to the history of the order.
     *
     * @throws Mage_Core_Exception_Conflict when the order cannot be held, or another request holds the order lock
     */
    public function hold(Order $order, ?string $reason = null): Order
    {
        return $this->changeState($order, 'hold', $reason);
    }

    /**
     * Release the order $order from hold. Add $reason to the history of the order.
     *
     * @throws Mage_Core_Exception_Conflict when the order is not on hold, or another request holds the order lock
     */
    public function unhold(Order $order, ?string $reason = null): Order
    {
        return $this->changeState($order, 'unhold', $reason);
    }

    /**
     * @param 'cancel'|'hold'|'unhold' $action
     */
    private function changeState(Order $order, string $action, ?string $reason): Order
    {
        return $this->withOrderLock((int) $order->getId(), function () use ($order, $action, $reason): Order {
            // Re-read under the lock, so that the check sees what another request changed while this one waited
            $order->load((int) $order->getId());
            [$allowed, $refusal, $prefix] = match ($action) {
                'cancel' => [$order->canCancel(), 'Order cannot be cancelled', 'Order cancelled: '],
                'hold' => [$order->canHold(), 'Order cannot be held', 'Order held: '],
                'unhold' => [$order->canUnhold(), 'Order is not on hold', 'Order released from hold: '],
            };
            if (!$allowed) {
                throw new Mage_Core_Exception_Conflict(Mage::helper('sales')->__($refusal));
            }

            $order->{$action}();
            if ($reason) {
                $history = $order->addStatusHistoryComment($prefix . $reason, false);
                if ($action === 'cancel') {
                    $history->setIsCustomerNotified(true);
                }
            }
            $order->save();
            return $order;
        });
    }

    /**
     * Add the comment $note to the history of the order $order, and email it to the customer when $notifyCustomer is
     * true. A comment without text must set a new status.
     *
     * @param string|null $status New order status; the order refuses one not assigned to its current state
     * @throws Mage_Core_Exception_Conflict when the order does not take comments
     * @throws Mage_Core_Exception when the comment is empty, or the status is not valid
     */
    public function addNote(
        Order $order,
        string $note,
        bool $notifyCustomer = false,
        bool $visibleOnFront = false,
        ?string $status = null,
    ): Order {
        if (!$order->canComment()) {
            throw new Mage_Core_Exception_Conflict(Mage::helper('sales')->__('This order does not accept comments'));
        }
        if (trim($note) === '' && ($status === null || $status === $order->getStatus())) {
            throw new Mage_Core_Exception(Mage::helper('sales')->__('Comment text is required'));
        }

        $order->addStatusHistoryComment($note, $status ?? false)
            ->setIsCustomerNotified($notifyCustomer)
            ->setIsVisibleOnFront($visibleOnFront);
        $order->save();
        $this->sendSafely(fn() => $order->sendOrderUpdateEmail($notifyCustomer, trim(strip_tags($note))));

        return $order;
    }

    /**
     * Get order status history. A customer or guest reader gets only the entries that are
     * visible on the storefront; a backend reader gets every entry.
     *
     * @param \Mage_Sales_Model_Order $order Order
     * @return array Order notes
     */
    public function getNotes(\Mage_Sales_Model_Order $order, bool $visibleOnly = false): array
    {
        $notes = [];

        // Newest first, as the admin shows them. The collection loads in that order, but a
        // comment added in this request sits at its end, so sort again.
        $history = array_values($order->getStatusHistoryCollection()->getItems());
        usort($history, fn($a, $b) => [(string) $b->getCreatedAt(), (int) $b->getId()] <=> [(string) $a->getCreatedAt(), (int) $a->getId()]);

        foreach ($history as $status) {
            if ($visibleOnly && ($status->isDeleted() || !$status->getIsVisibleOnFront())) {
                continue;
            }

            $notes[] = [
                'note' => $status->getComment(),
                'status' => $status->getStatus(),
                'createdAt' => $status->getCreatedAt(),
                'isCustomerNotified' => (bool) $status->getIsCustomerNotified(),
                'isVisibleOnFront' => (bool) $status->getIsVisibleOnFront(),
            ];
        }

        return $notes;
    }

    /**
     * Get the shipments of an order, with their comments preloaded. A customer or guest reader gets
     * only the shipment comments that are visible on the storefront.
     *
     * @return list<\Mage_Sales_Model_Order_Shipment>
     */
    public function getShipments(\Mage_Sales_Model_Order $order, bool $visibleCommentsOnly = false): array
    {
        $models = [];
        foreach ($order->getShipmentsCollection() as $shipment) {
            $models[] = $shipment;
        }

        $commentsByShipment = [];
        if ($visibleCommentsOnly && $models !== []) {
            $comments = \Mage::getResourceModel('sales/order_shipment_comment_collection')
                ->addFieldToFilter('parent_id', ['in' => array_map(static fn($s): int => (int) $s->getId(), $models)])
                ->addVisibleOnFrontFilter();
            foreach ($comments as $comment) {
                $commentsByShipment[(int) $comment->getParentId()][] = $comment;
            }
        }

        // getShipmentsCollection() is cached on the order, so a preload left over from an earlier
        // call would otherwise leak the filtered set into a later backend read.
        foreach ($models as $shipment) {
            if ($visibleCommentsOnly) {
                $shipment->setData('_preloaded_comments', $commentsByShipment[(int) $shipment->getId()] ?? []);
            } else {
                $shipment->unsetData('_preloaded_comments');
            }
        }

        return $models;
    }

    /**
     * Prepare an invoice of the order $order, which nothing saves yet.
     *
     * @param array<int, float|string> $qtys Order item id => qty to invoice. An empty map invoices every item.
     * @throws Mage_Core_Exception_Conflict when the order cannot be invoiced
     * @throws Mage_Core_Exception when a qty is not valid, or the invoice has no items
     */
    public function prepareInvoice(Order $order, array $qtys = []): Invoice
    {
        if (!$order->canInvoice()) {
            throw new Mage_Core_Exception_Conflict(Mage::helper('sales')->__('Order cannot be invoiced (already fully invoiced or not in an invoiceable state)'));
        }

        /** @var Mage_Sales_Model_Service_Order $service */
        $service = Mage::getModel('sales/service_order', $order);
        $service->updateLocaleNumbers($qtys);
        foreach ($qtys as $orderItemId => $qty) {
            $orderItem = $order->getItemById($orderItemId);
            // Dummy (bundle/configurable parent) items take their qty from the order, so neither check applies to them
            if (!$orderItem || $orderItem->isDummy()) {
                continue;
            }
            if (!$orderItem->getIsQtyDecimal() && fmod((float) $qty, 1.0) !== 0.0) {
                throw new Mage_Core_Exception(Mage::helper('sales')->__('Order item %s does not accept a fractional qty', $orderItemId));
            }
            if ((float) $qty > (float) $orderItem->getQtyToInvoice()) {
                throw new Mage_Core_Exception(Mage::helper('sales')->__('Qty to invoice for order item %s exceeds the qty available to invoice', $orderItemId));
            }
        }

        $invoice = $service->prepareInvoice($qtys);
        if (!$invoice->getTotalQty()) {
            throw new Mage_Core_Exception(Mage::helper('sales')->__('Cannot create invoice: no items to invoice'));
        }
        return $invoice;
    }

    /**
     * Invoice an order. Email the invoice when $sendEmail is true, which is $notifyCustomer when null, and put the
     * comment in the email when $notifyCustomer is true. With $ship, or when the payment of the order forces it,
     * ship the same items with the $tracks in the same transaction.
     *
     * @param array<int, float|string> $qtys Order item id => qty to invoice. An empty map invoices every item.
     * @param string|null $captureCase One of self::CAPTURE_CASES.
     * @param list<\Mage_Sales_Model_Order_Shipment_Track> $tracks Tracking numbers of the shipment.
     * @throws Mage_Core_Exception_Conflict When the order cannot be invoiced, or another request holds the order lock.
     * @throws Mage_Core_Exception When a qty or the capture mode is not valid, or the invoice has no items.
     */
    public function invoice(
        Order $order,
        array $qtys = [],
        ?string $captureCase = null,
        ?string $comment = null,
        bool $notifyCustomer = false,
        bool $visibleOnFront = false,
        ?bool $sendEmail = null,
        bool $ship = false,
        array $tracks = [],
    ): Invoice {
        if ($captureCase !== null && !in_array($captureCase, self::CAPTURE_CASES, true)) {
            throw new Mage_Core_Exception(Mage::helper('sales')->__('Invalid capture mode; expected one of: %s', implode(', ', self::CAPTURE_CASES)));
        }
        $sendEmail ??= $notifyCustomer;

        [$invoice, $shipment] = $this->withOrderLock((int) $order->getId(), function () use ($order, $qtys, $captureCase, $comment, $notifyCustomer, $visibleOnFront, $sendEmail, $ship, $tracks): array {
            // Re-read under the lock so canInvoice() reflects any invoice another
            // request created while we waited, otherwise both would register one.
            $order->load((int) $order->getId());
            $invoice = $this->prepareInvoice($order, $qtys);

            if ($captureCase === Invoice::CAPTURE_ONLINE && !$invoice->canCapture()) {
                throw new Mage_Core_Exception(Mage::helper('sales')->__('The order\'s payment method does not support online capture'));
            }
            if ($captureCase !== null) {
                $invoice->setRequestedCaptureCase($captureCase);
            }
            if ($comment) {
                $invoice->addComment($comment, $notifyCustomer, $visibleOnFront);
            }

            $invoice->register();
            if ($sendEmail) {
                $invoice->setEmailSent();
            }
            $invoice->getOrder()->setCustomerNoteNotify($sendEmail)->setIsInProcess();
            $transaction = Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder());

            $shipment = null;
            if ($ship || $order->getForcedDoShipmentWithInvoice()) {
                $shipment = Mage::getModel('sales/service_order', $invoice->getOrder())->prepareShipment($qtys);
                if ($shipment->getTotalQty()) {
                    $shipment->register();
                    foreach ($tracks as $track) {
                        $shipment->addTrack($track);
                    }
                    $transaction->addObject($shipment->setEmailSent($invoice->getEmailSent()));
                } else {
                    $shipment = null;
                }
            }
            $transaction->save();

            return [$invoice, $shipment];
        });

        $this->sendSafely(fn() => $invoice->sendEmail($sendEmail, $notifyCustomer ? (string) $comment : ''));
        if ($shipment) {
            $this->sendSafely(fn() => $shipment->sendEmail($sendEmail));
        }

        return $invoice;
    }

    /**
     * Capture an invoice.
     *
     * @throws \Mage_Core_Exception_Conflict When the invoice cannot be captured, or another request holds the order lock.
     */
    public function captureInvoice(Invoice $invoice): Invoice
    {
        return $this->changeInvoice($invoice, 'capture');
    }

    /**
     * Void an invoice.
     *
     * @throws \Mage_Core_Exception_Conflict When the invoice cannot be voided, or another request holds the order lock.
     */
    public function voidInvoice(Invoice $invoice): Invoice
    {
        return $this->changeInvoice($invoice, 'void');
    }

    /**
     * Cancel an invoice.
     *
     * @throws \Mage_Core_Exception_Conflict When the invoice cannot be canceled, or another request holds the order lock.
     */
    public function cancelInvoice(Invoice $invoice): Invoice
    {
        return $this->changeInvoice($invoice, 'cancel');
    }

    /**
     * @param 'capture'|'void'|'cancel' $action
     */
    private function changeInvoice(Invoice $invoice, string $action): Invoice
    {
        // Capture, void and cancel change the order totals, so they take the order lock too.
        return $this->withOrderLock((int) $invoice->getOrderId(), function () use ($invoice, $action) {
            // Re-read under the lock so the can*() checks reflect the live state.
            $invoice->load((int) $invoice->getId());

            [$allowed, $refusal] = match ($action) {
                'capture' => [$invoice->canCapture(), 'The invoice cannot be captured in its current state'],
                'void' => [$invoice->canVoid(), 'The invoice cannot be voided in its current state'],
                'cancel' => [$invoice->canCancel(), 'The invoice cannot be canceled in its current state'],
            };
            if (!$allowed) {
                throw new Mage_Core_Exception_Conflict(Mage::helper('sales')->__($refusal));
            }

            $invoice->{$action}();
            $invoice->getOrder()->setIsInProcess();

            Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder())
                ->save();

            return $invoice;
        });
    }

    /**
     * Prepare a shipment of the order $order with the tracking numbers $tracks, which nothing saves yet.
     *
     * @param array<int, float|string> $qtys Order item id => qty to ship. An empty map ships every item.
     * @param list<\Mage_Sales_Model_Order_Shipment_Track> $tracks
     * @throws Mage_Core_Exception_Conflict when the order cannot be shipped, or only with its invoice
     * @throws Mage_Core_Exception when the shipment has no items
     */
    public function prepareShipment(Order $order, array $qtys = [], array $tracks = []): Shipment
    {
        if ($order->getForcedDoShipmentWithInvoice()) {
            throw new Mage_Core_Exception_Conflict(Mage::helper('sales')->__('Cannot do shipment for the order separately from invoice.'));
        }
        if (!$order->canShip()) {
            throw new Mage_Core_Exception_Conflict(Mage::helper('sales')->__('Order cannot be shipped (already fully shipped or not in a shippable state)'));
        }

        $shipment = Mage::getModel('sales/service_order', $order)->prepareShipment($qtys);
        if (!$shipment->getTotalQty()) {
            throw new Mage_Core_Exception(Mage::helper('sales')->__('Cannot create shipment: no items to ship'));
        }
        foreach ($tracks as $track) {
            $shipment->addTrack($track);
        }
        return $shipment;
    }

    /**
     * Ship an order. Email the shipment when $sendEmail is true, which is $notifyCustomer when null, and put the
     * comment in the email when $notifyCustomer is true. With $packages, ask the carrier for the shipping labels.
     *
     * @param array<int, float|string> $qtys Order item id => qty to ship. An empty map ships every item.
     * @param list<\Mage_Sales_Model_Order_Shipment_Track> $tracks Tracking numbers to add to the shipment.
     * @param array<int, array<string, mixed>>|null $packages The packages of the admin packaging form.
     * @throws \Mage_Core_Exception_Conflict When the order cannot be shipped, or another request holds the order lock.
     * @throws \Mage_Core_Exception When the shipment has no items, a tracking number is empty, or the carrier refuses a label.
     */
    public function ship(
        Order $order,
        array $qtys = [],
        array $tracks = [],
        ?string $comment = null,
        bool $notifyCustomer = false,
        bool $visibleOnFront = false,
        ?bool $sendEmail = null,
        ?array $packages = null,
    ): Shipment {
        $sendEmail ??= $notifyCustomer;
        $shipment = $this->withOrderLock((int) $order->getId(), function () use ($order, $qtys, $tracks, $comment, $notifyCustomer, $visibleOnFront, $sendEmail, $packages): Shipment {
            // Re-read under the lock so canShip() reflects any shipment another
            // request created while we waited, otherwise both would register one
            // and decrement inventory twice.
            $order->load((int) $order->getId());
            $shipment = $this->prepareShipment($order, $qtys, $tracks);
            if ($comment) {
                $shipment->addComment($comment, $notifyCustomer, $visibleOnFront);
            }

            $shipment->register();
            if ($sendEmail) {
                $shipment->setEmailSent();
            }
            if ($packages !== null) {
                $this->createShippingLabel($shipment, $packages);
            }

            // Without a change on the order itself its save() short-circuits, so the
            // qty_shipped that register() put on the items never persists.
            $shipment->getOrder()->setCustomerNoteNotify($sendEmail)->setIsInProcess();

            Mage::getModel('core/resource_transaction')
                ->addObject($shipment)
                ->addObject($shipment->getOrder())
                ->save();

            return $shipment;
        });

        $this->sendSafely(fn() => $shipment->sendEmail($sendEmail, $notifyCustomer ? (string) $comment : ''));

        return $shipment;
    }

    /**
     * Add the tracking number $track to the shipment $shipment. The track checks its number and its carrier.
     *
     * @throws Mage_Core_Exception_Input when the number or the carrier is empty
     */
    public function addTrack(Shipment $shipment, Mage_Sales_Model_Order_Shipment_Track $track): Shipment
    {
        $shipment->addTrack($track)->save();
        return $shipment;
    }

    /**
     * Remove the tracking number $trackId from the shipment $shipment.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when the shipment has no such track
     */
    public function removeTrack(Shipment $shipment, int $trackId): Shipment
    {
        $track = Mage::getModel('sales/order_shipment_track');
        if ($trackId > 0) {
            $track->load($trackId);
        }
        if (!$track->getId() || $track->getParentId() !== (int) $shipment->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('sales')->__('Tracking entry not found for this shipment'));
        }
        $track->delete();
        return $shipment;
    }

    /**
     * Ask the carrier of the order of $shipment for the labels of $packages. Put the labels and their tracking numbers
     * on the shipment, which the caller saves. Return false when the carrier makes no label.
     *
     * @param array<int, array<string, mixed>> $packages
     * @throws Mage_Core_Exception when the carrier returns an error
     */
    public function createShippingLabel(Shipment $shipment, array $packages): bool
    {
        $carrier = $shipment->getOrder()->getShippingCarrier();
        if (!$carrier->isShippingLabelsAvailable()) {
            return false;
        }
        $shipment->setPackages($packages);
        $response = Mage::getModel('shipping/shipping')->requestToShipment($shipment);
        if ($response->hasErrors()) {
            Mage::throwException($response->getErrors());
        }

        $labels = [];
        foreach ($response->hasInfo() ? $response->getInfo() : [] as $info) {
            if (!empty($info['tracking_number']) && !empty($info['label_content'])) {
                $labels[sprintf('label_%s_package_%d.pdf', $shipment->getIncrementId(), count($labels) + 1)] = $info['label_content'];
                $shipment->addTrack(Mage::getModel('sales/order_shipment_track')
                    ->setNumber((string) $info['tracking_number'])
                    ->setCarrierCode($carrier->getCarrierCode())
                    ->setTitle((string) Mage::getStoreConfig('carriers/' . $carrier->getCarrierCode() . '/title', $shipment->getStoreId())));
            }
        }
        if ($labels === []) {
            return false;
        }

        $shipment->setShippingLabel(count($labels) === 1 ? reset($labels) : $this->zipShippingLabels($labels, 'shipping_label_'));
        return true;
    }

    /**
     * Return a ZIP archive of $files, which maps a file name to its content.
     *
     * @param array<string, string> $files
     * @throws Mage_Core_Exception when the archive cannot be written
     */
    public function zipShippingLabels(array $files, string $tempFilePrefix = 'shipping_labels_'): string
    {
        $tempFile = tempnam(Mage::getBaseDir('var') . DS . 'tmp', $tempFilePrefix);
        if ($tempFile === false) {
            throw new Mage_Core_Exception(Mage::helper('sales')->__('Cannot create temporary file for shipping labels archive.'));
        }

        try {
            $zip = new ZipArchive();
            $result = $zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            if ($result !== true) {
                throw new Mage_Core_Exception(Mage::helper('sales')->__('Cannot create ZIP archive for shipping labels. Error code: %s', $result));
            }
            foreach ($files as $filename => $content) {
                $zip->addFromString($filename, $content);
            }
            $zip->close();

            $zipContent = file_get_contents($tempFile);
            if ($zipContent === false) {
                throw new Mage_Core_Exception(Mage::helper('sales')->__('Cannot read created ZIP archive.'));
            }
            return $zipContent;
        } finally {
            @unlink($tempFile);
        }
    }

    /**
     * Add the comment $comment to the invoice, the shipment or the credit memo $document. Email it to the customer when
     * $notifyCustomer is true.
     *
     * @template T of Invoice|Shipment|Creditmemo
     * @param T $document
     * @return T
     * @throws Mage_Core_Exception when the comment is empty
     */
    public function addComment(
        Invoice|Shipment|Creditmemo $document,
        string $comment,
        bool $notifyCustomer = false,
        bool $visibleOnFront = false,
    ): Invoice|Shipment|Creditmemo {
        $comment = trim($comment);
        if ($comment === '') {
            throw new Mage_Core_Exception(Mage::helper('sales')->__('Comment text is required'));
        }

        // Save the comments only: a save of the credit memo itself would register it again
        $document->addComment($comment, $notifyCustomer, $visibleOnFront);
        $document->getCommentsCollection()->save();
        $this->sendSafely(fn() => $document->sendUpdateEmail($notifyCustomer, $comment));

        return $document;
    }

    /**
     * Prepare a credit memo of the order $order, or of its invoice $invoice, which nothing saves yet. Return to stock
     * the items in $backToStockItemIds and the children of an item in it. With null, return every item when the store
     * returns refunded items to stock.
     *
     * @param array<string, mixed> $data Credit memo data for prepareCreditmemo(): qtys, shipping_amount, adjustment_positive, adjustment_negative
     * @param array<int, bool>|null $backToStockItemIds Order item id => true.
     * @throws Mage_Core_Exception_Conflict when the state of the order does not allow a refund
     * @throws Mage_Core_Exception when the shipping amount is negative, or the credit memo has no items
     */
    public function prepareCreditmemo(Order $order, array $data, ?array $backToStockItemIds = null, ?Invoice $invoice = null): Creditmemo
    {
        if (!$order->canCreditmemo()) {
            throw new Mage_Core_Exception_Conflict(Mage::helper('sales')->__('Order cannot be refunded (already fully refunded or not in a refundable state)'));
        }
        if (isset($data['shipping_amount']) && (float) $data['shipping_amount'] < 0) {
            throw new Mage_Core_Exception(Mage::helper('sales')->__('Shipping amount must be >= 0'));
        }

        /** @var Mage_Sales_Model_Service_Order $service */
        $service = Mage::getModel('sales/service_order', $order);
        $creditmemo = $invoice ? $service->prepareInvoiceCreditmemo($invoice, $data) : $service->prepareCreditmemo($data);
        if (!$creditmemo) {
            throw new Mage_Core_Exception(Mage::helper('sales')->__('Cannot create credit memo: no items to refund'));
        }

        $autoReturn = Mage::helper('cataloginventory')->isAutoReturnEnabled();
        foreach ($creditmemo->getAllItems() as $creditmemoItem) {
            $orderItem = $creditmemoItem->getOrderItem();
            $creditmemoItem->setBackToStock($backToStockItemIds === null
                ? $autoReturn
                : isset($backToStockItemIds[(int) $orderItem->getId()]) || isset($backToStockItemIds[(int) $orderItem->getParentItemId()]));
        }

        return $creditmemo;
    }

    /**
     * Create a credit memo for an order, or for its invoice $invoice. Email it when $sendEmail is true, and put the
     * comment in the email when $notifyCustomer is true.
     *
     * The money rules stay in one place: the refund never exceeds the order's refundable balance,
     * and a memo that refunds nothing is not a refund.
     *
     * @param array<string, mixed> $data Credit memo data for prepareCreditmemo().
     * @param array<int, bool>|null $backToStockItemIds Order item ids to return to stock, see prepareCreditmemo().
     * @throws \Mage_Core_Exception_Conflict When the state of the order does not allow a refund, or another request holds the order lock.
     * @throws \Mage_Core_Exception When the refund breaks a money rule.
     * @throws \Exception When the refund itself fails, for example at the payment gateway.
     */
    public function refund(
        Order $order,
        array $data,
        ?string $comment = null,
        bool $offlineRefund = true,
        ?array $backToStockItemIds = null,
        bool $notifyCustomer = false,
        bool $visibleOnFront = false,
        bool $sendEmail = false,
        ?Invoice $invoice = null,
    ): Creditmemo {
        $creditmemo = $this->withOrderLock((int) $order->getId(), function () use ($order, $data, $comment, $offlineRefund, $backToStockItemIds, $notifyCustomer, $visibleOnFront, $sendEmail, $invoice): Creditmemo {
            // Re-read under the lock so canCreditmemo() sees the live
            // total_refunded, not a value another request changed while we waited.
            $order->load((int) $order->getId());
            $creditmemo = $this->prepareCreditmemo($order, $data, $backToStockItemIds, $invoice);
            return $this->registerCreditmemo($creditmemo, $comment, $offlineRefund, $notifyCustomer, $visibleOnFront, $sendEmail);
        });
        $this->sendSafely(fn() => $creditmemo->sendEmail($sendEmail, $notifyCustomer ? (string) $comment : ''));

        return $creditmemo;
    }

    /**
     * Save the credit memo $creditmemo that prepareCreditmemo() returned, for a transport that changes it first.
     * See refund().
     *
     * @throws \Mage_Core_Exception_Conflict When the state of the order does not allow a refund, or another request holds the order lock.
     * @throws \Mage_Core_Exception When the refund breaks a money rule.
     * @throws \Exception When the refund itself fails, for example at the payment gateway.
     */
    public function saveCreditmemo(
        Creditmemo $creditmemo,
        ?string $comment = null,
        bool $offlineRefund = true,
        bool $notifyCustomer = false,
        bool $visibleOnFront = false,
        bool $sendEmail = false,
    ): Creditmemo {
        $this->withOrderLock((int) $creditmemo->getOrderId(), function () use ($creditmemo, $comment, $offlineRefund, $notifyCustomer, $visibleOnFront, $sendEmail): Creditmemo {
            if (!$creditmemo->getOrder()->canCreditmemo()) {
                throw new Mage_Core_Exception_Conflict(Mage::helper('sales')->__('Order cannot be refunded (already fully refunded or not in a refundable state)'));
            }
            return $this->registerCreditmemo($creditmemo, $comment, $offlineRefund, $notifyCustomer, $visibleOnFront, $sendEmail);
        });
        $this->sendSafely(fn() => $creditmemo->sendEmail($sendEmail, $notifyCustomer ? (string) $comment : ''));

        return $creditmemo;
    }

    private function registerCreditmemo(
        Creditmemo $creditmemo,
        ?string $comment,
        bool $offlineRefund,
        bool $notifyCustomer,
        bool $visibleOnFront,
        bool $sendEmail,
    ): Creditmemo {
        $order = $creditmemo->getOrder();
        // Cap the refund at what the order can still refund, so an inflated
        // adjustment cannot over-refund or mint excess store credit.
        $refundable = (float) $order->getBaseTotalPaid() - (float) $order->getBaseTotalRefunded();
        if ((float) $creditmemo->getBaseGrandTotal() > $refundable + 0.0001) {
            throw new Mage_Core_Exception(Mage::helper('sales')->__('Refund amount exceeds the order\'s refundable balance'));
        }
        // A free order opts out through the flag.
        if ((float) $creditmemo->getBaseGrandTotal() <= 0 && !$creditmemo->getAllowZeroGrandTotal()) {
            throw new Mage_Core_Exception(Mage::helper('sales')->__('Credit memo total must be greater than zero'));
        }

        // Add the comment before register/save so it is persisted with the
        // credit memo. A separate save after the commit would re-trigger the
        // post-save observers on an already-refunded memo.
        if ($comment) {
            $creditmemo->addComment($comment, $notifyCustomer, $visibleOnFront);
        }
        $creditmemo->setOfflineRequested($offlineRefund);
        $creditmemo->register();
        if ($sendEmail) {
            $creditmemo->setEmailSent();
        }
        $order->setCustomerNoteNotify($sendEmail);

        $transaction = Mage::getModel('core/resource_transaction')
            ->addObject($creditmemo)
            ->addObject($order);
        if ($creditmemo->getInvoice()) {
            $transaction->addObject($creditmemo->getInvoice());
        }
        $transaction->save();

        return $creditmemo;
    }

    /**
     * Run $send, an email that follows a saved change. A failure is logged, so that a retry does not repeat the change.
     */
    private function sendSafely(callable $send): void
    {
        try {
            $send();
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    /**
     * Run a state-changing operation while holding a per-order advisory lock.
     *
     * All order mutations (invoice, ship, cancel, refund) share one lock name so
     * two concurrent requests can't both pass their can*() guard and both mutate
     * the same order, duplicate invoices/shipments or double-cancel side effects.
     * The lock releases on disconnect, so a crashed worker can't wedge the order.
     */
    private function withOrderLock(int $orderId, callable $operation): mixed
    {
        $write = \Mage::getSingleton('core/resource')->getConnection('core_write');
        $lockName = 'maho_order_mutate:' . $orderId;
        if (!$write->getLock($lockName, 5)) {
            throw new \Mage_Core_Exception_Conflict('Another operation is already in progress for this order');
        }

        try {
            return $operation();
        } finally {
            $write->releaseLock($lockName);
        }
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
