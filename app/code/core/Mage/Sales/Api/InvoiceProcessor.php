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
use Maho\ApiPlatform\Security\AdminAcl;

final class InvoiceProcessor extends \Maho\ApiPlatform\Processor
{
    use \Maho\ApiPlatform\Trait\OrderItemsTrait;

    /** The fields of a comment body. */
    private const array COMMENT_FIELDS = ['comment', 'notifyCustomer', 'visibleOnFront'];

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Invoice
    {
        $operationName = $operation->getName();

        $this->normalizeGraphQlInput($context);

        return match ($operationName) {
            'invoice_capture' => $this->executeLifecycleAction('captureInvoice', $uriVariables),
            'invoice_void' => $this->executeLifecycleAction('voidInvoice', $uriVariables),
            'invoice_cancel' => $this->executeLifecycleAction('cancelInvoice', $uriVariables),
            'invoice_add_comment' => $this->addComment($uriVariables, $context),
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
        if ($captureCase !== null && $captureCase !== \Mage_Sales_Model_Order_Invoice::NOT_CAPTURE) {
            AdminAcl::checkPath('sales/order/actions/capture');
        }

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
     * Add a comment to an existing invoice. Email the comment to the customer when notifyCustomer is true.
     */
    private function addComment(array $uriVariables, array $context): Invoice
    {
        $service = \Mage::getService('sales/order_document');
        $invoice = $service->requireInvoice((int) ($uriVariables['id'] ?? 0));
        $this->assertStoreAllowed($invoice->getStoreId(), $this->requireUser(), 'invoice');

        $body = self::withoutUriVariables($context['args']['input'] ?? [], $uriVariables);
        self::rejectUnknownFields($body, self::COMMENT_FIELDS);
        $input = RequestInput::of($body);
        $invoice = $service->addComment(
            $invoice,
            $input->string('comment') ?? '',
            $input->bool('notifyCustomer') ?? false,
            $input->bool('visibleOnFront') ?? false,
        );

        return Invoice::fromModel(\Mage::getModel('sales/order_invoice')->load($invoice->getId()));
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
