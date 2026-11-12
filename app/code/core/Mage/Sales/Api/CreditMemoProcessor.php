<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

namespace Mage\Sales\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Http\RequestInput;
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
        $input = RequestInput::of($args);
        $orderId = (int) ($uriVariables['orderId'] ?? $input->int('orderId') ?? 0);
        if (!$orderId) {
            throw new \Mage_Core_Exception_Input()->addError('order_id', 'Order ID is required');
        }

        $items = $input->rows('items');

        $order = $this->orderService->getById($orderId);
        $this->assertStoreAllowed($order->getStoreId(), $this->requireUser(), 'order');

        // Adjustments go through $data so prepareCreditmemo() applies them before
        // its single collectTotals() pass. Setting them afterwards and collecting
        // again would double every total (the creditmemo collectors accumulate
        // into grand total and never reset), over-refunding the order.
        $data = [];
        foreach (['adjustmentPositive' => 'adjustment_positive', 'adjustmentNegative' => 'adjustment_negative', 'shippingAmount' => 'shipping_amount'] as $field => $key) {
            $amount = $input->float($field);
            if ($amount !== null) {
                $data[$key] = $amount;
            }
        }

        // Without a backToStock flag on any item, the store setting decides the return to stock.
        $backToStock = null;
        if ($items !== null) {
            $data['qtys'] = [];
            foreach ($items as $item) {
                $entry = $this->parseOrderItemEntry($item);
                $orderItemId = $entry['orderItemId'];
                $data['qtys'][$orderItemId] = $entry['qty'];
                $flag = $item->bool('backToStock');
                if ($flag !== null) {
                    $backToStock ??= [];
                    if ($flag) {
                        $backToStock[$orderItemId] = true;
                    }
                }
            }
        }

        // refund() refuses an invoice that does not exist or is not an invoice of the order
        $invoiceId = $input->int('invoiceId');
        $invoice = $invoiceId !== null ? \Mage::getModel('sales/order_invoice')->load($invoiceId) : null;

        $creditmemo = \Mage::getService('sales/order_document')->refund(
            $order,
            $data,
            $input->string('comment'),
            $input->bool('offlineRefund') ?? true,
            $backToStock,
            invoice: $invoice,
        );

        return CreditMemo::fromModel($creditmemo);
    }
}
