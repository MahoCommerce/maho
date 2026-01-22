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
use Maho\ApiPlatform\ApiResource\PosPayment;
use Maho\ApiPlatform\Service\PaymentService;

/**
 * Payment State Processor - Handles payment mutations for API Platform
 *
 * Supports both POS payments and headless checkout payments.
 *
 * @implements ProcessorInterface<PosPayment, PosPayment>
 */
final class PaymentProcessor implements ProcessorInterface
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
     * Process payment mutations
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PosPayment|array
    {
        $operationName = $operation->getName();

        return match ($operationName) {
            'recordPayment' => $this->recordPayment($context),
            'recordSplitPayment' => $this->recordSplitPayment($context),
            default => $data instanceof PosPayment ? $data : new PosPayment(),
        };
    }

    /**
     * Record a single payment for an order
     */
    private function recordPayment(array $context): PosPayment
    {
        $args = $context['args']['input'] ?? [];

        $orderId = $args['orderId'] ?? null;
        $registerId = $args['registerId'] ?? 0;
        $methodCode = $args['methodCode'] ?? '';
        $amount = (float) ($args['amount'] ?? 0);
        $terminalId = $args['terminalId'] ?? null;
        $transactionId = $args['transactionId'] ?? null;
        $cardType = $args['cardType'] ?? null;
        $cardLast4 = $args['cardLast4'] ?? null;
        $authCode = $args['authCode'] ?? null;
        $receiptData = $args['receiptData'] ?? null;

        if (!$orderId) {
            throw new \RuntimeException('Order ID is required');
        }

        if (!$methodCode) {
            throw new \RuntimeException('Payment method code is required');
        }

        if ($amount <= 0) {
            throw new \RuntimeException('Payment amount must be greater than zero');
        }

        $payment = $this->paymentService->recordPayment(
            (int) $orderId,
            (int) $registerId,
            $methodCode,
            $amount,
            $terminalId,
            $transactionId,
            $cardType,
            $cardLast4,
            $authCode,
            $receiptData
        );

        return $this->mapToDto($payment);
    }

    /**
     * Record multiple payments for an order (split payment)
     *
     * @return PosPayment[]
     */
    private function recordSplitPayment(array $context): array
    {
        $args = $context['args']['input'] ?? [];

        $orderId = $args['orderId'] ?? null;
        $registerId = $args['registerId'] ?? 0;
        $payments = $args['payments'] ?? [];

        if (!$orderId) {
            throw new \RuntimeException('Order ID is required');
        }

        if (empty($payments)) {
            throw new \RuntimeException('At least one payment is required');
        }

        $createdPayments = $this->paymentService->recordMultiplePayments(
            (int) $orderId,
            (int) $registerId,
            $payments
        );

        $dtos = [];
        foreach ($createdPayments as $payment) {
            $dtos[] = $this->mapToDto($payment);
        }

        return $dtos;
    }

    /**
     * Map payment model to DTO
     */
    private function mapToDto(\Maho_Pos_Model_Payment $payment): PosPayment
    {
        $dto = new PosPayment();
        $dto->id = (int) $payment->getId();
        $dto->orderId = (int) $payment->getOrderId();
        $dto->registerId = $payment->getRegisterId() ? (int) $payment->getRegisterId() : null;
        $dto->methodCode = $payment->getMethodCode();
        $dto->methodLabel = $this->methodLabels[$payment->getMethodCode()] ?? $payment->getMethodCode();
        $dto->amount = (float) $payment->getAmount();
        $dto->baseAmount = (float) $payment->getBaseAmount();
        $dto->currencyCode = $payment->getCurrencyCode();
        $dto->terminalId = $payment->getTerminalId();
        $dto->transactionId = $payment->getTransactionId();
        $dto->cardType = $payment->getCardType();
        $dto->cardLast4 = $payment->getCardLast4();
        $dto->authCode = $payment->getAuthCode();
        $dto->status = $payment->getStatus();
        $dto->createdAt = $payment->getCreatedAt();

        // Get receipt data if available
        $receiptData = $payment->getReceiptData();
        if ($receiptData) {
            $dto->receiptData = is_array($receiptData) ? $receiptData : json_decode($receiptData, true) ?? [];
        }

        return $dto;
    }
}
