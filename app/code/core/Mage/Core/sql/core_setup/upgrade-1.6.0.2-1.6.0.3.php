<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$table = $installer->getTable('core/translate');

$connection->dropIndex($table, $installer->getIdxName(
    'core/translate',
    ['store_id', 'locale', 'string'],
    Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE,
));

$connection->addColumn($table, 'crc_string', [
    'type'     => Maho\Db\Ddl\Table::TYPE_BIGINT,
    'nullable' => false,
    'default'  => crc32(Mage_Core_Model_Translate::DEFAULT_STRING),
    'comment'  => 'Translation String CRC32 Hash',
]);

$connection->addIndex($table, $installer->getIdxName(
    'core/translate',
    ['store_id', 'locale', 'crc_string', 'string'],
    Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE,
), ['store_id', 'locale', 'crc_string', 'string'], Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE);

$installer->endSetup();
