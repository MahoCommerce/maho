<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CatalogLinkRule
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Maho\Db\Ddl\Table;

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$table = $installer->getConnection()->newTable($installer->getTable('cataloglinkrule/rule'));
$table->addColumn('rule_id', Table::TYPE_INTEGER, null, [
    'identity' => true,
    'unsigned' => true,
    'nullable' => false,
    'primary'  => true,
], 'Rule ID')
->addColumn('name', Table::TYPE_VARCHAR, 255, [
    'nullable' => false,
], 'Rule Name')
->addColumn('description', Table::TYPE_TEXT, null, [
    'nullable' => true,
], 'Rule Description')
->addColumn('link_type_id', Table::TYPE_SMALLINT, null, [
    'unsigned' => true,
    'nullable' => false,
], 'Link Type ID (1=related, 4=upsell, 5=crosssell)')
->addColumn('is_active', Table::TYPE_SMALLINT, null, [
    'nullable' => false,
    'default'  => 0,
], 'Is Active')
->addColumn('priority', Table::TYPE_INTEGER, null, [
    'nullable' => false,
    'default'  => 0,
], 'Priority (Lower number = higher priority)')
->addColumn('sort_order', Table::TYPE_VARCHAR, 50, [
    'nullable' => false,
    'default'  => 'random',
], 'Sort Order')
->addColumn('max_links', Table::TYPE_INTEGER, null, [
    'unsigned' => true,
    'nullable' => true,
], 'Maximum linked products per source (NULL = unlimited)')
->addColumn('from_date', Table::TYPE_DATE, null, [
    'nullable' => true,
], 'From Date')
->addColumn('to_date', Table::TYPE_DATE, null, [
    'nullable' => true,
], 'To Date')
->addColumn('source_conditions_serialized', Table::TYPE_TEXT, null, [
    'nullable' => true,
], 'Source product conditions')
->addColumn('target_conditions_serialized', Table::TYPE_TEXT, null, [
    'nullable' => true,
], 'Target product conditions')
->addColumn('created_at', Table::TYPE_TIMESTAMP, null, [
    'nullable' => false,
    'default'  => Table::TIMESTAMP_INIT,
], 'Created At')
->addColumn('updated_at', Table::TYPE_TIMESTAMP, null, [
    'nullable' => false,
    'default'  => Table::TIMESTAMP_INIT_UPDATE,
], 'Updated At')
->addIndex(
    $installer->getIdxName('cataloglinkrule/rule', ['is_active', 'priority', 'link_type_id']),
    ['is_active', 'priority', 'link_type_id'],
)
->setComment('Catalog Product Link Rules');

$installer->getConnection()->createTable($table);

$installer->endSetup();
