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

    $message = 'Request Path for Specified Store already exists.';
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

describe('a system rewrite', function () {
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

    it('lets the API change its description and delete it, as the admin page does', function () {
        $update = urlRewriteViaApi(['description' => 'Changed in the API'], (int) $this->system->getId());
        $saved = Mage::getModel('core/url_rewrite')->load($this->system->getId());
        expect($update['status'])->toBe(200)
            ->and($saved->getDescription())->toBe('Changed in the API')
            ->and((bool) $saved->getIsSystem())->toBeTrue();

        $delete = urlRewriteViaApi([], (int) $this->system->getId(), delete: true);
        expect($delete['status'])->toBe(200)
            ->and(Mage::getModel('core/url_rewrite')->load($this->system->getId())->getId())->toBeNull();
    });

    it('keeps its store view on the API, as the admin form does', function () {
        $otherStoreId = (int) array_key_last(array_diff_key(Mage::app()->getStores(), [$this->storeId => true]));

        $update = urlRewriteViaApi(['storeId' => $otherStoreId], (int) $this->system->getId());

        expect($update['status'])->toBe(422)
            ->and($update['fields'])->toBe(['storeId'])
            ->and((int) Mage::getModel('core/url_rewrite')->load($this->system->getId())->getStoreId())->toBe($this->storeId);
    });
});

it('refuses a redirect type that the form does not have in the service', function () {
    $rewrite = Mage::getModel('core/url_rewrite')->setStoreId($this->storeId)->setIdPath('pest/parity/direct')
        ->setRequestPath('pest-parity-direct.html')->setTargetPath('contacts')->setOptions('X');

    expect(fn() => Mage::getService('core/url_rewrite')->save($rewrite))->toThrow(Mage_Core_Exception_Input::class, 'The redirect must be');
});

it('lets a script save a rewrite of the admin store without a target path, as before the service', function () {
    $rewrite = Mage::getModel('core/url_rewrite')->setStoreId(Mage_Core_Model_App::ADMIN_STORE_ID)
        ->setIdPath('pest/parity/script')->setRequestPath('pest-parity-script.html');
    $rewrite->save();

    $saved = Mage::getModel('core/url_rewrite')->load($rewrite->getId());
    expect((int) $saved->getStoreId())->toBe(Mage_Core_Model_App::ADMIN_STORE_ID)
        ->and($saved->getRequestPath())->toBe('pest-parity-script.html')
        ->and($saved->getTargetPath())->toBeNull();
});

describe('custom rewrites of a category', function () {
    beforeEach(function () {
        $this->category = Mage::getModel('core/url_rewrite')->getCollection()
            ->addFieldToFilter('store_id', $this->storeId)
            ->addFieldToFilter('is_system', 1)
            ->addFieldToFilter('id_path', ['like' => 'category/%'])
            ->setPageSize(1)
            ->getFirstItem();
        $this->categoryId = (int) $this->category->getCategoryId();
    });

    it('saves several custom rewrites for the same category on both transports', function () {
        $first = urlRewriteViaAdmin(['category' => $this->categoryId, 'store_id' => $this->storeId, 'request_path' => 'pest-parity-old-1.html', 'options' => 'RP']);
        $second = urlRewriteViaAdmin(['category' => $this->categoryId, 'store_id' => $this->storeId, 'request_path' => 'pest-parity-old-2.html', 'options' => 'RP']);
        $third = urlRewriteViaApi(['storeId' => $this->storeId, 'categoryId' => $this->categoryId, 'requestPath' => 'pest-parity-old-3.html', 'options' => 'RP']);

        $saved = Mage::getModel('core/url_rewrite')->getCollection()
            ->addFieldToFilter('request_path', ['like' => 'pest-parity-old-%']);
        expect($first['errors'])->toBe([])
            ->and($second['errors'])->toBe([])
            ->and($third['status'])->toBe(200)
            ->and($saved->count())->toBe(3);
        foreach ($saved as $rewrite) {
            expect((int) $rewrite->getCategoryId())->toBe($this->categoryId)
                ->and($rewrite->getIdPath())->not->toBe('category/' . $this->categoryId)
                ->and($rewrite->getTargetPath())->toBe($this->category->getRequestPath());
        }
    });

    it('keeps the ID path of a custom rewrite when the admin page saves it again', function () {
        urlRewriteViaAdmin(['category' => $this->categoryId, 'store_id' => $this->storeId, 'request_path' => 'pest-parity-old-1.html']);
        $saved = Mage::getModel('core/url_rewrite')->setStoreId($this->storeId)->loadByRequestPath('pest-parity-old-1.html');

        $again = urlRewriteViaAdmin(['id' => (int) $saved->getId(), 'store_id' => $this->storeId, 'request_path' => 'pest-parity-old-1.html', 'description' => 'Changed']);

        $reloaded = Mage::getModel('core/url_rewrite')->load($saved->getId());
        expect($again['errors'])->toBe([])
            ->and($reloaded->getDescription())->toBe('Changed')
            ->and($reloaded->getIdPath())->toBe($saved->getIdPath());
    });

    it('redirects to the system rewrite when an older custom rewrite has the same ID path', function () {
        Mage::getModel('core/url_rewrite')
            ->setStoreId($this->storeId)
            ->setCategoryId($this->categoryId)
            ->setIdPath('category/' . $this->categoryId)
            ->setRequestPath('pest-parity-legacy.html')
            ->setTargetPath($this->category->getRequestPath())
            ->setOptions('RP')
            ->setIsSystem(false)
            ->save();

        $api = urlRewriteViaApi(['storeId' => $this->storeId, 'categoryId' => $this->categoryId, 'requestPath' => 'pest-parity-old-1.html', 'options' => 'RP']);

        expect($api['status'])->toBe(200)
            ->and($api['result']->targetPath)->toBe($this->category->getRequestPath());
    });

    it('saves an old custom redirect two times when its category has no system rewrite', function () {
        $rootId = (int) Mage::app()->getStore($this->storeId)->getRootCategoryId();
        $legacy = Mage::getModel('core/url_rewrite')
            ->setStoreId($this->storeId)
            ->setCategoryId($rootId)
            ->setIdPath('category/' . $rootId)
            ->setRequestPath('pest-parity-legacy-root.html')
            ->setTargetPath('catalog/category/view/id/' . $rootId)
            ->setOptions('RP')
            ->setIsSystem(false)
            ->save();
        $fields = ['id' => (int) $legacy->getId(), 'store_id' => $this->storeId, 'request_path' => 'pest-parity-legacy-root.html', 'options' => 'RP'];

        $first = urlRewriteViaAdmin($fields + ['description' => 'First']);
        $second = urlRewriteViaAdmin($fields + ['description' => 'Changed']);

        $reloaded = Mage::getModel('core/url_rewrite')->load($legacy->getId());
        expect($first['errors'])->toBe([])
            ->and($second['errors'])->toBe([])
            ->and($reloaded->getDescription())->toBe('Changed')
            ->and($reloaded->getIdPath())->toBe('category/' . $rootId);
    });
});
