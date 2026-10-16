<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Mage_Core_Model_Image_Settings', function () {
    it('reads widths in ascending order, once each, and only above zero', function (): void {
        expect(Mage_Core_Model_Image_Settings::parseWidths('600, 150,300,150,0,-5,abc'))->toBe([150, 300, 600])
            ->and(Mage_Core_Model_Image_Settings::parseWidths([1200, '300']))->toBe([300, 1200]);
    });

    it('reads a ratio, and gives null for original or a value that is not a ratio', function (): void {
        expect(Mage_Core_Model_Image_Settings::parseRatio('1:1'))->toBe([1, 1])
            ->and(Mage_Core_Model_Image_Settings::parseRatio(' 3 : 4 '))->toBe([3, 4])
            ->and(Mage_Core_Model_Image_Settings::parseRatio('original'))->toBeNull()
            ->and(Mage_Core_Model_Image_Settings::parseRatio('0:1'))->toBeNull()
            ->and(Mage_Core_Model_Image_Settings::parseRatio(''))->toBeNull();
    });

    it('picks the smallest width that is the requested size or more, else the largest', function (): void {
        $settings = new Mage_Core_Model_Image_Settings([150, 300, 600]);

        expect($settings->getWidthFor(150))->toBe(150)
            ->and($settings->getWidthFor(160))->toBe(300)
            ->and($settings->getWidthFor(600))->toBe(600)
            ->and($settings->getWidthFor(5000))->toBe(600)
            ->and($settings->getWidthFor(0))->toBe(600);
    });

    it('gives the height of the box from the ratio, and none for the ratio of the source', function (): void {
        expect(new Mage_Core_Model_Image_Settings([300], ratio: [3, 4])->getHeightFor(300))->toBe(400)
            ->and(new Mage_Core_Model_Image_Settings([300])->getHeightFor(300))->toBeNull();
    });

    it('keeps the hash when only the widths change, and changes it for any other setting', function (): void {
        $base = new Mage_Core_Model_Image_Settings([150, 300]);

        expect(new Mage_Core_Model_Image_Settings([150, 300, 600])->getHash())->toBe($base->getHash())
            ->and(new Mage_Core_Model_Image_Settings([150, 300], quality: 70)->getHash())->not->toBe($base->getHash())
            ->and(new Mage_Core_Model_Image_Settings([150, 300], ratio: [1, 1])->getHash())->not->toBe($base->getHash())
            ->and(new Mage_Core_Model_Image_Settings([150, 300], ratio: [1, 1], fit: 'cover')->getHash())
            ->not->toBe(new Mage_Core_Model_Image_Settings([150, 300], ratio: [1, 1])->getHash())
            ->and(new Mage_Core_Model_Image_Settings([150, 300], extension: '.jpg')->getHash())->not->toBe($base->getHash());
    });

    it('reads back what it writes, and refuses data with no widths', function (): void {
        $settings = new Mage_Core_Model_Image_Settings(
            [150, 300],
            ratio: [3, 4],
            fit: 'cover',
            quality: 70,
            background: 'eeeeee',
            extension: '.jpg',
            watermark: ['path' => 'catalog/product/watermark/default/w.png', 'position' => 'tile', 'width' => 20, 'height' => null, 'opacity' => 50],
        );

        expect(Mage_Core_Model_Image_Settings::fromArray(json_decode((string) json_encode($settings->toArray()), true)))->toEqual($settings)
            ->and(Mage_Core_Model_Image_Settings::fromArray(['quality' => 80]))->toBeNull();
    });

    it('keeps transparency only for an output format with an alpha channel', function (): void {
        expect(new Mage_Core_Model_Image_Settings([300], extension: '.webp')->keepsTransparency())->toBeTrue()
            ->and(new Mage_Core_Model_Image_Settings([300], extension: '.jpg')->keepsTransparency())->toBeFalse();
    });
});
