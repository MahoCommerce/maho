<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Downloadable
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('keeps spaces and punctuation in one downloadable filename parameter', function () {
    $header = Mage_Downloadable_Helper_Download::formatContentDisposition(
        'attachment',
        'Quarterly report; final v2.pdf',
    );

    expect($header)->toBe('attachment; filename="Quarterly report; final v2.pdf"');
});

it('provides an ASCII fallback and removes control characters', function () {
    $header = Mage_Downloadable_Helper_Download::formatContentDisposition(
        'inline',
        "résumé\n.pdf",
    );

    expect($header)->toStartWith('inline;')
        ->toContain('filename=r_sum__.pdf')
        ->toContain("filename*=utf-8''r%C3%A9sum%C3%A9.pdf");
});
