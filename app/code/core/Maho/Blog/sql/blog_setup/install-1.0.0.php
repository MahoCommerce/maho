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
    'attribute_model'             => '',
    'table'                       => 'blog/post',
    'increment_model'             => '',
    'increment_per_store'         => 0,
    'increment_pad_length'        => 0,
    'additional_attribute_table'  => '',
    'entity_attribute_collection' => 'eav/entity_attribute_collection',
]);

/**
 * Create table 'blog/post'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('blog/post'))
    ->addColumn('entity_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Entity ID')
    ->addColumn('entity_type_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Entity Type ID')
    ->addColumn('attribute_set_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Attribute Set ID')
    ->addColumn('url_key', Varien_Db_Ddl_Table::TYPE_TEXT, 255, [
        'nullable'  => false,
    ], 'URL Key')
    ->addColumn('title', Varien_Db_Ddl_Table::TYPE_TEXT, 255, [
        'nullable'  => false,
    ], 'Title (static)')
    ->addColumn('is_active', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '1',
    ], 'Is Active (static)')
    ->addColumn('publish_date', Varien_Db_Ddl_Table::TYPE_DATE, null, [
        'nullable'  => true,
    ], 'Publish Date (static)')
    ->addColumn('content', Varien_Db_Ddl_Table::TYPE_TEXT, '2M', [
        'nullable'  => true,
    ], 'Content (static)')
    ->addColumn('meta_description', Varien_Db_Ddl_Table::TYPE_TEXT, '64k', [
        'nullable'  => true,
    ], 'Meta Description (static)')
    ->addColumn('meta_keywords', Varien_Db_Ddl_Table::TYPE_TEXT, '64k', [
        'nullable'  => true,
    ], 'Meta Keywords (static)')
    ->addColumn('meta_title', Varien_Db_Ddl_Table::TYPE_TEXT, 255, [
        'nullable'  => true,
    ], 'Meta Title (static)')
    ->addColumn('meta_robots', Varien_Db_Ddl_Table::TYPE_TEXT, 50, [
        'nullable'  => true,
    ], 'Meta Robots (static)')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
        'default'   => Varien_Db_Ddl_Table::TIMESTAMP_INIT,
    ], 'Created At')
    ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, [
        'nullable'  => false,
        'default'   => Varien_Db_Ddl_Table::TIMESTAMP_INIT_UPDATE,
    ], 'Updated At')
    ->addIndex(
        $installer->getIdxName('blog/post', ['entity_type_id']),
        ['entity_type_id'],
    )
    ->addIndex(
        $installer->getIdxName('blog/post', ['url_key']),
        ['url_key'],
    )
    ->addIndex(
        $installer->getIdxName('blog/post', ['is_active']),
        ['is_active'],
    )
    ->addIndex(
        $installer->getIdxName('blog/post', ['publish_date']),
        ['publish_date'],
    )
    ->addIndex(
        $installer->getIdxName('blog/post', ['is_active', 'publish_date']),
        ['is_active', 'publish_date'],
    )
    ->addIndex(
        $installer->getIdxName('blog/post', ['title']),
        ['title'],
    )
    ->addForeignKey(
        $installer->getFkName('blog/post', 'entity_type_id', 'eav/entity_type', 'entity_type_id'),
        'entity_type_id',
        $installer->getTable('eav/entity_type'),
        'entity_type_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->setComment('Blog Post Entity Table');
$installer->getConnection()->createTable($table);

/**
 * Create table 'blog/post_datetime'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable(['blog/post', 'datetime']))
    ->addColumn('value_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Value ID')
    ->addColumn('entity_type_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Entity Type ID')
    ->addColumn('attribute_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Attribute ID')
    ->addColumn('store_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Store ID')
    ->addColumn('entity_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Entity ID')
    ->addColumn('value', Varien_Db_Ddl_Table::TYPE_DATETIME, null, [
    ], 'Value')
    ->addIndex(
        $installer->getIdxName(
            ['blog/post', 'datetime'],
            ['entity_id', 'attribute_id', 'store_id'],
            Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE,
        ),
        ['entity_id', 'attribute_id', 'store_id'],
        ['type' => Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE],
    )
    ->addIndex(
        $installer->getIdxName(['blog/post', 'datetime'], ['attribute_id']),
        ['attribute_id'],
    )
    ->addIndex(
        $installer->getIdxName(['blog/post', 'datetime'], ['store_id']),
        ['store_id'],
    )
    ->addIndex(
        $installer->getIdxName(['blog/post', 'datetime'], ['entity_id']),
        ['entity_id'],
    )
    ->addForeignKey(
        $installer->getFkName(['blog/post', 'datetime'], 'attribute_id', 'eav/attribute', 'attribute_id'),
        'attribute_id',
        $installer->getTable('eav/attribute'),
        'attribute_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName(['blog/post', 'datetime'], 'entity_id', 'blog/post', 'entity_id'),
        'entity_id',
        $installer->getTable('blog/post'),
        'entity_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName(['blog/post', 'datetime'], 'store_id', 'core/store', 'store_id'),
        'store_id',
        $installer->getTable('core/store'),
        'store_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->setComment('Blog Post Datetime Attribute Backend Table');
$installer->getConnection()->createTable($table);

/**
 * Create table 'blog/post_int'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable(['blog/post', 'int']))
    ->addColumn('value_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Value ID')
    ->addColumn('entity_type_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Entity Type ID')
    ->addColumn('attribute_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Attribute ID')
    ->addColumn('store_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Store ID')
    ->addColumn('entity_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Entity ID')
    ->addColumn('value', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
    ], 'Value')
    ->addIndex(
        $installer->getIdxName(
            ['blog/post', 'int'],
            ['entity_id', 'attribute_id', 'store_id'],
            Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE,
        ),
        ['entity_id', 'attribute_id', 'store_id'],
        ['type' => Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE],
    )
    ->addIndex(
        $installer->getIdxName(['blog/post', 'int'], ['attribute_id']),
        ['attribute_id'],
    )
    ->addIndex(
        $installer->getIdxName(['blog/post', 'int'], ['store_id']),
        ['store_id'],
    )
    ->addIndex(
        $installer->getIdxName(['blog/post', 'int'], ['entity_id']),
        ['entity_id'],
    )
    ->addForeignKey(
        $installer->getFkName(['blog/post', 'int'], 'attribute_id', 'eav/attribute', 'attribute_id'),
        'attribute_id',
        $installer->getTable('eav/attribute'),
        'attribute_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName(['blog/post', 'int'], 'entity_id', 'blog/post', 'entity_id'),
        'entity_id',
        $installer->getTable('blog/post'),
        'entity_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName(['blog/post', 'int'], 'store_id', 'core/store', 'store_id'),
        'store_id',
        $installer->getTable('core/store'),
        'store_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->setComment('Blog Post Integer Attribute Backend Table');
$installer->getConnection()->createTable($table);

/**
 * Create table 'blog/post_text'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable(['blog/post', 'text']))
    ->addColumn('value_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Value ID')
    ->addColumn('entity_type_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Entity Type ID')
    ->addColumn('attribute_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Attribute ID')
    ->addColumn('store_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Store ID')
    ->addColumn('entity_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Entity ID')
    ->addColumn('value', Varien_Db_Ddl_Table::TYPE_TEXT, '64k', [
    ], 'Value')
    ->addIndex(
        $installer->getIdxName(
            ['blog/post', 'text'],
            ['entity_id', 'attribute_id', 'store_id'],
            Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE,
        ),
        ['entity_id', 'attribute_id', 'store_id'],
        ['type' => Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE],
    )
    ->addIndex(
        $installer->getIdxName(['blog/post', 'text'], ['attribute_id']),
        ['attribute_id'],
    )
    ->addIndex(
        $installer->getIdxName(['blog/post', 'text'], ['store_id']),
        ['store_id'],
    )
    ->addIndex(
        $installer->getIdxName(['blog/post', 'text'], ['entity_id']),
        ['entity_id'],
    )
    ->addForeignKey(
        $installer->getFkName(['blog/post', 'text'], 'attribute_id', 'eav/attribute', 'attribute_id'),
        'attribute_id',
        $installer->getTable('eav/attribute'),
        'attribute_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName(['blog/post', 'text'], 'entity_id', 'blog/post', 'entity_id'),
        'entity_id',
        $installer->getTable('blog/post'),
        'entity_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName(['blog/post', 'text'], 'store_id', 'core/store', 'store_id'),
        'store_id',
        $installer->getTable('core/store'),
        'store_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->setComment('Blog Post Text Attribute Backend Table');
$installer->getConnection()->createTable($table);

/**
 * Create table 'blog/post_varchar'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable(['blog/post', 'varchar']))
    ->addColumn('value_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Value ID')
    ->addColumn('entity_type_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Entity Type ID')
    ->addColumn('attribute_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Attribute ID')
    ->addColumn('store_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Store ID')
    ->addColumn('entity_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Entity ID')
    ->addColumn('value', Varien_Db_Ddl_Table::TYPE_TEXT, 255, [
    ], 'Value')
    ->addIndex(
        $installer->getIdxName(
            ['blog/post', 'varchar'],
            ['entity_id', 'attribute_id', 'store_id'],
            Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE,
        ),
        ['entity_id', 'attribute_id', 'store_id'],
        ['type' => Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE],
    )
    ->addIndex(
        $installer->getIdxName(['blog/post', 'varchar'], ['attribute_id']),
        ['attribute_id'],
    )
    ->addIndex(
        $installer->getIdxName(['blog/post', 'varchar'], ['store_id']),
        ['store_id'],
    )
    ->addIndex(
        $installer->getIdxName(['blog/post', 'varchar'], ['entity_id']),
        ['entity_id'],
    )
    ->addForeignKey(
        $installer->getFkName(['blog/post', 'varchar'], 'attribute_id', 'eav/attribute', 'attribute_id'),
        'attribute_id',
        $installer->getTable('eav/attribute'),
        'attribute_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName(['blog/post', 'varchar'], 'entity_id', 'blog/post', 'entity_id'),
        'entity_id',
        $installer->getTable('blog/post'),
        'entity_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName(['blog/post', 'varchar'], 'store_id', 'core/store', 'store_id'),
        'store_id',
        $installer->getTable('core/store'),
        'store_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->setComment('Blog Post Varchar Attribute Backend Table');
$installer->getConnection()->createTable($table);

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

// Create the blog_website table
$table = $installer->getConnection()
    ->newTable($installer->getTable('blog/post_store'))
    ->addColumn(
        'post_id',
        Varien_Db_Ddl_Table::TYPE_INTEGER,
        null,
        [
            'unsigned' => true,
            'nullable' => false,
            'primary' => true,
        ],
    )
    ->addColumn(
        'store_id',
        Varien_Db_Ddl_Table::TYPE_SMALLINT,
        null,
        [
            'unsigned' => true,
            'nullable' => false,
            'primary' => true,
        ],
    )
    ->addIndex(
        $installer->getIdxName(
            'blog/post_store',
            ['store_id'],
        ),
        ['store_id'],
    )
    ->addForeignKey(
        $installer->getFkName(
            'blog/post_store',
            'post_id',
            'blog/post',
            'entity_id',
        ),
        'post_id',
        $installer->getTable('blog/post'),
        'entity_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName(
            'blog/post_store',
            'store_id',
            'core/store',
            'store_id',
        ),
        'store_id',
        $installer->getTable('core/store'),
        'store_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->setComment('Blog Post To Store Linkage Table');
$installer->getConnection()->createTable($table);

$installer->endSetup();
