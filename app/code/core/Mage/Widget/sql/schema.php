<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Widget
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $widget = $schema->createTable('widget');
    $widget->addColumn('widget_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $widget->addColumn('widget_code', Types::STRING, ['length' => 255, 'notnull' => false]);
    $widget->addColumn('widget_type', Types::STRING, ['length' => 255, 'notnull' => false]);
    $widget->addColumn('parameters', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $widget->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('widget_id')->create(),
    );
    $widget->addIndex(['widget_code']);
    $widget->setComment('Preconfigured Widgets');

    $instance = $schema->createTable('widget_instance');
    $instance->addColumn('instance_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $instance->addColumn('instance_type', Types::STRING, ['length' => 255, 'notnull' => false]);
    $instance->addColumn('package_theme', Types::STRING, ['length' => 255, 'notnull' => false]);
    $instance->addColumn('title', Types::STRING, ['length' => 255, 'notnull' => false]);
    $instance->addColumn('store_ids', Types::STRING, ['length' => 255, 'default' => '0']);
    $instance->addColumn('widget_parameters', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $instance->addColumn('sort_order', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $instance->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('instance_id')->create(),
    );
    $instance->setComment('Instances of Widget for Package Theme');

    $instancePage = $schema->createTable('widget_instance_page');
    $instancePage->addColumn('page_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $instancePage->addColumn('instance_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $instancePage->addColumn('page_group', Types::STRING, ['length' => 255, 'notnull' => false]);
    $instancePage->addColumn('layout_handle', Types::STRING, ['length' => 255, 'notnull' => false]);
    $instancePage->addColumn('block_reference', Types::STRING, ['length' => 255, 'notnull' => false]);
    $instancePage->addColumn('page_for', Types::STRING, ['length' => 255, 'notnull' => false]);
    $instancePage->addColumn('entities', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $instancePage->addColumn('page_template', Types::STRING, ['length' => 255, 'notnull' => false]);
    $instancePage->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('page_id')->create(),
    );
    $instancePage->addIndex(['instance_id']);
    $instancePage->addForeignKeyConstraint(
        'widget_instance',
        ['instance_id'],
        ['instance_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $instancePage->setComment('Instance of Widget on Page');

    $instancePageLayout = $schema->createTable('widget_instance_page_layout');
    $instancePageLayout->addColumn('page_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $instancePageLayout->addColumn('layout_update_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $instancePageLayout->addIndex(['page_id']);
    $instancePageLayout->addIndex(['layout_update_id']);
    $instancePageLayout->addUniqueIndex(['layout_update_id', 'page_id']);
    $instancePageLayout->addForeignKeyConstraint(
        'widget_instance_page',
        ['page_id'],
        ['page_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $instancePageLayout->addForeignKeyConstraint(
        'core_layout_update',
        ['layout_update_id'],
        ['layout_update_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $instancePageLayout->setComment('Layout updates');
};
