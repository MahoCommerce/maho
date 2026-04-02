<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

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
            ? '/api/customers/me/orders/' . $orderId . '/invoices/'
            : '/api/orders/' . $orderId . '/invoices/';

        foreach ($order->getInvoiceCollection() as $invoice) {
            $dto = new Invoice();
            $dto->id = (int) $invoice->getId();
            $dto->incrementId = $invoice->getIncrementId();
            $dto->orderId = (int) $invoice->getOrderId();
            $dto->grandTotal = (float) $invoice->getGrandTotal();
            $dto->state = (int) $invoice->getState();
            $dto->stateName = $this->getStateName((int) $invoice->getState());
            $dto->createdAt = $invoice->getCreatedAt();
            $dto->pdfUrl = $basePath . $invoice->getId() . '/pdf';
            $invoices[] = $dto;
        }

        return $invoices;
    }

    private function downloadPdf(array $uriVariables, string $operationName): Response
    {
        $orderId = (int) ($uriVariables['orderId'] ?? 0);
        $invoiceId = (int) ($uriVariables['id'] ?? 0);

        $order = $this->loadAndAuthorizeOrder($orderId, $operationName);

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
            $customerId = $order->getCustomerId();
            if ($customerId) {
                $this->authorizeCustomerAccess((int) $customerId, 'You can only access your own order invoices');
            } elseif (!$this->isAdmin()) {
                throw new AccessDeniedHttpException('Access denied');
            }
        }

        return $order;
    }

    private function getStateName(int $state): string
    {
        return match ($state) {
            \Mage_Sales_Model_Order_Invoice::STATE_OPEN => 'open',
            \Mage_Sales_Model_Order_Invoice::STATE_PAID => 'paid',
            \Mage_Sales_Model_Order_Invoice::STATE_CANCELED => 'canceled',
            default => 'unknown',
        };
    }
}
