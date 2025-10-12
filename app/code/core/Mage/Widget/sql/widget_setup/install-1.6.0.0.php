<?php

/**
 * Maho
 *
 * @package    Mage_Widget
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

/**
 * Create table 'widget/widget'
 */
if (!$installer->getConnection()->isTableExists($installer->getTable('widget/widget'))) {
    $table = $installer->getConnection()
        ->newTable($installer->getTable('widget/widget'))
        ->addColumn('widget_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'identity'  => true,
            'unsigned'  => true,
            'nullable'  => false,
            'primary'   => true,
        ], 'Widget Id')
        ->addColumn('widget_code', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
        ], 'Widget code for template directive')
        ->addColumn('widget_type', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
        ], 'Widget Type')
        ->addColumn('parameters', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
            'nullable'  => true,
        ], 'Parameters')
        ->addIndex($installer->getIdxName('widget/widget', 'widget_code'), 'widget_code')
        ->setComment('Preconfigured Widgets');
    $installer->getConnection()->createTable($table);
} else {
    $installer->getConnection()->dropIndex(
        $installer->getTable('widget/widget'),
        'IDX_CODE',
    );

    $tables = [
        $installer->getTable('widget/widget') => [
            'columns' => [
                'widget_id' => [
                    'type'      => Maho\Db\Ddl\Table::TYPE_INTEGER,
                    'identity'  => true,
                    'unsigned'  => true,
                    'nullable'  => false,
                    'primary'   => true,
                    'comment'   => 'Widget Id',
                ],
                'parameters' => [
                    'type'      => Maho\Db\Ddl\Table::TYPE_TEXT,
                    'length'    => '64K',
                    'comment'   => 'Parameters',
                ],
            ],
            'comment' => 'Preconfigured Widgets',
        ],
    ];

    $installer->getConnection()->modifyTables($tables);

    $installer->getConnection()->changeColumn(
        $installer->getTable('widget/widget'),
        'code',
        'widget_code',
        [
            'type'      => Maho\Db\Ddl\Table::TYPE_TEXT,
            'length'    => 255,
            'comment'   => 'Widget code for template directive',
        ],
    );

    $installer->getConnection()->changeColumn(
        $installer->getTable('widget/widget'),
        'type',
        'widget_type',
        [
            'type'      => Maho\Db\Ddl\Table::TYPE_TEXT,
            'length'    => 255,
            'comment'   => 'Widget Type',
        ],
    );

    $installer->getConnection()->addIndex(
        $installer->getTable('widget/widget'),
        $installer->getIdxName('widget/widget', ['widget_code']),
        ['widget_code'],
    );
}

/**
 * Create table 'widget/widget_instance'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('widget/widget_instance'))
    ->addColumn('instance_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Instance Id')
    ->addColumn('instance_type', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'Instance Type')
    ->addColumn('package_theme', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'Package Theme')
    ->addColumn('title', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'Widget Title')
    ->addColumn('store_ids', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
        'nullable'  => false,
        'default'   => '0',
    ], 'Store ids')
    ->addColumn('widget_parameters', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
    ], 'Widget parameters')
    ->addColumn('sort_order', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Sort order')
    ->setComment('Instances of Widget for Package Theme');
$installer->getConnection()->createTable($table);

/**
 * Create table 'widget/widget_instance_page'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('widget/widget_instance_page'))
    ->addColumn('page_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ], 'Page Id')
    ->addColumn('instance_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Instance Id')
    ->addColumn('page_group', Maho\Db\Ddl\Table::TYPE_TEXT, 25, [
    ], 'Block Group Type')
    ->addColumn('layout_handle', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'Layout Handle')
    ->addColumn('block_reference', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'Block Reference')
    ->addColumn('page_for', Maho\Db\Ddl\Table::TYPE_TEXT, 25, [
    ], 'For instance entities')
    ->addColumn('entities', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
    ], 'Catalog entities (comma separated)')
    ->addColumn('page_template', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
    ], 'Path to widget template')
    ->addIndex($installer->getIdxName('widget/widget_instance_page', 'instance_id'), 'instance_id')
    ->addForeignKey(
        $installer->getFkName('widget/widget_instance_page', 'instance_id', 'widget/widget_instance', 'instance_id'),
        'instance_id',
        $installer->getTable('widget/widget_instance'),
        'instance_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Instance of Widget on Page');
$installer->getConnection()->createTable($table);

/**
 * Create table 'widget/widget_instance_page_layout'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('widget/widget_instance_page_layout'))
    ->addColumn('page_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Page Id')
    ->addColumn('layout_update_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned'  => true,
        'nullable'  => false,
        'default'   => '0',
    ], 'Layout Update Id')
    ->addIndex($installer->getIdxName('widget/widget_instance_page_layout', 'page_id'), 'page_id')
    ->addIndex($installer->getIdxName('widget/widget_instance_page_layout', 'layout_update_id'), 'layout_update_id')
    ->addIndex(
        $installer->getIdxName('widget/widget_instance_page_layout', ['layout_update_id', 'page_id'], Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE),
        ['layout_update_id', 'page_id'],
        ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE],
    )
    ->addForeignKey(
        $installer->getFkName('widget/widget_instance_page_layout', 'page_id', 'widget/widget_instance_page', 'page_id'),
        'page_id',
        $installer->getTable('widget/widget_instance_page'),
        'page_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName('widget/widget_instance_page_layout', 'layout_update_id', 'core/layout_update', 'layout_update_id'),
        'layout_update_id',
        $installer->getTable('core/layout_update'),
        'layout_update_id',
        Maho\Db\Ddl\Table::ACTION_CASCADE,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    )
    ->setComment('Layout updates');
$installer->getConnection()->createTable($table);

$installer->endSetup();
