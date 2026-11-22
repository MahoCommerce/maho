<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Downloadable
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('refuses file data that is not an array with a message', function () {
    expect(fn() => new Mage_Downloadable_Model_Link_Api_Uploader('not an array'))
        ->toThrow(Exception::class, 'The file data is not correct.');
});
