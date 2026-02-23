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

namespace Maho\Sales\Api\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Maho\ApiPlatform\Trait\AuthenticationTrait;
use Maho\Sales\Api\Resource\CreditMemo;
use Maho\Sales\Api\Resource\CreditMemoItem;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Credit Memo State Processor - Handles credit memo creation for API Platform
 *
 * @implements ProcessorInterface<CreditMemo, CreditMemo>
 */
final class CreditMemoProcessor implements ProcessorInterface
{
    use AuthenticationTrait;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CreditMemo
    {
        $this->requireAdminOrApiUser('Credit memo creation requires admin or API access');
        $operationName = $operation->getName();

        return match ($operationName) {
            'createCreditMemo' => $this->createCreditMemoFromGraphQl($context),
            default => $this->createCreditMemoFromRest($uriVariables, $context),
        };
    }

    private function createCreditMemoFromRest(array $uriVariables, array $context): CreditMemo
    {
        $orderId = (int) ($uriVariables['orderId'] ?? 0);
        if (!$orderId) {
            throw new BadRequestHttpException('Order ID is required');
        }

        $body = $context['request']?->toArray() ?? [];

        return $this->doCreateCreditMemo(
            $orderId,
            $body['items'] ?? [],
            $body['comment'] ?? null,
            isset($body['adjustmentPositive']) ? (float) $body['adjustmentPositive'] : null,
            isset($body['adjustmentNegative']) ? (float) $body['adjustmentNegative'] : null,
            (bool) ($body['offlineRefund'] ?? true),
        );
    }

    private function createCreditMemoFromGraphQl(array $context): CreditMemo
    {
        $args = $context['args']['input'] ?? [];
        $orderId = (int) ($args['orderId'] ?? 0);

        if (!$orderId) {
            throw new BadRequestHttpException('Order ID is required');
        }

        return $this->doCreateCreditMemo(
            $orderId,
            $args['items'] ?? [],
            $args['comment'] ?? null,
            isset($args['adjustmentPositive']) ? (float) $args['adjustmentPositive'] : null,
            isset($args['adjustmentNegative']) ? (float) $args['adjustmentNegative'] : null,
            (bool) ($args['offlineRefund'] ?? true),
        );
    }

    private function doCreateCreditMemo(
        int $orderId,
        array $items,
        ?string $comment,
        ?float $adjustmentPositive,
        ?float $adjustmentNegative,
        bool $offlineRefund,
    ): CreditMemo {
        /** @var \Mage_Sales_Model_Order $order */
        $order = \Mage::getModel('sales/order');
        $order->load($orderId);

        if (!$order->getId()) {
            throw new NotFoundHttpException('Order not found');
        }

        if (!$order->canCreditmemo()) {
            throw new BadRequestHttpException('Order cannot be refunded (already fully refunded or not in a refundable state)');
        }

        // Build qty data array: ['qtys' => [orderItemId => qty]]
        $data = ['qtys' => []];
        $backToStockItems = [];

        if (!empty($items)) {
            foreach ($items as $itemData) {
                $orderItemId = (int) ($itemData['orderItemId'] ?? 0);
                $qty = (float) ($itemData['qty'] ?? 0);

                if ($orderItemId <= 0) {
                    throw new BadRequestHttpException('Each item must have a valid orderItemId');
                }
                if ($qty <= 0) {
                    throw new BadRequestHttpException('Each item must have qty > 0');
                }

                $data['qtys'][$orderItemId] = $qty;

                if (!empty($itemData['backToStock'])) {
                    $backToStockItems[$orderItemId] = true;
                }
            }
        }

        // Prepare credit memo using service/order
        /** @var \Mage_Sales_Model_Service_Order $service */
        $service = \Mage::getModel('sales/service_order', $order);
        $creditmemo = $service->prepareCreditmemo($data);

        if (!$creditmemo) {
            throw new BadRequestHttpException('Cannot create credit memo: no items to refund');
        }

        // Set adjustments
        if ($adjustmentPositive !== null) {
            $creditmemo->setAdjustmentPositive($adjustmentPositive);
        }
        if ($adjustmentNegative !== null) {
            $creditmemo->setAdjustmentNegative($adjustmentNegative);
        }

        // Handle back to stock for individual items
        if (!empty($backToStockItems)) {
            foreach ($creditmemo->getAllItems() as $creditmemoItem) {
                if (isset($backToStockItems[$creditmemoItem->getOrderItemId()])) {
                    $creditmemoItem->setBackToStock(true);
                }
            }
        }

        // Handle online vs offline refund
        if ($offlineRefund) {
            $creditmemo->setOfflineRequested(true);
        }
        $creditmemo->setPaymentRefundDisallowed($offlineRefund ? 1.0 : 0.0);

        // Register the credit memo (triggers payment gateway for online refunds)
        $creditmemo->register();

        // Save credit memo and order in a transaction
        /** @var \Mage_Core_Model_Resource_Transaction $transaction */
        $transaction = \Mage::getModel('core/resource_transaction');
        $transaction->addObject($creditmemo)
            ->addObject($order)
            ->save();

        // Add comment if provided
        if ($comment) {
            $creditmemo->addComment($comment);
            $creditmemo->save();
        }

        return $this->mapToDto($creditmemo);
    }

    private function mapToDto(\Mage_Sales_Model_Order_Creditmemo $creditmemo): CreditMemo
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
            $itemDto = new CreditMemoItem();
            $itemDto->id = (int) $item->getId();
            $itemDto->orderItemId = (int) $item->getOrderItemId();
            $itemDto->sku = $item->getSku() ?? '';
            $itemDto->name = $item->getName() ?? '';
            $itemDto->qty = (float) $item->getQty();
            $itemDto->price = (float) $item->getPrice();
            $itemDto->rowTotal = (float) $item->getRowTotal();
            $itemDto->taxAmount = (float) $item->getTaxAmount();
            $itemDto->discountAmount = (float) $item->getDiscountAmount();
            $itemDto->backToStock = (bool) $item->getBackToStock();
            $dto->items[] = $itemDto;
        }

        $comments = $creditmemo->getCommentsCollection();
        if ($comments && $comments->getSize() > 0) {
            $firstComment = $comments->getFirstItem();
            $dto->comment = $firstComment->getComment();
        }

        return $dto;
    }
}
