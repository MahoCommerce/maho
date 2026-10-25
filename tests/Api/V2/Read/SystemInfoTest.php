<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 version and build of the installation.
 *
 * @group read
 */

const SYSTEM_INFO_PATH = '/api/rest/v2/system-info';

afterAll(function (): void {
    cleanupTestData();
});

describe('System info', function (): void {

    it('needs a token', function (): void {
        expect(apiGet(SYSTEM_INFO_PATH)['status'])->toBe(401);
    });

    it('returns the version and the build', function (): void {
        $response = apiGet(SYSTEM_INFO_PATH, adminToken());

        expect($response['status'])->toBe(200)
            ->and($response['json']['version'])->toBe(Mage::getVersion())
            ->and($response['json']['build'])->toBe(\Maho\Build\Info::get()->describe());
    });

    it('applies the admin ACL of the health check', function (): void {
        $healthcheck = adminTokenWithAcl(['admin/system/tools/healthcheck'], 'apitest_system_info_healthcheck');
        $dashboard = adminTokenWithAcl(['admin/dashboard'], 'apitest_system_info_dashboard');

        expect(apiGet(SYSTEM_INFO_PATH, $healthcheck)['status'])->toBe(200)
            ->and(apiGet(SYSTEM_INFO_PATH, $dashboard)['status'])->toBe(403);
    });

    it('gives service accounts the system-info permission', function (): void {
        expect(apiGet(SYSTEM_INFO_PATH, serviceToken(['system-info/read']))['status'])->toBe(200)
            ->and(apiGet(SYSTEM_INFO_PATH, serviceToken(['reports/read']))['status'])->toBe(403);
    });
});
