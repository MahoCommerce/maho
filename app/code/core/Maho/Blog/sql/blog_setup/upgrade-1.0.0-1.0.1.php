<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Eav_Model_Entity_Setup $this */
$installer = $this;
$installer->startSetup();

/**
 * Create table 'blog/eav_attribute' to store additional EAV attribute properties
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('blog/eav_attribute'))
    ->addColumn('attribute_id', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Attribute ID')
    ->addColumn('is_global', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '1',
    ], 'Is Global')
    ->addColumn('position', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'nullable'  => false,
        'default'   => '0',
    ], 'Position')
    ->addForeignKey(
        $installer->getFkName('blog/eav_attribute', 'attribute_id', 'eav/attribute', 'attribute_id'),
        'attribute_id',
        $installer->getTable('eav/attribute'),
        'attribute_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Blog EAV Attribute Table');
$installer->getConnection()->createTable($table);

// Update entity type configuration
$installer->updateEntityType('blog_post', [
    'attribute_model'            => 'blog/resource_eav_attribute',
    'additional_attribute_table' => 'blog/eav_attribute',
]);

// Insert rows into blog_eav_attribute for existing attributes
$connection = $installer->getConnection();
$entityTypeId = $installer->getEntityTypeId('blog_post');

// Get all blog_post attributes
$select = $connection->select()
    ->from($installer->getTable('eav/attribute'), ['attribute_id'])
    ->where('entity_type_id = ?', $entityTypeId);

$attributeIds = $connection->fetchCol($select);

// Insert default values for each attribute
foreach ($attributeIds as $attributeId) {
    $connection->insert(
        $installer->getTable('blog/eav_attribute'),
        [
            'attribute_id' => $attributeId,
            'is_global'    => Maho_Blog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
            'position'     => 0,
        ],
    );
}

$installer->endSetup();
