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
use Mage_Checkout_Model_Actor as Actor;
use Mage_Sales_Model_Order as Order;
use Mage_Sales_Model_Order_Creditmemo as Creditmemo;
use Mage_Sales_Model_Order_Invoice as Invoice;
use Mage_Sales_Model_Order_Item as OrderItem;
use Mage_Sales_Model_Order_Shipment as Shipment;
use Mage_Sales_Model_Resource_Order_Collection as OrderCollection;

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

    /** The quote field of the cash that the customer of a POS order gives, which place() sets. */
    private const CASH_TENDERED_KEY = 'cash_tendered';

    /**
     * Refuse the order of $quote when it has no customer and its store does not allow guest checkout. The admin
     * scope places a guest order in any store, as the admin order form does.
     *
     * @throws Mage_Core_Exception_Forbidden
     */
    public function assertGuestCheckoutAllowed(\Mage_Sales_Model_Quote $quote): void
    {
        if (!$quote->getCustomerId() && !Mage::app()->getStore()->isAdmin() && !$quote->isAllowedGuestCheckout()) {
            throw new Mage_Core_Exception_Forbidden(
                Mage::helper('checkout')->__('Sorry, guest checkout is not enabled. Please try again or contact store owner.'),
            );
        }
    }

    /**
     * Place the order of $quote through submit(), for the API. $guestEmail is the email of a guest order, $orderNote
     * the note of the customer, and $cashTendered and $employeeId the fields of a POS order.
     *
     * @param list<int|string>|null $agreementIds see submit()
     * @return array{order: Order, accessToken: ?string, changeAmount: ?float}
     * @throws Mage_Core_Exception
     */
    public function place(
        \Mage_Sales_Model_Quote $quote,
        ?string $guestEmail = null,
        ?string $orderNote = null,
        ?float $cashTendered = null,
        ?int $employeeId = null,
        Actor $actor = Actor::Customer,
        ?array $agreementIds = null,
        ?bool $sendConfirmation = null,
    ): array {
        if ($guestEmail && !$quote->getCustomerId()) {
            $quote->setCustomerEmail($guestEmail);
        }
        if ($orderNote) {
            $quote->setCustomerNote($orderNote)->setCustomerNoteNotify(false);
        }
        if ($employeeId) {
            $quote->setData('employee_id', $employeeId);
        }
        $quote->setData(self::CASH_TENDERED_KEY, $cashTendered);

        $order = $this->submit($quote, $actor, $agreementIds, sendConfirmation: $sendConfirmation);
        $changeAmount = $order->getPayment()->getAdditionalInformation('change_amount');

        return [
            'order' => $order,
            'accessToken' => $order->getData('guest_access_token') ?: null,
            'changeAmount' => $changeAmount === null ? null : (float) $changeAmount,
        ];
    }

    /**
     * Check that $quote can become an order. A Customer must reach the minimum order amount, and must accept each
     * required agreement in $agreementIds.
     *
     * @param list<int|string>|null $agreementIds null when the flow of the caller has no agreements step
     * @throws Mage_Core_Exception when the cart is not active or is empty
     * @throws Mage_Core_Exception_Input one error for each other rule that $quote breaks
     */
    public function validateQuote(\Mage_Sales_Model_Quote $quote, Actor $actor, ?array $agreementIds = null): void
    {
        $helper = Mage::helper('sales');
        // The admin order create keeps its cart inactive, so that the storefront does not show it
        if ($quote->getIsActive() === false && !$quote->getIsSuperMode()) {
            throw new Mage_Core_Exception($helper->__('Cart is not active or does not exist'));
        }
        if (!$quote->getAllVisibleItems()) {
            throw new Mage_Core_Exception($helper->__('Cart is empty'));
        }

        $errors = new Mage_Core_Exception_Input();
        foreach ($quote->getAllItems() as $item) {
            $messages = $item->getHasError() ? $item->getMessage(false) : [];
            foreach (is_array($messages) ? $messages : [] as $message) {
                $errors->addError('items', (string) $message);
            }
        }
        if ($quote->getHasError() && $errors->getErrors() === []) {
            foreach ($quote->getErrors() as $message) {
                $errors->addError('items', $message->getText());
            }
        }

        if ($actor === Actor::Customer) {
            $minimumAmountError = $this->getMinimumAmountError($quote);
            if ($minimumAmountError !== null) {
                $errors->addError('subtotal', $minimumAmountError);
            }
            if ($agreementIds !== null && array_diff(Mage::helper('checkout')->getRequiredAgreementIds(), $agreementIds) !== []) {
                $errors->addError('agreement_ids', Mage::helper('checkout')->__('Please agree to all the terms and conditions before placing the order.'));
            }
        }

        $cart = $this->getCartService();
        $addresses = ['billing_address' => $quote->getBillingAddress()];
        if (!$quote->isVirtual()) {
            $addresses['shipping_address'] = $quote->getShippingAddress();
        }
        foreach ($addresses as $field => $address) {
            try {
                $cart->validateAddress($address);
            } catch (Mage_Core_Exception_Input $e) {
                foreach ($e->getErrors() as $error) {
                    $errors->addError($field, $field === 'billing_address'
                        ? $helper->__('Please check billing address information. %s', $error['message'])
                        : $helper->__('Please check shipping address information. %s', $error['message']));
                }
            }
        }

        if (!$quote->isVirtual()) {
            $shippingMethod = (string) $quote->getShippingAddress()->getShippingMethod();
            try {
                if ($shippingMethod === '') {
                    Mage::throwException($helper->__('Please specify a shipping method.'));
                }
                $cart->assertShippingMethodAvailable($quote, $shippingMethod);
            } catch (Mage_Core_Exception $e) {
                $errors->addError('shipping_method', $e->getMessage());
            }
        }

        // Mage_Checkout_Service_Cart::selectPaymentMethod() checks the method. A PayPal order is paid before it is placed
        if (!$quote->getPayment()->getMethod()) {
            $errors->addError('payment_method', $helper->__('Please select a valid payment method.'));
        }

        $errors->throwIfErrors();
    }

    /**
     * Return the message of the minimum order amount when the subtotal of $quote is below it, or null.
     */
    public function getMinimumAmountError(\Mage_Sales_Model_Quote $quote): ?string
    {
        if ($quote->validateMinimumAmount()) {
            return null;
        }

        return Mage::getStoreConfig('sales/minimum_order/error_message', $quote->getStoreId())
            ?: Mage::helper('checkout')->__('Subtotal must exceed minimum order amount');
    }

    /**
     * Place the order of $quote, in the store of $quote, or in the admin scope when the caller is in it. The checks
     * of validateQuote() run first. Then, under a lock on the cart, the gift cards and the totals are checked again,
     * the order is saved, and the cart is closed. A guest order gets an access token. The new order email follows
     * $sendConfirmation, and checkout_submit_all_after fires once.
     *
     * @param list<int|string>|null $agreementIds the agreements that a Customer accepted, or null when the flow of
     *                                            the caller has no agreements step
     * @param array<string, mixed> $orderData the fields that the order gets before its save, such as the relation
     *                                        of an order edit
     * @param bool|null $sendConfirmation null: Staff sends the email, and a Customer sends it when the payment
     *                                    method does not redirect to a third party
     * @throws Mage_Core_Exception_Conflict another placement of $quote is in progress, or $quote has an order
     * @throws Mage_Core_Exception_Input see validateQuote()
     * @throws Mage_Core_Exception
     */
    public function submit(
        \Mage_Sales_Model_Quote $quote,
        Actor $actor,
        ?array $agreementIds = null,
        array $orderData = [],
        ?bool $sendConfirmation = null,
        ?Mage_Sales_Model_Convert_Quote $convertor = null,
    ): Order {
        $submit = function () use ($quote, $actor, $agreementIds, $orderData, $sendConfirmation, $convertor): Order {
            $this->prepareCustomerData($quote);
            $this->validateQuote($quote, $actor, $agreementIds);

            return $this->withQuoteLock($quote, function () use ($quote, $actor, $orderData, $sendConfirmation, $convertor): Order {
                if (!$quote->getCustomerId() && !$quote->getCustomerIsNew()) {
                    $orderData['guest_access_token'] = $this->generateAccessToken();
                }
                $order = $this->convert($quote, $actor, $orderData, $convertor ?? Mage::getModel('sales/convert_quote'));
                $this->recordCashTendered($quote, $order);
                $quote->save();

                $redirects = (bool) $quote->getPayment()->getOrderPlaceRedirectUrl();
                if ($sendConfirmation ?? ($actor === Actor::Staff || (!$redirects && $order->getCanSendNewEmailFlag()))) {
                    $this->sendNewOrderEmail($order);
                }
                Mage::dispatchEvent('checkout_submit_all_after', ['order' => $order, 'quote' => $quote]);

                return $order;
            });
        };

        // The admin scope places a backend order, so that a payment method applies MOTO handling (issue #1337)
        return Mage::app()->getStore()->isAdmin() ? $submit() : $this->getCartService()->inQuoteStoreScope($quote, $submit);
    }

    /**
     * Give a customer order the email of the account and no guest flag, and give an order without a customer name
     * the name of the account or of the billing address.
     */
    private function prepareCustomerData(\Mage_Sales_Model_Quote $quote): void
    {
        if ($quote->getCustomerId()) {
            // A cart assigned to a customer before its guest flag was cleared still carries it
            $quote->setCustomerIsGuest(false);
            // The cart keeps the email the account had when the customer was set on it
            $accountEmail = $quote->getCustomer()->getEmail();
            if ($accountEmail) {
                $quote->setCustomerEmail($accountEmail);
            }
        }

        if (!$quote->getCustomerFirstname()) {
            $nameSource = $quote->getCustomer()->getFirstname() ? $quote->getCustomer() : $quote->getBillingAddress();
            foreach (Mage::getConfig()->getFieldset('customer_account') as $code => $node) {
                if ($node->is('name') && $node->to_quote) {
                    $targetCode = (string) $node->to_quote;
                    $quote->setDataUsingMethod($targetCode === '*' ? $code : $targetCode, $nameSource->getDataUsingMethod($code));
                }
            }
        }
    }

    /**
     * Run $operation while this request holds the placement lock of $quote, and refuse a cart that has an order. The
     * lock releases on disconnect, so a crashed worker does not block the cart.
     *
     * @param callable(): Order $operation
     * @throws Mage_Core_Exception_Conflict
     */
    private function withQuoteLock(\Mage_Sales_Model_Quote $quote, callable $operation): Order
    {
        if (!$quote->getId()) {
            return $operation();
        }

        $resource = Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $lockName = 'maho_quote_place_order:' . (int) $quote->getId();
        if (!$write->getLock($lockName, 5)) {
            throw new Mage_Core_Exception_Conflict(Mage::helper('sales')->__('Order placement is already in progress for this cart.'));
        }

        try {
            $placed = $write->fetchOne($write->select()
                ->from($resource->getTableName('sales/order'), ['entity_id'])
                ->where('quote_id = ?', (int) $quote->getId())
                ->limit(1));
            if ($placed) {
                throw new Mage_Core_Exception_Conflict(Mage::helper('sales')->__('An order was already placed from this cart.'));
            }

            return $operation();
        } finally {
            $write->releaseLock($lockName);
        }
    }

    /**
     * Check the gift cards and the cash of a POS order again, collect the totals again, and save the order of $quote.
     * When the save of a Customer order fails, the store gets the payment failed email.
     *
     * @param array<string, mixed> $orderData
     * @throws Mage_Core_Exception
     */
    private function convert(\Mage_Sales_Model_Quote $quote, Actor $actor, array $orderData, Mage_Sales_Model_Convert_Quote $convertor): Order
    {
        try {
            // A card spent elsewhere since it was applied must not discount this order
            $this->getCartService()->revalidateGiftcards($quote);
            // The conversion copies the currency of the quote, and the totals were just converted at the live rate
            $quote->collectTotals()->refreshCurrencyStamp();

            // A saved order has taken the stock, so the cash is checked before the save
            $cashTendered = $quote->getData(self::CASH_TENDERED_KEY);
            if ($cashTendered !== null
                && $quote->getPayment()->getMethod() === 'cashondelivery'
                && (float) $cashTendered < (float) $quote->getGrandTotal()
            ) {
                throw new Mage_Core_Exception(Mage::helper('sales')->__('Insufficient cash tendered'));
            }

            return $this->saveOrder($quote, $orderData, $convertor);
        } catch (Throwable $e) {
            if ($actor === Actor::Customer && !$e instanceof Mage_Payment_Model_Info_Exception) {
                $this->sendPaymentFailedEmail($quote, $e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Create the order of $quote and save it, with a new customer of $quote, in one transaction.
     *
     * @param array<string, mixed> $orderData
     */
    private function saveOrder(\Mage_Sales_Model_Quote $quote, array $orderData, Mage_Sales_Model_Convert_Quote $convertor): Order
    {
        $isVirtual = $quote->isVirtual();

        $transaction = Mage::getModel('core/resource_transaction');
        $newCustomer = null;
        if ($quote->getCustomerId() || $quote->getCustomerIsNew()) {
            $customer = $quote->getCustomer();
            $transaction->addObject($customer);
            // A customer that registers in the checkout gets its ID from the save below
            $newCustomer = $customer->getId() ? null : $customer;
        }
        $transaction->addObject($quote);

        $quote->reserveOrderId();
        // A transport can change an address after the last save of the cart
        foreach ($quote->getAllAddresses() as $address) {
            $address->sanitizeText();
        }
        $order = $convertor->addressToOrder($isVirtual ? $quote->getBillingAddress() : $quote->getShippingAddress());
        $order->setBillingAddress($convertor->addressToOrderAddress($quote->getBillingAddress()));
        if ($quote->getBillingAddress()->getCustomerAddress()) {
            $order->getBillingAddress()->setCustomerAddress($quote->getBillingAddress()->getCustomerAddress());
        }
        if (!$isVirtual) {
            $order->setShippingAddress($convertor->addressToOrderAddress($quote->getShippingAddress()));
            if ($quote->getShippingAddress()->getCustomerAddress()) {
                $order->getShippingAddress()->setCustomerAddress($quote->getShippingAddress()->getCustomerAddress());
            }
        }
        $order->setPayment($convertor->paymentToOrderPayment($quote->getPayment()));

        foreach ($orderData as $key => $value) {
            $order->setData($key, $value);
        }

        foreach ($quote->getAllItems() as $item) {
            $orderItem = $convertor->itemToOrderItem($item);
            if ($item->getParentItem()) {
                $orderItem->setParentItem($order->getItemByQuoteItemId($item->getParentItem()->getId()));
            }
            $order->addItem($orderItem);
        }

        $order->setQuote($quote);

        $transaction->addObject($order);
        $transaction->addCommitCallback($order->place(...));
        $transaction->addCommitCallback($order->save(...));

        Mage::unregister('current_order');
        Mage::register('current_order', $order);

        // An observer can give the new order its status
        Mage::dispatchEvent('checkout_type_onepage_save_order', ['order' => $order, 'quote' => $quote]);
        Mage::dispatchEvent('sales_model_service_quote_submit_before', ['order' => $order, 'quote' => $quote]);
        try {
            $transaction->save();
        } catch (Exception $e) {
            // The rollback removed the new customer, so it must not keep the ID of the failed save
            $newCustomer?->setId(null);

            // The order is not saved, so it and its items keep no ID
            $order->setId(null);
            /** @var Mage_Sales_Model_Order_Item $item */
            foreach ($order->getItemsCollection() as $item) {
                $item->setOrderId(null);
                $item->setItemId(null);
            }

            Mage::dispatchEvent('sales_model_service_quote_submit_failure', ['order' => $order, 'quote' => $quote]);
            throw $e;
        }
        $quote->setIsActive(false);
        Mage::dispatchEvent('sales_model_service_quote_submit_success', ['order' => $order, 'quote' => $quote]);
        Mage::dispatchEvent('sales_model_service_quote_submit_after', ['order' => $order, 'quote' => $quote]);

        return $order;
    }

    /**
     * Keep the cash that the customer of a POS order gave, and the change, on the payment of $order.
     */
    private function recordCashTendered(\Mage_Sales_Model_Quote $quote, Order $order): void
    {
        $cashTendered = $quote->getData(self::CASH_TENDERED_KEY);
        if ($cashTendered === null || $quote->getPayment()->getMethod() !== 'cashondelivery') {
            return;
        }
        $orderPayment = $order->getPayment();
        $orderPayment->setAdditionalInformation('cash_tendered', (float) $cashTendered);
        $orderPayment->setAdditionalInformation('change_amount', (float) $cashTendered - (float) $order->getGrandTotal());
        $orderPayment->save();
    }

    /**
     * Queue the new order email of $order. A failure is logged, because the order is saved.
     */
    protected function sendNewOrderEmail(Order $order): void
    {
        $this->sendSafely(fn() => $order->queueNewOrderEmail());
    }

    /**
     * Send the payment failed email of $quote to the store. A failure is logged.
     */
    protected function sendPaymentFailedEmail(\Mage_Sales_Model_Quote $quote, string $message): void
    {
        $this->sendSafely(fn() => Mage::helper('checkout')->sendPaymentFailedEmail($quote, $message));
    }

    private function getCartService(): Mage_Checkout_Service_Cart
    {
        return Mage::getService('checkout/cart');
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
     * Load the order $orderId.
     *
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function requireById(int $orderId): Order
    {
        return $this->requireFound($orderId > 0 ? $this->getById($orderId) : null);
    }

    /**
     * Load the order $incrementId.
     *
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function requireByIncrementId(string $incrementId): Order
    {
        return $this->requireFound($incrementId !== '' ? $this->getByIncrementId($incrementId) : null);
    }

    /**
     * Return true when the customer $customerId may see the order $order on the storefront: the order is theirs, and
     * its state is visible on the storefront.
     */
    public function isVisibleToCustomer(Order $order, ?int $customerId): bool
    {
        return $order->getId() && $this->isOwner($order, $customerId)
            && in_array($order->getState(), $this->getVisibleOnFrontStates(), true);
    }

    /**
     * Load the order $orderId when the customer $customerId may see it. Every read and every action of a customer
     * finds the order here, so a hidden, a foreign and a missing order give the same answer. See isVisibleToCustomer().
     *
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function requireVisibleToCustomer(int $orderId, ?int $customerId): Order
    {
        return $this->requireVisible($orderId > 0 ? $this->getById($orderId) : null, $customerId);
    }

    /**
     * Load the order $incrementId when the customer $customerId may see it. See requireVisibleToCustomer().
     *
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function requireVisibleToCustomerByIncrementId(string $incrementId, ?int $customerId): Order
    {
        return $this->requireVisible($incrementId !== '' ? $this->getByIncrementId($incrementId) : null, $customerId);
    }

    /**
     * Keep in $collection only the orders that the customer $customerId may see. See isVisibleToCustomer().
     */
    public function addVisibleToCustomerFilter(OrderCollection $collection, ?int $customerId): OrderCollection
    {
        return $collection
            ->addFieldToFilter('main_table.customer_id', $customerId > 0 ? $customerId : -1)
            ->addFieldToFilter('main_table.state', ['in' => $this->getVisibleOnFrontStates()]);
    }

    /**
     * @return list<string>
     */
    private function getVisibleOnFrontStates(): array
    {
        return Mage::getSingleton('sales/order_config')->getVisibleOnFrontStates();
    }

    /**
     * Load the invoice $invoiceId. With $orderId, only an invoice of that order exists.
     *
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function requireInvoice(int $invoiceId, ?int $orderId = null): Invoice
    {
        return $this->requireDocument(Mage::getModel('sales/order_invoice'), $invoiceId, $orderId, Mage::helper('sales')->__('Invoice not found'));
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function requireShipment(int $shipmentId): Shipment
    {
        return $this->requireDocument(Mage::getModel('sales/order_shipment'), $shipmentId, null, Mage::helper('sales')->__('Shipment not found'));
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function requireCreditmemo(int $creditmemoId): Creditmemo
    {
        return $this->requireDocument(Mage::getModel('sales/order_creditmemo'), $creditmemoId, null, Mage::helper('sales')->__('Credit memo not found'));
    }

    /**
     * Get the guest order $incrementId whose one-time access token is $accessToken. Count a failed lookup against the
     * limit of the client, which the guest order form shares.
     *
     * @throws Mage_Core_Exception_TooManyRequests when the client is over the limit
     */
    public function getForGuest(string $incrementId, string $accessToken): ?Order
    {
        $limiter = $this->guestLookupLimiter();
        $order = $incrementId !== '' ? $this->getByIncrementId($incrementId) : null;
        $storedToken = (string) $order?->getData('guest_access_token');
        if ($storedToken !== '' && hash_equals($storedToken, $accessToken)) {
            return $order;
        }
        $limiter->hit();
        return null;
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
     * Give $customer each guest order of the store $storeId that has the email of the customer, and return the number
     * of orders. The email is the only proof that the orders are of the customer, so a store that requires a
     * confirmed email refuses a customer who has not confirmed it.
     *
     * @throws Mage_Core_Exception_Forbidden when the customer must confirm the email first
     */
    public function assignGuestOrders(\Mage_Customer_Model_Customer $customer, int $storeId): int
    {
        if (!Mage::helper('customer')->isCustomerEligibleForGuestOrderAssociation($customer, $storeId)) {
            throw new Mage_Core_Exception_Forbidden(Mage::helper('sales')->__('Please confirm your email address before associating guest orders.'));
        }
        $orders = Mage::getResourceModel('sales/order_collection')
            ->addFieldToFilter('customer_id', ['null' => true])
            ->addFieldToFilter('customer_email', $customer->getEmail())
            ->addFieldToFilter('store_id', $storeId);
        foreach ($orders as $order) {
            $this->assignToCustomer($order, $customer);
        }
        return count($orders);
    }

    /**
     * Give the guest order $order to $customer, who has the email of the order and can use its store. The order gets
     * the customer and the group of the customer, and keeps the name that it was placed with.
     *
     * @throws Mage_Core_Exception_Conflict when the order has a customer
     * @throws Mage_Core_Exception when the customer does not have the email of the order or cannot use its store
     */
    public function assignToCustomer(Order $order, \Mage_Customer_Model_Customer $customer): Order
    {
        if ($order->getCustomerId()) {
            throw new Mage_Core_Exception_Conflict(Mage::helper('sales')->__('This order already belongs to a customer.'));
        }
        if (strtolower((string) $order->getCustomerEmail()) !== strtolower((string) $customer->getEmail())
            || !$customer->isInStore($order->getStoreId())
        ) {
            throw new Mage_Core_Exception(Mage::helper('sales')->__('This order cannot be added to this account.'));
        }
        $order->setCustomerId((int) $customer->getId())
            ->setCustomerIsGuest(false)
            ->setCustomerGroupId((int) $customer->getGroupId())
            ->save();

        return $order;
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
     * Return the limiter of the guest order lookups of the client, which counts only the failed lookups.
     *
     * @throws Mage_Core_Exception_TooManyRequests when the client is over the limit
     */
    private function guestLookupLimiter(): \Maho\Security\RateLimiter
    {
        $limiter = Mage::helper('core')->rateLimiter(
            'guest_order_lookup',
            (int) Mage::getStoreConfig(self::XML_PATH_GUEST_LOOKUP_RATE_LIMIT),
            self::GUEST_LOOKUP_WINDOW,
            RateLimitScope::Ip,
        );
        if ($limiter->tooManyAttempts()) {
            throw new Mage_Core_Exception_TooManyRequests(
                Mage::helper('sales')->__('Too many requests. Please try again later.'),
                self::GUEST_LOOKUP_WINDOW,
            );
        }
        return $limiter;
    }

    /**
     * @param callable(): ?Order $find
     */
    private function lookUpForGuest(callable $find): Order
    {
        $limiter = $this->guestLookupLimiter();
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

    private function requireFound(?Order $order): Order
    {
        return $order ?? throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('sales')->__('Order not found'));
    }

    private function requireVisible(?Order $order, ?int $customerId): Order
    {
        return $order && $this->isVisibleToCustomer($order, $customerId) ? $order : $this->requireFound(null);
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
            throw new Mage_Core_Exception_NoSuchEntity($notFound);
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
     * Get the orders that the customer $customerId may see, as getListForCustomer() does. See isVisibleToCustomer().
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
        $collection = $this->addVisibleToCustomerFilter(
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
                'cancel' => [$order->canCancel(), Mage::helper('sales')->__('Order cannot be cancelled'), 'Order cancelled: '],
                'hold' => [$order->canHold(), Mage::helper('sales')->__('Order cannot be held'), 'Order held: '],
                'unhold' => [$order->canUnhold(), Mage::helper('sales')->__('Order is not on hold'), 'Order released from hold: '],
            };
            if (!$allowed) {
                throw new Mage_Core_Exception_Conflict($refusal);
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

        $qtys = $this->parseLocaleNumbers($qtys);
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

        $invoice = $this->buildInvoice($order, $qtys);
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
                $shipment = $this->buildShipment($invoice->getOrder(), $qtys);
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
                'capture' => [$invoice->canCapture(), Mage::helper('sales')->__('The invoice cannot be captured in its current state')],
                'void' => [$invoice->canVoid(), Mage::helper('sales')->__('The invoice cannot be voided in its current state')],
                'cancel' => [$invoice->canCancel(), Mage::helper('sales')->__('The invoice cannot be canceled in its current state')],
            };
            if (!$allowed) {
                throw new Mage_Core_Exception_Conflict($refusal);
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

        $shipment = $this->buildShipment($order, $qtys);
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
        $trackingNumbers = [];
        foreach ($response->hasInfo() ? $response->getInfo() : [] as $info) {
            if (!empty($info['tracking_number']) && !empty($info['label_content'])) {
                $labels[sprintf('label_%s_package_%d.pdf', $shipment->getIncrementId(), count($labels) + 1)] = $info['label_content'];
                $trackingNumbers[] = (string) $info['tracking_number'];
            }
        }
        if ($labels === []) {
            return false;
        }

        try {
            $shipment->setShippingLabel(count($labels) === 1 ? reset($labels) : $this->zipShippingLabels($labels, 'shipping_label_'));
        } catch (Mage_Core_Exception $e) {
            // The shipment saves without its labels, as before: the admin can create them again
            Mage::logException($e);
            return false;
        }
        foreach ($trackingNumbers as $trackingNumber) {
            $shipment->addTrack(Mage::getModel('sales/order_shipment_track')
                ->setNumber($trackingNumber)
                ->setCarrierCode($carrier->getCarrierCode())
                ->setTitle((string) Mage::getStoreConfig('carriers/' . $carrier->getCarrierCode() . '/title', $shipment->getStoreId())));
        }
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
     * @param array<string, mixed> $data Credit memo data for buildCreditmemo(): qtys, shipping_amount, adjustment_positive, adjustment_negative
     * @param array<int, bool>|null $backToStockItemIds Order item id => true.
     * @throws Mage_Core_Exception_Conflict when the state of the order does not allow a refund
     * @throws Mage_Core_Exception when the shipping amount is negative
     */
    public function prepareCreditmemo(Order $order, array $data, ?array $backToStockItemIds = null, ?Invoice $invoice = null): Creditmemo
    {
        if (!$order->canCreditmemo()) {
            throw new Mage_Core_Exception_Conflict(Mage::helper('sales')->__('Order cannot be refunded (already fully refunded or not in a refundable state)'));
        }
        if (isset($data['shipping_amount']) && (float) $data['shipping_amount'] < 0) {
            throw new Mage_Core_Exception(Mage::helper('sales')->__('Shipping amount must be >= 0'));
        }

        $creditmemo = $this->buildCreditmemo($order, $data, $invoice);

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
     * Build an invoice of the order $order and add it to the invoices of the order, which nothing saves yet. Unlike
     * prepareInvoice(), do not check the state of the order or the qtys.
     *
     * @param array<int|string, mixed> $qtys Order item id => qty to invoice. An empty map invoices every item.
     */
    public function buildInvoice(Order $order, array $qtys = [], ?Mage_Sales_Model_Convert_Order $convertor = null): Invoice
    {
        $qtys = $this->parseLocaleNumbers($qtys);
        $convertor ??= $this->getConvertor();
        $invoice = $convertor->toInvoice($order);
        $totalQty = 0;
        foreach ($order->getAllItems() as $orderItem) {
            if (!$this->canInvoiceItem($orderItem)) {
                continue;
            }
            $item = $convertor->itemToInvoiceItem($orderItem);
            if ($orderItem->isDummy()) {
                $qty = $orderItem->getQtyOrdered() ?: 1;
            } elseif (isset($qtys[$orderItem->getId()])) {
                $qty = (float) $qtys[$orderItem->getId()];
            } elseif (!count($qtys)) {
                $qty = $orderItem->getQtyToInvoice();
            } else {
                $qty = 0;
            }

            $totalQty += $qty;
            $item->setQty($qty);
            $invoice->addItem($item);
        }

        $invoice->setTotalQty($totalQty);
        $invoice->collectTotals();
        $order->getInvoiceCollection()->addItem($invoice);

        return $invoice;
    }

    /**
     * Build a shipment of the order $order, which nothing saves yet. Unlike prepareShipment(), do not check the state
     * of the order.
     *
     * @param array<int|string, mixed> $qtys Order item id => qty to ship. An empty map ships every item.
     */
    public function buildShipment(Order $order, array $qtys = [], ?Mage_Sales_Model_Convert_Order $convertor = null): Shipment
    {
        $qtys = $this->parseLocaleNumbers($qtys);
        $convertor ??= $this->getConvertor();
        $totalQty = 0;
        $shipment = $convertor->toShipment($order);
        foreach ($order->getAllItems() as $orderItem) {
            if (!$this->canShipItem($orderItem, $qtys)) {
                continue;
            }

            $item = $convertor->itemToShipmentItem($orderItem);

            if ($orderItem->isDummy(true)) {
                $qty = 0;
                if (isset($qtys[$orderItem->getParentItemId()])) {
                    $productOptions = $orderItem->getProductOptions();
                    if (isset($productOptions['bundle_selection_attributes'])) {
                        $bundleSelectionAttributes = Mage::helper('core/string')->unserialize($productOptions['bundle_selection_attributes']);

                        if ($bundleSelectionAttributes) {
                            $qty = $bundleSelectionAttributes['qty'] * $qtys[$orderItem->getParentItemId()];
                            $qty = min($qty, $orderItem->getSimpleQtyToShip());

                            $item->setQty($qty);
                            $shipment->addItem($item);
                            continue;
                        }
                        $qty = 1;
                    }
                } else {
                    $qty = 1;
                }
            } elseif (isset($qtys[$orderItem->getId()])) {
                $qty = min($qtys[$orderItem->getId()], $orderItem->getQtyToShip());
            } elseif (empty($qtys)) {
                $qty = $orderItem->getQtyToShip();
            } else {
                continue;
            }
            $totalQty += $qty;
            $item->setQty($qty);
            $shipment->addItem($item);
        }

        $shipment->setTotalQty($totalQty);
        return $shipment;
    }

    /**
     * Build a credit memo of the order $order, or of its invoice $invoice, which nothing saves yet. Unlike
     * prepareCreditmemo(), do not check the state of the order or the shipping amount. Without a shipping amount in
     * $data, the credit memo of an invoice refunds the shipping of that invoice that is not refunded yet.
     *
     * @param array<string, mixed> $data qtys (order item id => qty), shipping_amount, adjustment_positive, adjustment_negative
     */
    public function buildCreditmemo(
        Order $order,
        array $data = [],
        ?Invoice $invoice = null,
        ?Mage_Sales_Model_Convert_Order $convertor = null,
    ): Creditmemo {
        $convertor ??= $this->getConvertor();
        $qtys = $this->parseLocaleNumbers($data['qtys'] ?? []);
        $creditmemo = $convertor->toCreditmemo($order);
        if ($invoice) {
            $creditmemo->setInvoice($invoice);
            $this->addInvoiceCreditmemoItems($creditmemo, $invoice, $qtys, $convertor);
        } else {
            $this->addOrderCreditmemoItems($creditmemo, $order, $qtys, $convertor);
        }

        $this->setCreditmemoAmounts($creditmemo, $data);
        if ($invoice && !isset($data['shipping_amount'])) {
            $invoiceOrder = $invoice->getOrder();
            if ((float) $invoice->getBaseShippingAmount() == 0) {
                $creditmemo->setBaseShippingAmount(0);
            } elseif (!Mage::getSingleton('tax/config')->displaySalesShippingInclTax($invoiceOrder->getStoreId())) {
                // Incl. tax, the shipping total offers the shipping left. It counts the shipping hidden tax refunded.
                $baseAllowedAmount = $invoiceOrder->getBaseShippingAmount() - $invoiceOrder->getBaseShippingRefunded();
                $creditmemo->setBaseShippingAmount(min($baseAllowedAmount, $invoice->getBaseShippingAmount()));
            }
        }

        $creditmemo->collectTotals();
        return $creditmemo;
    }

    /**
     * Set the shipping amount and the adjustments of $data on the credit memo $creditmemo.
     *
     * @param array<string, mixed> $data shipping_amount, adjustment_positive, adjustment_negative
     */
    public function setCreditmemoAmounts(Creditmemo $creditmemo, array $data): void
    {
        $data = $this->parseLocaleNumbers($data);
        if (isset($data['shipping_amount'])) {
            $creditmemo->setBaseShippingAmount((float) $data['shipping_amount']);
        }
        if (isset($data['adjustment_positive'])) {
            $creditmemo->setAdjustmentPositive($data['adjustment_positive']);
        }
        if (isset($data['adjustment_negative'])) {
            $creditmemo->setAdjustmentNegative($data['adjustment_negative']);
        }
    }

    /**
     * @param array<int|string, mixed> $qtys
     */
    private function addOrderCreditmemoItems(Creditmemo $creditmemo, Order $order, array $qtys, Mage_Sales_Model_Convert_Order $convertor): void
    {
        $totalQty = 0;
        foreach ($order->getAllItems() as $orderItem) {
            if (!$this->canRefundItem($orderItem, $qtys)) {
                continue;
            }

            $item = $convertor->itemToCreditmemoItem($orderItem);
            if ($orderItem->isDummy()) {
                $qty = 1;
                $orderItem->setLockedDoShip();
            } elseif (isset($qtys[$orderItem->getId()])) {
                $qty = (float) $qtys[$orderItem->getId()];
            } elseif (!count($qtys)) {
                $qty = $orderItem->getQtyToRefund();
            } else {
                continue;
            }
            $totalQty += $qty;
            $item->setQty($qty);
            $creditmemo->addItem($item);
        }
        $creditmemo->setTotalQty($totalQty);
    }

    /**
     * Refund at most the qty of each item of $invoice that the other credit memos of the invoice do not refund yet.
     *
     * @param array<int|string, mixed> $qtys
     */
    private function addInvoiceCreditmemoItems(Creditmemo $creditmemo, Invoice $invoice, array $qtys, Mage_Sales_Model_Convert_Order $convertor): void
    {
        $invoiceQtysRefunded = [];
        foreach ($invoice->getOrder()->getCreditmemosCollection() as $createdCreditmemo) {
            if ($createdCreditmemo->getState() != Creditmemo::STATE_CANCELED
                && $createdCreditmemo->getInvoiceId() == $invoice->getId()
            ) {
                foreach ($createdCreditmemo->getAllItems() as $createdCreditmemoItem) {
                    $orderItemId = $createdCreditmemoItem->getOrderItem()->getId();
                    $invoiceQtysRefunded[$orderItemId] = ($invoiceQtysRefunded[$orderItemId] ?? 0) + $createdCreditmemoItem->getQty();
                }
            }
        }

        $invoiceQtysRefundLimits = [];
        foreach ($invoice->getAllItems() as $invoiceItem) {
            $orderItemId = $invoiceItem->getOrderItem()->getId();
            $invoiceQtysRefundLimits[$orderItemId] = $invoiceItem->getQty() - ($invoiceQtysRefunded[$orderItemId] ?? 0);
        }

        $totalQty = 0;
        foreach ($invoice->getAllItems() as $invoiceItem) {
            $orderItem = $invoiceItem->getOrderItem();

            if (!$this->canRefundItem($orderItem, $qtys, $invoiceQtysRefundLimits)) {
                continue;
            }

            $item = $convertor->itemToCreditmemoItem($orderItem);
            if ($orderItem->isDummy()) {
                $qty = 1;
            } else {
                if (isset($qtys[$orderItem->getId()])) {
                    $qty = (float) $qtys[$orderItem->getId()];
                } elseif (!count($qtys)) {
                    $qty = $orderItem->getQtyToRefund();
                } else {
                    continue;
                }
                if (isset($invoiceQtysRefundLimits[$orderItem->getId()])) {
                    $qty = min($qty, $invoiceQtysRefundLimits[$orderItem->getId()]);
                }
            }
            $qty = min($qty, $invoiceItem->getQty());
            $totalQty += $qty;
            $item->setQty($qty);
            $creditmemo->addItem($item);
        }
        $creditmemo->setTotalQty($totalQty);
    }

    /**
     * Tell if the order item $item goes on an invoice. A dummy item goes on it with its children, or with its parent.
     *
     * @param array<int|string, mixed> $qtys Order item id => qty to invoice. An empty map invoices every item.
     */
    public function canInvoiceItem(OrderItem $item, array $qtys = []): bool
    {
        if ($item->getLockedDoInvoice()) {
            return false;
        }
        if (!$item->isDummy()) {
            return $item->getQtyToInvoice() > 0;
        }
        $qtys = $this->parseLocaleNumbers($qtys);
        if ($item->getHasChildren()) {
            foreach ($item->getChildrenItems() as $child) {
                if (empty($qtys) ? $child->getQtyToInvoice() > 0 : ($qtys[$child->getId()] ?? 0) > 0) {
                    return true;
                }
            }
            return false;
        }
        if ($parent = $item->getParentItem()) {
            return empty($qtys) ? $parent->getQtyToInvoice() > 0 : ($qtys[$parent->getId()] ?? 0) > 0;
        }
        return false;
    }

    /**
     * Tell if the order item $item goes on a shipment. A dummy item goes on it with its children, or with its parent.
     *
     * @param array<int|string, mixed> $qtys Order item id => qty to ship. An empty map ships every item.
     */
    public function canShipItem(OrderItem $item, array $qtys = []): bool
    {
        if ($item->getIsVirtual() || $item->getLockedDoShip()) {
            return false;
        }
        if (!$item->isDummy(true)) {
            return $item->getQtyToShip() > 0;
        }
        $qtys = $this->parseLocaleNumbers($qtys);
        if ($item->getHasChildren()) {
            if ($item->isShipSeparately()) {
                return true;
            }
            foreach ($item->getChildrenItems() as $child) {
                if ($child->getIsVirtual()) {
                    continue;
                }
                if (empty($qtys) ? $child->getQtyToShip() > 0 : ($qtys[$child->getId()] ?? 0) > 0) {
                    return true;
                }
            }
            return false;
        }
        if ($parent = $item->getParentItem()) {
            return empty($qtys) ? $parent->getQtyToShip() > 0 : ($qtys[$parent->getId()] ?? 0) > 0;
        }
        return false;
    }

    /**
     * Tell if the order item $item goes on a credit memo. A dummy item goes on it with its children, or with its
     * parent.
     *
     * @param array<int|string, mixed> $qtys Order item id => qty to refund. An empty map refunds every item.
     * @param array<int|string, float> $invoiceQtysRefundLimits Order item id => qty of the invoice that is not refunded yet.
     */
    public function canRefundItem(OrderItem $item, array $qtys = [], array $invoiceQtysRefundLimits = []): bool
    {
        if (!$item->isDummy()) {
            return $this->hasQtyToRefund($item, $invoiceQtysRefundLimits);
        }
        $qtys = $this->parseLocaleNumbers($qtys);
        if ($item->getHasChildren()) {
            foreach ($item->getChildrenItems() as $child) {
                if (empty($qtys) ? $this->hasQtyToRefund($child, $invoiceQtysRefundLimits) : ($qtys[$child->getId()] ?? 0) > 0) {
                    return true;
                }
            }
            return false;
        }
        if ($parent = $item->getParentItem()) {
            return empty($qtys) ? $this->hasQtyToRefund($parent, $invoiceQtysRefundLimits) : ($qtys[$parent->getId()] ?? 0) > 0;
        }
        return false;
    }

    /**
     * Tell if the order item $item has a qty to refund, without a look at its parent or its children.
     *
     * @param array<int|string, float> $invoiceQtysRefundLimits Order item id => qty of the invoice that is not refunded yet.
     */
    public function hasQtyToRefund(OrderItem $item, array $invoiceQtysRefundLimits = []): bool
    {
        if ($item->getQtyToRefund() < 0) {
            return false;
        }
        if (isset($invoiceQtysRefundLimits[$item->getId()])) {
            return $invoiceQtysRefundLimits[$item->getId()] > 0;
        }
        return true;
    }

    /**
     * Turn each string and number of $values into a float, with the decimal separator that the value uses. Keep the
     * other values.
     *
     * @param array<array-key, mixed> $values
     * @return array<array-key, mixed>
     */
    public function parseLocaleNumbers(array $values): array
    {
        $locale = Mage::app()->getLocale();
        foreach ($values as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $values[$key] = $locale->getNumber($value);
            }
        }
        return $values;
    }

    private function getConvertor(): Mage_Sales_Model_Convert_Order
    {
        return Mage::getModel('sales/convert_order');
    }

    /**
     * Create a credit memo for an order, or for its invoice $invoice. Email it when $sendEmail is true, and put the
     * comment in the email when $notifyCustomer is true.
     *
     * A memo that refunds nothing is not a refund. The credit memo model refuses a refund above the refundable balance
     * of the order.
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
        // The payment refunds online only against the invoice of the capture. Without an invoice, the gateway refunds
        // nothing, but the order counts the credit memo as refunded online.
        if (!$offlineRefund && !$creditmemo->getInvoice()) {
            throw new Mage_Core_Exception(Mage::helper('sales')->__('Cannot refund online: the credit memo has no invoice. Refund it offline.'));
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
