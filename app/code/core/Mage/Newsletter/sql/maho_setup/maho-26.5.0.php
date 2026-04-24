<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Newsletter
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

// Force explicit DEFAULT on TYPE_TIMESTAMP columns originally declared without one.
// On MySQL with `explicit_defaults_for_timestamp = OFF` such TIMESTAMP NOT NULL columns
// silently receive `DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`, which is
// engine-specific (PgSQL/SQLite don't do it). See issue #857.
if ($installer->getConnection() instanceof \Maho\Db\Adapter\Pdo\Mysql) {
    $installer->getConnection()->modifyColumn(
        $installer->getTable('newsletter/queue'),
        'queue_finish_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => true,
            'default'  => null,
            'comment'  => 'Queue Finish At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('newsletter/queue'),
        'queue_start_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => true,
            'default'  => null,
            'comment'  => 'Queue Start At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('newsletter/queue_link'),
        'letter_sent_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => true,
            'default'  => null,
            'comment'  => 'Letter Sent At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('newsletter/subscriber'),
        'change_status_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => true,
            'default'  => null,
            'comment'  => 'Change Status At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('newsletter/template'),
        'added_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Added At',
        ],
    );
    $installer->getConnection()->modifyColumn(
        $installer->getTable('newsletter/template'),
        'modified_at',
        [
            'type'     => Maho\Db\Ddl\Table::TYPE_TIMESTAMP,
            'nullable' => false,
            'default'  => Maho\Db\Ddl\Table::TIMESTAMP_INIT,
            'comment'  => 'Modified At',
        ],
    );
}

$installer->endSetup();
