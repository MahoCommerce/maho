<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Downloadable
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('generates a random link hash that carries no purchase data', function (): void {
    $helper = Mage::helper('downloadable');
    $hashes = [];
    for ($i = 0; $i < 50; $i++) {
        $hashes[] = $helper->generateLinkHash();
    }

    foreach ($hashes as $hash) {
        expect($hash)->toMatch('/^[A-Za-z0-9]{40}$/');
    }
    expect(count(array_unique($hashes)))->toBe(50);
});
