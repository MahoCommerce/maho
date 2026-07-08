<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\Mime\Email;

uses(Tests\MahoBackendTestCase::class);

describe('Mage_Core_Model_Email_Attachment::applyDescriptors', function () {
    it('is a no-op for an empty descriptor list', function () {
        $email = new Email();

        Mage_Core_Model_Email_Attachment::applyDescriptors($email, []);

        expect($email->getAttachments())->toBe([]);
    });

    it('skips and does not throw when a descriptor cannot be materialized', function () {
        $email = new Email();
        $descriptor = (new Mage_Core_Model_Email_Attachment('nonexistent/pdf_model', 'nonexistent/entity', 999999, 'x.pdf'))->toArray();

        Mage_Core_Model_Email_Attachment::applyDescriptors($email, [$descriptor]);

        // A document that cannot be regenerated must not abort the send
        expect($email->getAttachments())->toBe([]);
    });
});
