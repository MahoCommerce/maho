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

/**
 * Look up a single product attribute by its exact code through the collection
 * search filter (REST has no by-code route).
 */
function attributeByCode(string $code): ?array
{
    $response = apiGet('/api/rest/v2/product-attributes?search=' . $code, adminToken());
    expect($response['status'])->toBe(200);

    foreach (getItems($response) as $attribute) {
        if (($attribute['attributeCode'] ?? null) === $code) {
            return $attribute;
        }
    }

    return null;
}

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
            'isWysiwygEnabled', 'isConfigurable', 'applyTo',
            'position', 'isGlobal', 'scope',
        ] as $key) {
            expect($first)->toHaveKey($key);
        }

        expect($first['applyTo'])->toBeArray();
        expect($first['isGlobal'])->toBeInt();
        expect($first['isFilterable'])->toBeInt();
        expect($first['isSearchable'])->toBeBool();
    });

    it('returns the stored frontend validation class', function (): void {
        // special_price gets its class from the catalog data upgrade, so every
        // install has one attribute with a known frontend_class.
        $attribute = attributeByCode('special_price');
        expect($attribute)->not->toBeNull();
        expect($attribute['frontendClass'])->toBe('validate-special-price');
    });

    it('returns the stored admin note', function (): void {
        // meta_description is created with a note by the catalog setup.
        $attribute = attributeByCode('meta_description');
        expect($attribute)->not->toBeNull();
        expect($attribute['note'])->toBe('Maximum 255 chars');

        // Null fields are omitted from REST responses (skip_null_values), so an
        // attribute without a note carries no key at all.
        $withoutNote = attributeByCode('special_price');
        expect($withoutNote['note'] ?? null)->toBeNull();
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
