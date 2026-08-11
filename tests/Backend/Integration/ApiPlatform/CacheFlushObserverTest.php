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
