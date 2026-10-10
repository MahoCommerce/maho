<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('removes the compiled API Platform container on a full cache flush', function (): void {
    $dir = BP . '/var/cache/api_platform';
    if (!is_dir($dir . '/test')) {
        mkdir($dir . '/test', 0777, true);
    }
    file_put_contents($dir . '/test/marker.php', '<?php');

    Mage::dispatchEvent('adminhtml_cache_flush_all');

    expect(is_dir($dir))->toBeFalse()
        ->and(glob($dir . '.old.*'))->toBe([]);
});

it('keeps the compiled API Platform container until the API request that flushes ends', function (): void {
    $dir = BP . '/var/cache/api_platform';
    if (!is_dir($dir . '/test')) {
        mkdir($dir . '/test', 0777, true);
    }
    file_put_contents($dir . '/test/marker.php', '<?php');

    $activeRequests = new ReflectionProperty(Maho\ApiPlatform\Kernel::class, 'activeRequests');
    $activeRequests->setValue(null, 1);
    try {
        Mage::dispatchEvent('adminhtml_cache_flush_all');
    } finally {
        $activeRequests->setValue(null, 0);
    }

    expect(is_file($dir . '/test/marker.php'))->toBeTrue();
});
