<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('API service URL', function (): void {
    it('takes its host from the configured base URL, not from the Host header', function (): void {
        $previous = $_SERVER['HTTP_HOST'] ?? null;
        $_SERVER['HTTP_HOST'] = 'attacker.example';
        try {
            $url = Mage::helper('api')->getServiceUrl('*/*/*');
        } finally {
            if ($previous === null) {
                unset($_SERVER['HTTP_HOST']);
            } else {
                $_SERVER['HTTP_HOST'] = $previous;
            }
        }

        $configuredHost = parse_url(Mage::getSingleton('core/url')->getUrl('*/*/*'), PHP_URL_HOST);
        expect(parse_url($url, PHP_URL_HOST))->toBe($configuredHost)->not->toBe('attacker.example');
    });
});
