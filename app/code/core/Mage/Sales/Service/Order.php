<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

use Maho\Security\RateLimitScope;
use Mage_Checkout_Model_Actor as Actor;
use Mage_Sales_Model_Order as Order;
use Mage_Sales_Model_Order_Creditmemo as Creditmemo;
use Mage_Sales_Model_Order_Invoice as Invoice;
use Mage_Sales_Model_Order_Shipment as Shipment;
use Mage_Sales_Model_Resource_Order_Collection as OrderCollection;

/**
 * Places orders from carts, finds orders, cancels and holds them, and keeps their history, for the admin, the
 * storefront and the API. Mage_Sales_Service_Order_Document invoices, ships and refunds them.
 */
class Mage_Sales_Service_Order
{
    public const XML_PATH_GUEST_LOOKUP_RATE_LIMIT = 'system/rate_limit/guest_order_lookup';

    public const GUEST_LOOKUP_WINDOW = 3600;

    /**
     * Refuse the order of $quote when it is a guest order and its store does not allow guest checkout. A shopper who
     * registers in the checkout gets a customer before the order is placed, so that order is not a guest order.
     *
     * @throws Mage_Core_Exception_Forbidden
     */
    private function assertGuestCheckoutAllowed(\Mage_Sales_Model_Quote $quote): void
    {
        if ($quote->getCustomerId()
            || $quote->getCustomerIsNew()
            || $quote->getCheckoutMethod(true) === Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER
        ) {
            return;
        }
        if (!$quote->isAllowedGuestCheckout()) {
            throw new Mage_Core_Exception_Forbidden(
                Mage::helper('checkout')->__('Sorry, guest checkout is not enabled. Please try again or contact store owner.'),
            );
        }
    }

    /**
     * Check the customer fields of $quote, a cart with no customer: the email, and each field of the Checkout
     * Registration form. The one page checkout calls it at the billing step, and validateQuote() calls it for each
     * Customer order.
     *
     * @throws Mage_Core_Exception_Input one error for each field, which has the name of the cart field
     */
    public function validateCustomerData(\Mage_Sales_Model_Quote $quote): void
    {
        $errors = new Mage_Core_Exception_Input();
        $this->addCustomerDataErrors($quote, $errors);
        $errors->throwIfErrors();
    }

    private function addCustomerDataErrors(\Mage_Sales_Model_Quote $quote, Mage_Core_Exception_Input $errors): void
    {
        $email = trim((string) $quote->getCustomerEmail());
        if ($email === '' || !Mage::helper('core')->isValidEmail($email)) {
            $errors->addError('customer_email', Mage::helper('sales')->__('Please enter a valid email address.'));
        }

        $customer = Mage::getModel('customer/customer')->setStoreId((int) $quote->getStoreId());
        $form = Mage::getModel('customer/form')->setFormCode('checkout_register')->setEntity($customer);
        $columns = [];
        foreach (Mage::getConfig()->getFieldset('customer_account') ?? [] as $code => $node) {
            if (!empty($node->to_quote) && (string) $node->to_quote !== '*') {
                $columns[$code] = (string) $node->to_quote;
            }
        }
        $fields = [];
        $values = [];
        $attributes = [];
        foreach ($form->getAttributes() as $code => $attribute) {
            $input = (string) $attribute->getFrontendInput();
            if ($code === 'email' || !$attribute->getIsVisible() || in_array($input, ['file', 'image'], true)) {
                continue;
            }
            $fields[$code] = $columns[$code] ?? Mage_Sales_Model_Observer_CustomerAttributes::COLUMN_PREFIX . $code;
            $attributes[$code] = $attribute;
            $value = $quote->getData($fields[$code]);
            if (is_string($value) && $value !== '') {
                $value = match ($input) {
                    'date' => substr($value, 0, 10),
                    'multiselect' => explode(',', $value),
                    'multiline' => explode("\n", $value),
                    default => $value,
                };
            }
            $values[$code] = $value;
        }
        foreach ($fields as $code => $field) {
            $result = Mage_Eav_Model_Attribute_Data::factory($attributes[$code], $customer)
                ->setExtractedData($values)
                ->validateValue($values[$code]);
            foreach (is_array($result) ? $result : [] as $message) {
                $errors->addError($field, (string) $message);
            }
        }
    }

