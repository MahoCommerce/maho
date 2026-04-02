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
use Maho\ApiPlatform\Service\PaymentService;
use Maho\ApiPlatform\Service\PosPaymentMapper;

/**
 * Payment State Processor - Handles payment mutations for API Platform
 *
 * Supports both POS payments and headless checkout payments.
 */
final class PaymentProcessor extends \Maho\ApiPlatform\Processor
{
    private PaymentService $paymentService;
    private readonly PosPaymentMapper $posPaymentMapper;

    public function __construct()
    {
        parent::__construct();
        $this->paymentService = new PaymentService();
        $this->posPaymentMapper = new PosPaymentMapper();
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

        return $this->posPaymentMapper->mapToDto($payment);
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
            $dtos[] = $this->posPaymentMapper->mapToDto($payment);
        }

        return $dtos;
    }

}
