<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Mage_Core_Model_Input_Filter_MaliciousCode::linkFilter', function () {
    beforeEach(function () {
        $this->filter = new Mage_Core_Model_Input_Filter_MaliciousCode();
    });

    it('preserves UTF-8 multi-byte characters when content contains a link', function () {
        // Regression: loadHTML() without an encoding hint defaulted to ISO-8859-1
        // and mangled UTF-8 (e.g. "ö" -> "&Atilde;&para;"), corrupting stored
        // content via _beforeSave(). The bug only triggered when an <a> tag was
        // present, since the no-link early return skips the DOMDocument round-trip.
        $result = $this->filter->linkFilter('<div>Grüße ö</div><a href="x">l</a>');

        expect($result)->toContain('&uuml;')
            ->and($result)->toContain('&szlig;')
            ->and($result)->toContain('&ouml;')
            ->and($result)->not->toContain('&Atilde;');
    });

    it('does not leave the injected XML processing instruction in the output', function () {
        $result = $this->filter->linkFilter('<div>ö</div><a href="x">l</a>');

        expect($result)->not->toContain('<?xml');
    });

    it('adds safe rel and target attributes to links', function () {
        $result = $this->filter->linkFilter('<a href="https://example.com">link</a>');

        expect($result)->toContain('rel="noopener noreferrer"')
            ->and($result)->toContain('target="_blank"');
    });

    it('returns input untouched when no link is present (fast path)', function () {
        $input = '<div>Grüße ö</div>';

        expect($this->filter->linkFilter($input))->toBe($input);
    });
});
