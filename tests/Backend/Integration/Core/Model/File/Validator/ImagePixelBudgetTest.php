<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function imagePixelBudgetFixture(int $width, int $height): string
{
    $path = tempnam(sys_get_temp_dir(), 'maho-image-budget-');
    if ($path === false) {
        throw new RuntimeException('Could not create an image fixture.');
    }
    $image = imagecreatetruecolor($width, $height);
    imagepng($image, $path);
    return $path;
}

afterEach(function () {
    if (isset($this->imagePath) && is_file($this->imagePath)) {
        unlink($this->imagePath);
    }
    Mage::app()->getStore()->setConfig(
        Mage_Core_Model_File_Validator_Image::XML_PATH_MAX_PIXELS,
        (string) Mage_Core_Model_File_Validator_Image::DEFAULT_MAX_PIXELS,
    );
});

it('rejects an image above the decoded-pixel budget before rendering it', function () {
    Mage::app()->getStore()->setConfig(Mage_Core_Model_File_Validator_Image::XML_PATH_MAX_PIXELS, '99');
    $this->imagePath = imagePixelBudgetFixture(10, 10);
    $validator = new Mage_Core_Model_File_Validator_Image();

    expect(fn() => $validator->validate($this->imagePath))
        ->toThrow(Mage_Core_Exception::class, 'Image dimensions are too large');
});

it('accepts an image exactly at the decoded-pixel budget', function () {
    Mage::app()->getStore()->setConfig(Mage_Core_Model_File_Validator_Image::XML_PATH_MAX_PIXELS, '100');
    $this->imagePath = imagePixelBudgetFixture(10, 10);
    $validator = new Mage_Core_Model_File_Validator_Image();

    expect(fn() => $validator->validate($this->imagePath))->not->toThrow(Mage_Core_Exception::class);
});

it('checks the budget without integer multiplication overflow', function () {
    expect(Mage_Core_Model_File_Validator_Image::exceedsPixelBudget(PHP_INT_MAX, 2, PHP_INT_MAX))
        ->toBeTrue()
        ->and(Mage_Core_Model_File_Validator_Image::exceedsPixelBudget(0, 1, PHP_INT_MAX))
        ->toBeTrue()
        ->and(Mage_Core_Model_File_Validator_Image::exceedsPixelBudget(10, 10, 100))
        ->toBeFalse();
});
