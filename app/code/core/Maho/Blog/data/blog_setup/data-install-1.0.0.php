<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

/** @var Mage_Eav_Model_Entity_Setup $this */
$installer = $this;
$installer->startSetup();

$installer->addEntityType('blog_post', [
    'entity_model'                => 'blog/post',
    'attribute_model'             => '',
    'table'                       => 'blog/post',
    'increment_model'             => '',
    'increment_per_store'         => 0,
    'increment_pad_length'        => 0,
    'additional_attribute_table'  => '',
    'entity_attribute_collection' => 'eav/entity_attribute_collection',
]);

// EAV attributes (only non-static attributes)
$attributes = [
    'store_id' => [
        'type' => 'int',
        'label' => 'Store ID',
        'input' => 'int',
        'required' => true,
        'sort_order' => 50,
        'backend_model' => 'blog/post_attribute_backend_store',
    ],
    'image' => [
        'type' => 'varchar',
        'label' => 'Image',
        'input' => 'image',
        'required' => false,
        'sort_order' => 15,
        'backend_model' => 'blog/post_attribute_backend_image',
    ],
];

foreach ($attributes as $code => $options) {
    $installer->addAttribute('blog_post', $code, [
        'type'              => $options['type'],
        'backend'           => $options['backend_model'],
        'frontend'          => '',
        'label'             => $options['label'],
        'input'             => $options['input'],
        'class'             => '',
        'source'            => '',
        'global'            => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
        'visible'           => true,
        'required'          => $options['required'],
        'user_defined'      => false,
        'group'             => 'General',
        'searchable'        => true,
        'filterable'        => true,
        'comparable'        => false,
        'visible_on_front'  => true,
        'unique'            => false,
        'sort_order'        => $options['sort_order'],
        'system'            => false,
    ]);
}

$installer->endSetup();
