<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Mage\Catalog\Api\Product;
use Mage\Catalog\Api\ProductProcessor;
use Tests\Helpers\AdminApiParity;

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin product page and the product API save and delete a product through Mage_Catalog_Service_Product (#1348).
 * Each test gives the same input to both transports and compares the outcome.
 */

function productParityUnregister(): void
{
    Mage::unregister('product');
    Mage::unregister('current_product');
}

/**
 * @param array<string, mixed> $fields the fields of the admin form
 * @return array{success: list<string>, errors: list<string>}
 */
function productViaAdmin(string $action, array $fields): array
{
    productParityUnregister();
    return AdminApiParity::admin(Mage_Adminhtml_Catalog_ProductController::class, $action, $fields);
}

/**
 * @param array<string, mixed> $product the fields of the product form that differ from a valid new product
 * @return array<string, mixed>
 */
function productAdminFields(string $sku, array $product = []): array
{
    return [
        'set' => (int) Mage::getModel('catalog/product')->getDefaultAttributeSetId(),
        'type' => Mage_Catalog_Model_Product_Type::TYPE_SIMPLE,
        'product' => $product + [
            'sku' => $sku,
            'name' => 'Pest Parity Product',
            'price' => 10,
            'weight' => 1,
            'status' => Mage_Catalog_Model_Product_Status::STATUS_ENABLED,
            'visibility' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
            'tax_class_id' => 0,
            'website_ids' => [1],
            'stock_data' => ['qty' => 5, 'is_in_stock' => 1, 'use_config_manage_stock' => 1],
        ],
    ];
}

/**
 * @param array<string, mixed> $body the JSON body
 * @param list<int>|null $allowedStoreIds the store views of a store-restricted token
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function productViaApi(array $body, ?int $id = null, bool $delete = false, ?array $allowedStoreIds = null): array
{
    $method = $delete ? 'DELETE' : ($id ? 'PUT' : 'POST');
    $request = AdminApiParity::jsonRequest($method, '/api/rest/v2/products' . ($id ? "/{$id}" : ''), $body);
    $dto = new Product();
    foreach ($body as $property => $value) {
        $dto->{$property} = $value;
    }
    $operation = match ($method) {
        'DELETE' => new Delete(),
        'PUT' => new Put(),
        default => new Post(),
    };
    return AdminApiParity::api($request, fn() => new ProductProcessor(AdminApiParity::adminSecurity($allowedStoreIds))
        ->process($delete ? null : $dto, $operation, $id ? ['id' => $id] : [], ['request' => $request]));
}

function productParityCreate(string $sku): Mage_Catalog_Model_Product
{
    /** @var Mage_Catalog_Service_Product $service */
    $service = Mage::getService('catalog/product');
    $product = $service->newProduct()
        ->setSku($sku)
        ->setName('Pest Parity Existing')
        ->setPrice(10)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTaxClassId(0);
    $service->setWebsiteIds($product, [1]);
    return $service->save($product);
}

function productParityCleanup(): void
{
    Mage::register('isSecureArea', true, true);
    foreach (Mage::getModel('catalog/product')->getCollection()->addAttributeToFilter('sku', ['like' => 'PEST-PARITY-%']) as $product) {
        Mage::getModel('catalog/product')->load($product->getId())->delete();
    }
    Mage::unregister('isSecureArea');
}

beforeEach(function () {
    productParityCleanup();
});

afterEach(function () {
    productParityCleanup();
    productParityUnregister();
});

it('creates a product on both transports', function () {
    $admin = productViaAdmin('save', productAdminFields('PEST-PARITY-ADMIN'));
    $api = productViaApi(['sku' => 'PEST-PARITY-API', 'name' => 'Pest Parity Product', 'price' => 10.0, 'websiteIds' => [1], 'stockData' => ['qty' => 5]]);

    /** @var Mage_Catalog_Service_Product $service */
    $service = Mage::getService('catalog/product');
    expect($admin['errors'])->toBe([])
        ->and($admin['success'])->toBe(['The product has been saved.'])
        ->and($service->getIdBySku('PEST-PARITY-ADMIN'))->not->toBeNull()
        ->and($api['status'])->toBe(200)
        ->and($api['result']->sku)->toBe('PEST-PARITY-API')
        ->and($api['result']->stockQty)->toBe(5.0);
});

it('refuses a SKU that another product has on both transports, and keeps the other product', function () {
    $existing = productParityCreate('PEST-PARITY-TAKEN');

    $admin = productViaAdmin('save', productAdminFields('PEST-PARITY-TAKEN'));
    $api = productViaApi(['sku' => 'PEST-PARITY-TAKEN', 'name' => 'Pest Parity Overwrite', 'websiteIds' => [1]]);

    expect($admin['errors'])->toBe(['The value of attribute "SKU" must be unique'])
        ->and($api['status'])->toBe(409)
        ->and($api['errors'])->toBe(['The value of attribute "SKU" must be unique'])
        ->and(Mage::getModel('catalog/product')->load($existing->getId())->getName())->toBe('Pest Parity Existing');
});

