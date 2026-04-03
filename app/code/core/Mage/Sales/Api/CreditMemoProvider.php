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

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\ArrayPaginator;
use Maho\ApiPlatform\CrudProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CreditMemoProvider extends CrudProvider
{
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CreditMemo|ArrayPaginator|null
    {
        $this->requireAdminOrApiUser('Credit memo access requires admin or API access');
        $this->resourceClass = $operation->getClass();
        $this->modelAlias = 'sales/order_creditmemo';

        $operationName = $operation->getName();

        if ($operationName === 'orderCreditMemos') {
            $orderId = (int) ($context['args']['orderId'] ?? 0);
            return $this->getCreditMemosForOrder($orderId, $context);
        }

        if ($operation instanceof CollectionOperationInterface) {
            $orderId = (int) ($uriVariables['orderId'] ?? 0);
            return $this->getCreditMemosForOrder($orderId, $context);
        }

        $id = (int) ($uriVariables['id'] ?? 0);
        if ($id) {
            return $this->getCreditMemoById($id);
        }

        return null;
    }

    private function getCreditMemoById(int $id): CreditMemo
    {
        $creditmemo = \Mage::getModel('sales/order_creditmemo');
        $creditmemo->load($id);

        if (!$creditmemo->getId()) {
            throw new NotFoundHttpException('Credit memo not found');
        }

        return CreditMemo::fromModel($creditmemo);
    }

    private function getCreditMemosForOrder(int $orderId, array $context): ArrayPaginator
    {
        $order = \Mage::getModel('sales/order');
        $order->load($orderId);

        if (!$order->getId()) {
            throw new NotFoundHttpException('Order not found');
        }

        ['page' => $page, 'pageSize' => $perPage] = $this->extractPagination($context);

        $collection = \Mage::getResourceModel('sales/order_creditmemo_collection');
        $collection->addFieldToFilter('order_id', $orderId);
        $collection->setOrder('created_at', 'DESC');

        $creditmemos = [];
        foreach ($collection as $creditmemo) {
            $creditmemos[] = CreditMemo::fromModel($creditmemo);
        }

        $offset = ($page - 1) * $perPage;

        return new ArrayPaginator($creditmemos, $offset, $perPage);
    }
}
