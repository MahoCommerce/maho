<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

function galleryLayoutBlock(): Mage_Catalog_Block_Product_View_Media
{
    /** @var Mage_Catalog_Block_Product_View_Media $block */
    $block = Mage::app()->getLayout()
        ->createBlock('catalog/product_view_media')
        ->setTemplate('catalog/product/view/media.phtml');
    return $block;
}

describe('Product image gallery layout', function () {
    beforeEach(function () {
        Mage::unregister('product');
        Mage::register('product', Mage::getModel('catalog/product')->setName('Gallery Test'));
    });

    afterEach(function () {
        Mage::unregister('product');
    });

    it('shows the thumbnails below the image on a desktop, a carousel on a phone, and 2 grid columns by default', function () {
        $block = galleryLayoutBlock();

        expect($block->getGalleryLayout())->toBe(Mage_Catalog_Block_Product_View_Media::GALLERY_LAYOUT_BELOW)
            ->and($block->getGalleryPhoneLayout())->toBe(Mage_Catalog_Block_Product_View_Media::GALLERY_PHONE_LAYOUT_CAROUSEL)
            ->and($block->getGalleryGridColumns())->toBe(2);
    });

    it('reads the phone layout from the store config', function (string $value, string $expected) {
        Mage::app()->getStore()->setConfig(Mage_Catalog_Block_Product_View_Media::XML_PATH_GALLERY_PHONE_LAYOUT, $value);

        expect(galleryLayoutBlock()->getGalleryPhoneLayout())->toBe($expected);
    })->with([
        ['carousel', 'carousel'],
        ['below', 'below'],
        ['grid', 'carousel'],
    ]);

    it('reads the layout and the grid columns from the store config', function (string $layout) {
        $store = Mage::app()->getStore();
        $store->setConfig(Mage_Catalog_Block_Product_View_Media::XML_PATH_GALLERY_LAYOUT, $layout);
        $store->setConfig(Mage_Catalog_Block_Product_View_Media::XML_PATH_GALLERY_GRID_COLUMNS, '3');

        $block = galleryLayoutBlock();

        expect($block->getGalleryLayout())->toBe($layout)
            ->and($block->getGalleryGridColumns())->toBe(3);
    })->with(['below', 'left', 'right', 'grid']);

    it('shows the thumbnails below the image for an unknown layout', function () {
        Mage::app()->getStore()->setConfig(Mage_Catalog_Block_Product_View_Media::XML_PATH_GALLERY_LAYOUT, 'top');

        expect(galleryLayoutBlock()->getGalleryLayout())->toBe(Mage_Catalog_Block_Product_View_Media::GALLERY_LAYOUT_BELOW);
    });

    it('keeps the grid columns between 1 and 3', function (string $value, int $expected) {
        Mage::app()->getStore()->setConfig(Mage_Catalog_Block_Product_View_Media::XML_PATH_GALLERY_GRID_COLUMNS, $value);

        expect(galleryLayoutBlock()->getGalleryGridColumns())->toBe($expected);
    })->with([
        ['0', 1],
        ['1', 1],
        ['3', 3],
        ['7', 3],
        ['', 2],
    ]);

    it('writes the layouts and the grid columns on the gallery', function () {
        $store = Mage::app()->getStore();
        $store->setConfig(Mage_Catalog_Block_Product_View_Media::XML_PATH_GALLERY_LAYOUT, 'grid');
        $store->setConfig(Mage_Catalog_Block_Product_View_Media::XML_PATH_GALLERY_GRID_COLUMNS, '3');
        $store->setConfig(Mage_Catalog_Block_Product_View_Media::XML_PATH_GALLERY_PHONE_LAYOUT, 'carousel');

        $html = galleryLayoutBlock()->toHtml();

        expect($html)->toContain('data-gallery-layout="grid"')
            ->and($html)->toContain('data-gallery-phone="carousel"')
            ->and($html)->toContain('--gallery-columns: 3')
            ->and($html)->toContain('--gallery-ratio:');
    });
});
