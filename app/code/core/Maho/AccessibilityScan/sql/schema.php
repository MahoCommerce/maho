<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\SchemaEditor;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Maho\Db\Schema;

return function (SchemaEditor $schema): void {
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('accessibilityscan_scan')
            ->addColumn(Schema::column('scan_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('store_id', Types::SMALLINT, unsigned: true, default: 0))
            ->addColumn(Schema::column('status', Types::STRING, length: 20, default: 'pending'))
            ->addColumn(Schema::column('wcag_level', Types::STRING, length: 3, default: 'AA'))
            ->addColumn(Schema::column('triggered_by', Types::STRING, length: 16, default: 'manual'))
            ->addColumn(Schema::column('url', Types::STRING, length: 2048))
            ->addColumn(Schema::column('total_violations', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('violations_critical', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('violations_serious', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('violations_moderate', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('violations_minor', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('incomplete_count', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('error_message', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('started_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('completed_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addColumn(Schema::column('created_at', Types::DATETIME_MUTABLE, default: new CurrentTimestamp()))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('scan_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('store_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('status'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('created_at'))
            ->setComment('Accessibility Scan Table')
            ->create(),
    );

    $schema->addTable(
        Table::editor()
            ->setUnquotedName('accessibilityscan_page')
            ->addColumn(Schema::column('page_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('scan_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('viewport', Types::STRING, length: 16, default: 'desktop'))
            ->addColumn(Schema::column('url', Types::STRING, length: 2048))
            ->addColumn(Schema::column('page_title', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('status', Types::STRING, length: 20, default: 'pending'))
            ->addColumn(Schema::column('screenshot_path', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('page_width', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('page_height', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('violation_count', Types::INTEGER, unsigned: true, default: 0))
            ->addColumn(Schema::column('scanned_at', Types::DATETIME_MUTABLE, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('page_id')->create())
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('scan_id')
                    ->setUnquotedReferencedTableName('accessibilityscan_scan')
                    ->setUnquotedReferencedColumnNames('scan_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Accessibility Scan Page Table')
            ->create(),
    );

    // One row per distinct issue (axe rule + selector), deduplicated across
    // viewports: the viewports column lists where it occurred, element_rects
    // holds the per-viewport bounding boxes as JSON
    $schema->addTable(
        Table::editor()
            ->setUnquotedName('accessibilityscan_violation')
            ->addColumn(Schema::column('violation_id', Types::INTEGER, unsigned: true, autoincrement: true))
            ->addColumn(Schema::column('scan_id', Types::INTEGER, unsigned: true))
            ->addColumn(Schema::column('axe_rule_id', Types::STRING, length: 64))
            ->addColumn(Schema::column('impact', Types::STRING, length: 16, notNull: false))
            ->addColumn(Schema::column('wcag_level', Types::STRING, length: 3, notNull: false))
            ->addColumn(Schema::column('wcag_criteria', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('description', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('help_url', Types::STRING, length: 512, notNull: false))
            ->addColumn(Schema::column('html_snippet', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('css_selector', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('failure_summary', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('template_file', Types::STRING, length: 255, notNull: false))
            ->addColumn(Schema::column('template_line', Types::INTEGER, unsigned: true, notNull: false))
            ->addColumn(Schema::column('viewports', Types::STRING, length: 64, default: ''))
            ->addColumn(Schema::column('element_rects', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('ai_suggestion', Types::TEXT, length: 65535, notNull: false))
            ->addColumn(Schema::column('ai_diff', Types::TEXT, length: 65535, notNull: false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('violation_id')->create())
            ->addIndex(Index::editor()->setUnquotedColumnNames('axe_rule_id'))
            ->addIndex(Index::editor()->setUnquotedColumnNames('impact'))
            ->addForeignKeyConstraint(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('scan_id')
                    ->setUnquotedReferencedTableName('accessibilityscan_scan')
                    ->setUnquotedReferencedColumnNames('scan_id')
                    ->setOnUpdateAction(ReferentialAction::CASCADE)
                    ->setOnDeleteAction(ReferentialAction::CASCADE)
                    ->create(),
            )
            ->setComment('Accessibility Scan Violation Table')
            ->create(),
    );
};