    /**
     * Place the order of $quote through submit(), for the API. $guestEmail is the email of a guest order, $orderNote
     * the note of the customer. Before submit(), sales_api_place_order_before lets a module prepare the cart, such as
     * a module that sets a default shipping method.
     *
     * @param list<int|string>|null $agreementIds see submit()
     * @return array{order: Order, accessToken: ?string}
     * @throws Mage_Core_Exception_Input when $guestEmail is not a valid email address
     * @throws Mage_Core_Exception
     */
    public function place(
        \Mage_Sales_Model_Quote $quote,
        ?string $guestEmail = null,
        ?string $orderNote = null,
        Actor $actor = Actor::Customer,
        ?array $agreementIds = null,
        ?bool $sendConfirmation = null,
    ): array {
        if ($guestEmail && !$quote->getCustomerId()) {
            if (!Mage::helper('core')->isValidEmail($guestEmail)) {
                throw new Mage_Core_Exception_Input()->addError('guest_email', Mage::helper('core')->__('Invalid email address.'));
            }
            $quote->setCustomerEmail($guestEmail);
        }
        if ($orderNote) {
            $quote->setCustomerNote($orderNote)->setCustomerNoteNotify(false);
        }

        Mage::dispatchEvent('sales_api_place_order_before', [
            'quote' => $quote,
            'payment_method' => $quote->getPayment()->getMethod() ?: null,
            'shipping_method' => $quote->isVirtual() ? null : ($quote->getShippingAddress()->getShippingMethod() ?: null),
        ]);
        $order = $this->submit($quote, $actor, $agreementIds, sendConfirmation: $sendConfirmation);

        return [
            'order' => $order,
            'accessToken' => $order->getData('guest_access_token') ?: null,
        ];
    }

    /**
     * Check that $quote can become an order. A Customer must reach the minimum order amount, and must accept each
     * required agreement in $agreementIds. A Customer order of a cart with no customer needs a store that allows
     * guest checkout, a valid email and the fields of the Checkout Registration form. Staff places a guest order in
     * any store.
     *
     * @param list<int|string>|null $agreementIds null when the flow of the caller has no agreements step
     * @throws Mage_Core_Exception when the cart is not active or is empty
     * @throws Mage_Core_Exception_Forbidden when the store does not allow the guest order of a Customer
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
        if ($actor === Actor::Customer) {
            $this->assertGuestCheckoutAllowed($quote);
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
            if (!$quote->getCustomerId() && !$quote->getCustomerIsNew()) {
                // A caller that checks before submit(), such as PayPal, has the email and the name on the billing address only
                $this->prepareCustomerData($quote);
                $this->addCustomerDataErrors($quote, $errors);
            }
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
                $cart->validateAddress($address, $actor);
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

        if (!$quote->getPayment()->getMethod()) {
            $errors->addError('payment_method', $helper->__('Please select a valid payment method.'));
        } elseif (!$this->isPaymentMethodAvailable($quote)) {
            $errors->addError('payment_method', $helper->__('The requested Payment Method is not available.'));
        }

        $errors->throwIfErrors();
    }

    /**
     * Tell if the payment method of $quote is still available for it. The cart can change after the selection of
     * the method, so the selection is not enough. A cart with a total of zero collects no payment, and a module
     * such as the gift card then leaves only the method "free" available, so its method is not checked.
     */
    private function isPaymentMethodAvailable(\Mage_Sales_Model_Quote $quote): bool
    {
        if ((float) $quote->getBaseGrandTotal() <= 0.0) {
            return true;
        }
        $method = $quote->getPayment()->getMethodInstance();
        $method->setStore($quote->getStoreId());

        return $method->isAvailable($quote);
    }

