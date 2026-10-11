<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('leaves the small_image and thumbnail roles out of the gallery roles and the config fields when they exist', function (): void {
    $setup = new Mage_Catalog_Model_Resource_Setup('catalog_setup');
    $setup->addAttribute(Mage_Catalog_Model_Product::ENTITY, 'thumbnail', [
        'type' => 'varchar',
        'label' => 'Thumbnail',
        'input' => 'media_image',
        'group' => 'Images',
    ]);
    Mage::getSingleton('eav/config')->clear();

    try {
        $product = Mage::getModel('catalog/product')->setAttributeSetId(
            (int) Mage::getModel('catalog/product')->getDefaultAttributeSetId(),
        );
        $prefixes = array_column(Mage::getModel('adminhtml/system_config_clone_media_image')->getPrefixes(), 'field');

        expect(array_keys($product->getMediaAttributes()))->toContain('image')->not->toContain('thumbnail')
            ->and($prefixes)->toContain('image_')->not->toContain('thumbnail_');
    } finally {
        $setup->removeAttribute(Mage_Catalog_Model_Product::ENTITY, 'thumbnail');
        Mage::getSingleton('eav/config')->clear();
    }
});
