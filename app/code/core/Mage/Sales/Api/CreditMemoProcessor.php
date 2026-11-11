<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

namespace Mage\Sales\Api;

use ApiPlatform\Metadata\Operation;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Credit Memo State Processor - Handles credit memo creation for API Platform.
 */
final class CreditMemoProcessor extends \Maho\ApiPlatform\Processor
{
    use \Maho\ApiPlatform\Trait\OrderItemsTrait;

    private \Mage_Sales_Service_Order $orderService;

    public function __construct(Security $security)
    {
        parent::__construct($security);
        $this->orderService = \Mage::getService('sales/order');
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CreditMemo
    {
        $this->normalizeGraphQlInput($context);

        return $this->createCreditMemo($uriVariables, $context);
    }

    private function createCreditMemo(array $uriVariables, array $context): CreditMemo
    {
        $args = $context['args']['input'] ?? [];
        $orderId = (int) ($uriVariables['orderId'] ?? $args['orderId'] ?? 0);
        if (!$orderId) {
            throw new \Mage_Core_Exception_Input()->addError('order_id', 'Order ID is required');
        }

        $items = $args['items'] ?? null;
        if ($items !== null && !is_array($items)) {
            throw new BadRequestHttpException('Items must be a list of {orderItemId, qty} objects');
        }

        $order = $this->orderService->requireById($orderId);
        $this->assertStoreAllowed($order->getStoreId(), $this->requireUser(), 'order');

        // Adjustments go through $data so prepareCreditmemo() applies them before
        // its single collectTotals() pass. Setting them afterwards and collecting
        // again would double every total (the creditmemo collectors accumulate
        // into grand total and never reset), over-refunding the order.
        $data = [];
        foreach (['adjustmentPositive' => 'adjustment_positive', 'adjustmentNegative' => 'adjustment_negative', 'shippingAmount' => 'shipping_amount'] as $field => $key) {
            if (isset($args[$field])) {
                $data[$key] = (float) $args[$field];
            }
        }

        // Without a backToStock flag on any item, the store setting decides the return to stock.
        $backToStock = null;
        if ($items !== null) {
            $data['qtys'] = [];
            foreach ($items as $itemData) {
                $entry = $this->parseOrderItemEntry($itemData);
                $orderItemId = $entry['orderItemId'];
                $data['qtys'][$orderItemId] = $entry['qty'];
                if (array_key_exists('backToStock', $itemData)) {
                    $backToStock ??= [];
                    if ($itemData['backToStock']) {
                        $backToStock[$orderItemId] = true;
                    }
                }
            }
        }

        $invoice = null;
        if (isset($args['invoiceId'])) {
            $invoice = \Mage::getModel('sales/order_invoice')->load((int) $args['invoiceId']);
            if (!$invoice->getId() || (int) $invoice->getOrderId() !== (int) $order->getId()) {
                throw new \Mage_Core_Exception_Input()->addError('invoice_id', \Mage::helper('sales')->__('Invoice not found'));
            }
            $invoice->setOrder($order);
        }

        $creditmemo = $this->orderService->refund(
            $order,
            $data,
            $args['comment'] ?? null,
            (bool) ($args['offlineRefund'] ?? true),
            $backToStock,
            invoice: $invoice,
        );

        return CreditMemo::fromModel($creditmemo);
    }
}
