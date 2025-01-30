<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Eav_Model_Entity_Setup $this */
$installer = $this;
$installer->startSetup();

$installer->addEntityType('blog_post', [
    'entity_model'                => 'blog/post',
    'attribute_model'             => 'blog_post/attribute',
    'table'                       => 'blog/post',
    'increment_model'             => 'eav/entity_increment_numeric',
    'increment_per_store'         => 0,
    'increment_pad_length'        => 0,
    'additional_attribute_table'  => 'blog_post/eav_attribute',
    'entity_attribute_collection' => 'blog_post/attribute_collection',
]);

$installer->createEntityTables(
    $installer->getTable('blog/post'),
);

// Common attribute properties
$attributes = [
    'title' => [
        'type' => 'varchar',
        'label' => 'Title',
        'input' => 'text',
        'required' => true,
        'sort_order' => 10,
    ],
    'content' => [
        'type' => 'text',
        'label' => 'Content',
        'input' => 'textarea',
        'required' => true,
        'sort_order' => 20,
    ],
    'publish_date' => [
        'type' => 'datetime',
        'label' => 'Publish Date',
        'input' => 'date',
        'required' => false,
        'sort_order' => 30,
    ],
    'created_at' => [
        'type' => 'datetime',
        'label' => 'Creation Time',
        'input' => 'date',
        'required' => true,
        'sort_order' => 40,
    ],
];

foreach ($attributes as $code => $options) {
    $installer->addAttribute('blog_post', $code, [
        'type'              => $options['type'],
        'backend'           => '',
        'frontend'          => '',
        'label'             => $options['label'],
        'input'             => $options['input'],
        'class'             => '',
        'source'            => '',
        'global'            => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
        'visible'           => true,
        'required'          => $options['required'],
        'user_defined'      => false,
        'searchable'        => true,
        'filterable'        => true,
        'comparable'        => false,
        'visible_on_front'  => true,
        'unique'            => false,
        'sort_order'        => $options['sort_order'],
    ]);
}

$installer->endSetup();
