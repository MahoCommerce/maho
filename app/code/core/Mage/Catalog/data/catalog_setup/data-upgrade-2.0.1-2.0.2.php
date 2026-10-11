<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

/** @var Mage_Catalog_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// A product has one image from this version on. The theme slots set the size of each place where
// it shows, so the small_image and thumbnail roles and their labels go, and the listings read image.
foreach (['small_image', 'thumbnail', 'small_image_label', 'thumbnail_label'] as $code) {
    $installer->removeAttribute(Mage_Catalog_Model_Product::ENTITY, $code);
}
$installer->updateAttribute(Mage_Catalog_Model_Product::ENTITY, 'image', 'used_in_product_listing', 1);

$paths = ['catalog/product_image/small_width'];
foreach (['small_image', 'thumbnail'] as $role) {
    $paths[] = "catalog/placeholder/{$role}_placeholder";
    foreach (['image', 'imageOpacity', 'size', 'position'] as $field) {
        $paths[] = "design/watermark/{$role}_{$field}";
    }
}
$installer->getConnection()->delete($installer->getTable('core/config_data'), ['path IN (?)' => $paths]);

$indexProcess = Mage::getSingleton('index/indexer')->getProcessByCode('catalog_product_flat');
if ($indexProcess) {
    $indexProcess->changeStatus(Mage_Index_Model_Process::STATUS_REQUIRE_REINDEX);
}

$installer->endSetup();
