<?php

/**
 * Maho
 *
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $post = $schema->createTable('blog_post_entity');
    $post->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $post->addColumn('entity_type_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $post->addColumn('attribute_set_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $post->addColumn('url_key', Types::STRING, ['length' => 255]);
    $post->addColumn('title', Types::STRING, ['length' => 255]);
    $post->addColumn('is_active', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $post->addColumn('publish_date', Types::DATE_MUTABLE);
    $post->addColumn('content', Types::TEXT, ['length' => 2097152, 'notnull' => false]);
    $post->addColumn('meta_description', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $post->addColumn('meta_keywords', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $post->addColumn('meta_title', Types::STRING, ['length' => 255, 'notnull' => false]);
    $post->addColumn('meta_robots', Types::STRING, ['length' => 50, 'notnull' => false]);
    $post->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => 'CURRENT_TIMESTAMP']);
    $post->addColumn('updated_at', Types::DATETIME_MUTABLE, ['default' => 'CURRENT_TIMESTAMP']);
    $post->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $post->addIndex(['entity_type_id'], 'idx_blog_post_entity_entity_type_id');
    $post->addIndex(['url_key'], 'idx_blog_post_entity_url_key');
    $post->addIndex(['is_active'], 'idx_blog_post_entity_is_active');
    $post->addIndex(['publish_date'], 'idx_blog_post_entity_publish_date');
    $post->addIndex(['is_active', 'publish_date'], 'idx_blog_post_entity_is_active_publish_date');
    $post->addIndex(['title'], 'idx_blog_post_entity_title');
    $post->addForeignKeyConstraint(
        'eav_entity_type',
        ['entity_type_id'],
        ['entity_type_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_blog_post_entity_entity_type',
    );
    $post->setComment('Blog Post Entity Table');

    $postDatetime = $schema->createTable('blog_post_entity_datetime');
    $postDatetime->addColumn('value_id', Types::INTEGER, ['autoincrement' => true]);
    $postDatetime->addColumn('entity_type_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $postDatetime->addColumn('attribute_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $postDatetime->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $postDatetime->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $postDatetime->addColumn('value', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $postDatetime->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create(),
    );
    $postDatetime->addUniqueIndex(['entity_id', 'attribute_id', 'store_id'], 'unq_blog_post_entity_datetime_entity_attr_store');
    $postDatetime->addIndex(['attribute_id'], 'idx_blog_post_entity_datetime_attribute_id');
    $postDatetime->addIndex(['store_id'], 'idx_blog_post_entity_datetime_store_id');
    $postDatetime->addIndex(['entity_id'], 'idx_blog_post_entity_datetime_entity_id');
    $postDatetime->addForeignKeyConstraint(
        'eav_attribute',
        ['attribute_id'],
        ['attribute_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_blog_post_entity_datetime_attribute',
    );
    $postDatetime->addForeignKeyConstraint(
        'blog_post_entity',
        ['entity_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_blog_post_entity_datetime_entity',
    );
    $postDatetime->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_blog_post_entity_datetime_store',
    );
    $postDatetime->setComment('Blog Post Datetime Attribute Backend Table');

    $postInt = $schema->createTable('blog_post_entity_int');
    $postInt->addColumn('value_id', Types::INTEGER, ['autoincrement' => true]);
    $postInt->addColumn('entity_type_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $postInt->addColumn('attribute_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $postInt->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $postInt->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $postInt->addColumn('value', Types::INTEGER, ['notnull' => false]);
    $postInt->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create(),
    );
    $postInt->addUniqueIndex(['entity_id', 'attribute_id', 'store_id'], 'unq_blog_post_entity_int_entity_attr_store');
    $postInt->addIndex(['attribute_id'], 'idx_blog_post_entity_int_attribute_id');
    $postInt->addIndex(['store_id'], 'idx_blog_post_entity_int_store_id');
    $postInt->addIndex(['entity_id'], 'idx_blog_post_entity_int_entity_id');
    $postInt->addForeignKeyConstraint(
        'eav_attribute',
        ['attribute_id'],
        ['attribute_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_blog_post_entity_int_attribute',
    );
    $postInt->addForeignKeyConstraint(
        'blog_post_entity',
        ['entity_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_blog_post_entity_int_entity',
    );
    $postInt->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_blog_post_entity_int_store',
    );
    $postInt->setComment('Blog Post Integer Attribute Backend Table');

    $postText = $schema->createTable('blog_post_entity_text');
    $postText->addColumn('value_id', Types::INTEGER, ['autoincrement' => true]);
    $postText->addColumn('entity_type_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $postText->addColumn('attribute_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $postText->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $postText->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $postText->addColumn('value', Types::TEXT, ['length' => 65535]);
    $postText->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create(),
    );
    $postText->addUniqueIndex(['entity_id', 'attribute_id', 'store_id'], 'unq_blog_post_entity_text_entity_attr_store');
    $postText->addIndex(['attribute_id'], 'idx_blog_post_entity_text_attribute_id');
    $postText->addIndex(['store_id'], 'idx_blog_post_entity_text_store_id');
    $postText->addIndex(['entity_id'], 'idx_blog_post_entity_text_entity_id');
    $postText->addForeignKeyConstraint(
        'eav_attribute',
        ['attribute_id'],
        ['attribute_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_blog_post_entity_text_attribute',
    );
    $postText->addForeignKeyConstraint(
        'blog_post_entity',
        ['entity_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_blog_post_entity_text_entity',
    );
    $postText->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_blog_post_entity_text_store',
    );
    $postText->setComment('Blog Post Text Attribute Backend Table');

    $postVarchar = $schema->createTable('blog_post_entity_varchar');
    $postVarchar->addColumn('value_id', Types::INTEGER, ['autoincrement' => true]);
    $postVarchar->addColumn('entity_type_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $postVarchar->addColumn('attribute_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $postVarchar->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $postVarchar->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $postVarchar->addColumn('value', Types::STRING, ['length' => 255, 'notnull' => false]);
    $postVarchar->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('value_id')->create(),
    );
    $postVarchar->addUniqueIndex(['entity_id', 'attribute_id', 'store_id'], 'unq_blog_post_entity_varchar_entity_attr_store');
    $postVarchar->addIndex(['attribute_id'], 'idx_blog_post_entity_varchar_attribute_id');
    $postVarchar->addIndex(['store_id'], 'idx_blog_post_entity_varchar_store_id');
    $postVarchar->addIndex(['entity_id'], 'idx_blog_post_entity_varchar_entity_id');
    $postVarchar->addForeignKeyConstraint(
        'eav_attribute',
        ['attribute_id'],
        ['attribute_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_blog_post_entity_varchar_attribute',
    );
    $postVarchar->addForeignKeyConstraint(
        'blog_post_entity',
        ['entity_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_blog_post_entity_varchar_entity',
    );
    $postVarchar->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_blog_post_entity_varchar_store',
    );
    $postVarchar->setComment('Blog Post Varchar Attribute Backend Table');

    $postStore = $schema->createTable('blog_post_store');
    $postStore->addColumn('post_id', Types::INTEGER, ['unsigned' => true]);
    $postStore->addColumn('store_id', Types::SMALLINT, ['unsigned' => true]);
    $postStore->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('post_id', 'store_id')->create(),
    );
    $postStore->addIndex(['store_id'], 'idx_blog_post_store_store_id');
    $postStore->addForeignKeyConstraint(
        'blog_post_entity',
        ['post_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_blog_post_store_post',
    );
    $postStore->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_blog_post_store_store',
    );
    $postStore->setComment('Blog Post To Store Linkage Table');

    // Added by upgrade-1.0.0-1.0.1.php
    $eavAttribute = $schema->createTable('blog_eav_attribute');
    $eavAttribute->addColumn('attribute_id', Types::SMALLINT, ['unsigned' => true]);
    $eavAttribute->addColumn('is_global', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $eavAttribute->addColumn('position', Types::INTEGER, ['default' => 0]);
    $eavAttribute->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('attribute_id')->create(),
    );
    $eavAttribute->addForeignKeyConstraint(
        'eav_attribute',
        ['attribute_id'],
        ['attribute_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_blog_eav_attribute_attribute',
    );
    $eavAttribute->setComment('Blog EAV Attribute Table');

    // Added by upgrade-1.0.1-2.0.0.php
    $category = $schema->createTable('blog_category_entity');
    $category->addColumn('entity_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $category->addColumn('entity_type_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $category->addColumn('attribute_set_id', Types::SMALLINT, ['unsigned' => true, 'default' => 0]);
    $category->addColumn('parent_id', Types::INTEGER, ['unsigned' => true, 'default' => 0]);
    $category->addColumn('path', Types::STRING, ['length' => 255, 'default' => '']);
    $category->addColumn('level', Types::INTEGER, ['default' => 0]);
    $category->addColumn('position', Types::INTEGER, ['default' => 0]);
    $category->addColumn('name', Types::STRING, ['length' => 255]);
    $category->addColumn('url_key', Types::STRING, ['length' => 255]);
    $category->addColumn('is_active', Types::SMALLINT, ['unsigned' => true, 'default' => 1]);
    $category->addColumn('meta_title', Types::STRING, ['length' => 255, 'notnull' => false]);
    $category->addColumn('meta_keywords', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $category->addColumn('meta_description', Types::TEXT, ['length' => 65535, 'notnull' => false]);
    $category->addColumn('meta_robots', Types::STRING, ['length' => 50, 'notnull' => false]);
    $category->addColumn('created_at', Types::DATETIME_MUTABLE, ['default' => 'CURRENT_TIMESTAMP']);
    $category->addColumn('updated_at', Types::DATETIME_MUTABLE, ['default' => 'CURRENT_TIMESTAMP']);
    $category->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('entity_id')->create(),
    );
    $category->addIndex(['entity_type_id'], 'idx_blog_category_entity_entity_type_id');
    $category->addIndex(['parent_id'], 'idx_blog_category_entity_parent_id');
    $category->addIndex(['path'], 'idx_blog_category_entity_path');
    $category->addIndex(['url_key'], 'idx_blog_category_entity_url_key');
    $category->addIndex(['is_active'], 'idx_blog_category_entity_is_active');
    $category->addIndex(['level'], 'idx_blog_category_entity_level');
    $category->addUniqueIndex(['url_key', 'parent_id'], 'unq_blog_category_entity_url_key_parent_id');
    $category->addForeignKeyConstraint(
        'eav_entity_type',
        ['entity_type_id'],
        ['entity_type_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_blog_category_entity_entity_type',
    );
    $category->setComment('Blog Category Entity Table');

    $categoryStore = $schema->createTable('blog_category_store');
    $categoryStore->addColumn('category_id', Types::INTEGER, ['unsigned' => true]);
    $categoryStore->addColumn('store_id', Types::SMALLINT, ['unsigned' => true]);
    $categoryStore->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('category_id', 'store_id')->create(),
    );
    $categoryStore->addIndex(['store_id'], 'idx_blog_category_store_store_id');
    $categoryStore->addForeignKeyConstraint(
        'blog_category_entity',
        ['category_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_blog_category_store_category',
    );
    $categoryStore->addForeignKeyConstraint(
        'core_store',
        ['store_id'],
        ['store_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_blog_category_store_store',
    );
    $categoryStore->setComment('Blog Category To Store Linkage Table');

    $postCategory = $schema->createTable('blog_post_category');
    $postCategory->addColumn('post_id', Types::INTEGER, ['unsigned' => true]);
    $postCategory->addColumn('category_id', Types::INTEGER, ['unsigned' => true]);
    $postCategory->addColumn('position', Types::INTEGER, ['default' => 0]);
    $postCategory->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('post_id', 'category_id')->create(),
    );
    $postCategory->addIndex(['category_id'], 'idx_blog_post_category_category_id');
    $postCategory->addForeignKeyConstraint(
        'blog_post_entity',
        ['post_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_blog_post_category_post',
    );
    $postCategory->addForeignKeyConstraint(
        'blog_category_entity',
        ['category_id'],
        ['entity_id'],
        ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'],
        'fk_blog_post_category_category',
    );
    $postCategory->setComment('Blog Post To Category Linkage Table');
};
