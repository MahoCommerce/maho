<?php

/**
 * Maho
 *
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Eav_Model_Entity_Setup $this */
$installer = $this;
$installer->startSetup();

/**
 * Create table 'eav/attribute_option_swatch'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('eav/attribute_option_swatch'))
    ->addColumn('value_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Value Id')
    ->addColumn('option_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Option Id')
    ->addColumn('value', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
        'nullable'  => true,
        'default'   => null,
    ], 'Value')
    ->addColumn('filename', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
        'nullable'  => true,
        'default'   => null,
    ], 'Filename')
    ->addIndex(
        $installer->getIdxName('eav/attribute_option_swatch', ['option_id']),
        ['option_id'],
        ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE],
    )
    ->addForeignKey(
        $installer->getFkName('eav/attribute_option_swatch', 'option_id', 'eav/attribute_option', 'option_id'),
        'option_id',
        $installer->getTable('eav/attribute_option'),
        'option_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Eav Attribute Option Swatch');
$installer->getConnection()->createTable($table);
