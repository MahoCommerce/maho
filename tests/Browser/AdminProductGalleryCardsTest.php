<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

/**
 * The product images tab shows one card for each image. The order of the cards is the sort
 * order, and each card sets its roles (Main Image), Exclude and Remove. The cards write the same
 * hidden JSON fields as the old table, so a save must store what the cards show.
 */

const GALLERY_CARDS_SKU_PREFIX = 'pest-gallery-cards-';
const GALLERY_CARDS_ADMIN_USERNAME = 'pest_gallery_cards';
const GALLERY_CARDS_ADMIN_PASSWORD = 'PestGalleryCards2026!';

beforeEach(function () {
    deleteGalleryCardsFixtures();
});

afterEach(function () {
    deleteGalleryCardsFixtures();
});

/**
 * A simple product with three gallery images. The first one is the main image.
 *
 * @return array{id: int, files: list<string>}
 */
function createGalleryCardsProduct(): array
{
    $dir = Mage::getBaseDir('var') . '/tmp/gallery-cards-test';
    @mkdir($dir, 0777, true);

    $product = Mage::getModel('catalog/product')
        ->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID)
        ->setName('Pest Gallery Cards')
        ->setSku(GALLERY_CARDS_SKU_PREFIX . uniqid())
        ->setDescription('Pest Gallery Cards')
        ->setShortDescription('Pest Gallery Cards')
        ->setPrice(10.00)
        ->setWeight(1)
        ->setTaxClassId(2)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId(4)
        ->setWebsiteIds([1]);

    for ($i = 0; $i < 3; $i++) {
        $image = imagecreatetruecolor(200, 200);
        imagefill($image, 0, 0, imagecolorallocate($image, 80 * $i, 120, 200 - 60 * $i));
        $file = "{$dir}/card-{$i}.png";
        imagepng($image, $file);
        $product->addImageToMediaGallery($file, $i === 0 ? ['image'] : null, false, false);
    }
    $product->save();

    $images = Mage::getModel('catalog/product')->load($product->getId())->getMediaGallery()['images'] ?? [];
    usort($images, fn(array $a, array $b): int => (int) $a['position'] <=> (int) $b['position']);

    return ['id' => (int) $product->getId(), 'files' => array_column($images, 'file')];
}

function deleteGalleryCardsFixtures(): void
{
    /** @var Mage_Catalog_Model_Resource_Product_Collection $products */
    $products = Mage::getResourceModel('catalog/product_collection');
    $products->addAttributeToFilter('sku', ['like' => GALLERY_CARDS_SKU_PREFIX . '%']);

    // Product deletion is admin-guarded, hence isSecureArea.
    Mage::register('isSecureArea', true);
    try {
        foreach ($products as $product) {
            $product->delete();
        }
    } finally {
        Mage::unregister('isSecureArea');
    }

    $user = Mage::getModel('admin/user')->loadByUsername(GALLERY_CARDS_ADMIN_USERNAME);
    if ($user->getId()) {
        $roleId = (int) $user->getRole()->getRoleId();
        $user->delete();
        if ($roleId) {
            Mage::getModel('admin/roles')->load($roleId)->delete();
        }
    }
}

/** A throwaway admin with full access, so the test does not depend on the password of the install. */
function createGalleryCardsAdminUser(): void
{
    $role = Mage::getModel('admin/roles')
        ->setName('Pest Gallery Cards')
        ->setRoleType('G')
        ->setParentId(0)
        ->save();

    Mage::getModel('admin/rules')
        ->setRoleId($role->getId())
        ->setResources(['all'])
        ->saveRel();

    $user = Mage::getModel('admin/user');
    $user->setData([
        'username' => GALLERY_CARDS_ADMIN_USERNAME,
        'firstname' => 'Pest',
        'lastname' => 'Gallery',
        'email' => GALLERY_CARDS_ADMIN_USERNAME . '@example.test',
        'password' => GALLERY_CARDS_ADMIN_PASSWORD,
        'is_active' => 1,
    ])->save();

    Mage::getModel('admin/user')
        ->setRoleId($role->getId())
        ->setUserId($user->getId())
        ->add();
}

function galleryCard(string $file): string
{
    return '.gallery-cards .preview[data-file="' . $file . '"]';
}

it('stores the order, the main image, Exclude and Remove that the cards show', function () {
    ['id' => $id, 'files' => [$first, $second, $third]] = createGalleryCardsProduct();
    createGalleryCardsAdminUser();

    $page = adminLoginAndVisit(
        GALLERY_CARDS_ADMIN_USERNAME,
        GALLERY_CARDS_ADMIN_PASSWORD,
        '/admin/catalog_product/edit/id/' . $id,
        'ul.tabs a[title="Images"]',
    );
    $page->click('ul.tabs a[title="Images"]');
    $page->text('.gallery-cards .preview:first-child');

    $order = "[...document.querySelectorAll('.gallery-cards .preview')].map((c) => c.dataset.file).join(',')";
    expect($page->script($order))->toBe("{$first},{$second},{$third}");

    // The arrow keys on the handle move the card, as a drag does.
    $page->keys(galleryCard($third) . ' .gallery-handle', ['ArrowLeft', 'ArrowLeft']);
    expect($page->script($order))->toBe("{$third},{$first},{$second}");

    $page->click(galleryCard($third) . ' .gallery-card-options .cell-image')
        ->click(galleryCard($first) . ' .cell-disable')
        ->click(galleryCard($second) . ' .cell-remove')
        ->click('button[title="Save"]')->wait(2);
    waitForPageLoad($page, '#productGrid');

    $product = Mage::getModel('catalog/product')->setStoreId(0)->load($id);
    $images = array_column($product->getMediaGallery()['images'] ?? [], null, 'file');

    expect($product->getImage())->toBe($third)
        ->and($images)->toHaveCount(2)
        ->and(array_keys($images))->not->toContain($second)
        ->and((int) $images[$third]['position'])->toBe(1)
        ->and((int) $images[$first]['position'])->toBe(2)
        ->and((int) $images[$first]['disabled'])->toBe(1)
        ->and((int) $images[$third]['disabled'])->toBe(0);
});

it('clears the main image when the user clicks the Main Image of the main card again', function () {
    ['id' => $id, 'files' => [$first]] = createGalleryCardsProduct();
    createGalleryCardsAdminUser();

    $page = adminLoginAndVisit(
        GALLERY_CARDS_ADMIN_USERNAME,
        GALLERY_CARDS_ADMIN_PASSWORD,
        '/admin/catalog_product/edit/id/' . $id,
        'ul.tabs a[title="Images"]',
    );
    $page->click('ul.tabs a[title="Images"]');
    $page->text('.gallery-cards .preview:first-child');

    $checked = "document.querySelector('" . galleryCard($first) . " .cell-image input').checked";
    expect($page->script($checked))->toBeTrue();

    $page->click(galleryCard($first) . ' .gallery-card-options .cell-image');
    expect($page->script($checked))->toBeFalse();

    $page->click('button[title="Save"]')->wait(2);
    waitForPageLoad($page, '#productGrid');

    $product = Mage::getModel('catalog/product')->setStoreId(0)->load($id);
    expect($product->getImage())->toBeIn([null, '', 'no_selection']);
});
