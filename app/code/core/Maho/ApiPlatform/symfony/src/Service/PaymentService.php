<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\Service;

/**
 * Payment Service - Business logic for POS payment operations
 */
class PaymentService
{
    /**
     * Get payments for an order
     *
     * @param int $orderId
     * @return \Maho_Pos_Model_Resource_Payment_Collection
     */
    /** @phpstan-ignore-next-line */
    public function getOrderPayments(int $orderId): \Maho_Pos_Model_Resource_Payment_Collection
    {
        /** @phpstan-ignore-next-line */
        $collection = \Mage::getModel('maho_pos/payment')->getCollection();
        $collection->addOrderFilter($orderId)
            ->setOrder('created_at', 'ASC');

        return $collection;
    }

    /**
     * Record a payment for an order
     *
     * @param int $orderId
     * @param int $registerId
     * @param string $methodCode
     * @param float $amount
     * @param string|null $terminalId
     * @param string|null $transactionId
     * @param string|null $cardType
     * @param string|null $cardLast4
     * @param string|null $authCode
     * @param array|null $receiptData
     * @param string $status
     * @throws \Mage_Core_Exception
     * @phpstan-ignore-next-line
     */
    public function recordPayment(
        int $orderId,
        int $registerId,
        string $methodCode,
        float $amount,
        ?string $terminalId = null,
        ?string $transactionId = null,
        ?string $cardType = null,
        ?string $cardLast4 = null,
        ?string $authCode = null,
        ?array $receiptData = null,
        /** @phpstan-ignore-next-line */
        string $status = \Maho_Pos_Model_Payment::STATUS_CAPTURED,
    ) {
        // Load order
        $order = \Mage::getModel('sales/order')->load($orderId);
        if (!$order->getId()) {
            throw new \Mage_Core_Exception('Order not found');
        }

        // Get currency
        $currencyCode = $order->getOrderCurrencyCode();

        // Create payment record
        /** @phpstan-ignore-next-line */
        $payment = \Mage::getModel('maho_pos/payment');
        /** @phpstan-ignore-next-line */
        $payment->setData([
            'order_id' => $orderId,
            'register_id' => $registerId,
            'method_code' => $methodCode,
            'amount' => $amount,
            'base_amount' => $amount, // TODO: Convert to base currency if needed
            'currency_code' => $currencyCode,
            'terminal_id' => $terminalId,
            'transaction_id' => $transactionId,
            'card_type' => $cardType,
            'card_last4' => $cardLast4,
            'auth_code' => $authCode,
            'receipt_data' => $receiptData,
            'status' => $status,
        ]);

        /** @phpstan-ignore-next-line */
        $payment->save();

        return $payment;
    }

    /**
     * Record multiple payments for an order (split payment)
     *
     * @param int $orderId
     * @param int $registerId
     * @param array $payments Array of payment data
     * @return array Array of created payment models
     * @throws \Mage_Core_Exception
     */
    public function recordMultiplePayments(int $orderId, int $registerId, array $payments): array
    {
        $createdPayments = [];

        // Load order
        $order = \Mage::getModel('sales/order')->load($orderId);
        if (!$order->getId()) {
            throw new \Mage_Core_Exception('Order not found');
        }

        // Validate total amount matches order total
        $totalPaid = 0.0;
        foreach ($payments as $paymentData) {
            $totalPaid += (float) $paymentData['amount'];
        }

        $orderTotal = (float) $order->getGrandTotal();
        $tolerance = 0.01; // Allow 1 cent tolerance for rounding

        if (abs($totalPaid - $orderTotal) > $tolerance) {
            throw new \Mage_Core_Exception(
                "Total payment amount ({$totalPaid}) does not match order total ({$orderTotal})",
            );
        }

        // Create each payment
        foreach ($payments as $paymentData) {
            $payment = $this->recordPayment(
                $orderId,
                $registerId,
                $paymentData['methodCode'],
                (float) $paymentData['amount'],
                $paymentData['terminalId'] ?? null,
                $paymentData['transactionId'] ?? null,
                $paymentData['cardType'] ?? null,
                $paymentData['cardLast4'] ?? null,
                $paymentData['authCode'] ?? null,
                $paymentData['receiptData'] ?? null,
                /** @phpstan-ignore-next-line */
                \Maho_Pos_Model_Payment::STATUS_CAPTURED,
            );

            $createdPayments[] = $payment;
        }

        return $createdPayments;
    }

    /**
     * Get total paid amount for an order
     *
     * @param int $orderId
     * @return float
     */
    public function getTotalPaidAmount(int $orderId): float
    {
        /** @phpstan-ignore-next-line */
        $resource = \Mage::getResourceModel('maho_pos/payment');
        /** @phpstan-ignore-next-line */
        return $resource->getTotalPaidAmount($orderId);
    }

    /**
     * Validate if order is fully paid
     *
     * @param int $orderId
     * @return bool
     */
    public function isOrderFullyPaid(int $orderId): bool
    {
        $order = \Mage::getModel('sales/order')->load($orderId);
        if (!$order->getId()) {
            return false;
        }

        $totalPaid = $this->getTotalPaidAmount($orderId);
        $orderTotal = (float) $order->getGrandTotal();

        return abs($totalPaid - $orderTotal) < 0.01; // 1 cent tolerance
    }

    /**
     * Get payment methods available for POS
     *
     * @return array
     */
    public function getAvailablePaymentMethods(): array
    {
        /** @phpstan-ignore-next-line */
        return \Maho_Pos_Model_Payment::getPaymentMethods();
    }
}
