<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 request body values of the wrong JSON type (WRITE)
 *
 * Values read from the raw body (aliases, keys the resource does not declare,
 * entries of array fields) answer 400 instead of 500 or a silent cast.
 *
 * @group write
 */

function requestBodyTypesProduct(): int
{
    $sku = 'pest-body-types-' . uniqid();
    $product = Mage::getModel('catalog/product')->setStoreId(0)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId((int) Mage::getModel('catalog/product')->getDefaultAttributeSetId())
        ->setSku($sku)->setName('Pest body types')->setUrlKey($sku)->setWeight(1)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setPrice(10)->setTaxClassId(0)->setWebsiteIds([1])
        ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 1, 'is_in_stock' => 1, 'qty' => 10]);
    $product->save();
    trackCreated('product', (int) $product->getId());

    return (int) $product->getId();
}

function requestBodyTypesManageStock(int $productId): int
{
    $resource = Mage::getSingleton('core/resource');

    return (int) $resource->getConnection('core_read')->fetchOne(
        'SELECT manage_stock FROM ' . $resource->getTableName('cataloginventory/stock_item') . ' WHERE product_id = ?',
        [$productId],
    );
}

describe('Request body types', function (): void {

    beforeEach(function (): void {
        Mage::app();
        $this->productId = requestBodyTypesProduct();
        $this->sku = (string) Mage::getModel('catalog/product')->load($this->productId)->getSku();
    });

    afterEach(function (): void {
        cleanupTestData();
    });

    it('answers 400 naming the field, as for a field of a resource', function (string $method, string $path, array $body, array $permissions, string $field, string $message): void {
        $path = str_replace('{id}', (string) $this->productId, $path);
        $body = json_decode(str_replace('"{sku}"', json_encode($this->sku), json_encode($body)), true);
        $token = serviceToken($permissions);

        $response = $method === 'PUT' ? apiPut($path, $body, $token) : apiPost($path, $body, $token);

        expect($response['status'])->toBe(400);
        expect($response['json']['message'] ?? '')->toBe($message);
        expect($response['json']['details']['errors'] ?? [])->toBe([['field' => $field, 'message' => $message]]);
    })->with([
        'a tier price that is a word' => ['PUT', '/api/rest/v2/products/{id}/tier-prices', [['qty' => 2, 'price' => 'abc']], ['products/write'], '[0].price', 'The type of the "[0].price" attribute must be "float", "string" given.'],
        'an image URL that is a list' => ['POST', '/api/rest/v2/products/{id}/media', ['imageUrl' => ['x']], ['products/write'], 'imageUrl', 'The type of the "imageUrl" attribute must be "string", "array" given.'],
        'a linked product id that is a list' => ['POST', '/api/rest/v2/products/{id}/links/related', ['linked_product_id' => [1]], ['products/write'], 'linked_product_id', 'The type of the "linked_product_id" attribute must be "int", "array" given.'],
        'a bulk stock quantity that is a word' => ['PUT', '/api/rest/v2/inventory/bulk', ['items' => [['sku' => '{sku}', 'qty' => 'abc']]], ['inventory/write'], 'items[0].qty', 'The type of the "items[0].qty" attribute must be "float", "string" given.'],
        'a coupon cart id that is a list' => ['POST', '/api/rest/v2/coupons/validate', ['code' => 'PEST-NONE', 'cartId' => [1]], [], 'cartId', 'The type of the "cartId" attribute must be "int", "array" given.'],
        'bulk stock items that are not a list' => ['PUT', '/api/rest/v2/inventory/bulk', ['items' => 'x'], ['inventory/write'], 'items', 'The type of the "items" attribute must be "list", "string" given.'],
        'a product stock quantity that is a word' => ['PUT', '/api/rest/v2/products/{id}', ['stockData' => ['qty' => 'abc']], ['products/write'], 'stockData.qty', 'The type of the "stockData.qty" attribute must be "float", "string" given.'],
    ]);

    it('answers a field of a resource of the wrong type the same way', function (): void {
        $response = apiPut('/api/rest/v2/inventory', ['sku' => $this->sku, 'qty' => 'abc'], serviceToken(['inventory/write']));

        expect($response['status'])->toBe(400);
        expect($response['json']['details']['errors'] ?? [])->toBe([['field' => 'qty', 'message' => 'The type of the "qty" attribute must be "float", "string" given.']]);
    });

    it('keeps no tier price from a rejected request', function (): void {
        apiPut("/api/rest/v2/products/{$this->productId}/tier-prices", [['qty' => 2, 'price' => 'abc']], serviceToken(['products/write']));

        $read = apiGet("/api/rest/v2/products/{$this->productId}/tier-prices", serviceToken(['products/read']));
        expect($read['status'])->toBe(200);
        expect(getItems($read))->toBe([]);
    });

    it('rejects product links sent as an object instead of a list', function (): void {
        $response = apiPut("/api/rest/v2/products/{$this->productId}/links/related", ['a' => ['linkedProductId' => $this->productId]], serviceToken(['products/write']));

        expect($response['status'])->toBe(400);
        expect($response['json']['message'] ?? '')->toBe('Request body must be an array of links');
    });

    it('rejects tier prices sent as an object instead of a list', function (): void {
        $response = apiPut("/api/rest/v2/products/{$this->productId}/tier-prices", ['a' => ['qty' => 2, 'price' => 5]], serviceToken(['products/write']));

        expect($response['status'])->toBe(400);
        expect($response['json']['message'] ?? '')->toBe('Request body must be an array of tier prices');
    });

    it('reads the string "false" as false', function (): void {
        $response = apiPut('/api/rest/v2/inventory/bulk', ['items' => [['sku' => $this->sku, 'manageStock' => 'false']]], serviceToken(['inventory/write']));

        expect($response['status'])->toBe(200);
        expect(requestBodyTypesManageStock($this->productId))->toBe(0);
    });

    it('reads the string "false" as false in the stock data of a product', function (): void {
        $response = apiPut("/api/rest/v2/products/{$this->productId}", ['stockData' => ['manageStock' => 'false']], serviceToken(['products/write']));

        expect($response['status'])->toBe(200);
        expect(requestBodyTypesManageStock($this->productId))->toBe(0);
    });

    it('answers a client secret that is not a string with the invalid_request error of OAuth', function (): void {
        $response = apiPost('/api/rest/v2/auth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => 'pest-unknown-client',
            'client_secret' => ['x'],
        ]);

        expect($response['status'])->toBe(400);
        expect($response['json']['error'] ?? null)->toBe('invalid_request');
    });

    it('keeps the values of a select option when an update is rejected', function (): void {
        $token = serviceToken(['products/write', 'products/delete']);
        $path = "/api/rest/v2/products/{$this->productId}/custom-options";
        $create = apiPost($path, [
            'title' => 'Pest body types option',
            'type' => 'drop_down',
            'values' => [['title' => 'Kept', 'price' => 1]],
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        $optionId = (int) $create['json']['id'];

        $update = apiPut("{$path}/{$optionId}", ['values' => [['title' => 'New', 'price' => 'abc']]], $token);
        expect($update['status'])->toBe(400);
        expect($update['json']['details']['errors'][0]['field'] ?? null)->toBe('values[0].price');

        $notObject = apiPut("{$path}/{$optionId}", ['values' => ['Red']], $token);
        expect($notObject['status'])->toBe(400);
        expect($notObject['json']['details']['errors'][0]['field'] ?? null)->toBe('values[0]');

        $titles = [];
        foreach (getItems(apiGet($path)) as $option) {
            if ((int) $option['id'] === $optionId) {
                $titles = array_column($option['values'], 'title');
            }
        }
        expect($titles)->toBe(['Kept']);
    });

    it('takes a password sent as a number as its string', function (): void {
        $response = apiPost('/api/rest/v2/auth/token', [
            'grant_type' => 'customer',
            'email' => 'pest-nobody@example.com',
            'password' => 12345,
        ]);

        expect($response['status'])->toBe(401);
    });

});
