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
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class InvoiceProcessor extends \Maho\ApiPlatform\Processor
{
    private const CAPTURE_CASES = [
        \Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE,
        \Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE,
        \Mage_Sales_Model_Order_Invoice::NOT_CAPTURE,
    ];

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Invoice
    {
        $this->requireAdminOrApiUser('Invoice management requires admin or API access');
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
        $this->requireApiPermission('invoices/create');

        $orderId = (int) ($uriVariables['orderId'] ?? 0);
        if (!$orderId) {
            throw new BadRequestHttpException('Order ID is required');
        }

        $args = $context['args']['input'] ?? [];
        $captureCase = $args['capture'] ?? null;
        if ($captureCase !== null && !in_array($captureCase, self::CAPTURE_CASES, true)) {
            throw new BadRequestHttpException('Invalid capture mode; expected one of: ' . implode(', ', self::CAPTURE_CASES));
        }

        $order = \Mage::getModel('sales/order')->load($orderId);
        if (!$order->getId()) {
            throw new NotFoundHttpException('Order not found');
        }

        // Serialize with the order's other state transitions so two concurrent
        // requests can't both pass canInvoice() and both register an invoice,
        // double-invoicing the order. Shared per-order lock name, see
        // OrderService::withOrderLock().
        $write = \Mage::getSingleton('core/resource')->getConnection('core_write');
        $lockName = 'maho_order_mutate:' . (int) $order->getId();
        if (!$write->getLock($lockName, 5)) {
            throw new ConflictHttpException('Another operation is already in progress for this order');
        }

        try {
            // Re-read under the lock so canInvoice() reflects the live state.
            $order->load($orderId);
            return $this->buildAndRegisterInvoice(
                $order,
                $args['items'] ?? null,
                $captureCase,
                $args['comment'] ?? null,
                (bool) ($args['notifyCustomer'] ?? false),
            );
        } finally {
            $write->releaseLock($lockName);
        }
    }

    private function buildAndRegisterInvoice(
        \Mage_Sales_Model_Order $order,
        ?array $items,
        ?string $captureCase,
        ?string $comment,
        bool $notifyCustomer,
    ): Invoice {
        if (!$order->canInvoice()) {
            throw new BadRequestHttpException('Order cannot be invoiced (already fully invoiced or not in an invoiceable state)');
        }

        $qtyMap = [];
        if ($items !== null && count($items) > 0) {
            foreach ($items as $itemData) {
                $orderItemId = (int) ($itemData['orderItemId'] ?? 0);
                $qty = (float) ($itemData['qty'] ?? 0);

                if ($orderItemId <= 0) {
                    throw new BadRequestHttpException('Each item must have a valid orderItemId');
                }
                if ($qty <= 0) {
                    throw new BadRequestHttpException('Each item must have qty > 0');
                }

                $qtyMap[$orderItemId] = $qty;
            }
        }

        $invoice = \Mage::getModel('sales/service_order', $order)->prepareInvoice($qtyMap);

        if (!$invoice->getTotalQty()) {
            throw new BadRequestHttpException('Cannot create invoice: no items to invoice');
        }

        if ($captureCase === \Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE && !$invoice->canCapture()) {
            throw new BadRequestHttpException('The order\'s payment method does not support online capture');
        }
        if ($captureCase !== null) {
            $invoice->setRequestedCaptureCase($captureCase);
        }

        if ($comment) {
            $invoice->addComment($comment, $notifyCustomer);
        }

        try {
            $invoice->register();
        } catch (\Mage_Core_Exception $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        $invoice->getOrder()->setIsInProcess(true);

        \Mage::getModel('core/resource_transaction')
            ->addObject($invoice)
            ->addObject($invoice->getOrder())
            ->save();

        if ($notifyCustomer) {
            $invoice->sendEmail(true, $comment ?? '');
        }

        return Invoice::fromModel($invoice);
    }

    /**
     * @param 'capture'|'void'|'cancel' $action
     */
    private function executeLifecycleAction(string $action, array $uriVariables): Invoice
    {
        $this->requireApiPermission('invoices/write');

        $invoiceId = (int) ($uriVariables['id'] ?? 0);
        if (!$invoiceId) {
            throw new BadRequestHttpException('Invoice ID is required');
        }

        $invoice = \Mage::getModel('sales/order_invoice')->load($invoiceId);
        if (!$invoice->getId()) {
            throw new NotFoundHttpException('Invoice not found');
        }

        // Same per-order critical section as invoice/shipment/refund creation:
        // capture/void/cancel mutate order totals and must not interleave.
        $write = \Mage::getSingleton('core/resource')->getConnection('core_write');
        $lockName = 'maho_order_mutate:' . (int) $invoice->getOrderId();
        if (!$write->getLock($lockName, 5)) {
            throw new ConflictHttpException('Another operation is already in progress for this order');
        }

        try {
            // Re-read under the lock so the can*() checks reflect the live state.
            $invoice->load($invoiceId);

            $allowed = match ($action) {
                'capture' => $invoice->canCapture(),
                'void' => $invoice->canVoid(),
                'cancel' => $invoice->canCancel(),
            };
            if (!$allowed) {
                $past = match ($action) {
                    'capture' => 'captured',
                    'void' => 'voided',
                    'cancel' => 'canceled',
                };
                throw new BadRequestHttpException("The invoice cannot be {$past} in its current state");
            }

            try {
                $invoice->{$action}();
            } catch (\Mage_Core_Exception $e) {
                throw new BadRequestHttpException($e->getMessage());
            }

            $invoice->getOrder()->setIsInProcess(true);

            \Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder())
                ->save();
        } finally {
            $write->releaseLock($lockName);
        }

        return Invoice::fromModel($invoice);
    }
}
