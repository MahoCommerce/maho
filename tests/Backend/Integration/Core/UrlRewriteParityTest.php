<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Mage\Core\Api\UrlRewrite;
use Mage\Core\Api\UrlRewriteProcessor;
use Tests\Helpers\AdminApiParity;

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin page and the API save a URL rewrite through Mage_Core_Service_Url_Rewrite (#1348).
 * Each test gives the same rewrite to both transports and compares the outcome.
 */

/**
 * @param array<string, mixed> $fields the fields of the admin form
 * @return array{success: list<string>, errors: list<string>}
 */
function urlRewriteViaAdmin(array $fields): array
{
    foreach (['current_urlrewrite', 'current_product', 'current_category'] as $key) {
        Mage::unregister($key);
    }
    return AdminApiParity::admin(Mage_Adminhtml_UrlrewriteController::class, 'save', $fields);
}

/**
 * @param array<string, mixed> $body the JSON body
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function urlRewriteViaApi(array $body, ?int $id = null, bool $delete = false): array
{
    $method = $delete ? 'DELETE' : ($id ? 'PUT' : 'POST');
    $request = AdminApiParity::jsonRequest($method, '/api/rest/v2/url-rewrites' . ($id ? "/{$id}" : ''), $body);
    $dto = new UrlRewrite();
    foreach ($body as $property => $value) {
        $dto->{$property} = $value;
    }
    $operation = match ($method) {
        'DELETE' => new Delete(),
        'PUT' => new Put(),
        default => new Post(),
    };
    return AdminApiParity::api($request, fn() => new UrlRewriteProcessor(AdminApiParity::adminSecurity())
        ->process($delete ? null : $dto, $operation, $id ? ['id' => $id] : [], ['request' => $request]));
}

function urlRewriteParityCleanup(): void
{
    $collection = Mage::getModel('core/url_rewrite')->getCollection()
        ->addFieldToFilter('request_path', ['like' => 'pest-parity-%']);
    foreach ($collection as $rewrite) {
        $rewrite->delete();
    }
}

beforeEach(function () {
    $this->storeId = (int) Mage::app()->getDefaultStoreView()->getId();
    urlRewriteParityCleanup();
});

afterEach(function () {
    urlRewriteParityCleanup();
});

it('saves the same custom rewrite on both transports', function () {
    $admin = urlRewriteViaAdmin([
        'store_id' => $this->storeId,
        'request_path' => 'PEST-PARITY-ADMIN.html',
        'id_path' => 'pest/parity/admin',
        'target_path' => 'contacts',
        'options' => 'RP',
    ]);
    $api = urlRewriteViaApi([
        'storeId' => $this->storeId,
        'requestPath' => 'PEST-PARITY-API.html',
        'idPath' => 'pest/parity/api',
        'targetPath' => 'contacts',
        'options' => 'RP',
    ]);

    $saved = Mage::getModel('core/url_rewrite')->setStoreId($this->storeId)->loadByRequestPath('pest-parity-admin.html');
    expect($admin['errors'])->toBe([])
        ->and($admin['success'])->toBe(['The URL Rewrite has been saved.'])
        ->and($saved->getIsSystem())->toBeFalse()
        ->and($api['status'])->toBe(200)
        ->and($api['result']->requestPath)->toBe('pest-parity-api.html')
        ->and($api['result']->isSystem)->toBeFalse();
});

it('rejects a request path that another rewrite of the store view has on both transports', function () {
    urlRewriteViaApi(['storeId' => $this->storeId, 'requestPath' => 'pest-parity-taken.html', 'idPath' => 'pest/parity/one', 'targetPath' => 'contacts']);

    $admin = urlRewriteViaAdmin(['store_id' => $this->storeId, 'request_path' => 'pest-parity-taken.html', 'id_path' => 'pest/parity/two', 'target_path' => 'contacts']);
    $api = urlRewriteViaApi(['storeId' => $this->storeId, 'requestPath' => 'pest-parity-taken.html', 'idPath' => 'pest/parity/three', 'targetPath' => 'contacts']);

    $message = "A URL rewrite for request path 'pest-parity-taken.html' already exists in store view {$this->storeId}";
    expect($admin['errors'])->toBe([$message])
        ->and($api['status'])->toBe(409)
        ->and($api['errors'])->toBe([$message]);
});

it('gives the same field errors on both transports', function () {
    $admin = urlRewriteViaAdmin(['store_id' => $this->storeId, 'request_path' => 'pest-parity//bad.html', 'options' => 'X', 'id_path' => '', 'target_path' => '']);
    $api = urlRewriteViaApi(['storeId' => $this->storeId, 'requestPath' => 'pest-parity//bad.html', 'options' => 'X']);

    expect($api['status'])->toBe(422)
        ->and($api['fields'])->toBe(['requestPath', 'options', 'idPath', 'targetPath'])
        ->and($admin['errors'])->toBe($api['errors']);
});

it('answers a missing rewrite as not found on both transports', function () {
    $admin = urlRewriteViaAdmin(['id' => 999999999, 'store_id' => $this->storeId, 'request_path' => 'pest-parity-missing.html']);
    $api = urlRewriteViaApi(['description' => 'x'], 999999999);

    expect($admin['errors'])->toBe(['This URL rewrite no longer exists.'])
        ->and($api['status'])->toBe(404);
});

describe('a system rewrite (decision needed: the admin page may change it, the API may not)', function () {
    beforeEach(function () {
        $this->system = Mage::getModel('core/url_rewrite')
            ->setStoreId($this->storeId)
            ->setIdPath('pest/parity/system')
            ->setRequestPath('pest-parity-system.html')
            ->setTargetPath('contacts')
            ->setIsSystem(true);
        $this->system->save();
    });

    it('lets the admin page change its description', function () {
        $admin = urlRewriteViaAdmin([
            'id' => (int) $this->system->getId(),
            'request_path' => 'pest-parity-system.html',
            'id_path' => 'pest/parity/system',
            'target_path' => 'contacts',
            'description' => 'Changed in the admin',
        ]);

        expect($admin['errors'])->toBe([])
            ->and(Mage::getModel('core/url_rewrite')->load($this->system->getId())->getDescription())->toBe('Changed in the admin');
    });

    it('refuses an API update and an API delete', function () {
        $update = urlRewriteViaApi(['description' => 'Changed in the API'], (int) $this->system->getId());
        $delete = urlRewriteViaApi([], (int) $this->system->getId(), delete: true);

        expect($update['status'])->toBe(409)
            ->and($update['errors'][0])->toContain('system URL rewrite')
            ->and($delete['status'])->toBe(409)
            ->and(Mage::getModel('core/url_rewrite')->load($this->system->getId())->getId())->not->toBeNull();
    });
});
