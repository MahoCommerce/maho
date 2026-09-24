<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function productImageWithCanvas(int $width, int $height): Mage_Catalog_Model_Product_Image
{
    $model = Mage::getModel('catalog/product_image');
    (new ReflectionProperty($model, 'image'))->setValue($model, Maho::getImageManager()->createImage($width, $height));
    return $model;
}

describe('product image resize', function () {
    it('accepts the width as a numeric string, as config values and signed resize tokens give it', function () {
        $model = productImageWithCanvas(400, 200);
        $model->setTransformParams(['_width' => '100', '_height' => null, '_keepFrame' => false]);

        $model->resize();

        expect($model->getImage()->width())->toBe(100)
            ->and($model->getImage()->height())->toBe(50);
    });

    it('accepts both sizes as numeric strings when it keeps the frame', function () {
        $model = productImageWithCanvas(400, 200);
        $model->setWidth('120')->setHeight('60');

        $model->resize();

        expect($model->getImage()->width())->toBe(120)
            ->and($model->getImage()->height())->toBe(60);
    });
});
