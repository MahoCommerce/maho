<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Mage_Adminhtml_Block_Template::maliciousCodeFilter', function () {
    beforeEach(function () {
        $this->block = Mage::app()->getLayout()->createBlock('adminhtml/template');
    });

    it('strips script tags', function () {
        $result = $this->block->maliciousCodeFilter('<p>hi</p><script>alert(1)</script>');

        expect($result)->not->toContain('<script');
    });

    it('does not let content wrapped in {{ }} bypass sanitization', function () {
        // Regression: masking {{...}} spans before filtering and restoring them
        // verbatim let any markup inside braces escape the malicious-code filter,
        // turning the preview sanitizer into an XSS sink.
        $result = $this->block->maliciousCodeFilter('{{<img src=x onerror=alert(1)>}}');

        expect($result)->not->toContain('onerror');
    });

    it('strips a script tag even when wrapped in braces', function () {
        $result = $this->block->maliciousCodeFilter('{{<script>alert(1)</script>}}');

        expect($result)->not->toContain('<script');
    });
});
