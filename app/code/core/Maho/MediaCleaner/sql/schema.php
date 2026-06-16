<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_MediaCleaner
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $image = $schema->createTable('mediacleaner_image');
    $image->addColumn('image_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $image->addColumn('type', Types::STRING, ['length' => 32]);
    $image->addColumn('path', Types::STRING, ['length' => 255]);
    $image->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => new CurrentTimestamp()]);
    $image->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('image_id')->create(),
    );
    $image->addUniqueIndex(['type', 'path']);
    $image->setComment('Media Cleaner orphan files');
};
