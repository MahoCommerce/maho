<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sitemap
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $sitemap = $schema->createTable('sitemap');
    $sitemap->addColumn('sitemap_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $sitemap->addColumn('sitemap_type', Types::STRING, ['length' => 32, 'notnull' => false]);
    $sitemap->addColumn('sitemap_filename', Types::STRING, ['length' => 32, 'notnull' => false]);
    $sitemap->addColumn('sitemap_path', Types::STRING, ['length' => 255, 'notnull' => false]);
    $sitemap->addColumn('sitemap_time', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $sitemap->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $sitemap->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('sitemap_id')->create(),
    );
    $sitemap->addIndex(['store_id']);
    $sitemap->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $sitemap->setComment('Google Sitemap');
};
