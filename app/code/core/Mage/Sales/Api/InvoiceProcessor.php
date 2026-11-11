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

final class InvoiceProcessor extends \Maho\ApiPlatform\Processor
{
    use \Maho\ApiPlatform\Trait\OrderItemsTrait;

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Invoice
    {
        $operationName = $operation->getName();

        $this->normalizeGraphQlInput($context);

        return match ($operationName) {
            'invoice_capture' => $this->executeLifecycleAction('captureInvoice', $uriVariables),
            'invoice_void' => $this->executeLifecycleAction('voidInvoice', $uriVariables),
            'invoice_cancel' => $this->executeLifecycleAction('cancelInvoice', $uriVariables),
            default => $this->createInvoice($uriVariables, $context),
        };
    }

    private function createInvoice(array $uriVariables, array $context): Invoice
    {
        $args = $context['args']['input'] ?? [];
        $captureCase = $args['capture'] ?? null;
        if ($captureCase !== null && !is_string($captureCase)) {
            throw new BadRequestHttpException('capture must be a string');
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

        $service = \Mage::getService('sales/order_document');
        $order = \Mage::getService('sales/order')->getById((int) ($uriVariables['orderId'] ?? 0));
        $this->assertStoreAllowed($order->getStoreId(), $this->requireUser(), 'order');

        $qtys = [];
        foreach ($items ?? [] as $itemData) {
            $entry = $this->parseOrderItemEntry($itemData);
            $qtys[$entry['orderItemId']] = $entry['qty'];
        }

        return Invoice::fromModel($service->invoice($order, $qtys, $captureCase, $comment, (bool) $notifyCustomer));
    }

    /**
     * @param 'captureInvoice'|'voidInvoice'|'cancelInvoice' $method
     */
    private function executeLifecycleAction(string $method, array $uriVariables): Invoice
    {
        $service = \Mage::getService('sales/order_document');
        $invoice = $service->requireInvoice((int) ($uriVariables['id'] ?? 0));
        $this->assertStoreAllowed($invoice->getStoreId(), $this->requireUser(), 'invoice');

        return Invoice::fromModel($service->{$method}($invoice));
    }
}
