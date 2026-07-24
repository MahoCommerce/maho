<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Mage_Core_Model_Email_Info attachments', function () {
    beforeEach(function () {
        $this->info = Mage::getModel('core/email_info');
    });

    it('starts with no attachments', function () {
        expect($this->info->getAttachments())->toBe([]);
    });

    it('accumulates attachments via addAttachment', function () {
        $attachment = new Mage_Core_Model_Email_Attachment('sales/order_pdf_invoice', 'sales/order_invoice', 1, 'a.pdf');

        $this->info->addAttachment($attachment);

        expect($this->info->getAttachments())->toBe([$attachment]);
    });

    it('builds a pdf attachment descriptor via addPdfAttachment', function () {
        $this->info->addPdfAttachment('sales/order_pdf_invoice', 'sales/order_invoice', 7, 'invoice-7.pdf');

        $attachments = $this->info->getAttachments();
        expect($attachments)->toHaveCount(1);
        expect($attachments[0])->toBeInstanceOf(Mage_Core_Model_Email_Attachment::class);
        expect($attachments[0]->toArray())->toBe([
            'source_model' => 'sales/order_pdf_invoice',
            'entity_model' => 'sales/order_invoice',
            'entity_id'    => 7,
            'filename'     => 'invoice-7.pdf',
            'mime_type'    => 'application/pdf',
        ]);
    });
});
