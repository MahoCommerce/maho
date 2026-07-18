<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ContentVersion
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $version = $schema->createTable('content_version');
    $version->addColumn('version_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $version->addColumn('entity_type', Types::STRING, ['length' => 50]);
    $version->addColumn('entity_id', Types::INTEGER, ['unsigned' => true]);
    $version->addColumn('version_number', Types::INTEGER, ['unsigned' => true]);
    $version->addColumn('content_data', Types::TEXT, ['length' => 16777215]);
    $version->addColumn('editor', Types::STRING, ['length' => 100, 'notnull' => false]);
    $version->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $version->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('version_id')->create(),
    );
    $version->addUniqueIndex(
        ['entity_type', 'entity_id', 'version_number'],
    );
    $version->addIndex(['entity_type', 'entity_id']);
    $version->addIndex(['created_at']);
    $version->setComment('Content Version History');
};
