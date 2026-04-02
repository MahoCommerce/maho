<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Sales\Api;

use ApiPlatform\Metadata\Operation;
use Mage\Customer\Api\AddressMapper;
use Mage\Checkout\Api\CartService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Order State Processor - Handles order mutations for API Platform
 */
final class OrderProcessor extends \Maho\ApiPlatform\Processor
{
    private AddressMapper $addressMapper;
    private CartService $cartService;
    private OrderService $orderService;
    private PaymentService $paymentService;
    private readonly PosPaymentMapper $posPaymentMapper;

    public function __construct(Security $security)
    {
        parent::__construct($security);
        $this->addressMapper = new AddressMapper();
        $this->cartService = new CartService();
        $this->orderService = new OrderService();
        $this->paymentService = new PaymentService();
        $this->posPaymentMapper = new PosPaymentMapper();
    }

    /**
     * Process order mutations
     */
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Order|PlaceOrderWithSplitPaymentsResult|PosPayment
    {
        $operationName = $operation->getName();

        // Pass incrementId from URI for verify_order
        if (isset($uriVariables['incrementId'])) {
            $context['args']['input']['incrementId'] = (string) $uriVariables['incrementId'];
        }

        return match ($operationName) {
            'placeOrder', '_api_/orders_post', 'place_guest_order' => $this->placeOrder($context),
            'cancelOrder' => $this->cancelOrder($context),
            'placeOrderWithSplitPayments' => $this->placeOrderWithSplitPayments($context),
            'recordPayment' => $this->recordPayment($context),
            'verify_order' => $this->verifyOrder($context),
            default => $data instanceof Order ? $data : new Order(),
        };
    }

    /**
     * Place order from cart
     */
    private function placeOrder(array $context): Order
    {
        $args = $context['args']['input'] ?? $context['request_data'] ?? [];
        $cartId = $args['cartId'] ?? null;
        $maskedId = $args['maskedId'] ?? null;
        $guestEmail = $args['guestEmail'] ?? null;
        $orderNote = $args['orderNote'] ?? null;
        $cashTendered = isset($args['cashTendered']) ? (float) $args['cashTendered'] : null;
        $employeeId = isset($args['employeeId']) ? (int) $args['employeeId'] : null;
        $paymentMethod = $args['paymentMethod'] ?? null;
        $shippingMethod = $args['shippingMethod'] ?? null;

        // Get cart/quote
        $quote = $this->cartService->getCart(
            $cartId ? (int) $cartId : null,
            $maskedId,
        );

        if (!$quote) {
            throw new NotFoundHttpException('Cart not found');
        }

        // Verify cart ownership
        $this->verifyCartOwnership($quote, $maskedId !== null);

        // Set payment method on quote if provided
        if ($paymentMethod) {
            $quote->getPayment()->setMethod($paymentMethod);
        }

        // Set shipping method on quote if provided
        if ($shippingMethod && !$quote->isVirtual()) {
            $shippingAddress = $quote->getShippingAddress();
            $shippingAddress->setShippingMethod($shippingMethod);
            $shippingAddress->setCollectShippingRates(1);
        }

        if ($paymentMethod || $shippingMethod) {
            $quote->collectTotals()->save();
        }

        // Place order
        $result = $this->orderService->placeAdminOrder(
            $quote,
            $guestEmail,
            $orderNote,
            $cashTendered,
            $employeeId,
        );

        $order = $result['order'];
        $accessToken = $result['accessToken'];
        $changeAmount = $result['changeAmount'];

        return $this->mapOrderToDto($order, $accessToken, $changeAmount);
    }

    /**
     * Cancel order
     */
    private function cancelOrder(array $context): Order
    {
        $args = $context['args']['input'] ?? [];
        $orderId = $args['orderId'] ?? null;
        $incrementId = $args['incrementId'] ?? null;
        $reason = $args['reason'] ?? null;

        // Get order
        $order = $this->orderService->getOrder(
            $orderId ? (int) $orderId : null,
            $incrementId,
        );

        if (!$order) {
            throw new NotFoundHttpException('Order not found');
        }

        // Verify access: customers can only cancel their own orders
        if (!$this->isAdmin() && !$this->isApiUser()) {
            $customerId = $this->getAuthenticatedCustomerId();
            if (!$customerId) {
                throw new BadRequestHttpException('Authentication required to cancel orders');
            }
            if ((int) $order->getCustomerId() !== $customerId) {
                throw new NotFoundHttpException('Order not found');
            }
        }

        // Cancel order
        $order = $this->orderService->cancelOrder($order, $reason);

        return $this->mapOrderToDto($order);
    }

