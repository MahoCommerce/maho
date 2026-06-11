<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

/** @var Mage_Eav_Model_Entity_Setup $this */
$installer = $this;
$installer->startSetup();

/**
 * Register blog_category entity type
 */
$installer->addEntityType('blog_category', [
    'entity_model'                => 'blog/category',
    'attribute_model'             => '',
    'table'                       => 'blog/category',
    'increment_model'             => '',
    'increment_per_store'         => 0,
    'increment_pad_length'        => 0,
    'additional_attribute_table'  => '',
    'entity_attribute_collection' => 'eav/entity_attribute_collection',
]);

$installer->endSetup();
