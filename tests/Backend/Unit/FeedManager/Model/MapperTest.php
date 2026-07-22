<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Mapper default mappings', function () {
    function getMapperMappings(Maho_FeedManager_Model_Feed $feed): array
    {
        $mapper = new Maho_FeedManager_Model_Mapper($feed);
        $property = new ReflectionProperty(Maho_FeedManager_Model_Mapper::class, '_mappings');
        return $property->getValue($mapper);
    }

    beforeEach(function () {
        $this->feed = Mage::getModel('feedmanager/feed');
        $this->feed->setPlatform('google');
        $this->feed->setStoreId(1);
    });

    test('platform defaults keep their transformer chains', function () {
        $mappings = getMapperMappings($this->feed);

        // Google default: availability maps is_in_stock 1/0 -> in_stock/out_of_stock
        expect($mappings)->toHaveKey('availability');
        expect($mappings['availability']['transformers'])->not->toBeEmpty();
        expect($mappings['availability']['transformers'][0]['transformer'])->toBe('conditional');
        expect($mappings['availability']['transformers'][0]['options']['true_value'])->toBe('in_stock');
    });

    test('platform defaults keep their use_parent mode', function () {
        $mappings = getMapperMappings($this->feed);

        // Google defaults: variant children link to the parent product page
        // and fall back to the parent image when they have none of their own
        expect($mappings['link']['use_parent'])->toBe('always');
        expect($mappings['image_link']['use_parent'])->toBe('if_empty');
    });

    test('db mappings are not overridden by platform defaults', function () {
        $this->feed->setName('Mapper Test Feed');
        $this->feed->setFilename('mapper-test-feed');
        $this->feed->save();
        Mage::getModel('feedmanager/attributeMapping')
            ->setFeedId((int) $this->feed->getId())
            ->setPlatformAttribute('availability')
            ->setSourceType('static')
            ->setSourceValue('in_stock')
            ->save();

        $mappings = getMapperMappings($this->feed);

        expect($mappings['availability']['source_type'])->toBe('static');
        expect($mappings['availability']['source_value'])->toBe('in_stock');
        expect($mappings['availability']['transformers'])->toBeEmpty();
    });
});