    /**
     * Map Maho order model to Order DTO
     */
    private function mapOrderToDto(
        \Mage_Sales_Model_Order $order,
        ?string $accessToken = null,
        ?float $changeAmount = null,
    ): Order {
        $dto = new Order();
        $dto->id = (int) $order->getId();
        $dto->incrementId = $order->getIncrementId();
        $dto->customerId = $order->getCustomerId() ? (int) $order->getCustomerId() : null;
        $dto->customerEmail = $order->getCustomerEmail();
        $dto->customerFirstname = $order->getCustomerFirstname();
        $dto->customerLastname = $order->getCustomerLastname();
        $dto->status = $order->getStatus();
        $dto->state = $order->getState();
        $dto->storeId = (int) $order->getStoreId();
        $dto->currency = $order->getOrderCurrencyCode() ?: \Mage::app()->getStore()->getDefaultCurrencyCode();
        $dto->totalItemCount = (int) $order->getTotalItemCount();
        $dto->totalQtyOrdered = (float) $order->getTotalQtyOrdered();
        $dto->createdAt = $order->getCreatedAt();
        $dto->updatedAt = $order->getUpdatedAt();
        $dto->couponCode = $order->getCouponCode();

        // Set access token for guest orders
        if ($accessToken) {
            $dto->accessToken = $accessToken;
        }

        // Set change amount for cash payments
        if ($changeAmount !== null) {
            $dto->changeAmount = $changeAmount;
        }

        // Map items
        $dto->items = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $dto->items[] = $this->mapItemToDto($item);
        }

        // Map prices
        $dto->prices = $this->mapPricesToArray($order);

        // Map billing address
        $billingAddress = $order->getBillingAddress();
        if ($billingAddress && $billingAddress->getId()) {
            $dto->billingAddress = $this->addressMapper->fromOrderAddress($billingAddress);
        }