it('refuses HTML tags in the SKU on both transports', function () {
    $admin = productViaAdmin('save', productAdminFields('PEST-PARITY-<b>HTML</b>'));
    $api = productViaApi(['sku' => 'PEST-PARITY-<b>HTML</b>', 'name' => 'Pest Parity Product', 'websiteIds' => [1]]);

    expect($admin['errors'])->toBe(['HTML tags are not allowed in SKU attribute.'])
        ->and($api['status'])->toBe(422)
        ->and($api['fields'])->toBe(['sku'])
        ->and($api['errors'])->toBe(['HTML tags are not allowed in SKU attribute.']);
});

it('refuses a special price date range that starts after it ends on both transports', function () {
    $admin = productViaAdmin('save', productAdminFields('PEST-PARITY-DATES', ['special_from_date' => '2026-08-10', 'special_to_date' => '2026-08-01']));
    $api = productViaApi(['sku' => 'PEST-PARITY-DATES', 'name' => 'Pest Parity Product', 'websiteIds' => [1], 'specialFromDate' => '2026-08-10', 'specialToDate' => '2026-08-01']);

    $message = 'The From Date value should be less than or equal to the To Date value.';
    expect($admin['errors'])->toBe([$message])
        ->and($api['status'])->toBe(422)
        ->and($api['fields'])->toBe(['specialFromDate'])
        ->and($api['errors'])->toBe([$message]);
});

it('refuses a negative quantity on both transports', function () {
    $fields = productAdminFields('PEST-PARITY-QTY');
    $fields['product']['stock_data']['qty'] = -1;
    $admin = productViaAdmin('save', $fields);
    $api = productViaApi(['sku' => 'PEST-PARITY-QTY', 'name' => 'Pest Parity Product', 'websiteIds' => [1], 'stockData' => ['qty' => -1]]);

    expect($admin['errors'])->toBe(['Quantity cannot be negative'])
        ->and($api['status'])->toBe(422)
        ->and($api['fields'])->toBe(['qty'])
        ->and($api['errors'])->toBe(['Quantity cannot be negative']);
});

it('deletes a product through the service, which sends the delete event, on both transports', function () {
    $forAdmin = productParityCreate('PEST-PARITY-DELETE-ADMIN');
    $forApi = productParityCreate('PEST-PARITY-DELETE-API');
    $spy = new class extends Mage_Catalog_Service_Product {
        /** @var list<int> */
        public array $deleted = [];

        #[\Override]
        public function delete(Mage_Catalog_Model_Product $product): void
        {
            $this->deleted[] = (int) $product->getId();
            parent::delete($product);
        }
    };
    Mage::unregister('_service/catalog/product');
    Mage::register('_service/catalog/product', $spy);

    $admin = productViaAdmin('delete', ['id' => (int) $forAdmin->getId()]);
    $api = productViaApi([], (int) $forApi->getId(), delete: true);

    expect($admin['success'])->toBe(['The product has been deleted.'])
        ->and($api['status'])->toBe(200)
        ->and($spy->deleted)->toBe([(int) $forAdmin->getId(), (int) $forApi->getId()])
        ->and(Mage::getModel('catalog/product')->load($forAdmin->getId())->getId())->toBeNull()
        ->and(Mage::getModel('catalog/product')->load($forApi->getId())->getId())->toBeNull();
});

it('reverts a store view value with use default, which the API refuses without a store view', function () {
    $storeId = (int) Mage::app()->getDefaultStoreView()->getId();
    $product = productParityCreate('PEST-PARITY-DEFAULT');
    Mage::getModel('catalog/product')->setStoreId($storeId)->load($product->getId())->setData('meta_title', 'Store title')->save();

    $fields = ['id' => (int) $product->getId(), 'store' => $storeId, 'use_default' => ['meta_title'], 'product' => ['name' => 'Pest Parity Existing']];
    $admin = productViaAdmin('save', $fields);
    $api = productViaApi(['useDefault' => ['meta_title']], (int) $product->getId());

    expect($admin['errors'])->toBe([])
        ->and(Mage::getModel('catalog/product')->setStoreId($storeId)->load($product->getId())->getMetaTitle())->toBeNull()
        ->and($api['status'])->toBe(422)
        ->and($api['fields'])->toBe(['useDefault']);
});

describe('the mass status action of the admin', function () {
    it('refuses a status that does not exist', function () {
        $product = productParityCreate('PEST-PARITY-STATUS');

        $admin = productViaAdmin('massStatus', ['product' => [(int) $product->getId()], 'status' => 7]);

        expect($admin['errors'])->toBe(['Unknown product status: 7'])
            ->and((int) Mage::getModel('catalog/product')->load($product->getId())->getStatus())->toBe(Mage_Catalog_Model_Product_Status::STATUS_ENABLED);
    });
});

describe('a store-restricted API token', function () {
    it('cannot delete a product outside the websites of its store views', function () {
        $product = productParityCreate('PEST-PARITY-SCOPE');

        $api = productViaApi([], (int) $product->getId(), delete: true, allowedStoreIds: [999999]);

        expect($api['status'])->toBe(403)
            ->and($api['errors'])->toBe(["Access denied for this product's websites"])
            ->and(Mage::getModel('catalog/product')->load($product->getId())->getId())->not->toBeNull();
    });
});
