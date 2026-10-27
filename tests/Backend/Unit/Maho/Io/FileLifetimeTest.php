<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

describe('\Maho\Io\File lifetime', function () {
    it('frees an instance that nothing references', function () {
        $io = new \Maho\Io\File();
        $reference = WeakReference::create($io);
        unset($io);

        expect($reference->get())->toBeNull();
    });
});
