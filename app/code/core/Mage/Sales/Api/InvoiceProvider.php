<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

namespace Mage\Sales\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;

class InvoiceProvider extends \Maho\ApiPlatform\Provider
{
    use SalesGridTrait;

    public function __construct(Security $security)
    {
        parent::__construct($security);
    }

    private const WRITE_OPERATIONS = ['order_invoice_create', 'invoice_capture', 'invoice_void', 'invoice_cancel'];

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|Invoice|TraversablePaginator|Response|null
    {
        $operationName = $operation->getName();

        return match (true) {
            // POST operations are handled entirely by InvoiceProcessor; the
            // read pass (triggered because the ops have uri variables) must
            // not resolve anything here.
            in_array($operationName, self::WRITE_OPERATIONS, true) => null,
            str_contains($operationName, 'pdf') => $this->downloadPdf($uriVariables, $operationName),
            $operationName === 'invoice_list' => $this->listAllInvoices($context),
            $operationName === 'invoice_get' => $this->getInvoice((int) ($uriVariables['id'] ?? 0)),
            default => $this->listInvoices($uriVariables, $operationName),
        };
    }

    private function listInvoices(array $uriVariables, string $operationName): array
    {
        $orderId = (int) ($uriVariables['orderId'] ?? 0);
        $order = $this->loadAndAuthorizeOrder($orderId, $operationName);

        $invoices = [];
        $basePath = str_contains($operationName, 'my_')
            ? '/api/rest/v2/customers/me/orders/' . $orderId . '/invoices/'
            : '/api/rest/v2/orders/' . $orderId . '/invoices/';

        $models = iterator_to_array($order->getInvoiceCollection());
        $this->preloadItemsAndComments($models, !$this->isAdmin() && !$this->isApiUser());

        foreach ($models as $invoice) {
            // Reuse the already-loaded order so afterLoad's getOrder() doesn't
            // re-load it per invoice.
            $dto = Invoice::fromModel($invoice->setOrder($order));
            $dto->pdfUrl = $basePath . $invoice->getId() . '/pdf';
            $invoices[] = $dto;
        }

        return $invoices;
    }

    /**
     * @return TraversablePaginator<Invoice>
     */
    private function listAllInvoices(array $context): TraversablePaginator
    {
        $list = $this->loadGridPage(\Mage_Sales_Model_Order_Invoice::class, 'sales/order_invoice_collection', 'sales/invoice_grid', 'billing_name', [
            'open' => \Mage_Sales_Model_Order_Invoice::STATE_OPEN,
            'paid' => \Mage_Sales_Model_Order_Invoice::STATE_PAID,
            'canceled' => \Mage_Sales_Model_Order_Invoice::STATE_CANCELED,
        ], $context);
        $this->preloadItemsAndComments($list['models']);

        $invoices = array_map($this->adminInvoiceDto(...), $list['models']);

        return new TraversablePaginator(new \ArrayIterator($invoices), $list['page'], $list['pageSize'], $list['total']);
    }

    private function getInvoice(int $id): Invoice
    {
        $invoice = \Mage::getService('sales/order_document')->requireInvoice($id);
        $this->assertStoreAllowed($invoice->getStoreId(), $this->requireUser(), 'invoice');

        return $this->adminInvoiceDto($invoice);
    }

    private function adminInvoiceDto(\Mage_Sales_Model_Order_Invoice $invoice): Invoice
    {
        $dto = Invoice::fromModel($invoice);
        $dto->pdfUrl = '/api/rest/v2/orders/' . $invoice->getOrderId() . '/invoices/' . $invoice->getId() . '/pdf';
        return $dto;
    }

    /**
     * Batch-load items and comments for a set of invoices (2 queries instead of
     * 2 per invoice); Invoice::afterLoad() consumes the preloaded sets.
     *
     * @param array<\Mage_Sales_Model_Order_Invoice> $invoices
     */
    private function preloadItemsAndComments(array $invoices, bool $visibleCommentsOnly = false): void
    {
        if ($invoices === []) {
            return;
        }
        $invoiceIds = array_map(static fn($invoice): int => (int) $invoice->getId(), $invoices);

        $itemsByInvoice = [];
        $itemCollection = \Mage::getResourceModel('sales/order_invoice_item_collection')
            ->addFieldToFilter('parent_id', ['in' => $invoiceIds]);
        foreach ($itemCollection as $item) {
            $itemsByInvoice[(int) $item->getParentId()][] = $item;
        }

        $commentsByInvoice = [];
        $commentCollection = \Mage::getResourceModel('sales/order_invoice_comment_collection')
            ->addFieldToFilter('parent_id', ['in' => $invoiceIds]);
        if ($visibleCommentsOnly) {
            $commentCollection->addVisibleOnFrontFilter();
        }
        foreach ($commentCollection as $comment) {
            $commentsByInvoice[(int) $comment->getParentId()][] = $comment;
        }

        foreach ($invoices as $invoice) {
            $id = (int) $invoice->getId();
            $invoice->setData('_preloaded_items', $itemsByInvoice[$id] ?? []);
            $invoice->setData('_preloaded_comments', $commentsByInvoice[$id] ?? []);
        }
    }

    private function downloadPdf(array $uriVariables, string $operationName): Response
    {
        $order = $this->loadAndAuthorizeOrder((int) ($uriVariables['orderId'] ?? 0), $operationName);
        $invoice = \Mage::getService('sales/order_document')->requireInvoice((int) ($uriVariables['id'] ?? 0), (int) $order->getId());

        $pdf = \Mage::getModel('sales/order_pdf_invoice')->getPdf([$invoice]);
        if (empty($pdf)) {
            throw new \RuntimeException('Failed to generate invoice PDF');
        }

        $filename = 'invoice_' . $invoice->getIncrementId() . '.pdf';

        return new Response($pdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => strlen($pdf),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    /**
     * Load the order $orderId. A customer reads only the orders that the storefront shows them, an admin or an API
     * user the orders of the stores of the token. The operation's `security:` expression has already checked the
     * permission.
     */
    private function loadAndAuthorizeOrder(int $orderId, string $operationName): \Mage_Sales_Model_Order
    {
        $service = \Mage::getService('sales/order');
        if (str_contains($operationName, 'my_') || (!$this->isAdmin() && !$this->isApiUser())) {
            return $service->requireVisibleToCustomer($orderId, $this->getAuthenticatedCustomerId());
        }
        $order = $service->getById($orderId);
        $this->assertStoreAllowed($order->getStoreId(), $this->requireUser(), 'order');

        return $order;
    }
}
