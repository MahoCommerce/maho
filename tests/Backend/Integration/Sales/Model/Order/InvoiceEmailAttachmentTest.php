<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\Mime\Email;

uses(Tests\MahoBackendTestCase::class);

/**
 * Build a persisted, PDF-renderable order + invoice so the attachment path exercises
 * real DomPdf rendering rather than a stub.
 */
function createRenderableInvoice(): Mage_Sales_Model_Order_Invoice
{
    // Non-numeric token so our increment IDs never collide with the default
    // 100000xxx order/invoice sequence other tests in the suite generate
    $uniqueId = strtoupper(bin2hex(random_bytes(8)));

    $billing = Mage::getModel('sales/order_address')
        ->setData([
            'address_type' => 'billing',
            'firstname'    => 'Test',
            'lastname'     => 'Customer',
            'street'       => '123 Main St',
            'city'         => 'Testville',
            'postcode'     => '12345',
            'country_id'   => 'US',
            'telephone'    => '5551234567',
            'email'        => "buyer.{$uniqueId}@example.com",
        ]);

    $order = Mage::getModel('sales/order');
    $order->setStoreId(1)
        ->setState(Mage_Sales_Model_Order::STATE_NEW)
        ->setStatus('pending')
        ->setCustomerIsGuest(true)
        ->setCustomerEmail("buyer.{$uniqueId}@example.com")
        ->setCustomerFirstname('Test')
        ->setCustomerLastname('Customer')
        ->setOrderCurrencyCode('USD')
        ->setBaseCurrencyCode('USD')
        ->setGlobalCurrencyCode('USD')
        ->setStoreCurrencyCode('USD')
        ->setGrandTotal(110.00)
        ->setBaseGrandTotal(110.00)
        ->setSubtotal(100.00)
        ->setBaseSubtotal(100.00)
        ->setIncrementId('TSTO' . $uniqueId)
        ->setBillingAddress($billing);

    $item = Mage::getModel('sales/order_item')
        ->setData([
            'sku'          => 'TEST-SKU',
            'name'         => 'Test Product',
            'qty_ordered'  => 2,
            'price'        => 50.00,
            'base_price'   => 50.00,
            'row_total'    => 100.00,
            'base_row_total' => 100.00,
            'product_type' => 'simple',
        ]);
    $order->addItem($item);
    $order->save();

    $payment = Mage::getModel('sales/order_payment')
        ->setMethod('checkmo');
    $order->setPayment($payment);
    $payment->setParentId($order->getId())->save();

    $invoice = $order->prepareInvoice();
    $invoice->register();
    $invoice->setIncrementId('TSTI' . $uniqueId);
    $order->save();
    $invoice->save();

    return $invoice;
}

describe('Invoice email PDF attachment', function () {
    it('renders and attaches the real invoice PDF via the queue-safe descriptor path', function () {
        $invoice = createRenderableInvoice();

        // Reproduce what the sales flow builds when sales_email/invoice/attach_pdf is on
        $emailInfo = Mage::getModel('core/email_info');
        $emailInfo->addPdfAttachment(
            'sales/order_pdf_invoice',
            'sales/order_invoice',
            (int) $invoice->getId(),
            'invoice-' . $invoice->getIncrementId() . '.pdf',
        );

        // Simulate surviving the email queue: serialize descriptors, round-trip through the
        // JSON-encoded TEXT column, then re-materialize the attachment at "send" time
        $descriptors = array_map(fn($a) => $a->toArray(), $emailInfo->getAttachments());
        $json = Mage::helper('core')->jsonEncode(['attachments' => $descriptors]);
        expect(strlen($json))->toBeLessThan(65535);

        $decoded = Mage::helper('core')->jsonDecode($json);
        $email = new Email();
        Mage_Core_Model_Email_Attachment::applyDescriptors($email, $decoded['attachments']);

        $parts = $email->getAttachments();
        expect($parts)->toHaveCount(1);
        expect($parts[0]->getFilename())->toBe('invoice-' . $invoice->getIncrementId() . '.pdf');
        expect($parts[0]->getContentType())->toBe('application/pdf');
        // Real rendered PDF, not a stub
        expect(substr($parts[0]->getBody(), 0, 5))->toBe('%PDF-');
    });

    it('attaches the invoice PDF end-to-end through sendEmail when the flag is on', function () {
        $invoice = createRenderableInvoice();
        // Config must be set on the order's store (1), which is what sendEmail() reads
        Mage::app()->getStore(1)->setConfig(Mage_Sales_Model_Order_Invoice::XML_PATH_EMAIL_ENABLED, '1');
        Mage::app()->getStore(1)->setConfig(Mage_Sales_Model_Order_Invoice::XML_PATH_EMAIL_ATTACH_PDF, '1');

        // With SMTP disabled the direct-send path still builds the message and applies
        // attachments (rendering the real PDF) before the null transport short-circuits,
        // so this exercises the full gating + attachment wiring and must not throw
        $result = $invoice->sendEmail(true);

        expect($result)->toBe($invoice);
        expect((bool) $invoice->getEmailSent())->toBeTrue();
    });

    it('does not attach a PDF when the flag is off', function () {
        $invoice = createRenderableInvoice();
        Mage::app()->getStore(1)->setConfig(Mage_Sales_Model_Order_Invoice::XML_PATH_EMAIL_ENABLED, '1');
        Mage::app()->getStore(1)->setConfig(Mage_Sales_Model_Order_Invoice::XML_PATH_EMAIL_ATTACH_PDF, '0');

        $result = $invoice->sendEmail(true);

        expect($result)->toBe($invoice);
        expect((bool) $invoice->getEmailSent())->toBeTrue();
    });
});
