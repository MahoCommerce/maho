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

const ONE_IMAGE_COPIED_WATERMARK_PATHS = [
    'design/watermark/image_imageOpacity',
    'design/watermark/image_size',
    'design/watermark/image_position',
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

/**
 * Write the value of a product attribute in one store view, or delete it when $value is null.
 */
function oneImageSetValue(int $productId, string $code, int $storeId, ?string $value): void
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $table = $resource->getTableName(['catalog/product', 'varchar']);
    $attributeId = (int) Mage::getResourceModel('catalog/setup', 'catalog_setup')
        ->getAttributeId(Mage_Catalog_Model_Product::ENTITY, $code);

    $adapter->delete($table, ['entity_id = ?' => $productId, 'attribute_id = ?' => $attributeId, 'store_id = ?' => $storeId]);
    if ($value !== null) {
        $adapter->insert($table, [
            'entity_type_id' => (int) Mage::getSingleton('eav/config')->getEntityType('catalog_product')->getId(),
            'attribute_id' => $attributeId,
            'store_id' => $storeId,
            'entity_id' => $productId,
            'value' => $value,
        ]);
    }
}

/**
 * @return array<int, string> the values of image or image_label of the product, by store view
 */
function oneImageValues(int $productId, string $code): array
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');
    $attributeId = (int) Mage::getResourceModel('catalog/setup', 'catalog_setup')
        ->getAttributeId(Mage_Catalog_Model_Product::ENTITY, $code);

    return array_map(strval(...), $adapter->fetchPairs($adapter->select()
        ->from($resource->getTableName(['catalog/product', 'varchar']), ['store_id', 'value'])
        ->where('entity_id = ?', $productId)
        ->where('attribute_id = ?', $attributeId)
        ->order('store_id')));
}

function oneImageRemoveTestData(Mage_Catalog_Model_Resource_Setup $setup): void
{
    foreach (ONE_IMAGE_OLD_ATTRIBUTES as $code) {
        $setup->removeAttribute(Mage_Catalog_Model_Product::ENTITY, $code);
    }
    foreach ([...ONE_IMAGE_OLD_PATHS, ...ONE_IMAGE_KEPT_PATHS] as $path) {
        $setup->deleteConfigData($path);
    }
    foreach (ONE_IMAGE_COPIED_WATERMARK_PATHS as $path) {
        $setup->deleteConfigData($path, 'websites');
    }
}

beforeEach(function () {
    $this->setup = Mage::getResourceModel('catalog/setup', 'catalog_setup');
    oneImageRemoveTestData($this->setup);

    $product = Mage::getModel('catalog/product');
    $product->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID);
    $product->setName('One Image Upgrade');
    $product->setSku('one-image-upgrade-' . uniqid());
    $product->setPrice(10.00);
    $product->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED);
    $product->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);
    $product->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE);
    $product->setAttributeSetId($product->getDefaultAttributeSetId());
    $product->setWebsiteIds([1]);
    $product->save();
    $this->productId = (int) $product->getId();
    foreach (['image', 'image_label'] as $code) {
        foreach (array_keys(oneImageValues($this->productId, $code)) as $storeId) {
            oneImageSetValue($this->productId, $code, $storeId, null);
        }
    }

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
    Mage::getModel('catalog/product')->load($this->productId)->delete();
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

it('copies small_image into an empty image', function () {
    oneImageSetValue($this->productId, 'image', 0, 'no_selection');
    oneImageSetValue($this->productId, 'small_image', 0, '/s/m/small.jpg');
    oneImageSetValue($this->productId, 'thumbnail', 0, '/t/h/thumb.jpg');

    oneImageRunUpgrade();

    expect(oneImageValues($this->productId, 'image'))->toBe([0 => '/s/m/small.jpg']);
});

it('copies thumbnail into an empty image when small_image is empty too', function () {
    oneImageSetValue($this->productId, 'small_image', 0, 'no_selection');
    oneImageSetValue($this->productId, 'thumbnail', 0, '/t/h/thumb.jpg');

    oneImageRunUpgrade();

    expect(oneImageValues($this->productId, 'image'))->toBe([0 => '/t/h/thumb.jpg']);
});

it('copies small_image into a store view whose image is empty', function () {
    oneImageSetValue($this->productId, 'image', 0, '/m/a/main.jpg');
    oneImageSetValue($this->productId, 'image', 1, 'no_selection');
    oneImageSetValue($this->productId, 'small_image', 1, '/s/m/store.jpg');

    oneImageRunUpgrade();

    expect(oneImageValues($this->productId, 'image'))->toBe([0 => '/m/a/main.jpg', 1 => '/s/m/store.jpg']);
});

it('keeps an image that is not empty', function () {
    oneImageSetValue($this->productId, 'image', 0, '/m/a/main.jpg');
    oneImageSetValue($this->productId, 'small_image', 0, '/s/m/small.jpg');
    oneImageSetValue($this->productId, 'small_image', 1, '/s/m/small-store.jpg');

    oneImageRunUpgrade();

    expect(oneImageValues($this->productId, 'image'))->toBe([0 => '/m/a/main.jpg']);
});

it('copies the label of small_image into an empty image label', function () {
    oneImageSetValue($this->productId, 'small_image', 0, '/s/m/small.jpg');
    oneImageSetValue($this->productId, 'small_image_label', 0, 'Front');
    oneImageSetValue($this->productId, 'thumbnail_label', 1, 'Side');

    oneImageRunUpgrade();

    expect(oneImageValues($this->productId, 'image_label'))->toBe([0 => 'Front']);
});

it('copies the placeholder of small_image to a scope without an image placeholder', function () {
    $this->setup->setConfigData('catalog/placeholder/small_image_placeholder', 'default/small.png', 'websites', 1);

    oneImageRunUpgrade();

    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    expect($adapter->fetchOne($adapter->select()
        ->from(Mage::getSingleton('core/resource')->getTableName('core/config_data'), ['value'])
        ->where('path = ?', 'catalog/placeholder/image_placeholder')
        ->where('scope = ?', 'websites')
        ->where('scope_id = ?', 1)))->toBe('default/small.png');
});

it('copies the watermark opacity of small_image to a scope without an image watermark', function () {
    $this->setup->setConfigData('design/watermark/small_image_image', 'w.png', 'websites', 1);
    $this->setup->setConfigData('design/watermark/small_image_imageOpacity', '40', 'websites', 1);

    oneImageRunUpgrade();

    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    $read = fn(string $path): string|false => $adapter->fetchOne($adapter->select()
        ->from(Mage::getSingleton('core/resource')->getTableName('core/config_data'), ['value'])
        ->where('path = ?', $path)
        ->where('scope = ?', 'websites')
        ->where('scope_id = ?', 1));
    expect($read('design/watermark/image_imageOpacity'))->toBe('40')
        ->and($read('design/watermark/image_image'))->toBe('w.png');
});
