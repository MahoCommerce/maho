<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The upgrade to 2.0.2 leaves a product with one image. The test puts back the state of a store
 * before the upgrade, runs the script, and reads the database.
 */

const ONE_IMAGE_OLD_ATTRIBUTES = ['small_image', 'thumbnail', 'small_image_label', 'thumbnail_label'];

const ONE_IMAGE_OLD_PATHS = [
    'catalog/product_image/base_width',
    'catalog/product_image/small_width',
    'catalog/placeholder/small_image_placeholder',
    'catalog/placeholder/thumbnail_placeholder',
    'design/watermark/small_image_image',
    'design/watermark/small_image_imageOpacity',
    'design/watermark/small_image_size',
    'design/watermark/small_image_position',
    'design/watermark/thumbnail_image',
    'design/watermark/thumbnail_imageOpacity',
    'design/watermark/thumbnail_size',
    'design/watermark/thumbnail_position',
];

const ONE_IMAGE_KEPT_PATHS = [
    'catalog/placeholder/image_placeholder',
    'design/watermark/image_image',
];

function oneImageRunUpgrade(): void
{
    $script = Mage::getModuleDir('data', 'Mage_Catalog') . '/catalog_setup/data-upgrade-2.0.1-2.0.2.php';
    (function () use ($script): void {
        include $script;
    })->call(Mage::getResourceModel('catalog/setup', 'catalog_setup'));
}

function oneImageAttributeCodes(): array
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');

    return $adapter->fetchCol(
        $adapter->select()
            ->from($resource->getTableName('eav/attribute'), ['attribute_code'])
            ->where('entity_type_id = ?', (int) Mage::getSingleton('eav/config')->getEntityType('catalog_product')->getId())
            ->where('attribute_code IN (?)', [...ONE_IMAGE_OLD_ATTRIBUTES, 'image'])
            ->order('attribute_code'),
    );
}

function oneImageMainAttribute(): array
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');

    return $adapter->fetchRow(
        $adapter->select()
            ->from(['ea' => $resource->getTableName('eav/attribute')], ['frontend_label'])
            ->join(
                ['cea' => $resource->getTableName('catalog/eav_attribute')],
                'cea.attribute_id = ea.attribute_id',
                ['used_in_product_listing'],
            )
            ->where('ea.entity_type_id = ?', (int) Mage::getSingleton('eav/config')->getEntityType('catalog_product')->getId())
            ->where('ea.attribute_code = ?', 'image'),
    );
}

function oneImageConfigPaths(): array
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');

    return $adapter->fetchCol(
        $adapter->select()
            ->from($resource->getTableName('core/config_data'), ['path'])
            ->where('path IN (?)', [...ONE_IMAGE_OLD_PATHS, ...ONE_IMAGE_KEPT_PATHS])
            ->order('path'),
    );
}

function oneImageRemoveTestData(Mage_Catalog_Model_Resource_Setup $setup): void
{
    foreach (ONE_IMAGE_OLD_ATTRIBUTES as $code) {
        $setup->removeAttribute(Mage_Catalog_Model_Product::ENTITY, $code);
    }
    foreach ([...ONE_IMAGE_OLD_PATHS, ...ONE_IMAGE_KEPT_PATHS] as $path) {
        $setup->deleteConfigData($path);
    }
}

beforeEach(function () {
    $this->setup = Mage::getResourceModel('catalog/setup', 'catalog_setup');
    oneImageRemoveTestData($this->setup);

    foreach (['small_image' => 'Small Image', 'thumbnail' => 'Thumbnail'] as $code => $label) {
        $this->setup->addAttribute(Mage_Catalog_Model_Product::ENTITY, $code, [
            'type' => 'varchar',
            'label' => $label,
            'input' => 'media_image',
            'frontend' => 'catalog/product_attribute_frontend_image',
            'required' => false,
            'used_in_product_listing' => true,
        ]);
        $this->setup->addAttribute(Mage_Catalog_Model_Product::ENTITY, "{$code}_label", [
            'type' => 'varchar',
            'label' => "{$label} Label",
            'input' => 'text',
            'required' => false,
        ]);
    }
    $this->setup->updateAttribute(Mage_Catalog_Model_Product::ENTITY, 'image', 'used_in_product_listing', 0);
    $this->setup->updateAttribute(Mage_Catalog_Model_Product::ENTITY, 'image', 'frontend_label', 'Base Image');

    foreach ([...ONE_IMAGE_OLD_PATHS, ...ONE_IMAGE_KEPT_PATHS] as $path) {
        $this->setup->setConfigData($path, 'old');
    }
});

afterEach(function () {
    oneImageRemoveTestData($this->setup);
    $this->setup->updateAttribute(Mage_Catalog_Model_Product::ENTITY, 'image', 'used_in_product_listing', 1);
    $this->setup->updateAttribute(Mage_Catalog_Model_Product::ENTITY, 'image', 'frontend_label', 'Main Image');
});

it('starts from the state of a store before the upgrade', function () {
    expect(oneImageAttributeCodes())->toBe(['image', 'small_image', 'small_image_label', 'thumbnail', 'thumbnail_label'])
        ->and(oneImageMainAttribute())->toEqual(['frontend_label' => 'Base Image', 'used_in_product_listing' => 0])
        ->and(oneImageConfigPaths())->toHaveCount(count(ONE_IMAGE_OLD_PATHS) + count(ONE_IMAGE_KEPT_PATHS));
});

it('removes the small_image and thumbnail attributes and their labels', function () {
    oneImageRunUpgrade();

    expect(oneImageAttributeCodes())->toBe(['image']);
});

it('shows the image in listings with the Main Image label', function () {
    oneImageRunUpgrade();

    expect(oneImageMainAttribute())->toEqual(['frontend_label' => 'Main Image', 'used_in_product_listing' => 1]);
});

it('deletes the settings of the old roles and keeps the settings of image', function () {
    oneImageRunUpgrade();

    $kept = ONE_IMAGE_KEPT_PATHS;
    sort($kept);
    expect(oneImageConfigPaths())->toBe($kept);
});

it('runs a second time without errors', function () {
    oneImageRunUpgrade();
    oneImageRunUpgrade();

    expect(oneImageAttributeCodes())->toBe(['image'])
        ->and(oneImageMainAttribute())->toEqual(['frontend_label' => 'Main Image', 'used_in_product_listing' => 1]);
});
