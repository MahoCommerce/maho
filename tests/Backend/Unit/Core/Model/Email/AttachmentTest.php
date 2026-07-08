<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\Mime\Email;

uses(Tests\MahoBackendTestCase::class);

/**
 * Build an attachment whose content generation is stubbed, so we can exercise
 * applyTo()/round-tripping without rendering a real PDF.
 */
function makeStubbedAttachment(string $content = 'PDFBYTES'): Mage_Core_Model_Email_Attachment
{
    return new class ('sales/order_pdf_invoice', 'sales/order_invoice', 42, 'invoice-100000042.pdf', $content) extends Mage_Core_Model_Email_Attachment {
        public function __construct(string $sourceModel, string $entityModel, int $entityId, string $filename, private string $stubContent)
        {
            parent::__construct($sourceModel, $entityModel, $entityId, $filename);
        }

        #[\Override]
        public function getContent(): string
        {
            return $this->stubContent;
        }
    };
}

describe('Mage_Core_Model_Email_Attachment', function () {
    it('exposes filename and defaults mime type to application/pdf', function () {
        $attachment = new Mage_Core_Model_Email_Attachment('sales/order_pdf_invoice', 'sales/order_invoice', 42, 'invoice-100000042.pdf');

        expect($attachment->getFilename())->toBe('invoice-100000042.pdf');
        expect($attachment->getMimeType())->toBe('application/pdf');
    });

    it('round-trips through toArray/fromArray without carrying content bytes', function () {
        $attachment = new Mage_Core_Model_Email_Attachment('sales/order_pdf_invoice', 'sales/order_invoice', 42, 'invoice-100000042.pdf');

        $data = $attachment->toArray();

        // Descriptor only, never bytes, so it stays tiny and JSON/TEXT-column safe
        expect($data)->not->toHaveKey('content');
        expect($data['source_model'])->toBe('sales/order_pdf_invoice');
        expect($data['entity_model'])->toBe('sales/order_invoice');
        expect($data['entity_id'])->toBe(42);
        expect($data['filename'])->toBe('invoice-100000042.pdf');
        expect($data['mime_type'])->toBe('application/pdf');

        $restored = Mage_Core_Model_Email_Attachment::fromArray($data);
        expect($restored->toArray())->toBe($data);
    });

    it('serializes to a size that fits the queue TEXT column', function () {
        $descriptors = array_map(
            fn($i) => (new Mage_Core_Model_Email_Attachment('sales/order_pdf_invoice', 'sales/order_invoice', $i, "invoice-{$i}.pdf"))->toArray(),
            range(1, 10),
        );

        $encoded = Mage::helper('core')->jsonEncode(['attachments' => $descriptors]);

        expect(strlen($encoded))->toBeLessThan(65535);
    });

    it('applies itself to a Symfony Email as an attachment', function () {
        $email = new Email();
        $attachment = makeStubbedAttachment('PDFBYTES');

        $attachment->applyTo($email);

        $parts = $email->getAttachments();
        expect($parts)->toHaveCount(1);
        expect($parts[0]->getBody())->toBe('PDFBYTES');
        expect($parts[0]->getContentType())->toBe('application/pdf');
        expect($parts[0]->getFilename())->toBe('invoice-100000042.pdf');
    });
});
