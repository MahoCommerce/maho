<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Browser\MahoServer;
use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

const GALLERY_IMAGE_COUNT = 4;

afterEach(function () {
    $config = Mage::getModel('core/config');
    $config->deleteConfig(Mage_Catalog_Block_Product_View_Media::XML_PATH_GALLERY_LAYOUT);
    $config->deleteConfig(Mage_Catalog_Block_Product_View_Media::XML_PATH_GALLERY_GRID_COLUMNS);
    $config->deleteConfig(Mage_Catalog_Block_Product_View_Media::XML_PATH_GALLERY_PHONE_LAYOUT);
    Mage::app()->getStore()->resetConfig();
    Mage::app()->cleanCache();
});

function setGalleryLayout(string $layout, int $columns = 2, string $phoneLayout = 'carousel'): void
{
    $config = Mage::getModel('core/config');
    $config->saveConfig(Mage_Catalog_Block_Product_View_Media::XML_PATH_GALLERY_LAYOUT, $layout);
    $config->saveConfig(Mage_Catalog_Block_Product_View_Media::XML_PATH_GALLERY_GRID_COLUMNS, (string) $columns);
    $config->saveConfig(Mage_Catalog_Block_Product_View_Media::XML_PATH_GALLERY_PHONE_LAYOUT, $phoneLayout);
    Mage::app()->getStore()->resetConfig();
    Mage::app()->cleanCache();
}

/**
 * The URL of a new simple product with GALLERY_IMAGE_COUNT gallery images. The sample
 * products have one image each.
 */
function galleryProductUrl(): string
{
    $dir = Mage::getBaseDir('var') . '/tmp/gallery-layout-test';
    @mkdir($dir, 0777, true);

    $product = Mage::getModel('catalog/product')
        ->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID)
        ->setName('Gallery Layout Test')
        ->setSku('gallery-layout-' . uniqid())
        ->setPrice(10.00)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId(4)
        ->setWebsiteIds([1]);

    for ($i = 0; $i < GALLERY_IMAGE_COUNT; $i++) {
        $image = imagecreatetruecolor(400, 400);
        imagefill($image, 0, 0, imagecolorallocate($image, 60 * $i, 120, 200 - 40 * $i));
        $file = "{$dir}/gallery-{$i}.png";
        imagepng($image, $file);
        $product->addImageToMediaGallery($file, $i === 0 ? ['image', 'small_image', 'thumbnail'] : null, false, false);
    }

    $product->save();

    return '/catalog/product/view/id/' . (int) $product->getId() . '/';
}

/**
 * Run $expression in the page until it returns true, for at most 5s.
 */
function waitForGalleryScript(object $page, string $expression): void
{
    $deadline = microtime(true) + 5;
    while ($page->script($expression) !== true) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException("[{$expression}] did not become true within 5s");
        }
        usleep(50_000);
    }
}

const VISIBLE_GALLERY_IMAGES = "[...document.querySelectorAll('.product-image-gallery img')].filter(i => i.checkVisibility()).length";

it('shows every gallery image in the grid layout', function () {
    setGalleryLayout('grid', 2);

    $page = visit(MahoServer::baseUrl() . galleryProductUrl());
    waitForPageLoad($page, '.product-image-gallery');

    expect($page->script(VISIBLE_GALLERY_IMAGES))->toBe(GALLERY_IMAGE_COUNT)
        ->and($page->script("getComputedStyle(document.querySelector('.more-views')).display"))->toBe('none')
        ->and($page->script("getComputedStyle(document.querySelector('.product-image-gallery')).gridTemplateColumns.split(' ').length"))->toBe(2);

    $page->click('.product-image-gallery img:nth-of-type(3)')
        ->assertPresent('.fullscreen-gallery');
    waitForGalleryScript($page, "document.querySelector('.fg-scroll').scrollLeft > 0");
});

it('shows one image and the thumbnails at its left in the left layout', function () {
    setGalleryLayout('left');

    $page = visit(MahoServer::baseUrl() . galleryProductUrl());
    waitForPageLoad($page, '.product-image-thumbs');

    expect($page->script(VISIBLE_GALLERY_IMAGES))->toBe(1)
        ->and($page->script(
            "document.querySelector('.more-views').getBoundingClientRect().right"
            . " <= document.querySelector('.product-image-gallery').getBoundingClientRect().left",
        ))->toBeTrue();
});

it('shows the thumbnails at the right of the image in the right layout', function () {
    setGalleryLayout('right');

    $page = visit(MahoServer::baseUrl() . galleryProductUrl());
    waitForPageLoad($page, '.product-image-thumbs');

    expect($page->script(
        "document.querySelector('.more-views').getBoundingClientRect().left"
        . " >= document.querySelector('.product-image-gallery').getBoundingClientRect().right",
    ))->toBeTrue();
});

it('shows one image and the thumbnails below it on a phone in the below phone layout', function (string $layout) {
    setGalleryLayout($layout, phoneLayout: 'below');

    $page = visit(MahoServer::baseUrl() . galleryProductUrl())->on()->mobile()->assertPresent('.product-image-gallery');
    waitForPageLoad($page, '.product-image-thumbs');

    expect($page->script(VISIBLE_GALLERY_IMAGES))->toBe(1)
        ->and($page->script("document.querySelector('.more-views').checkVisibility()"))->toBeTrue()
        ->and($page->script("document.querySelector('.gallery-counter')"))->toBeNull();
})->with(['below', 'grid']);

it('shows a swipe carousel with a counter on a phone', function (string $layout) {
    setGalleryLayout($layout);

    $page = visit(MahoServer::baseUrl() . galleryProductUrl())->on()->mobile()->assertPresent('.product-image-gallery');
    waitForPageLoad($page, '.gallery-counter');

    $gallery = "document.querySelector('.product-image-gallery')";
    expect($page->script("{$gallery}.scrollWidth > {$gallery}.clientWidth"))->toBeTrue()
        ->and($page->script("getComputedStyle(document.querySelector('.more-views')).display"))->toBe('none')
        ->and($page->text('.gallery-counter'))->toBe('1 / ' . GALLERY_IMAGE_COUNT);

    $page->script("{$gallery}.scrollTo({left: {$gallery}.clientWidth})");
    waitForGalleryScript($page, "document.querySelector('.gallery-counter').textContent === '2 / " . GALLERY_IMAGE_COUNT . "'");

    $page->click('.product-image-gallery img:nth-of-type(3)')
        ->assertPresent('.fullscreen-gallery');
    waitForGalleryScript($page, "document.querySelector('.fg-scroll').scrollLeft > 0");
})->with(['below', 'grid']);
