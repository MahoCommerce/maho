<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 CMS page layout codes.
 *
 * The layout codes come from the theme configuration. An unknown code must be refused with
 * the list of known codes, and a page created without one gets the default layout.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('CMS page layout through the API', function (): void {

    it('refuses an unknown layout code and names the known ones', function (): void {
        $token = serviceToken(['cms-pages/write']);

        $create = apiPost('/api/rest/v2/cms-pages', [
            'identifier' => 'test-layout-unknown-code',
            'title' => 'Layout test',
            'content' => '<p>layout</p>',
            'pageLayout' => '1column',
        ], $token);

        expect($create['status'])->toBe(400);
        expect(json_encode($create['json']))->toContain('one_column');
    });

    it('gives a page created without a layout the default layout', function (): void {
        $token = serviceToken(['cms-pages/write']);

        $create = apiPost('/api/rest/v2/cms-pages', [
            'identifier' => 'test-layout-default-code',
            'title' => 'Layout default test',
            'content' => '<p>layout</p>',
        ], $token);

        expect($create['status'])->toBeIn([200, 201]);
        trackCreated('cms_page', $create['json']['id']);
        expect($create['json']['pageLayout'])->toBe(Mage::getSingleton('page/source_layout')->getDefaultValue());
    });
});
