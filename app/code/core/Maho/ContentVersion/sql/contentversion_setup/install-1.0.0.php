<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ContentVersion
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();

/**
 * Create table 'content_version'
 * Stores JSON snapshots of CMS pages, CMS blocks, and blog posts before each edit
 */
$table = $connection
    ->newTable($installer->getTable('contentversion/version'))
    ->addColumn('version_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ], 'Version ID')
    ->addColumn('entity_type', Maho\Db\Ddl\Table::TYPE_VARCHAR, 50, [
        'nullable' => false,
    ], 'Entity Type (cms_page, cms_block, blog_post)')
    ->addColumn('entity_id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => false,
    ], 'Entity ID')
    ->addColumn('version_number', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => false,
    ], 'Version Number')
    ->addColumn('content_data', Maho\Db\Ddl\Table::TYPE_TEXT, '16M', [
        'nullable' => false,
    ], 'JSON Snapshot of Content')
    ->addColumn('editor', Maho\Db\Ddl\Table::TYPE_VARCHAR, 100, [
        'nullable' => true,
    ], 'Who Made the Edit')
    ->addColumn('created_at', Maho\Db\Ddl\Table::TYPE_TIMESTAMP, null, [
        'nullable' => false,
        'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
    ], 'Created At')
    ->addIndex(
        $installer->getIdxName(
            'contentversion/version',
            ['entity_type', 'entity_id', 'version_number'],
            Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE,
        ),
        ['entity_type', 'entity_id', 'version_number'],
        ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE],
    )
    ->addIndex(
        $installer->getIdxName('contentversion/version', ['entity_type', 'entity_id']),
        ['entity_type', 'entity_id'],
    )
    ->addIndex(
        $installer->getIdxName('contentversion/version', ['created_at']),
        ['created_at'],
    )
    ->setComment('Content Version History');

$connection->createTable($table);

$installer->endSetup();
