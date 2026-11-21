<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('bounds a data URI before decoding it', function () {
    $source = 'data:image/png;base64,' . base64_encode('12345');

    expect(Maho\Ai\Api\Agent\ImageTool::decodeImageSource($source, 4))->toBeFalse()
        ->and(Maho\Ai\Api\Agent\ImageTool::decodeImageSource($source, 5))->toBe('12345');
});

it('reads at most the configured limit from a referenced image', function () {
    $path = tempnam(sys_get_temp_dir(), 'maho-ai-image-');
    if ($path === false) {
        throw new RuntimeException('Could not create an image source fixture.');
    }
    file_put_contents($path, '12345');

    try {
        expect(Maho\Ai\Api\Agent\ImageTool::decodeImageSource($path, 4))->toBeFalse()
            ->and(Maho\Ai\Api\Agent\ImageTool::decodeImageSource($path, 5))->toBe('12345');
    } finally {
        unlink($path);
    }
});