        // Map shipping address
        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress && $shippingAddress->getId()) {
            $dto->shippingAddress = $this->addressMapper->fromOrderAddress($shippingAddress);
        }

        // Map shipping method
        $dto->shippingMethod = $order->getShippingMethod();
        $dto->shippingDescription = $order->getShippingDescription();

        // Map payment method
        $payment = $order->getPayment();
        if ($payment) {
            $dto->paymentMethod = $payment->getMethod();
            try {
                $dto->paymentMethodTitle = $payment->getMethodInstance()->getTitle();
            } catch (\Exception $e) {
                $dto->paymentMethodTitle = $payment->getMethod();
            }
        }

        // Map status history
        $dto->statusHistory = $this->orderService->getOrderNotes($order);

        return $dto;
    }

    /**
     * Map Maho order item model to OrderItem DTO
     */
    private function mapItemToDto(\Mage_Sales_Model_Order_Item $item): OrderItem
    {
        $dto = new OrderItem();
        $dto->id = (int) $item->getId();
        $dto->sku = $item->getSku();
        $dto->name = $item->getName() ?? '';
        $dto->qty = (float) $item->getQtyOrdered();
        $dto->qtyOrdered = (float) $item->getQtyOrdered();
        $dto->qtyShipped = (float) $item->getQtyShipped();
        $dto->qtyRefunded = (float) $item->getQtyRefunded();
        $dto->qtyCanceled = (float) $item->getQtyCanceled();
        $dto->price = (float) $item->getPrice();
        $dto->priceInclTax = (float) $item->getPriceInclTax();
        $dto->rowTotal = (float) $item->getRowTotal();
        $dto->rowTotalInclTax = (float) $item->getRowTotalInclTax();
        $dto->discountAmount = $item->getDiscountAmount() ? (float) $item->getDiscountAmount() : null;
        $dto->discountPercent = $item->getDiscountPercent() ? (float) $item->getDiscountPercent() : null;
        $dto->taxAmount = $item->getTaxAmount() ? (float) $item->getTaxAmount() : null;
        $dto->taxPercent = $item->getTaxPercent() ? (float) $item->getTaxPercent() : null;
        $dto->productId = $item->getProductId() ? (int) $item->getProductId() : null;
        $dto->productType = $item->getProductType();
        $dto->parentItemId = $item->getParentItemId() ? (int) $item->getParentItemId() : null;

        return $dto;
    }

    /**
     * Map Maho order to prices array
     */
    private function mapPricesToArray(\Mage_Sales_Model_Order $order): array
    {
        $prices = [
            'subtotal' => (float) $order->getSubtotal(),
            'subtotalInclTax' => (float) $order->getSubtotalInclTax(),
            'discountAmount' => $order->getDiscountAmount()
                ? (float) abs($order->getDiscountAmount())
                : null,
            'shippingAmount' => $order->getShippingAmount()
                ? (float) $order->getShippingAmount()
                : null,
            'shippingAmountInclTax' => $order->getShippingInclTax()
                ? (float) $order->getShippingInclTax()
                : null,
            'taxAmount' => (float) $order->getTaxAmount(),
            'grandTotal' => (float) $order->getGrandTotal(),
            'totalPaid' => (float) $order->getTotalPaid(),
            'totalRefunded' => (float) $order->getTotalRefunded(),
            'totalDue' => (float) $order->getTotalDue(),
            'giftcardAmount' => null,
        ];

        $giftcardAmount = $order->getData('giftcard_amount');
        if ($giftcardAmount) {
            $prices['giftcardAmount'] = (float) abs($giftcardAmount);
        }

        return $prices;
    }

    /**
     * Place order with split payments (POS)
     */
    private function placeOrderWithSplitPayments(array $context): PlaceOrderWithSplitPaymentsResult
    {
        // POS operation — require admin, POS, or API user role
        if (!$this->isAdmin() && !$this->isPosUser() && !$this->isApiUser()) {
            throw new AccessDeniedHttpException('Admin, POS, or API user access required for split payments');
        }

        $args = $context['args']['input'] ?? [];
        $cartId = $args['cartId'] ?? null;
        $maskedId = $args['maskedId'] ?? null;
        $payments = $args['payments'] ?? [];
        $registerId = (int) ($args['registerId'] ?? 1);
        $shippingMethod = $args['shippingMethod'] ?? null;
        $employeeId = isset($args['employeeId']) ? (int) $args['employeeId'] : null;

        if (!$cartId && !$maskedId) {
            throw new BadRequestHttpException('Cart ID or masked ID is required');
        }

        if (empty($payments)) {
            throw new BadRequestHttpException('At least one payment is required');
        }

        // Get quote
        $quote = $this->cartService->getCart(
            $cartId ? (int) $cartId : null,
            $maskedId,
        );

        if (!$quote) {
            throw new NotFoundHttpException('Cart not found');
        }

        // Set store context
        if ($quote->getStoreId()) {
            \Mage::app()->setCurrentStore($quote->getStoreId());
            $quote->setStore(\Mage::app()->getStore($quote->getStoreId()));
        }

        // POS default address — all values from store config, nothing hardcoded
        $sid = $quote->getStoreId();
        $posAddress = [
            'firstname' => 'POS',
            'lastname' => 'Customer',
            'street' => \Mage::getStoreConfig('general/store_information/address', $sid) ?: 'In-Store Pickup',
            'city' => \Mage::getStoreConfig('general/store_information/city', $sid) ?: 'Store',
            'region' => \Mage::getStoreConfig('general/store_information/region', $sid) ?: '',
            'postcode' => \Mage::getStoreConfig('general/store_information/postcode', $sid) ?: '0000',
            'country_id' => \Mage::getStoreConfig('general/country/default', $sid) ?: 'US',
            'telephone' => \Mage::getStoreConfig('general/store_information/phone', $sid) ?: '0000000000',
        ];

        // Set shipping address and method
        if (!$quote->isVirtual()) {
            $shippingAddress = $quote->getShippingAddress();
            if (!$shippingAddress->getFirstname()) {
                $shippingAddress->addData($posAddress);
            }
            if (!$shippingAddress->getShippingMethod() || $shippingMethod) {
                $method = $shippingMethod ?: 'freeshipping_freeshipping';
                $shippingAddress->setShippingMethod($method);
                if ($method === 'freeshipping_freeshipping') {
                    $shippingAddress->setShippingDescription('Free Shipping - POS Pickup');
                    $shippingAddress->setShippingAmount(0);
                    $shippingAddress->setBaseShippingAmount(0);
                }
            }
        }

        // Set billing address
        $billingAddress = $quote->getBillingAddress();
        if (!$billingAddress->getFirstname()) {
            $billingAddress->addData($posAddress);
        }

        // Set payment method to split payment
        $payment = $quote->getPayment();
        $payment->setMethod('maho_pos_split');

        // Set email if not present
        if (!$quote->getCustomerEmail()) {
            $quote->setCustomerEmail('pos@store.local');
        }

        // Collect totals
        $quote->collectTotals();
        $quote->save();

        // Validate payment total
        $totalPayment = 0.0;
        foreach ($payments as $paymentData) {
            $totalPayment += (float) ($paymentData['amount'] ?? 0);
        }

        $grandTotal = (float) $quote->getGrandTotal();
        if ($totalPayment < $grandTotal - 0.01) {
            throw new BadRequestHttpException(
                "Insufficient payment: total payment ({$totalPayment}) is less than order total ({$grandTotal})",
            );
        }

        // Place order
        $result = $this->orderService->placeAdminOrder(
            $quote,
            null,
            null,
            null,
            $employeeId,
        );

        $order = $result['order'];
        $savedPayments = [];

        // Record payments to pos_payment table
        foreach ($payments as $paymentData) {
            /** @var \Maho_Pos_Model_Payment $posPayment */
            $posPayment = \Mage::getModel('maho_pos/payment');
            $posPayment->setOrderId((int) $order->getId())
                ->setRegisterId($registerId)
                ->setMethodCode($paymentData['method'] ?? $paymentData['methodCode'] ?? 'cash')
                ->setAmount((float) $paymentData['amount'])
                ->setBaseAmount((float) $paymentData['amount'])
                ->setCurrencyCode($order->getOrderCurrencyCode())
                ->setStatus('captured');

            if (!empty($paymentData['cardType'])) {
                $posPayment->setCardType($paymentData['cardType']);
            }
            if (!empty($paymentData['cardLast4'])) {
                $posPayment->setCardLast4($paymentData['cardLast4']);
            }
            if (!empty($paymentData['authCode'])) {
                $posPayment->setAuthCode($paymentData['authCode']);
            }
            if (!empty($paymentData['transactionId'])) {
                $posPayment->setTransactionId($paymentData['transactionId']);
            }
            if (!empty($paymentData['terminalId'])) {
                $posPayment->setTerminalId($paymentData['terminalId']);
            }

            $posPayment->save();
            $savedPayments[] = $this->posPaymentMapper->mapToDto($posPayment);
        }

        // Create invoice and shipment
        $invoiceDto = null;
        $shipmentDto = null;

        try {
            if ($order->canInvoice()) {
                $invoice = \Mage::getModel('sales/service_order', $order)
                    ->prepareInvoice()
                    ->setRequestedCaptureCase(\Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE)
                    ->register();
                $invoice->getOrder()->setIsInProcess(true);
                \Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder())
                    ->save();

                $invoiceDto = new Invoice();
                $invoiceDto->id = (int) $invoice->getId();
                $invoiceDto->incrementId = $invoice->getIncrementId();
                $invoiceDto->orderId = (int) $order->getId();
                $invoiceDto->grandTotal = (float) $invoice->getGrandTotal();
                $invoiceDto->state = (int) $invoice->getState();
                $invoiceDto->createdAt = $invoice->getCreatedAt();
            }

            if ($order->canShip()) {
                $shipment = \Mage::getModel('sales/service_order', $order)
                    ->prepareShipment();
                $shipment->register();
                \Mage::getModel('core/resource_transaction')
                    ->addObject($shipment)
                    ->addObject($shipment->getOrder())
                    ->save();

                $shipmentDto = new Shipment();
                $shipmentDto->id = (int) $shipment->getId();
                $shipmentDto->incrementId = $shipment->getIncrementId();
                $shipmentDto->orderId = (int) $order->getId();
                $shipmentDto->totalQty = (int) $shipment->getTotalQty();
                $shipmentDto->createdAt = $shipment->getCreatedAt();
            }

            // Reload order to get updated state
            $order->load($order->getId());
        } catch (\Exception $e) {
            \Mage::logException($e);
        }

        // Calculate change amount (for cash payments)
        $changeAmount = max(0, $totalPayment - $grandTotal);

        // Build result
        $resultDto = new PlaceOrderWithSplitPaymentsResult();
        $resultDto->order = $this->mapOrderToDto($order);
        $resultDto->payments = $savedPayments;
        $resultDto->changeAmount = $changeAmount > 0 ? round($changeAmount, 2) : null;
        $resultDto->invoice = $invoiceDto;
        $resultDto->shipment = $shipmentDto;

        return $resultDto;
    }

    /**
     * Record a payment against an order
     */
    private function recordPayment(array $context): PosPayment
    {
        // recordPayment is a POS operation — require admin, POS, or API user role
        if (!$this->isAdmin() && !$this->isPosUser() && !$this->isApiUser()) {
            throw new AccessDeniedHttpException('Admin, POS, or API user access required to record payments');
        }

        $args = $context['args']['input'] ?? [];
        $orderId = (int) ($args['orderId'] ?? 0);
        $method = $args['method'] ?? $args['methodCode'] ?? 'cash';
        $amount = (float) ($args['amount'] ?? 0);
        $registerId = (int) ($args['registerId'] ?? 1);
        $transactionId = $args['transactionId'] ?? null;
        $terminalId = $args['terminalId'] ?? null;
        $cardType = $args['cardType'] ?? null;
        $cardLast4 = $args['cardLast4'] ?? null;
        $authCode = $args['authCode'] ?? null;

        if (!$orderId) {
            throw new BadRequestHttpException('Order ID is required');
        }

        if ($amount <= 0) {
            throw new BadRequestHttpException('Amount must be greater than 0');
        }

        // Verify order exists
        $order = \Mage::getModel('sales/order')->load($orderId);
        if (!$order->getId()) {
            throw new NotFoundHttpException('Order not found');
        }

        // Record payment
        $posPayment = $this->paymentService->recordPayment(
            $orderId,
            $registerId,
            $method,
            $amount,
            $terminalId,
            $transactionId,
            $cardType,
            $cardLast4,
            $authCode,
        );

        return $this->posPaymentMapper->mapToDto($posPayment);
    }

    /**
     * Verify the current user has access to place an order for this cart
     */
    private function verifyCartOwnership(\Mage_Sales_Model_Quote $quote, bool $accessedByMaskedId): void
    {
        if ($this->isAdmin() || $this->isPosUser() || $this->isApiUser()) {
            return;
        }

        $cartCustomerId = $quote->getCustomerId();
        $authenticatedCustomerId = $this->getAuthenticatedCustomerId();

        if ($cartCustomerId) {
            if ($authenticatedCustomerId === null || (int) $cartCustomerId !== $authenticatedCustomerId) {
                throw new AccessDeniedHttpException('You can only place orders for your own cart');
            }
        } elseif (!$accessedByMaskedId) {
            throw new AccessDeniedHttpException('Guest carts must be accessed via masked ID');
        }
    }

    /**
     * Verify a placed order by one-time storefront token
     */
    private function verifyOrder(array $context): Order
    {
        $request = $context['request'] ?? null;
        $body = $request ? (json_decode($request->getContent(), true) ?? []) : [];
        $args = $context['args']['input'] ?? $body;

        $incrementId = $args['incrementId'] ?? '';
        $token = $args['orderToken'] ?? $body['orderToken'] ?? '';

        if (!$token || !$incrementId) {
            throw new BadRequestHttpException('Order increment ID and order token are required');
        }

        $order = \Mage::getModel('sales/order')->loadByIncrementId($incrementId);
        if (!$order || !$order->getId()) {
            throw new NotFoundHttpException('Order not found');
        }

        $storedToken = $order->getData('storefront_order_token');
        if (!$storedToken || !hash_equals($storedToken, $token)) {
            throw new HttpException(403, 'Invalid order token');
        }

        // Clear the token after successful verification (one-time use)
        $resource = \Mage::getSingleton('core/resource');
        $resource->getConnection('core_write')->update(
            $resource->getTableName('sales/order'),
            ['storefront_order_token' => null],
            ['entity_id = ?' => $order->getId()],
        );

        $dto = new Order();
        $dto->id = (int) $order->getId();
        $dto->incrementId = $order->getIncrementId();
        $dto->status = $order->getStatus();
        $dto->state = $order->getState();
        $dto->customerEmail = $order->getCustomerEmail();
        $dto->currency = $order->getOrderCurrencyCode();

        // Build items
        $items = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $orderItem = new OrderItem();
            $orderItem->sku = $item->getSku();
            $orderItem->name = $item->getName();
            $orderItem->price = (float) $item->getPriceInclTax();
            $orderItem->qtyOrdered = (float) $item->getQtyOrdered();
            $items[] = $orderItem;
        }
        $dto->items = $items;

        $dto->prices = [
            'grandTotal' => (float) $order->getGrandTotal(),
            'subtotal' => (float) $order->getSubtotalInclTax(),
            'taxAmount' => (float) $order->getTaxAmount(),
            'shippingAmount' => (float) $order->getShippingInclTax(),
        ];

        // Generate account creation token for guest orders without existing account
        $orderEmail = $order->getCustomerEmail();
        $existingCustomer = \Mage::getModel('customer/customer')
            ->setWebsiteId(\Mage::app()->getStore()->getWebsiteId())
            ->loadByEmail($orderEmail);

        if (!$existingCustomer->getId()) {
            $cryptKey = (string) \Mage::app()->getConfig()->getNode('global/crypt/key');
            $timestamp = time();
            $payload = $order->getId() . '|' . $orderEmail . '|' . $timestamp . '|action=create_account';
            $payloadBase64 = base64_encode($payload);
            $signature = hash_hmac('sha256', $payloadBase64, $cryptKey);
            $dto->accessToken = $payloadBase64 . '.' . $signature;
        }

        return $dto;
    }
}
