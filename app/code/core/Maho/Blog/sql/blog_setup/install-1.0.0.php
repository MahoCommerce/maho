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

/*
$installer->getConnection()->query('drop table blog_post_entity');
$installer->getConnection()->query('drop table blog_post_entity_char');
$installer->getConnection()->query('drop table blog_post_entity_datetime');
$installer->getConnection()->query('drop table blog_post_entity_decimal');
$installer->getConnection()->query('drop table blog_post_entity_int');
$installer->getConnection()->query('drop table blog_post_entity_text');
$installer->getConnection()->query('drop table blog_post_entity_varchar');
$installer->getConnection()->query('delete from eav_entity_type where entity_type_code="blog_post"');
*/

$installer->addEntityType('blog_post', [
    'entity_model'                => 'blog/post',
    'attribute_model'             => '',
    'table'                       => 'blog/post',
    'increment_model'             => '',
    'increment_per_store'         => 0,
    'increment_pad_length'        => 0,
    'additional_attribute_table'  => '',
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
    'url_key' => [
        'type' => 'varchar',
        'label' => 'URL Key',
        'input' => 'text',
        'required' => true,
        'sort_order' => 20,
    ],
    'content' => [
        'type' => 'text',
        'label' => 'Content',
        'input' => 'textarea',
        'required' => true,
        'sort_order' => 30,
    ],
    'publish_date' => [
        'type' => 'datetime',
        'label' => 'Publish Date',
        'input' => 'date',
        'required' => false,
        'sort_order' => 40,
    ],
    'created_at' => [
        'type' => 'datetime',
        'label' => 'Creation Time',
        'input' => 'date',
        'required' => true,
        'sort_order' => 50,
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
        'group'             => 'General',
        'searchable'        => true,
        'filterable'        => true,
        'comparable'        => false,
        'visible_on_front'  => true,
        'unique'            => false,
        'sort_order'        => $options['sort_order'],
    ]);
}

// Create the blog_website table
$table = $installer->getConnection()
    ->newTable($installer->getTable('blog/post_store'))
    ->addColumn(
        'blog_post_id',
        Varien_Db_Ddl_Table::TYPE_INTEGER,
        null,
        [
            'unsigned' => true,
            'nullable' => false,
            'primary' => true,
        ]
    )
    ->addColumn(
        'store_id',
        Varien_Db_Ddl_Table::TYPE_SMALLINT,
        null,
        [
            'unsigned' => true,
            'nullable' => false,
            'primary' => true,
        ]
    )
    ->addIndex(
        $installer->getIdxName(
            'blog/post_store',
            ['store_id']
        ),
        ['store_id']
    )
    ->addForeignKey(
        $installer->getFkName(
            'blog/post_store',
            'blog_post_id',
            'blog/post',
            'entity_id'
        ),
        'blog_post_id',
        $installer->getTable('blog/post'),
        'entity_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE
    )
    ->addForeignKey(
        $installer->getFkName(
            'blog/post_store',
            'store_id',
            'core/store',
            'store_id'
        ),
        'store_id',
        $installer->getTable('core/store'),
        'store_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE
    )
    ->setComment('Blog Post To Store Linkage Table');
$installer->getConnection()->createTable($table);

$installer->endSetup();
