<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\Service;

use Mage\Sales\Api\PosPayment;

/**
 * Centralized POS payment mapping service
 *
 * Converts Maho POS payment models to the PosPayment DTO.
 */
class PosPaymentMapper
{
    /**
     * Map a POS payment model to a PosPayment DTO.
     */
    public function mapToDto(\Maho_Pos_Model_Payment $payment): PosPayment
    {
        $dto = new PosPayment();
        $dto->id = (int) $payment->getId();
        $dto->orderId = (int) $payment->getOrderId();
        $dto->registerId = $payment->getRegisterId() ? (int) $payment->getRegisterId() : null;
        $dto->methodCode = $payment->getMethodCode();
        $dto->methodLabel = PaymentService::getMethodLabel($payment->getMethodCode());
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

        $receiptData = $payment->getReceiptData();
        if ($receiptData) {
            $dto->receiptData = is_array($receiptData) ? $receiptData : json_decode($receiptData, true) ?? [];
        }

        return $dto;
    }
}
