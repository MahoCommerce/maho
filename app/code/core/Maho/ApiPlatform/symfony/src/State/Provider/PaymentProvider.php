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

namespace Maho\ApiPlatform\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\State\ProviderInterface;
use Maho\ApiPlatform\ApiResource\PosPayment;
use Maho\ApiPlatform\ApiResource\PaymentSummary;
use Maho\ApiPlatform\Service\PaymentService;

/**
 * Payment State Provider - Fetches payment data for API Platform
 *
 * Supports both POS payments and headless checkout payments.
 *
 * @implements ProviderInterface<PosPayment>
 */
final class PaymentProvider implements ProviderInterface
{
    private PaymentService $paymentService;

    private array $methodLabels = [
        'cashondelivery' => 'Cash',
        'cash' => 'Cash',
        'purchaseorder' => 'EFTPOS/Card',
        'eftpos' => 'EFTPOS/Card',
        'gene_braintree_creditcard' => 'Credit Card',
        'checkmo' => 'Check/Money Order',
        'banktransfer' => 'Bank Transfer',
        'free' => 'Free',
    ];

    public function __construct()
    {
        $this->paymentService = new PaymentService();
    }

    /**
     * Provide payment data based on operation type
     *
     * @return PosPayment|PosPayment[]|PaymentSummary[]|null
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PosPayment|array|null
    {
        $operationName = $operation->getName();

        // Handle REST collection endpoint (GET /pos-payments)
        if ($operation instanceof CollectionOperationInterface && $operationName !== 'orderPosPayments') {
            return $this->getCollection($context);
        }

        // Handle orderPosPayments query - get all payments for an order
        if ($operationName === 'orderPosPayments') {
            $orderId = $context['args']['orderId'] ?? null;
            if (!$orderId) {
                return [];
            }

            return $this->getOrderPayments((int) $orderId);
        }

        // Handle single payment query by ID
        $paymentId = $context['args']['id'] ?? $uriVariables['id'] ?? null;

        if (!$paymentId) {
            return null;
        }

        return $this->getPayment((int) $paymentId);
    }

    /**
     * Get payment collection with pagination
     *
     * @return array<PosPayment>
     */
    private function getCollection(array $context): array
    {
        $filters = $context['filters'] ?? [];
        $page = (int) ($filters['page'] ?? 1);
        $pageSize = min((int) ($filters['itemsPerPage'] ?? $filters['pageSize'] ?? 20), 100);
        $orderId = $filters['orderId'] ?? null;

        // If orderId filter provided, get payments for that order
        if ($orderId) {
            return $this->getOrderPayments((int) $orderId);
        }

        // Otherwise get all payments with pagination
        return $this->getAllPayments($page, $pageSize);
    }

    /**
     * Get all payments with pagination
     *
     * @return array<PosPayment>
     */
    private function getAllPayments(int $page = 1, int $pageSize = 20): array
    {
        // Check if POS module is enabled
        if (!class_exists('Maho_Pos_Model_Payment')) {
            return [];
        }

        /** @phpstan-ignore-next-line */
        $collection = \Mage::getModel('maho_pos/payment')->getCollection()
            ->setOrder('created_at', 'DESC');

        $collection->setPageSize($pageSize);
        $collection->setCurPage($page);

        $payments = [];
        foreach ($collection as $payment) {
            $payments[] = $this->mapToDto($payment);
        }

        return $payments;
    }

    /**
     * Get single payment by ID
     */
    private function getPayment(int $paymentId): ?PosPayment
    {
        // Check if POS module is enabled
        if (!class_exists('Maho_Pos_Model_Payment')) {
            return null;
        }

        /** @phpstan-ignore-next-line */
        $payment = \Mage::getModel('maho_pos/payment')->load($paymentId);

        if (!$payment->getId()) {
            return null;
        }

        return $this->mapToDto($payment);
    }

    /**
     * Get all payments for an order
     *
     * @return PosPayment[]
     */
    private function getOrderPayments(int $orderId): array
    {
        $collection = $this->paymentService->getOrderPayments($orderId);
        $payments = [];

        /** @phpstan-ignore-next-line */
        foreach ($collection as $payment) {
            $payments[] = $this->mapToDto($payment);
        }

        return $payments;
    }

    /**
     * Map payment model to DTO
     */
    /** @phpstan-ignore-next-line */
    private function mapToDto(\Maho_Pos_Model_Payment $payment): PosPayment
    {
        $dto = new PosPayment();
        /** @phpstan-ignore-next-line */
        $dto->id = (int) $payment->getId();
        /** @phpstan-ignore-next-line */
        $dto->orderId = (int) $payment->getOrderId();
        /** @phpstan-ignore-next-line */
        $dto->registerId = $payment->getRegisterId() ? (int) $payment->getRegisterId() : null;
        /** @phpstan-ignore-next-line */
        $dto->methodCode = $payment->getMethodCode();
        /** @phpstan-ignore-next-line */
        $dto->methodLabel = $this->methodLabels[$payment->getMethodCode()] ?? $payment->getMethodCode();
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

        // Get receipt data if available
        /** @phpstan-ignore-next-line */
        $receiptData = $payment->getReceiptData();
        if ($receiptData) {
            $dto->receiptData = is_array($receiptData) ? $receiptData : json_decode($receiptData, true) ?? [];
        }

        return $dto;
    }
}
