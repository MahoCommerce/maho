<?php

/**
 * Invoice State Processor - Handles invoice creation and lifecycle for API Platform.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

namespace Mage\Sales\Api;

use ApiPlatform\Metadata\Operation;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class InvoiceProcessor extends \Maho\ApiPlatform\Processor
{
    use \Maho\ApiPlatform\Trait\OrderItemsTrait;

    private const CAPTURE_CASES = [
        \Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE,
        \Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE,
        \Mage_Sales_Model_Order_Invoice::NOT_CAPTURE,
    ];

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Invoice
    {
        $operationName = $operation->getName();

        $this->normalizeGraphQlInput($context);

        return match ($operationName) {
            'invoice_capture' => $this->executeLifecycleAction('capture', $uriVariables),
            'invoice_void' => $this->executeLifecycleAction('void', $uriVariables),
            'invoice_cancel' => $this->executeLifecycleAction('cancel', $uriVariables),
            default => $this->createInvoice($uriVariables, $context),
        };
    }

    private function createInvoice(array $uriVariables, array $context): Invoice
    {
        $orderId = (int) ($uriVariables['orderId'] ?? 0);
        if (!$orderId) {
            throw new BadRequestHttpException('Order ID is required');
        }

        $args = $context['args']['input'] ?? [];
        $captureCase = $args['capture'] ?? null;
        if ($captureCase !== null && !in_array($captureCase, self::CAPTURE_CASES, true)) {
            throw new BadRequestHttpException('Invalid capture mode; expected one of: ' . implode(', ', self::CAPTURE_CASES));
        }

        $items = $args['items'] ?? null;
        if ($items !== null && !is_array($items)) {
            throw new BadRequestHttpException('Items must be a list of {orderItemId, qty} objects');
        }

        $comment = $args['comment'] ?? null;
        if ($comment !== null && !is_string($comment)) {
            throw new BadRequestHttpException('Comment must be a string');
        }

        $notifyCustomer = $args['notifyCustomer'] ?? false;
        if (!is_scalar($notifyCustomer)) {
            throw new BadRequestHttpException('notifyCustomer must be a boolean');
        }

        $order = \Mage::getModel('sales/order')->load($orderId);
        if (!$order->getId()) {
            throw new NotFoundHttpException('Order not found');
        }

        $this->assertStoreAllowed($order->getStoreId(), $this->requireUser(), 'order');

        $qtys = [];
        foreach ($items ?? [] as $itemData) {
            $entry = $this->parseOrderItemEntry($itemData, $order);
            $qtys[(int) $entry['item']->getId()] = $entry['qty'];
        }

        $invoice = \Mage::getService('sales/order')->invoice($order, $qtys, $captureCase, $comment, (bool) $notifyCustomer);

        return Invoice::fromModel($invoice);
    }

    /**
     * @param 'capture'|'void'|'cancel' $action
     */
    private function executeLifecycleAction(string $action, array $uriVariables): Invoice
    {
        $invoiceId = (int) ($uriVariables['id'] ?? 0);
        if (!$invoiceId) {
            throw new BadRequestHttpException('Invoice ID is required');
        }

        $invoice = \Mage::getModel('sales/order_invoice')->load($invoiceId);
        if (!$invoice->getId()) {
            throw new NotFoundHttpException('Invoice not found');
        }

        $this->assertStoreAllowed($invoice->getStoreId(), $this->requireUser(), 'invoice');

        $service = \Mage::getService('sales/order');
        $invoice = match ($action) {
            'capture' => $service->captureInvoice($invoice),
            'void' => $service->voidInvoice($invoice),
            'cancel' => $service->cancelInvoice($invoice),
        };

        return Invoice::fromModel($invoice);
    }
}
