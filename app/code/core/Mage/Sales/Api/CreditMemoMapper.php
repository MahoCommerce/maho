<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Sales
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Sales\Api;

final class CreditMemoMapper
{
    public static function mapToDto(\Mage_Sales_Model_Order_Creditmemo $creditmemo): CreditMemo
    {
        $dto = new CreditMemo();
        $dto->id = (int) $creditmemo->getId();
        $dto->orderId = (int) $creditmemo->getOrderId();
        $dto->incrementId = $creditmemo->getIncrementId();
        $dto->createdAt = $creditmemo->getCreatedAt();

        $stateMap = [
            \Mage_Sales_Model_Order_Creditmemo::STATE_OPEN => 'open',
            \Mage_Sales_Model_Order_Creditmemo::STATE_REFUNDED => 'refunded',
            \Mage_Sales_Model_Order_Creditmemo::STATE_CANCELED => 'canceled',
        ];
        $dto->state = $stateMap[(int) $creditmemo->getState()] ?? 'unknown';

        $dto->grandTotal = (float) $creditmemo->getGrandTotal();
        $dto->baseGrandTotal = (float) $creditmemo->getBaseGrandTotal();
        $dto->subtotal = (float) $creditmemo->getSubtotal();
        $dto->taxAmount = (float) $creditmemo->getTaxAmount();
        $dto->shippingAmount = (float) $creditmemo->getShippingAmount();
        $dto->discountAmount = (float) $creditmemo->getDiscountAmount();
        $dto->adjustmentPositive = (float) $creditmemo->getAdjustmentPositive();
        $dto->adjustmentNegative = (float) $creditmemo->getAdjustmentNegative();

        $order = $creditmemo->getOrder();
        $dto->orderIncrementId = $order ? $order->getIncrementId() : null;

        $dto->items = [];
        foreach ($creditmemo->getAllItems() as $item) {
            $dto->items[] = self::mapItemToDto($item);
        }

        $comments = $creditmemo->getCommentsCollection();
        if ($comments && $comments->getSize() > 0) {
            $firstComment = $comments->getFirstItem();
            $dto->comment = $firstComment->getComment();
        }

        return $dto;
    }

    public static function mapItemToDto(\Mage_Sales_Model_Order_Creditmemo_Item $item): CreditMemoItem
    {
        $dto = new CreditMemoItem();
        $dto->id = (int) $item->getId();
        $dto->orderItemId = (int) $item->getOrderItemId();
        $dto->sku = $item->getSku() ?? '';
        $dto->name = $item->getName() ?? '';
        $dto->qty = (float) $item->getQty();
        $dto->price = (float) $item->getPrice();
        $dto->rowTotal = (float) $item->getRowTotal();
        $dto->taxAmount = (float) $item->getTaxAmount();
        $dto->discountAmount = (float) $item->getDiscountAmount();
        $dto->backToStock = (bool) $item->getBackToStock();

        return $dto;
    }
}
