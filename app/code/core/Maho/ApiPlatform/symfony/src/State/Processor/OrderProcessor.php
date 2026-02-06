<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Maho\ApiPlatform\ApiResource\Order;
use Maho\ApiPlatform\ApiResource\OrderItem;
use Maho\ApiPlatform\ApiResource\OrderPrices;
use Maho\ApiPlatform\ApiResource\Address;
use Maho\ApiPlatform\ApiResource\PosPayment;
use Maho\ApiPlatform\ApiResource\PlaceOrderWithSplitPaymentsResult;
use Maho\ApiPlatform\ApiResource\Invoice;
use Maho\ApiPlatform\ApiResource\Shipment;
use Maho\ApiPlatform\Service\CartService;
use Maho\ApiPlatform\Service\OrderService;
use Maho\ApiPlatform\Service\PaymentService;

/**
 * Order State Processor - Handles order mutations for API Platform
 *
 * @implements ProcessorInterface<Order, Order>
 */
final class OrderProcessor implements ProcessorInterface
{
    private CartService $cartService;
    private OrderService $orderService;
    private PaymentService $paymentService;

    public function __construct()
    {
        $this->cartService = new CartService();
        $this->orderService = new OrderService();
        $this->paymentService = new PaymentService();
    }

    /**
     * Process order mutations
     */
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Order|PlaceOrderWithSplitPaymentsResult|PosPayment
    {
        $operationName = $operation->getName();

        return match ($operationName) {
            'placeOrder', '_api_/orders_post' => $this->placeOrder($context),
            'cancelOrder' => $this->cancelOrder($context),
            'placeOrderWithSplitPayments' => $this->placeOrderWithSplitPayments($context),
            'recordPayment' => $this->recordPayment($context),
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

        // Get cart/quote
        $quote = $this->cartService->getCart(
            $cartId ? (int) $cartId : null,
            $maskedId,
        );

        if (!$quote) {
            throw new \RuntimeException('Cart not found');
        }

        // Place order
        $result = $this->orderService->placeOrder(
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
            throw new \RuntimeException('Order not found');
        }

        // Verify customer access for non-admin requests
        $customerId = $context['customer_id'] ?? null;
        if ($customerId && $order->getCustomerId() != $customerId) {
            throw new \RuntimeException('Order not found');
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
        $dto->currency = $order->getOrderCurrencyCode() ?: 'AUD';
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
        $dto->prices = $this->mapPricesToDto($order);

        // Map billing address
        $billingAddress = $order->getBillingAddress();
        if ($billingAddress && $billingAddress->getId()) {
            $dto->billingAddress = $this->mapAddressToDto($billingAddress);
        }

        // Map shipping address
        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress && $shippingAddress->getId()) {
            $dto->shippingAddress = $this->mapAddressToDto($shippingAddress);
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
     * Map Maho order to OrderPrices DTO
     */
    private function mapPricesToDto(\Mage_Sales_Model_Order $order): OrderPrices
    {
        $prices = new OrderPrices();

        $prices->subtotal = (float) $order->getSubtotal();
        $prices->subtotalInclTax = (float) $order->getSubtotalInclTax();
        $prices->discountAmount = $order->getDiscountAmount()
            ? (float) abs($order->getDiscountAmount())
            : null;
        $prices->shippingAmount = $order->getShippingAmount()
            ? (float) $order->getShippingAmount()
            : null;
        $prices->shippingAmountInclTax = $order->getShippingInclTax()
            ? (float) $order->getShippingInclTax()
            : null;
        $prices->taxAmount = (float) $order->getTaxAmount();
        $prices->grandTotal = (float) $order->getGrandTotal();
        $prices->totalPaid = (float) $order->getTotalPaid();
        $prices->totalRefunded = (float) $order->getTotalRefunded();
        $prices->totalDue = (float) $order->getTotalDue();

        // Check for giftcard amount if available
        $giftcardAmount = $order->getData('giftcard_amount');
        if ($giftcardAmount) {
            $prices->giftcardAmount = (float) abs($giftcardAmount);
        }

        return $prices;
    }

    /**
     * Map Maho order address model to Address DTO
     */
    private function mapAddressToDto(\Mage_Sales_Model_Order_Address $address): Address
    {
        $dto = new Address();
        $dto->id = (int) $address->getId();
        $dto->firstName = $address->getFirstname() ?? '';
        $dto->lastName = $address->getLastname() ?? '';
        $dto->company = $address->getCompany();
        $dto->street = $address->getStreet();
        $dto->city = $address->getCity() ?? '';
        $dto->region = $address->getRegion();
        $dto->regionId = $address->getRegionId() ? (int) $address->getRegionId() : null;
        $dto->postcode = $address->getPostcode() ?? '';
        $dto->countryId = $address->getCountryId() ?? '';
        $dto->telephone = $address->getTelephone() ?? '';

        return $dto;
    }

    /**
     * Place order with split payments (POS)
     */
    private function placeOrderWithSplitPayments(array $context): PlaceOrderWithSplitPaymentsResult
    {
        $args = $context['args']['input'] ?? [];
        $cartId = $args['cartId'] ?? null;
        $maskedId = $args['maskedId'] ?? null;
        $payments = $args['payments'] ?? [];
        $registerId = (int) ($args['registerId'] ?? 1);
        $shippingMethod = $args['shippingMethod'] ?? null;
        $employeeId = isset($args['employeeId']) ? (int) $args['employeeId'] : null;

        if (!$cartId && !$maskedId) {
            throw new \RuntimeException('Cart ID or masked ID is required');
        }

        if (empty($payments)) {
            throw new \RuntimeException('At least one payment is required');
        }

        // Get quote
        $quote = $this->cartService->getCart(
            $cartId ? (int) $cartId : null,
            $maskedId,
        );

        if (!$quote) {
            throw new \RuntimeException('Cart not found');
        }

        // Set store context
        if ($quote->getStoreId()) {
            \Mage::app()->setCurrentStore($quote->getStoreId());
            $quote->setStore(\Mage::app()->getStore($quote->getStoreId()));
        }

        // POS default address
        $posAddress = [
            'firstname' => 'POS',
            'lastname' => 'Customer',
            'street' => 'In-Store Pickup',
            'city' => 'Melbourne',
            'region' => 'Victoria',
            'region_id' => 574,
            'postcode' => '3000',
            'country_id' => 'AU',
            'telephone' => '0000000000',
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
            throw new \RuntimeException(
                "Insufficient payment: total payment ({$totalPayment}) is less than order total ({$grandTotal})",
            );
        }

        // Place order
        $result = $this->orderService->placeOrder(
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
            /** @phpstan-ignore-next-line */
            $posPayment = \Mage::getModel('maho_pos/payment');
            /** @phpstan-ignore-next-line */
            $posPayment->setOrderId((int) $order->getId())
                ->setRegisterId($registerId)
                ->setMethodCode($paymentData['method'] ?? $paymentData['methodCode'] ?? 'cash')
                ->setAmount((float) $paymentData['amount'])
                ->setBaseAmount((float) $paymentData['amount'])
                ->setCurrencyCode($order->getOrderCurrencyCode())
                ->setStatus('captured');

            if (!empty($paymentData['cardType'])) {
                /** @phpstan-ignore-next-line */
                $posPayment->setCardType($paymentData['cardType']);
            }
            if (!empty($paymentData['cardLast4'])) {
                /** @phpstan-ignore-next-line */
                $posPayment->setCardLast4($paymentData['cardLast4']);
            }
            if (!empty($paymentData['authCode'])) {
                /** @phpstan-ignore-next-line */
                $posPayment->setAuthCode($paymentData['authCode']);
            }
            if (!empty($paymentData['transactionId'])) {
                /** @phpstan-ignore-next-line */
                $posPayment->setTransactionId($paymentData['transactionId']);
            }
            if (!empty($paymentData['terminalId'])) {
                /** @phpstan-ignore-next-line */
                $posPayment->setTerminalId($paymentData['terminalId']);
            }

            /** @phpstan-ignore-next-line */
            $posPayment->save();
            /** @phpstan-ignore-next-line */
            $savedPayments[] = $this->mapPosPaymentToDto($posPayment);
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
                $invoiceDto->state = (string) $invoice->getState();
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
                /** @phpstan-ignore-next-line */
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
            throw new \RuntimeException('Order ID is required');
        }

        if ($amount <= 0) {
            throw new \RuntimeException('Amount must be greater than 0');
        }

        // Verify order exists
        $order = \Mage::getModel('sales/order')->load($orderId);
        if (!$order->getId()) {
            throw new \RuntimeException('Order not found');
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

        return $this->mapPosPaymentToDto($posPayment);
    }

    /**
     * Map POS payment model to DTO
     */
    /** @phpstan-ignore-next-line */
    private function mapPosPaymentToDto(\Maho_Pos_Model_Payment $payment): PosPayment
    {
        $methodLabels = [
            'cashondelivery' => 'Cash',
            'cash' => 'Cash',
            'purchaseorder' => 'EFTPOS/Card',
            'eftpos' => 'EFTPOS/Card',
            'gene_braintree_creditcard' => 'Credit Card',
            'checkmo' => 'Check/Money Order',
            'banktransfer' => 'Bank Transfer',
        ];

        $dto = new PosPayment();
        /** @phpstan-ignore-next-line */
        $dto->id = (int) $payment->getId();
        /** @phpstan-ignore-next-line */
        $dto->orderId = (int) $payment->getOrderId();
        /** @phpstan-ignore-next-line */
        $dto->registerId = (int) $payment->getRegisterId();
        /** @phpstan-ignore-next-line */
        $dto->methodCode = $payment->getMethodCode();
        /** @phpstan-ignore-next-line */
        $dto->methodLabel = $methodLabels[$payment->getMethodCode()] ?? $payment->getMethodCode();
        /** @phpstan-ignore-next-line */
        $dto->amount = (float) $payment->getAmount();
        /** @phpstan-ignore-next-line */
        $dto->baseAmount = (float) $payment->getBaseAmount();
        /** @phpstan-ignore-next-line */
        $dto->currencyCode = $payment->getCurrencyCode();
        /** @phpstan-ignore-next-line */
        $dto->terminalId = $payment->getTerminalId();
        /** @phpstan-ignore-next-line */
        $dto->transactionId = $payment->getTransactionId();
        /** @phpstan-ignore-next-line */
        $dto->cardType = $payment->getCardType();
        /** @phpstan-ignore-next-line */
        $dto->cardLast4 = $payment->getCardLast4();
        /** @phpstan-ignore-next-line */
        $dto->authCode = $payment->getAuthCode();
        /** @phpstan-ignore-next-line */
        $dto->status = $payment->getStatus();
        /** @phpstan-ignore-next-line */
        $dto->createdAt = $payment->getCreatedAt();

        return $dto;
    }
}
