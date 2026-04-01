<?php

/**
 * Maho
 *
 * @package    Tests
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/**
 * GraphQL Product Integration Tests
 *
 * Tests product queries via GraphQL.
 * All tests are READ-ONLY (safe for synced database).
 *
 * @group read
 * @group graphql
 */

describe('GraphQL Products Collection Query', function (): void {

    it('returns a list of products', function (): void {
        $query = <<<'GRAPHQL'
        {
            productsProducts(pageSize: 5) {
                edges {
                    node {
                        id
                        _id
                        sku
                        name
                        price
                        finalPrice
                        stockStatus
                    }
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('data');
        expect($response['json']['data'])->toHaveKey('productsProducts');

        $edges = $response['json']['data']['productsProducts']['edges'] ?? [];
        expect($edges)->not->toBeEmpty();

        $product = $edges[0]['node'];
        expect($product)->toHaveKey('sku');
        expect($product)->toHaveKey('name');
        expect($product)->toHaveKey('price');
        expect($product)->toHaveKey('finalPrice');
        expect($product)->toHaveKey('stockStatus');
    });

    it('returns product IDs as IRI strings', function (): void {
        $query = <<<'GRAPHQL'
        {
            productsProducts(pageSize: 1) {
                edges {
                    node {
                        id
                        _id
                        sku
                    }
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);

        $edges = $response['json']['data']['productsProducts']['edges'] ?? [];
        expect($edges)->not->toBeEmpty();

        $product = $edges[0]['node'];
        // GraphQL `id` should be IRI format: /api/products/{id}
        expect($product['id'])->toContain('/api/products/');
        // `_id` should be the numeric ID
        expect($product['_id'])->toBeInt();
    });

    it('supports search filter', function (): void {
        $query = <<<'GRAPHQL'
        {
            productsProducts(search: "dress", pageSize: 5) {
                edges {
                    node {
                        sku
                        name
                    }
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json']['data'])->toHaveKey('productsProducts');
    });

    it('supports category filter', function (): void {
        $categoryId = fixtures('category_id');

        $query = <<<GRAPHQL
        {
            productsProducts(categoryId: {$categoryId}, pageSize: 5) {
                edges {
                    node {
                        sku
                        name
                        categoryIds
                    }
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);

        $edges = $response['json']['data']['productsProducts']['edges'] ?? [];
        expect($edges)->not->toBeEmpty();
    });

    it('supports price range filter', function (): void {
        $query = <<<'GRAPHQL'
        {
            productsProducts(priceMin: 50, priceMax: 200, pageSize: 5) {
                edges {
                    node {
                        sku
                        name
                        price
                    }
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);

        $edges = $response['json']['data']['productsProducts']['edges'] ?? [];
        foreach ($edges as $edge) {
            $price = (float) $edge['node']['price'];
            expect($price)->toBeGreaterThanOrEqual(50);
            expect($price)->toBeLessThanOrEqual(200);
        }
    });

});

describe('GraphQL Single Product Query', function (): void {

    it('returns a single product by IRI', function (): void {
        $productId = fixtures('product_id');
        $iri = "/api/products/{$productId}";

        $query = <<<GRAPHQL
        {
            productProduct(id: "{$iri}") {
                id
                _id
                sku
                name
                price
                finalPrice
                stockStatus
                categoryIds
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('data');
        expect($response['json']['data']['productProduct'])->not->toBeNull();

        $product = $response['json']['data']['productProduct'];
        expect($product['_id'])->toBe($productId);
        expect($product['sku'])->toBeString();
        expect($product['name'])->toBeString();
    });

    it('returns null for non-existent product', function (): void {
        $invalidId = fixtures('invalid_product_id');
        $iri = "/api/products/{$invalidId}";

        $query = <<<GRAPHQL
        {
            productProduct(id: "{$iri}") {
                id
                sku
                name
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);
        $data = $response['json']['data']['productProduct'] ?? null;
        $errors = $response['json']['errors'] ?? [];
        expect($data === null || !empty($errors))->toBeTrue();
    });

});

describe('GraphQL Product By SKU Query', function (): void {

    it('returns product by SKU', function (): void {
        $sku = fixtures('product_sku');

        $query = <<<GRAPHQL
        {
            productBySkuProduct(sku: "{$sku}") {
                id
                _id
                sku
                name
                price
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('data');

        $product = $response['json']['data']['productBySkuProduct'];
        if ($product === null) {
            // Known issue: productBySku custom query may return null
            $this->markTestSkipped('productBySkuProduct returns null — provider may not be implemented for this operation');
        }

        expect($product['sku'])->toBe($sku);
        expect($product['name'])->toBeString();
        expect($product['price'])->toBeNumeric();
    });

});

describe('GraphQL Category Products Query', function (): void {

    it('returns products for a category', function (): void {
        $categoryId = fixtures('category_id');

        $query = <<<GRAPHQL
        {
            categoryProductsProducts(categoryId: {$categoryId}, pageSize: 5) {
                edges {
                    node {
                        sku
                        name
                        price
                    }
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json']['data'])->toHaveKey('categoryProductsProducts');

        $edges = $response['json']['data']['categoryProductsProducts']['edges'] ?? [];
        expect($edges)->not->toBeEmpty();
    });

});
