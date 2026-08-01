<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Attribute Metadata Extended Read Tests
 *
 * Verifies the extended frontend/search/listing flags on product attributes
 * and the grouped structure on attribute sets.
 *
 * @group read
 */

describe('Product Attribute Extended Flags', function (): void {

    it('exposes search, frontend, and listing flags on the collection', function (): void {
        $response = apiGet('/api/rest/v2/product-attributes', adminToken());
        expect($response['status'])->toBe(200);
        $items = getItems($response);
        expect($items)->not->toBeEmpty();

        $first = $items[0];
        foreach ([
            'isSearchable', 'isFilterable', 'isFilterableInSearch', 'isComparable',
            'isVisibleOnFront', 'isHtmlAllowedOnFront', 'isUsedForPriceRules',
            'usedInProductListing', 'usedForSortBy', 'isVisibleInAdvancedSearch',
            'isWysiwygEnabled', 'isConfigurable', 'applyTo', 'frontendClass',
            'note', 'position', 'isGlobal', 'scope',
        ] as $key) {
            expect($first)->toHaveKey($key);
        }

        expect($first['applyTo'])->toBeArray();
        expect($first['isGlobal'])->toBeInt();
        expect($first['isFilterable'])->toBeInt();
        expect($first['isSearchable'])->toBeBool();
    });

    it('keeps the derived scope string consistent with the raw isGlobal flag', function (): void {
        $response = apiGet('/api/rest/v2/product-attributes', adminToken());
        expect($response['status'])->toBe(200);

        foreach (getItems($response) as $attribute) {
            $expected = match ($attribute['isGlobal']) {
                2 => 'website',
                0 => 'store',
                default => 'global',
            };
            expect($attribute['scope'])->toBe($expected);
        }
    });

    it('returns extended flags on a single attribute', function (): void {
        $list = apiGet('/api/rest/v2/product-attributes', adminToken());
        $first = getItems($list)[0] ?? null;
        expect($first)->not->toBeNull();

        $response = apiGet('/api/rest/v2/product-attributes/' . (int) $first['id'], adminToken());
        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('isSearchable');
        expect($response['json'])->toHaveKey('applyTo');
        expect($response['json'])->toHaveKey('isGlobal');
    });

});

describe('Attribute Set Groups', function (): void {

    it('exposes groups with sorted attributes on a single set', function (): void {
        $list = apiGet('/api/rest/v2/attribute-sets', adminToken());
        expect($list['status'])->toBe(200);
        $first = getItems($list)[0] ?? null;
        expect($first)->not->toBeNull();
        expect($first)->toHaveKey('groups');

        $response = apiGet('/api/rest/v2/attribute-sets/' . (int) $first['id'], adminToken());
        expect($response['status'])->toBe(200);
        $groups = $response['json']['groups'];
        expect($groups)->toBeArray()->not->toBeEmpty();

        $group = $groups[0];
        expect($group)->toHaveKey('name');
        expect($group)->toHaveKey('sortOrder');
        expect($group)->toHaveKey('attributes');
        expect($group['attributes'])->toBeArray();

        $attribute = $group['attributes'][0] ?? null;
        expect($attribute)->not->toBeNull();
        expect($attribute)->toHaveKey('code');
        expect($attribute)->toHaveKey('sortOrder');

        // Every attribute listed in groups is also in the flat code list
        $flatCodes = $response['json']['attributeCodes'];
        foreach ($groups as $g) {
            foreach ($g['attributes'] as $a) {
                expect($flatCodes)->toContain($a['code']);
            }
        }
    });

});
