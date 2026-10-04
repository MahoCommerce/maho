<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Email Template Tests (READ + WRITE)
 *
 * Covers REST CRUD on /email-templates, the built-in defaults listing, and the
 * permission gates. Email templates are admin data: reads and writes both need
 * an admin or granted token.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

function deleteEmailTemplate(int $id): void
{
    try {
        $template = Mage::getModel('core/email_template')->load($id);
        if ($template->getId()) {
            $template->delete();
        }
    } catch (\Throwable) {
        // The test already deleted it through the API.
    }
}

describe('Email template permission enforcement', function (): void {

    it('rejects anonymous reads', function (): void {
        expect(apiGet('/api/rest/v2/email-templates')['status'])->toBeUnauthorized();
        expect(apiGet('/api/rest/v2/email-templates/defaults')['status'])->toBeUnauthorized();
    });

    it('rejects a customer token', function (): void {
        expect(apiGet('/api/rest/v2/email-templates', customerToken())['status'])->toBeForbidden();
    });

    it('rejects a service token without the read permission', function (): void {
        $response = apiGet('/api/rest/v2/email-templates', serviceToken(['cms-pages/read']));

        expect($response['status'])->toBeForbidden();
    });

    it('rejects a create with only the read permission', function (): void {
        $response = apiPost('/api/rest/v2/email-templates', [
            'templateCode' => 'pest_noperm_' . uniqid(),
            'templateText' => '<p>x</p>',
        ], serviceToken(['email-templates/read']));

        expect($response['status'])->toBeForbidden();
    });

    it('denies an admin whose role lacks system/email_template', function (): void {
        $token = adminTokenWithAcl(['catalog/products'], 'pest_email_tpl_acl_' . substr(uniqid(), -6));

        expect(apiGet('/api/rest/v2/email-templates', $token)['status'])->toBeForbidden();
    });

});

describe('Default email templates', function (): void {

    it('lists the built-in defaults without ids', function (): void {
        $response = apiGet('/api/rest/v2/email-templates/defaults', adminToken());

        expect($response['status'])->toBe(200);
        $items = getItems($response);
        expect($items)->not->toBeEmpty();

        $codes = array_column($items, 'templateCode');
        expect($codes)->toContain('sales_email_order_template');
        expect($items[0]['id'] ?? null)->toBeNull();
        expect($items[0]['origTemplateCode'])->toBe($items[0]['templateCode']);
    });

    it('returns one default with its text and subject', function (): void {
        $response = apiGet('/api/rest/v2/email-templates/defaults/sales_email_order_template', adminToken());

        expect($response['status'])->toBe(200);
        expect($response['json']['id'] ?? null)->toBeNull();
        expect($response['json']['templateCode'])->toBe('sales_email_order_template');
        expect($response['json']['templateType'])->toBe('html');
        expect($response['json']['templateText'])->toBeString()->not->toBe('');
        expect($response['json']['templateSubject'])->toBeString()->not->toBe('');
    });

    it('returns 404 for an unknown default code', function (): void {
        $response = apiGet('/api/rest/v2/email-templates/defaults/no_such_template', adminToken());

        expect($response['status'])->toBeNotFound();
    });

});

describe('Email template CRUD lifecycle', function (): void {

    it('creates, reads, lists, updates and deletes a template', function (): void {
        $writeToken = serviceToken(['email-templates/read', 'email-templates/write']);
        $deleteToken = serviceToken(['email-templates/delete']);
        $code = 'Pest template ' . substr(uniqid(), -6);

        $create = apiPost('/api/rest/v2/email-templates', [
            'templateCode' => $code,
            'templateSubject' => 'Order {{var order.increment_id}}',
            'templateText' => '<p>Hello {{var customer.name}}</p>',
            'templateStyles' => 'p { color: red; }',
            'origTemplateCode' => 'sales_email_order_template',
        ], $writeToken);

        expect($create['status'])->toBeIn([200, 201]);
        $id = (int) $create['json']['id'];
        expect($id)->toBeGreaterThan(0);
        expect($create['json']['templateCode'])->toBe($code);
        expect($create['json']['templateType'])->toBe('html');
        expect($create['json']['templateText'])->toBe('<p>Hello {{var customer.name}}</p>');
        expect($create['json']['addedAt'])->not->toBeNull();

        $read = apiGet("/api/rest/v2/email-templates/{$id}", $writeToken);
        expect($read['status'])->toBe(200);
        expect($read['json']['templateSubject'])->toBe('Order {{var order.increment_id}}');

        $list = apiGet('/api/rest/v2/email-templates?search=' . rawurlencode($code), adminToken());
        expect($list['status'])->toBe(200);
        expect(array_column(getItems($list), 'id'))->toContain($id);

        $update = apiPut("/api/rest/v2/email-templates/{$id}", [
            'templateSubject' => 'Updated subject',
            'templateType' => 'text',
        ], $writeToken);
        expect($update['status'])->toBe(200);
        expect($update['json']['templateSubject'])->toBe('Updated subject');
        expect($update['json']['templateType'])->toBe('text');
        expect($update['json']['templateCode'])->toBe($code);

        $denied = apiDelete("/api/rest/v2/email-templates/{$id}", $writeToken);
        expect($denied['status'])->toBeForbidden();

        $delete = apiDelete("/api/rest/v2/email-templates/{$id}", $deleteToken);
        expect($delete['status'])->toBeIn([200, 204]);

        expect(apiGet("/api/rest/v2/email-templates/{$id}", adminToken())['status'])->toBeNotFound();
    });

    it('rejects a duplicate template code, missing text and a missing subject', function (): void {
        $token = adminToken();
        $code = 'Pest dup ' . substr(uniqid(), -6);

        $create = apiPost('/api/rest/v2/email-templates', [
            'templateCode' => $code,
            'templateSubject' => 'Pest subject',
            'templateText' => 'plain body',
            'templateType' => 'text',
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        $id = (int) $create['json']['id'];

        try {
            $duplicate = apiPost('/api/rest/v2/email-templates', [
                'templateCode' => $code,
                'templateText' => 'another body',
            ], $token);
            expect($duplicate['status'])->toBe(400);
            expect($duplicate['json']['message'] ?? '')->toContain('already exists');

            $noText = apiPost('/api/rest/v2/email-templates', [
                'templateCode' => $code . ' b',
            ], $token);
            expect($noText['status'])->toBe(400);

            $noSubject = apiPost('/api/rest/v2/email-templates', [
                'templateCode' => $code . ' d',
                'templateText' => 'x',
            ], $token);
            expect($noSubject['status'])->toBe(400);
            expect($noSubject['json']['details']['field'] ?? null)->toBe('templateSubject');

            $badType = apiPost('/api/rest/v2/email-templates', [
                'templateCode' => $code . ' c',
                'templateText' => 'x',
                'templateType' => 'markdown',
            ], $token);
            expect($badType['status'])->toBe(400);
        } finally {
            deleteEmailTemplate($id);
        }
    });

    it('filters the list by templateType', function (): void {
        $response = apiGet('/api/rest/v2/email-templates?templateType=pdf', adminToken());

        expect($response['status'])->toBe(400);
    });

});
