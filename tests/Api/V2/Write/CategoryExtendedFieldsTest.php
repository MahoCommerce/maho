<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Category Extended Fields Tests
 *
 * Round-trips for the category fields beyond the basic CRUD set: isAnchor,
 * landingPageId, available/default sort-by, custom design, and meta robots.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

/**
 * Every stored value of one category EAV attribute, across all store scopes.
 *
 * @return list<array{store_id: int, value: mixed}>
 */
function categoryAttributeRows(int $categoryId, string $code): array
{
    Tests\Helpers\ApiV2Helper::ensureMahoBootstrapped();

    $attribute = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Category::ENTITY, $code);
    $connection = Mage::getSingleton('core/resource')->getConnection('core_read');

    $rows = $connection->fetchAll(
        $connection->select()
            ->from($attribute->getBackend()->getTable(), ['store_id', 'value'])
            ->where('attribute_id = ?', (int) $attribute->getId())
            ->where('entity_id = ?', $categoryId)
            ->order('store_id'),
    );

    return array_map(
        static fn(array $row): array => ['store_id' => (int) $row['store_id'], 'value' => $row['value']],
        $rows,
    );
}

describe('Category Extended Fields (REST)', function (): void {

    it('round-trips isAnchor, sort-by, and layered navigation fields', function (): void {
        $token = serviceToken(['categories/write', 'categories/delete']);
        $suffix = substr(uniqid(), -8);

        $create = apiPost('/api/rest/v2/categories', [
            'name' => "Pest Anchor Category {$suffix}",
            'isActive' => true,
            'isAnchor' => true,
            'availableSortBy' => ['position', 'name'],
            'defaultSortBy' => 'name',
            'filterPriceRange' => 50.0,
            'metaRobots' => 'NOINDEX,FOLLOW',
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        $categoryId = $create['json']['id'];
        trackCreated('category', $categoryId);

        $read = apiGet("/api/rest/v2/categories/{$categoryId}");
        expect($read['status'])->toBe(200);
        expect($read['json']['isAnchor'])->toBeTrue();
        expect($read['json']['availableSortBy'])->toBe(['position', 'name']);
        expect($read['json']['defaultSortBy'])->toBe('name');
        expect($read['json']['filterPriceRange'])->toEqual(50.0);
        expect($read['json']['metaRobots'])->toBe('NOINDEX,FOLLOW');
        expect($read['json'])->toHaveKey('childrenCount');

        // Writes go to the admin scope, the one every store view resolves through.
        expect(categoryAttributeRows((int) $categoryId, 'default_sort_by'))
            ->toBe([['store_id' => 0, 'value' => 'name']]);

        // Update: flip anchor off, narrow sort-by
        $update = apiPut("/api/rest/v2/categories/{$categoryId}", [
            'isAnchor' => false,
            'availableSortBy' => ['position'],
            'defaultSortBy' => '',
        ], $token);
        expect($update['status'])->toBe(200);

        // The write response reflects the persisted values, not the raw input:
        // the empty-string clear is stored (and thus answered) as null.
        expect($update['json']['isAnchor'])->toBeFalse();
        expect($update['json']['availableSortBy'])->toBe(['position']);
        expect($update['json']['defaultSortBy'])->toBeNull();

        $verify = apiGet("/api/rest/v2/categories/{$categoryId}");
        expect($verify['status'])->toBe(200);
        expect($verify['json']['isAnchor'])->toBeFalse();
        expect($verify['json']['availableSortBy'])->toBe(['position']);
        expect($verify['json']['defaultSortBy'])->toBeNull();

        // A read resolves the admin scope as a fallback, so a clear that dropped only a
        // store-level row would still answer with the old value. Assert the value is gone
        // from every scope, not just absent from this response.
        expect(categoryAttributeRows((int) $categoryId, 'default_sort_by'))->toBe([]);
    });

    it('preserves availableSortBy across an unrelated update', function (): void {
        $token = serviceToken(['categories/write', 'categories/delete']);
        $suffix = substr(uniqid(), -8);

        $create = apiPost('/api/rest/v2/categories', [
            'name' => "Pest SortBy Keep {$suffix}",
            'isActive' => true,
            'availableSortBy' => ['position', 'name'],
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        $categoryId = $create['json']['id'];
        trackCreated('category', $categoryId);

        // Touch an unrelated field only
        $update = apiPut("/api/rest/v2/categories/{$categoryId}", [
            'description' => 'Unrelated change',
        ], $token);
        expect($update['status'])->toBe(200);

        $verify = apiGet("/api/rest/v2/categories/{$categoryId}");
        expect($verify['json']['availableSortBy'])->toBe(['position', 'name']);
    });

    it('rejects unknown sort-by codes', function (): void {
        $token = serviceToken(['categories/write']);
        $response = apiPost('/api/rest/v2/categories', [
            'name' => 'Pest Bad SortBy Category',
            'availableSortBy' => ['not_a_real_sort_code'],
        ], $token);
        expect($response['status'])->toBeIn([400, 422]);
    });

    it('round-trips landingPageId referencing a CMS block', function (): void {
        $blockToken = serviceToken(['cms-blocks/write', 'cms-blocks/delete']);
        $categoryToken = serviceToken(['categories/write', 'categories/delete']);
        $suffix = substr(uniqid(), -8);

        $block = apiPost('/api/rest/v2/cms-blocks', [
            'identifier' => "pest-landing-{$suffix}",
            'title' => "Pest Landing Block {$suffix}",
            'content' => '<p>Landing content</p>',
            'isActive' => true,
        ], $blockToken);
        expect($block['status'])->toBeIn([200, 201]);
        $blockId = $block['json']['id'];
        trackCreated('cms_block', $blockId);

        $create = apiPost('/api/rest/v2/categories', [
            'name' => "Pest Landing Category {$suffix}",
            'isActive' => true,
            'displayMode' => 'PAGE',
            'landingPageId' => $blockId,
        ], $categoryToken);
        expect($create['status'])->toBeIn([200, 201]);
        $categoryId = $create['json']['id'];
        trackCreated('category', $categoryId);

        $read = apiGet("/api/rest/v2/categories/{$categoryId}");
        expect($read['status'])->toBe(200);
        expect($read['json']['landingPageId'])->toBe($blockId);

        // 0 clears the landing page; the write response already answers null.
        $clear = apiPut("/api/rest/v2/categories/{$categoryId}", [
            'landingPageId' => 0,
        ], $categoryToken);
        expect($clear['status'])->toBe(200);
        expect($clear['json']['landingPageId'])->toBeNull();

        $verify = apiGet("/api/rest/v2/categories/{$categoryId}");
        expect($verify['json']['landingPageId'])->toBeNull();
    });

    it('rejects a landingPageId pointing to a missing CMS block', function (): void {
        $token = serviceToken(['categories/write']);
        $response = apiPost('/api/rest/v2/categories', [
            'name' => 'Pest Bad Landing Category',
            'landingPageId' => 99999999,
        ], $token);
        expect($response['status'])->toBeIn([400, 422]);
    });

    it('round-trips custom design fields and clears them with empty strings', function (): void {
        $token = serviceToken(['categories/write', 'categories/delete']);
        $suffix = substr(uniqid(), -8);

        $create = apiPost('/api/rest/v2/categories', [
            'name' => "Pest Design Category {$suffix}",
            'isActive' => true,
            'customDesignFrom' => '2026-01-01',
            'customDesignTo' => '2026-12-31',
            'customUseParentSettings' => false,
            'customApplyToProducts' => true,
            'customLayoutUpdate' => '',
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        $categoryId = $create['json']['id'];
        trackCreated('category', $categoryId);

        // Design fields are backend data, so they need an API token to read.
        $read = apiGet("/api/rest/v2/categories/{$categoryId}", $token);
        expect($read['status'])->toBe(200);
        expect($read['json']['customDesignFrom'])->toBe('2026-01-01');
        expect($read['json']['customDesignTo'])->toBe('2026-12-31');
        expect($read['json']['customApplyToProducts'])->toBeTrue();

        $clear = apiPut("/api/rest/v2/categories/{$categoryId}", [
            'customDesignFrom' => '',
            'customDesignTo' => '',
        ], $token);
        expect($clear['status'])->toBe(200);

        $verify = apiGet("/api/rest/v2/categories/{$categoryId}", $token);
        expect($verify['json']['customDesignFrom'])->toBeNull();
        expect($verify['json']['customDesignTo'])->toBeNull();
    });

    it('rejects protected codes in customAttributesWrite and skips unknown ones', function (): void {
        $token = serviceToken(['categories/write', 'categories/delete']);
        $suffix = substr(uniqid(), -8);

        $denied = apiPost('/api/rest/v2/categories', [
            'name' => "Pest CustomAttr Denied {$suffix}",
            'customAttributesWrite' => ['path' => '1/2/3'],
        ], $token);
        expect($denied['status'])->toBeIn([400, 422]);

        $create = apiPost('/api/rest/v2/categories', [
            'name' => "Pest CustomAttr Category {$suffix}",
            'isActive' => true,
            'customAttributesWrite' => [
                'meta_robots' => 'NOINDEX,NOFOLLOW',
                'definitely_not_an_attribute' => 'ignored',
            ],
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        $categoryId = $create['json']['id'];
        trackCreated('category', $categoryId);

        $read = apiGet("/api/rest/v2/categories/{$categoryId}");
        expect($read['json']['metaRobots'])->toBe('NOINDEX,NOFOLLOW');
    });

});

describe('Category layout update validation (REST)', function (): void {

    it('rejects a layout update that injects a template path', function (): void {
        $token = serviceToken(['categories/write']);

        $response = apiPost('/api/rest/v2/categories', [
            'name' => 'Pest Layout Injection Category',
            'customLayoutUpdate' => '<reference name="content">'
                . '<block type="core/template" template="../../../../app/etc/local.xml"/>'
                . '</reference>',
        ], $token);

        expect($response['status'])->toBeIn([400, 422]);
    });

    it('rejects a layout update that is not well-formed XML', function (): void {
        $token = serviceToken(['categories/write']);

        $response = apiPost('/api/rest/v2/categories', [
            'name' => 'Pest Layout Malformed Category',
            'customLayoutUpdate' => '<reference name="content"><block type="core/template">',
        ], $token);

        expect($response['status'])->toBeIn([400, 422]);
    });

    it('accepts a valid layout update and keeps it out of anonymous reads', function (): void {
        $token = serviceToken(['categories/write', 'categories/delete']);
        $suffix = substr(uniqid(), -8);

        $create = apiPost('/api/rest/v2/categories', [
            'name' => "Pest Layout Category {$suffix}",
            'isActive' => true,
            'customDesignFrom' => '2026-03-01',
            'customLayoutUpdate' => '<reference name="content"><remove name="left"/></reference>',
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        $categoryId = $create['json']['id'];
        trackCreated('category', $categoryId);

        $privileged = apiGet("/api/rest/v2/categories/{$categoryId}", $token);
        expect($privileged['status'])->toBe(200);
        expect($privileged['json']['customLayoutUpdate'])->toContain('<remove name="left"/>');
        expect($privileged['json']['customDesignFrom'])->toBe('2026-03-01');

        $anonymous = apiGet("/api/rest/v2/categories/{$categoryId}");
        expect($anonymous['status'])->toBe(200);
        expect($anonymous['json']['customLayoutUpdate'] ?? null)->toBeNull();
        expect($anonymous['json']['customDesignFrom'] ?? null)->toBeNull();
        // Public fields are unaffected.
        expect($anonymous['json']['name'])->toBe("Pest Layout Category {$suffix}");
    });

    it('applies the same validation to customAttributesWrite as to the dedicated fields', function (): void {
        $token = serviceToken(['categories/write']);

        $layout = apiPost('/api/rest/v2/categories', [
            'name' => 'Pest Bag Layout Category',
            'customAttributesWrite' => [
                'custom_layout_update' => '<reference name="content">'
                    . '<block type="core/template" template="../../../../app/etc/local.xml"/>'
                    . '</reference>',
            ],
        ], $token);
        expect($layout['status'])->toBeIn([400, 422]);

        $image = apiPost('/api/rest/v2/categories', [
            'name' => 'Pest Bag Image Category',
            'customAttributesWrite' => ['image' => '../../../app/etc/local.xml'],
        ], $token);
        expect($image['status'])->toBeIn([400, 422]);

        $sortBy = apiPost('/api/rest/v2/categories', [
            'name' => 'Pest Bag SortBy Category',
            'customAttributesWrite' => ['available_sort_by' => 'not_a_real_sort_code'],
        ], $token);
        expect($sortBy['status'])->toBeIn([400, 422]);
    });

    it('rejects productPositions on create instead of dropping it', function (): void {
        $token = serviceToken(['categories/write']);

        $response = apiPost('/api/rest/v2/categories', [
            'name' => 'Pest Positions On Create',
            'productPositions' => ['1' => 5],
        ], $token);

        expect($response['status'])->toBeIn([400, 422]);
    });

});
