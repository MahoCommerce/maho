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
        $data = ['qtys' => []];
        foreach (['adjustmentPositive' => 'adjustment_positive', 'adjustmentNegative' => 'adjustment_negative', 'shippingAmount' => 'shipping_amount'] as $field => $key) {
            if (isset($args[$field])) {
                $data[$key] = (float) $args[$field];
            }
        }

        // An absent items key refunds every refundable item. A present items key
        // refunds exactly the listed quantities, so an empty list refunds no item.
        // Without a backToStock flag on any item, the store setting decides the return to stock.
        $backToStock = null;
        foreach ($items ?? [] as $itemData) {
            $entry = $this->parseOrderItemEntry($itemData, $order, allowZeroQty: true);
            $orderItemId = (int) $entry['item']->getId();
            if ($entry['qty'] > 0) {
                $data['qtys'][$orderItemId] = $entry['qty'];
            }
            if (array_key_exists('backToStock', $itemData)) {
                $backToStock ??= [];
                if ($itemData['backToStock']) {
                    $backToStock[$orderItemId] = true;
                }
            }
        }

        // An empty qty map makes prepareCreditmemo() refund every item, so use a
        // key that matches no order item.
        if ($items !== null && $data['qtys'] === []) {
            $data['qtys'][0] = 0.0;
            // The shipping collector refunds the order's whole remaining shipping
            // unless an amount is given, so a memo that refunds no item would still
            // carry the delivery charge.
            $data['shipping_amount'] ??= 0.0;
        }

        $creditmemo = $this->orderService->refund(
            $order,
            $data,
            $args['comment'] ?? null,
            (bool) ($args['offlineRefund'] ?? true),
            $backToStock,
        );

        return CreditMemo::fromModel($creditmemo);
    }
}