    /**
     * Tell if the items of $order can go to a cart again: the store of the order allows reorders, the order is not on
     * hold or in payment review, and each of its products exists. A Customer also needs one catalog line, because the
     * storefront cart takes no custom line.
     */
    public function canReorder(Order $order, Actor $actor): bool
    {
        if (!Mage::helper('sales/reorder')->isAllowed($order->getStore())) {
            return false;
        }

        return $actor === Actor::Staff ? $order->canReorderIgnoreSalable() : $order->canReorder();
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
     * Place the order of $quote in the store scope of the caller. A caller whose scope is not the store of $quote
     * and is not the admin scope calls this method through Mage_Checkout_Service_Cart::inQuoteStoreScope(). The
     * admin scope places a backend order, so that a payment method applies MOTO handling (issue #1337). The checks
     * of validateQuote() run first. Then, under a lock on the cart, the gift cards and the totals are checked again,
     * the addresses go to the address book as addToAddressBook() tells, the order is saved, and the cart is closed.
     * A guest order gets an access token. Then $afterSave runs with the order, the new order email follows
     * $sendConfirmation, and checkout_submit_all_after fires once.
     *
     * @param list<int|string>|null $agreementIds the agreements that a Customer accepted, or null when the flow of
     *                                            the caller has no agreements step
     * @param array<string, mixed> $orderData the fields that the order gets before its save, such as the relation
     *                                        of an order edit
     * @param bool|null $sendConfirmation null: Staff sends the email, and a Customer sends it when the payment
     *                                    method does not redirect to a third party and the order allows the email
     * @param (callable(Order): void)|null $afterSave the step of the caller between the save and the email, such as
     *                                                 an event of its own, whose observer can stop the email
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
        ?callable $afterSave = null,
    ): Order {
        $this->prepareCustomerData($quote);
        $this->validateQuote($quote, $actor, $agreementIds);

        return $this->withQuoteLock($quote, function () use ($quote, $actor, $orderData, $sendConfirmation, $convertor, $afterSave): Order {
            $order = $this->saveOrderOf($quote, $actor, $orderData, $convertor);
            if ($afterSave) {
                $afterSave($order);
            }

            $redirects = (bool) $quote->getPayment()->getOrderPlaceRedirectUrl();
            if ($sendConfirmation ?? ($actor === Actor::Staff || (!$redirects && $order->getCanSendNewEmailFlag()))) {
                $this->sendNewOrderEmail($order);
            }
            Mage::dispatchEvent('checkout_submit_all_after', ['order' => $order, 'quote' => $quote]);

            return $order;
        });
    }

    /**
     * Place the order of $quote as submit() does, but send no email and do not dispatch checkout_submit_all_after:
     * the caller does both. Only Mage_Sales_Model_Service_Quote calls this method, because the callers of its
     * submitOrder() dispatch the event themselves.
     *
     * @param array<string, mixed> $orderData
     * @throws Mage_Core_Exception_Conflict|Mage_Core_Exception_Input|Mage_Core_Exception as submit() does
     */
    public function submitWithoutNotice(
        \Mage_Sales_Model_Quote $quote,
        Actor $actor,
        array $orderData = [],
        ?Mage_Sales_Model_Convert_Quote $convertor = null,
    ): Order {
        $this->prepareCustomerData($quote);
        $this->validateQuote($quote, $actor);

        return $this->withQuoteLock($quote, fn(): Order => $this->saveOrderOf($quote, $actor, $orderData, $convertor));
    }

    /**
     * Save the order of $quote and close the cart, for a caller that holds the placement lock of $quote.
     *
     * @param array<string, mixed> $orderData
     */
    private function saveOrderOf(
        \Mage_Sales_Model_Quote $quote,
        Actor $actor,
        array $orderData,
        ?Mage_Sales_Model_Convert_Quote $convertor,
    ): Order {
        if (!$quote->getCustomerId() && !$quote->getCustomerIsNew()) {
            $orderData['guest_access_token'] = $this->generateAccessToken();
        } else {
            $this->addToAddressBook($quote);
        }
        $order = $this->convert($quote, $actor, $orderData, $convertor ?? Mage::getModel('sales/convert_quote'));
        $quote->save();

        return $order;
    }

    /**
     * Give a customer order the email of the account and no guest flag, give a guest order without an email the
     * email of the billing address, and give an order without a customer name the name of the account or of the
     * billing address.
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
        } elseif (!$quote->getCustomerEmail() && $quote->getBillingAddress()->getEmail()) {
            $quote->setCustomerEmail($quote->getBillingAddress()->getEmail());
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
     * Add each address of $quote that has save_in_address_book to the address book of the customer of $quote, which
     * the order transaction saves. An address that the customer picked from the address book updates that saved
     * address instead. A shipping address that is the same as the billing address is not added again. When the
     * customer has no default billing address, the billing address becomes it. When the customer has no default
     * shipping address, the shipping address becomes it, or the billing address when it is also the shipping address.
     */
    private function addToAddressBook(\Mage_Sales_Model_Quote $quote): void
    {
        $customer = $quote->getCustomer();
        $billing = $quote->getBillingAddress();
        $shipping = $quote->isVirtual() ? null : $quote->getShippingAddress();
        $savedBilling = $this->saveInAddressBook($customer, $billing);
        $savedShipping = $shipping && !$shipping->getSameAsBilling() ? $this->saveInAddressBook($customer, $shipping) : null;

        if ($savedBilling && !$customer->getDefaultBilling()) {
            $savedBilling->setIsDefaultBilling(true);
        }
        if (!$customer->getDefaultShipping()) {
            if ($savedShipping) {
                $savedShipping->setIsDefaultShipping(true);
            } elseif ($savedBilling && (!$shipping || $shipping->getSameAsBilling())) {
                $savedBilling->setIsDefaultShipping(true);
            }
        }
    }

    /**
     * Return the address of the address book of $customer that $address adds or updates, or null when $address does
     * not go to the address book, or a caller already linked it to an address of the address book.
     */
    private function saveInAddressBook(\Mage_Customer_Model_Customer $customer, \Mage_Sales_Model_Quote_Address $address): ?\Mage_Customer_Model_Address
    {
        if (!$address->getSaveInAddressBook() || $address->getCustomerAddress()) {
            return null;
        }

        $exported = $address->exportCustomerAddress();
        $saved = $customer->getId() && $address->getCustomerAddressId()
            ? $customer->getAddressItemById($address->getCustomerAddressId())
            : null;
        if ($saved) {
            $saved->addData($exported->getData());
        } else {
            $customer->addAddress($exported);
            $saved = $exported;
        }
        $address->setCustomerAddress($saved);
        // The customer saves its addresses only when the save of the customer runs, and it skips a customer without changes
        $customer->setDataChanges(true);

        return $saved;
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
     * Dispatch sales_quote_submit_validate, collect the totals again, and save the order of $quote.
     * When the save of a Customer order fails, the store gets the payment failed email.
     *
     * @param array<string, mixed> $orderData
     * @throws Mage_Core_Exception
     */
    private function convert(\Mage_Sales_Model_Quote $quote, Actor $actor, array $orderData, Mage_Sales_Model_Convert_Quote $convertor): Order
    {
        try {
            // An observer checks what can change after the cart was built, such as the balance of a gift card,
            // and throws a Mage_Core_Exception to refuse the order
            Mage::dispatchEvent('sales_quote_submit_validate', ['quote' => $quote]);
            // The conversion copies the currency of the quote, and the totals were just converted at the live rate
            $quote->collectTotals()->refreshCurrencyStamp();

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
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getById(int $orderId): Order
    {
        return $this->requireFound($this->findById($orderId));
    }

    /**
     * Return the order $orderId, or null when there is none, for a caller that has its own answer for that case.
     */
    public function findById(int $orderId): ?Order
    {
        $order = Mage::getModel('sales/order')->load($orderId);

        return $order->getId() ? $order : null;
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getByIncrementId(string $incrementId): Order
    {
        return $this->requireFound($this->findByIncrementId($incrementId));
    }

    /**
     * Return the order $incrementId, or null when there is none, for a caller that has its own answer for that case.
     */
    public function findByIncrementId(string $incrementId): ?Order
    {
        if ($incrementId === '') {
            return null;
        }
        $order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);

        return $order->getId() ? $order : null;
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
        return $this->requireVisible($this->findById($orderId), $customerId);
    }

    /**
     * Load the order $incrementId when the customer $customerId may see it. See requireVisibleToCustomer().
     *
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function requireVisibleToCustomerByIncrementId(string $incrementId, ?int $customerId): Order
    {
        return $this->requireVisible($this->findByIncrementId($incrementId), $customerId);
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
     * Get the guest order $incrementId whose one-time access token is $accessToken. Count a failed lookup against the
     * limit of the client, which the guest order form shares.
     *
     * @throws Mage_Core_Exception_TooManyRequests when the client is over the limit
     */
    public function getForGuest(string $incrementId, string $accessToken): ?Order
    {
        $limiter = $this->guestLookupLimiter();
        $order = $this->findByIncrementId($incrementId);
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
                ? $this->findByIncrementId($incrementId)
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
            $order = $incrementId !== '' && $protectCode !== '' ? $this->findByIncrementId($incrementId) : null;
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
        $limiter->throwIfTooManyAttempts();
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
     * true. A comment without text must name a status, which can be the current status of the order.
     *
     * @param string|null $status New order status; the order refuses one not assigned to its current state
     * @throws Mage_Core_Exception_Conflict when the order does not take comments
     * @throws Mage_Core_Exception when the comment is empty and names no status, or the status is not valid
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
        if (trim($note) === '' && $status === null) {
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
     * Send the email of the order $document or of the shipment $document again, and mark the last history comment of
     * that kind that the customer did not get as notified. A failed email is not caught, so that the caller can tell
     * the user.
     *
     * @throws Mage_Core_Exception
     */
    public function resendEmail(Order|Shipment $document): void
    {
        if ($document instanceof Order) {
            // The order sends its new order email one time only, unless the flag is cleared
            $document->setEmailSent(false);
            $document->sendNewOrderEmail();
            $entityName = Order::HISTORY_ENTITY_NAME;
        } else {
            $document->sendEmail(true)->setEmailSent()->save();
            $entityName = Shipment::HISTORY_ENTITY_NAME;
        }
        Mage::getResourceModel('sales/order_status_history_collection')
            ->getUnnotifiedForInstance($document, $entityName)
            ?->setIsCustomerNotified(1)
            ->save();
    }

    /**
     * Save the address $address of an order after a change of its fields. The labels of its option attributes are
     * set again, and the street lines are joined.
     *
     * @throws Mage_Core_Exception_Input one error for each rule of the country that the address breaks
     */
    public function saveAddress(Mage_Sales_Model_Order_Address $address): Mage_Sales_Model_Order_Address
    {
        Mage::getModel('sales/observer_addressAttributes')->refreshOptionLabels($address);
        $address->implodeStreetAddress();
        Mage_Core_Exception_Input::assertValid($address->getFormatErrors() ?: true, 'address');
        $address->save();

        return $address;
    }

    /**
     * Change the email of the guest order $order to $email, and add a comment to the history of the order that
     * names $changedBy. Return false when the order has that email already.
     *
     * @throws Mage_Core_Exception_Conflict when the order belongs to a customer
     * @throws Mage_Core_Exception_Input when $email is not a valid email address
     */
    public function changeGuestEmail(Order $order, #[\SensitiveParameter]
        string $email, string $changedBy): bool
    {
        if (!$order->getCustomerIsGuest()) {
            throw new Mage_Core_Exception_Conflict(Mage::helper('sales')->__('Email can only be changed for guest orders.'));
        }
        $email = trim($email);
        $oldEmail = (string) $order->getCustomerEmail();
        if ($email === $oldEmail) {
            return false;
        }
        if ($email === '' || !Mage::helper('core')->isValidEmail($email)) {
            throw new Mage_Core_Exception_Input()->addError(
                'customer_email',
                Mage::helper('sales')->__('Please enter a valid email address. For example johndoe@domain.com.'),
            );
        }

        $order->setCustomerEmail($email);
        $order->addStatusHistoryComment(
            Mage::helper('sales')->__('Customer email address changed from %s to %s by %s.', $oldEmail, $email, $changedBy),
        );
        $order->save();

        return true;
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
     * @param array<int, float|string> $qtys
     * @param list<\Mage_Sales_Model_Order_Shipment_Track> $tracks
     */
    #[\Deprecated('Use Mage_Sales_Service_Order_Document::invoice().', since: '27.1')]
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
        return $this->getDocumentService()->invoice($order, $qtys, $captureCase, $comment, $notifyCustomer, $visibleOnFront, $sendEmail, $ship, $tracks);
    }

    /**
     * @param array<int, float|string> $qtys
     * @param list<\Mage_Sales_Model_Order_Shipment_Track> $tracks
     * @param array<int, array<string, mixed>>|null $packages
     */
    #[\Deprecated('Use Mage_Sales_Service_Order_Document::ship().', since: '27.1')]
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
        return $this->getDocumentService()->ship($order, $qtys, $tracks, $comment, $notifyCustomer, $visibleOnFront, $sendEmail, $packages);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, bool>|null $backToStockItemIds
     * @param (callable(Creditmemo): void)|null $beforeRegister
     */
    #[\Deprecated('Use Mage_Sales_Service_Order_Document::refund().', since: '27.1')]
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
        ?callable $beforeRegister = null,
    ): Creditmemo {
        return $this->getDocumentService()->refund($order, $data, $comment, $offlineRefund, $backToStockItemIds, $notifyCustomer, $visibleOnFront, $sendEmail, $invoice, $beforeRegister);
    }

    /**
     * The legacy API still calls this method.
     *
     * @param array<string, mixed> $data
     */
    #[\Deprecated('Use Mage_Sales_Service_Order_Document::buildCreditmemo().', since: '27.1')]
    public function buildCreditmemo(
        Order $order,
        array $data = [],
        ?Invoice $invoice = null,
        ?Mage_Sales_Model_Convert_Order $convertor = null,
    ): Creditmemo {
        return $this->getDocumentService()->buildCreditmemo($order, $data, $invoice, $convertor);
    }

    #[\Deprecated('Use Mage_Sales_Service_Order_Document::captureInvoice().', since: '27.1')]
    public function captureInvoice(Invoice $invoice): Invoice
    {
        return $this->getDocumentService()->captureInvoice($invoice);
    }

    #[\Deprecated('Use Mage_Sales_Service_Order_Document::voidInvoice().', since: '27.1')]
    public function voidInvoice(Invoice $invoice): Invoice
    {
        return $this->getDocumentService()->voidInvoice($invoice);
    }

    #[\Deprecated('Use Mage_Sales_Service_Order_Document::cancelInvoice().', since: '27.1')]
    public function cancelInvoice(Invoice $invoice): Invoice
    {
        return $this->getDocumentService()->cancelInvoice($invoice);
    }

    /**
     * @return list<Shipment>
     */
    #[\Deprecated('Use Mage_Sales_Service_Order_Document::getShipments().', since: '27.1')]
    public function getShipments(Order $order, bool $visibleCommentsOnly = false): array
    {
        return $this->getDocumentService()->getShipments($order, $visibleCommentsOnly);
    }

    private function getDocumentService(): Mage_Sales_Service_Order_Document
    {
        return Mage::getService('sales/order_document');
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
    public function withOrderLock(int $orderId, callable $operation): mixed
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
