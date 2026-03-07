<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Maho\Db\Ddl\Table;

/** @var Mage_Core_Model_Resource_Setup $this */
$this->startSetup();

$connection = $this->getConnection();

$table = $connection->newTable($this->getTable('core/email_log'))
    ->addColumn('log_id', Table::TYPE_INTEGER, null, [
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ], 'Log ID')
    ->addColumn('subject', Table::TYPE_TEXT, 255, [
        'nullable' => false,
        'default'  => '',
    ], 'Email Subject')
    ->addColumn('email_to', Table::TYPE_TEXT, null, [
        'nullable' => false,
    ], 'To Recipients')
    ->addColumn('email_from', Table::TYPE_TEXT, 255, [
        'nullable' => false,
        'default'  => '',
    ], 'From Address')
    ->addColumn('email_cc', Table::TYPE_TEXT, null, [
        'nullable' => true,
    ], 'CC Recipients')
    ->addColumn('email_bcc', Table::TYPE_TEXT, null, [
        'nullable' => true,
    ], 'BCC Recipients')
    ->addColumn('template', Table::TYPE_TEXT, 255, [
        'nullable' => true,
    ], 'Template ID')
    ->addColumn('content_type', Table::TYPE_TEXT, 4, [
        'nullable' => false,
        'default'  => 'html',
    ], 'Content Type')
    ->addColumn('email_body', Table::TYPE_TEXT, 16777215, [
        'nullable' => false,
    ], 'Email Body')
    ->addColumn('status', Table::TYPE_TEXT, 10, [
        'nullable' => false,
        'default'  => 'sent',
    ], 'Status')
    ->addColumn('error_message', Table::TYPE_TEXT, null, [
        'nullable' => true,
    ], 'Error Message')
    ->addColumn('created_at', Table::TYPE_TIMESTAMP, null, [
        'nullable' => false,
        'default'  => Table::TIMESTAMP_INIT,
    ], 'Created At')
    ->addIndex(
        $this->getIdxName('core/email_log', ['created_at']),
        ['created_at'],
    )
    ->addIndex(
        $this->getIdxName('core/email_log', ['status']),
        ['status'],
    )
    ->setComment('Email Log');

$connection->createTable($table);

$this->endSetup();
