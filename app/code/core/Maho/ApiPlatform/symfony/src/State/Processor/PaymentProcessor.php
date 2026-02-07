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

    public function __construct()
    {
        $this->paymentService = new PaymentService();
    }

    /**
     * Process payment mutations
     */
    #[\Override]
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
            $receiptData,
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
            $payments,
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
        $dto->methodLabel = PaymentService::getMethodLabel($payment->getMethodCode());
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
