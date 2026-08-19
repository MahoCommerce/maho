<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function valueFeed(): Maho_FeedManager_Model_Feed
{
    return Mage::getModel('feedmanager/feed')
        ->setPlatform('custom')
        ->setStoreId(1)
        ->setNoImageUrl('http://example.com/placeholder.jpg');
}

function valueProduct(): Mage_Catalog_Model_Product
{
    return Mage::getModel('catalog/product')
        ->setId(1)
        ->setTypeId('simple')
        ->setStoreId(1);
}

describe('Mapper image resolution', function () {
    it('takes the parent image when the child has none', function () {
        $mapper = new Maho_FeedManager_Model_Mapper(valueFeed());

        $value = $mapper->resolveFieldValue(
            ['source_type' => 'attribute', 'source_value' => 'image', 'use_parent' => 'if_empty'],
            ['image' => '', 'parent_image' => 'http://example.com/parent.jpg'],
            valueProduct(),
        );

        expect($value)->toBe('http://example.com/parent.jpg');
    });

    it('takes the placeholder when neither the child nor the parent has an image', function () {
        $mapper = new Maho_FeedManager_Model_Mapper(valueFeed());

        $value = $mapper->resolveFieldValue(
            ['source_type' => 'attribute', 'source_value' => 'image', 'use_parent' => 'if_empty'],
            ['image' => '', 'parent_image' => ''],
            valueProduct(),
        );

        expect($value)->toBe('http://example.com/placeholder.jpg');
    });

    it('keeps the child image when it has one', function () {
        $mapper = new Maho_FeedManager_Model_Mapper(valueFeed());

        $value = $mapper->resolveFieldValue(
            ['source_type' => 'attribute', 'source_value' => 'image', 'use_parent' => 'if_empty'],
            ['image' => 'http://example.com/child.jpg', 'parent_image' => 'http://example.com/parent.jpg'],
            valueProduct(),
        );

        expect($value)->toBe('http://example.com/child.jpg');
    });
});

describe('Mapper combined source', function () {
    it('fills a placeholder from the parent when the child value is empty', function () {
        $mapper = new Maho_FeedManager_Model_Mapper(valueFeed());

        $value = $mapper->resolveFieldValue(
            [
                'source_type' => 'combined',
                'source_value' => '{{brand}} {{name}}',
                'use_parent' => 'if_empty',
            ],
            [
                'brand' => '',
                'parent_brand' => 'Acme',
                'name' => 'Blue shirt',
                'parent_name' => 'Shirt',
            ],
            valueProduct(),
        );

        expect($value)->toBe('Acme Blue shirt');
    });
});
