<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('gives an uploaded image the extension of its real image type', function (string $name, string $expected) {
    $tmpFile = tempnam(sys_get_temp_dir(), 'img');
    file_put_contents($tmpFile, base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));

    $model = Mage::getModel('eav/attribute_data_image');
    $value = (new ReflectionMethod($model, '_setImageTypeExtension'))
        ->invoke($model, ['name' => $name, 'tmp_name' => $tmpFile]);
    unlink($tmpFile);

    expect($value['name'])->toBe($expected);
})->with([
    ['avatar.phtml', 'avatar.gif'],
    ['avatar.png', 'avatar.gif'],
    ['avatar.gif', 'avatar.gif'],
]);
