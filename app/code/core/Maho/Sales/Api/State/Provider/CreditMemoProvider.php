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

namespace Maho\Sales\Api\State\Provider;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\ArrayPaginator;
use ApiPlatform\State\ProviderInterface;
use Maho\ApiPlatform\Trait\AuthenticationTrait;
use Maho\Sales\Api\Resource\CreditMemo;
use Maho\Sales\Api\Resource\CreditMemoItem;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Credit Memo State Provider
 *
 * @implements ProviderInterface<CreditMemo>
 */
final class CreditMemoProvider implements ProviderInterface
{
    use AuthenticationTrait;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CreditMemo|ArrayPaginator|null
    {
        $this->requireAdminOrApiUser('Credit memo access requires admin or API access');

        $operationName = $operation->getName();

        // GraphQL named collection query
        if ($operationName === 'orderCreditMemos') {
            $orderId = (int) ($context['args']['orderId'] ?? 0);
            return $this->getCreditMemosForOrder($orderId, $context);
        }

        // REST collection
        if ($operation instanceof CollectionOperationInterface) {
            $orderId = (int) ($uriVariables['orderId'] ?? 0);
            return $this->getCreditMemosForOrder($orderId, $context);
        }

        // Single item
        $id = (int) ($uriVariables['id'] ?? 0);
        if ($id) {
            return $this->getCreditMemoById($id);
        }

        return null;
    }

    private function getCreditMemoById(int $id): CreditMemo
    {
        /** @var \Mage_Sales_Model_Order_Creditmemo $creditmemo */
        $creditmemo = \Mage::getModel('sales/order_creditmemo');
        $creditmemo->load($id);

        if (!$creditmemo->getId()) {
            throw new NotFoundHttpException('Credit memo not found');
        }

        return $this->mapToDto($creditmemo);
    }

    private function getCreditMemosForOrder(int $orderId, array $context): ArrayPaginator
    {
        /** @var \Mage_Sales_Model_Order $order */
        $order = \Mage::getModel('sales/order');
        $order->load($orderId);

        if (!$order->getId()) {
            throw new NotFoundHttpException('Order not found');
        }

        // Pagination
        $page = max(1, (int) ($context['filters']['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($context['filters']['itemsPerPage'] ?? 20)));

        /** @var \Mage_Sales_Model_Resource_Order_Creditmemo_Collection $collection */
        $collection = \Mage::getResourceModel('sales/order_creditmemo_collection');
        $collection->addFieldToFilter('order_id', $orderId);
        $collection->setOrder('created_at', 'DESC');

        $creditmemos = [];
        foreach ($collection as $creditmemo) {
            $creditmemos[] = $this->mapToDto($creditmemo);
        }

        $offset = ($page - 1) * $perPage;

        return new ArrayPaginator($creditmemos, $offset, $perPage);
    }

    private function mapToDto(\Mage_Sales_Model_Order_Creditmemo $creditmemo): CreditMemo
    {
        $dto = new CreditMemo();
        $dto->id = (int) $creditmemo->getId();
        $dto->orderId = (int) $creditmemo->getOrderId();
        $dto->incrementId = $creditmemo->getIncrementId();
        $dto->createdAt = $creditmemo->getCreatedAt();

        // Map state: 1=open, 2=refunded, 3=canceled
        $stateMap = [
            1 => 'open',
            2 => 'refunded',
            3 => 'canceled',
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

        // Map items
        $dto->items = [];
        foreach ($creditmemo->getAllItems() as $item) {
            $dto->items[] = $this->mapItemToDto($item);
        }

        // Get first comment if any
        $comments = $creditmemo->getCommentsCollection();
        if ($comments && $comments->getSize() > 0) {
            $firstComment = $comments->getFirstItem();
            $dto->comment = $firstComment->getComment();
        }

        return $dto;
    }

    private function mapItemToDto(\Mage_Sales_Model_Order_Creditmemo_Item $item): CreditMemoItem
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
