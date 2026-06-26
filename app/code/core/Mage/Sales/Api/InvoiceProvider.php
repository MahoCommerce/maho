<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

namespace Mage\Sales\Api;

use ApiPlatform\Metadata\Operation;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InvoiceProvider extends \Maho\ApiPlatform\Provider
{
    public function __construct(Security $security)
    {
        parent::__construct($security);
    }

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|Invoice|Response
    {
        $operationName = $operation->getName();

        return match (true) {
            str_contains($operationName, 'pdf') => $this->downloadPdf($uriVariables, $operationName),
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

        foreach ($order->getInvoiceCollection() as $invoice) {
            $dto = Invoice::fromModel($invoice);
            $dto->pdfUrl = $basePath . $invoice->getId() . '/pdf';
            $invoices[] = $dto;
        }

        return $invoices;
    }

    private function downloadPdf(array $uriVariables, string $operationName): Response
    {
        $orderId = (int) ($uriVariables['orderId'] ?? 0);
        $invoiceId = (int) ($uriVariables['id'] ?? 0);

        $this->loadAndAuthorizeOrder($orderId, $operationName);

        $invoice = \Mage::getModel('sales/order_invoice')->load($invoiceId);
        if (!$invoice->getId() || $invoice->getOrderId() != $orderId) {
            throw new NotFoundHttpException('Invoice not found');
        }

        $pdf = \Mage::getModel('sales/order_pdf_invoice')->getPdf([$invoice]);
        if (empty($pdf)) {
            throw new HttpException(500, 'Failed to generate invoice PDF');
        }

        $filename = 'invoice_' . $invoice->getIncrementId() . '.pdf';

        return new Response($pdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => strlen($pdf),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    private function loadAndAuthorizeOrder(int $orderId, string $operationName): \Mage_Sales_Model_Order
    {
        $order = \Mage::getModel('sales/order')->load($orderId);
        if (!$order->getId()) {
            throw new NotFoundHttpException('Order not found');
        }

        if (str_contains($operationName, 'my_')) {
            $customerId = $this->getAuthenticatedCustomerId();
            if (!$customerId || (int) $order->getCustomerId() !== $customerId) {
                throw new NotFoundHttpException('Order not found');
            }
        } else {
            // Admins and API users (permission already enforced upstream by the
            // operation's `security:` expression) may access any order's
            // invoices, matching OrderProvider::canAccessOrder(). Customers are
            // limited to their own orders.
            if (!$this->isAdmin() && !$this->isApiUser()) {
                $customerId = $order->getCustomerId();
                if ($customerId) {
                    $this->authorizeCustomerAccess((int) $customerId, 'You can only access your own order invoices');
                } else {
                    throw new AccessDeniedHttpException('Access denied');
                }
            }
        }

        return $order;
    }

}
