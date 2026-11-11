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
use Maho\ApiPlatform\Http\RequestInput;

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
        $input = RequestInput::of($context['args']['input'] ?? []);
        $captureCase = $input->string('capture');
        $items = $input->rows('items');
        $comment = $input->string('comment');
        $notifyCustomer = $input->bool('notifyCustomer') ?? false;

        $service = \Mage::getService('sales/order_document');
        $order = \Mage::getService('sales/order')->getById((int) ($uriVariables['orderId'] ?? 0));
        $this->assertStoreAllowed($order->getStoreId(), $this->requireUser(), 'order');

        $qtys = [];
        foreach ($items ?? [] as $item) {
            $entry = $this->parseOrderItemEntry($item);
            $qtys[$entry['orderItemId']] = $entry['qty'];
        }

        return Invoice::fromModel($service->invoice($order, $qtys, $captureCase, $comment, $notifyCustomer));
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
