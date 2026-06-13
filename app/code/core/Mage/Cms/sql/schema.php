<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $block = $schema->createTable('cms_block');
    $block->addColumn('block_id', Types::SMALLINT, ['autoincrement' => true]);
    $block->addColumn('title', Types::STRING, ['length' => 255]);
    $block->addColumn('identifier', Types::STRING, ['length' => 255]);
    $block->addColumn('content', Types::TEXT, ['length' => 2097152, 'notnull' => false]);
    $block->addColumn('creation_time', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $block->addColumn('update_time', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $block->addColumn('is_active', Types::SMALLINT, ['default' => 1]);
    $block->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('block_id')->create(),
    );
    $block->setComment('CMS Block Table');

    $blockStore = $schema->createTable('cms_block_store');
    $blockStore->addColumn('block_id', Types::SMALLINT);
    $blockStore->addColumn('store_id', Types::SMALLINT, ['unsigned' => true]);
    $blockStore->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('block_id', 'store_id')->create(),
    );
    $blockStore->addIndex(['store_id']);
    $blockStore->addForeignKeyConstraint(
        'cms_block',
        ['block_id'],
        ['block_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $blockStore->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $blockStore->setComment('CMS Block To Store Linkage Table');

    $page = $schema->createTable('cms_page');
    $page->addColumn('page_id', Types::SMALLINT, ['autoincrement' => true]);
    $page->addColumn('title', Types::STRING, ['length' => 255, 'notnull' => false]);
    $page->addColumn('root_template', Types::STRING, ['length' => 255, 'notnull' => false]);
    $page->addColumn('meta_keywords', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $page->addColumn('meta_description', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $page->addColumn('identifier', Types::STRING, ['length' => 100, 'notnull' => false, 'default' => null]);
    $page->addColumn('content_heading', Types::STRING, ['length' => 255, 'notnull' => false]);
    $page->addColumn('content', Types::TEXT, ['length' => 2097152, 'notnull' => false]);
    $page->addColumn('creation_time', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $page->addColumn('update_time', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $page->addColumn('is_active', Types::SMALLINT, ['default' => 1]);
    $page->addColumn('sort_order', Types::SMALLINT, ['default' => 0]);
    $page->addColumn('layout_update_xml', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $page->addColumn('custom_theme', Types::STRING, ['length' => 100, 'notnull' => false]);
    $page->addColumn('custom_root_template', Types::STRING, ['length' => 255, 'notnull' => false]);
    $page->addColumn('custom_layout_update_xml', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $page->addColumn('custom_theme_from', Types::DATE_MUTABLE, ['notnull' => false]);
    $page->addColumn('custom_theme_to', Types::DATE_MUTABLE, ['notnull' => false]);
    $page->addColumn('meta_robots', Types::STRING, ['length' => 50, 'notnull' => false]);
    $page->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('page_id')->create(),
    );
    $page->addIndex(['identifier']);
    $page->setComment('CMS Page Table');

    $pageStore = $schema->createTable('cms_page_store');
    $pageStore->addColumn('page_id', Types::SMALLINT);
    $pageStore->addColumn('store_id', Types::SMALLINT, ['unsigned' => true]);
    $pageStore->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('page_id', 'store_id')->create(),
    );
    $pageStore->addIndex(['store_id']);
    $pageStore->addForeignKeyConstraint(
        'cms_page',
        ['page_id'],
        ['page_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $pageStore->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
    );
    $pageStore->setComment('CMS Page To Store Linkage Table');
};
