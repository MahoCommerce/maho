<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $scan = $schema->createTable('accessibilityscan_scan');
    $scan->addColumn('scan_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $scan->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $scan->addColumn('status', Types::STRING, ['length' => 20, 'default' => 'pending']);
    $scan->addColumn('wcag_level', Types::STRING, ['length' => 3, 'default' => 'AA']);
    $scan->addColumn('triggered_by', Types::STRING, ['length' => 16, 'default' => 'manual']);
    $scan->addColumn('url', Types::STRING, ['length' => 2048]);
    $scan->addColumn('total_violations', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $scan->addColumn('violations_critical', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $scan->addColumn('violations_serious', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $scan->addColumn('violations_moderate', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $scan->addColumn('violations_minor', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $scan->addColumn('error_message', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $scan->addColumn('started_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $scan->addColumn('completed_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $scan->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $scan->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('scan_id')->create(),
    );
    $scan->addIndex(['store_id']);
    $scan->addIndex(['status']);
    $scan->addIndex(['created_at']);
    $scan->setComment('Accessibility Scan Table');

    $page = $schema->createTable('accessibilityscan_page');
    $page->addColumn('page_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $page->addColumn('scan_id', Types::INTEGER, ['unsigned' => true]);
    $page->addColumn('url', Types::STRING, ['length' => 2048]);
    $page->addColumn('page_title', Types::STRING, ['length' => 255, 'notnull' => false]);
    $page->addColumn('status', Types::STRING, ['length' => 20, 'default' => 'pending']);
    $page->addColumn('screenshot_path', Types::STRING, ['length' => 255, 'notnull' => false]);
    $page->addColumn('page_width', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $page->addColumn('page_height', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $page->addColumn('violation_count', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $page->addColumn('scanned_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $page->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('page_id')->create(),
    );
    $page->addForeignKeyConstraint(
        'accessibilityscan_scan',
        ['scan_id'],
        ['scan_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $page->setComment('Accessibility Scan Page Table');

    $violation = $schema->createTable('accessibilityscan_violation');
    $violation->addColumn('violation_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $violation->addColumn('page_id', Types::INTEGER, ['unsigned' => true]);
    $violation->addColumn('scan_id', Types::INTEGER, ['unsigned' => true]);
    $violation->addColumn('axe_rule_id', Types::STRING, ['length' => 64]);
    $violation->addColumn('impact', Types::STRING, ['length' => 16, 'notnull' => false]);
    $violation->addColumn('wcag_level', Types::STRING, ['length' => 3, 'notnull' => false]);
    $violation->addColumn('wcag_criteria', Types::STRING, ['length' => 255, 'notnull' => false]);
    $violation->addColumn('description', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $violation->addColumn('help_url', Types::STRING, ['length' => 512, 'notnull' => false]);
    $violation->addColumn('html_snippet', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $violation->addColumn('css_selector', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $violation->addColumn('failure_summary', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $violation->addColumn('template_file', Types::STRING, ['length' => 255, 'notnull' => false]);
    $violation->addColumn('template_line', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $violation->addColumn('element_x', Types::INTEGER, ['notnull' => false]);
    $violation->addColumn('element_y', Types::INTEGER, ['notnull' => false]);
    $violation->addColumn('element_width', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $violation->addColumn('element_height', Types::INTEGER, ['unsigned' => true, 'notnull' => false]);
    $violation->addColumn('ai_suggestion', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $violation->addColumn('ai_diff', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $violation->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('violation_id')->create(),
    );
    $violation->addIndex(['axe_rule_id']);
    $violation->addIndex(['impact']);
    $violation->addForeignKeyConstraint(
        'accessibilityscan_page',
        ['page_id'],
        ['page_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $violation->addForeignKeyConstraint(
        'accessibilityscan_scan',
        ['scan_id'],
        ['scan_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $violation->setComment('Accessibility Scan Violation Table');
};
