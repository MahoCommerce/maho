<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$tableName = $installer->getTable('maho_api_idempotency_keys');

if (!$connection->isTableExists($tableName)) {
    $table = $connection->newTable($tableName)
        ->addColumn('id', Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary'  => true,
        ], 'ID')
        ->addColumn('idempotency_key', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
            'nullable' => false,
        ], 'Idempotency Key')
        ->addColumn('user_scope', Maho\Db\Ddl\Table::TYPE_TEXT, 100, [
            'nullable' => false,
        ], 'User Scope (e.g. customer:123 or admin:5)')
        ->addColumn('request_path', Maho\Db\Ddl\Table::TYPE_TEXT, 255, [
            'nullable' => false,
        ], 'Request Path')
        ->addColumn('request_method', Maho\Db\Ddl\Table::TYPE_TEXT, 10, [
            'nullable' => false,
        ], 'Request Method')
        ->addColumn('response_code', Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
            'unsigned' => true,
            'nullable' => false,
        ], 'Response HTTP Status Code')
        ->addColumn('response_body', Maho\Db\Ddl\Table::TYPE_TEXT, '16M', [
            'nullable' => true,
        ], 'Response Body')
        ->addColumn('response_headers', Maho\Db\Ddl\Table::TYPE_TEXT, '64k', [
            'nullable' => true,
        ], 'Response Headers (JSON)')
        ->addColumn('created_at', Maho\Db\Ddl\Table::TYPE_DATETIME, null, [
            'nullable' => false,
        ], 'Created At')
        ->addIndex(
            $installer->getIdxName($tableName, ['idempotency_key', 'user_scope', 'request_path', 'request_method'], Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE),
            ['idempotency_key', 'user_scope', 'request_path', 'request_method'],
            ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE],
        )
        ->addIndex(
            $installer->getIdxName($tableName, ['created_at']),
            ['created_at'],
        )
        ->setComment('API Idempotency Keys');

    $connection->createTable($table);
}

$installer->endSetup();
