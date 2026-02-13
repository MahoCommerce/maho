<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\Controller;

use Maho\ApiPlatform\Trait\AuthenticationTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Invoice Controller
 * Handles invoice PDF generation and download
 */
class InvoiceController extends AbstractController
{
    use AuthenticationTrait;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    /**
     * Download invoice PDF for an order
     *
     * Customers can download invoices for their own orders.
     * Admins can download any invoice.
     */
    #[Route('/api/orders/{orderId}/invoices/{invoiceId}/pdf', name: 'api_invoice_pdf', methods: ['GET'])]
    public function downloadInvoicePdf(int $orderId, int $invoiceId): Response
    {
        // Load order
        $order = \Mage::getModel('sales/order')->load($orderId);
        if (!$order->getId()) {
            throw new NotFoundHttpException('Order not found');
        }

        // Verify customer access (unless admin)
        $customerId = $order->getCustomerId();
        if ($customerId) {
            $this->authorizeCustomerAccess((int) $customerId, 'You can only access your own order invoices');
        } elseif (!$this->isAdmin()) {
            // Guest orders - customers can't access, only admin
            throw new AccessDeniedHttpException('Access denied');
        }

        // Load invoice
        $invoice = \Mage::getModel('sales/order_invoice')->load($invoiceId);
        if (!$invoice->getId() || $invoice->getOrderId() != $orderId) {
            throw new NotFoundHttpException('Invoice not found');
        }

        // Generate PDF
        $pdf = \Mage::getModel('sales/order_pdf_invoice')->getPdf([$invoice]);

        if (empty($pdf)) {
            return new JsonResponse([
                'error' => 'pdf_generation_failed',
                'message' => 'Failed to generate invoice PDF',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
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
     * Get list of invoices for an order
     */
    #[Route('/api/orders/{orderId}/invoices', name: 'api_order_invoices', methods: ['GET'])]
    public function getOrderInvoices(int $orderId): JsonResponse
    {
        // Load order
        $order = \Mage::getModel('sales/order')->load($orderId);
        if (!$order->getId()) {
            throw new NotFoundHttpException('Order not found');
        }

        // Verify customer access (unless admin)
        $customerId = $order->getCustomerId();
        if ($customerId) {
            $this->authorizeCustomerAccess((int) $customerId, 'You can only access your own order invoices');
        } elseif (!$this->isAdmin()) {
            throw new AccessDeniedHttpException('Access denied');
        }

        $invoices = [];
        foreach ($order->getInvoiceCollection() as $invoice) {
            $invoices[] = [
                'id' => (int) $invoice->getId(),
                'incrementId' => $invoice->getIncrementId(),
                'orderId' => (int) $invoice->getOrderId(),
                'grandTotal' => (float) $invoice->getGrandTotal(),
                'state' => (int) $invoice->getState(),
                'stateName' => $this->getInvoiceStateName((int) $invoice->getState()),
                'createdAt' => $invoice->getCreatedAt(),
                'pdfUrl' => '/api/orders/' . $orderId . '/invoices/' . $invoice->getId() . '/pdf',
            ];
        }

        return new JsonResponse([
            'invoices' => $invoices,
            'count' => count($invoices),
        ]);
    }

    /**
     * Get invoice state name
     */
    private function getInvoiceStateName(int $state): string
    {
        return match ($state) {
            \Mage_Sales_Model_Order_Invoice::STATE_OPEN => 'open',
            \Mage_Sales_Model_Order_Invoice::STATE_PAID => 'paid',
            \Mage_Sales_Model_Order_Invoice::STATE_CANCELED => 'canceled',
            default => 'unknown',
        };
    }

    /**
     * Download invoice PDF for customer's own orders using "me" shorthand
     */
    #[Route('/api/customers/me/orders/{orderId}/invoices/{invoiceId}/pdf', name: 'api_my_invoice_pdf', methods: ['GET'])]
    public function downloadMyInvoicePdf(int $orderId, int $invoiceId): Response
    {
        // Require authentication
        $customerId = $this->requireAuthentication();

        // Load order
        $order = \Mage::getModel('sales/order')->load($orderId);
        if (!$order->getId()) {
            throw new NotFoundHttpException('Order not found');
        }

        // Verify this order belongs to the authenticated customer
        if ((int) $order->getCustomerId() !== $customerId) {
            throw new NotFoundHttpException('Order not found');
        }

        // Load invoice
        $invoice = \Mage::getModel('sales/order_invoice')->load($invoiceId);
        if (!$invoice->getId() || $invoice->getOrderId() != $orderId) {
            throw new NotFoundHttpException('Invoice not found');
        }

        // Generate PDF
        $pdf = \Mage::getModel('sales/order_pdf_invoice')->getPdf([$invoice]);

        if (empty($pdf)) {
            return new JsonResponse([
                'error' => 'pdf_generation_failed',
                'message' => 'Failed to generate invoice PDF',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
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
     * Get list of invoices for customer's own order using "me" shorthand
     */
    #[Route('/api/customers/me/orders/{orderId}/invoices', name: 'api_my_order_invoices', methods: ['GET'])]
    public function getMyOrderInvoices(int $orderId): JsonResponse
    {
        // Require authentication
        $customerId = $this->requireAuthentication();

        // Load order
        $order = \Mage::getModel('sales/order')->load($orderId);
        if (!$order->getId()) {
            throw new NotFoundHttpException('Order not found');
        }

        // Verify this order belongs to the authenticated customer
        if ((int) $order->getCustomerId() !== $customerId) {
            throw new NotFoundHttpException('Order not found');
        }

        $invoices = [];
        foreach ($order->getInvoiceCollection() as $invoice) {
            $invoices[] = [
                'id' => (int) $invoice->getId(),
                'incrementId' => $invoice->getIncrementId(),
                'orderId' => (int) $invoice->getOrderId(),
                'grandTotal' => (float) $invoice->getGrandTotal(),
                'state' => (int) $invoice->getState(),
                'stateName' => $this->getInvoiceStateName((int) $invoice->getState()),
                'createdAt' => $invoice->getCreatedAt(),
                'pdfUrl' => '/api/customers/me/orders/' . $orderId . '/invoices/' . $invoice->getId() . '/pdf',
            ];
        }

        return new JsonResponse([
            'invoices' => $invoices,
            'count' => count($invoices),
        ]);
    }
}
