<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Mage\Catalog\Api\ConfigurableSetupProcessor;
use Mage\Catalog\Api\ConfigurableSetupProvider;
use Mage\Catalog\Api\GroupedProductLinkProcessor;
use Mage\Catalog\Api\GroupedProductLinkProvider;
use Mage\Catalog\Api\ProductCustomOptionProcessor;
use Mage\Catalog\Api\ProductCustomOptionProvider;
use Mage\Catalog\Api\ProductLinkProcessor;
use Mage\Catalog\Api\ProductLinkProvider;
use Mage\Catalog\Api\ProductTierPriceProcessor;
use Mage\Catalog\Api\ProductTierPriceProvider;
use Tests\Helpers\AdminApiParity;

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin product save and the product sub-resources of the API write tier prices, custom options, links and
 * configurable children through the same backend, option and type models (#1348). Each test gives the same input to
 * both transports and compares the outcome.
 */

/**
 * Save $product as the admin product page does: put $data into it, validate it and save it.
 *
 * @param callable(Mage_Catalog_Model_Product): void $prepare
 * @return list<string> the error messages
 */
function productPartsViaAdmin(Mage_Catalog_Model_Product $product, callable $prepare): array
{
    $product = Mage::getModel('catalog/product')->setStoreId(0)->load($product->getId());
    $prepare($product);
    try {
        $product->validate();
        $product->save();
        return [];
    } catch (Mage_Core_Exception_Input $e) {
        return array_column($e->getErrors(), 'message');
    } catch (Mage_Core_Exception $e) {
        return [$e->getMessage()];
    }
}

/**
 * @param array<int|string, mixed> $body
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function productPartsViaApi(string $processorClass, string $providerClass, string $method, string $path, array $body, array $uriVariables): array
{
    $request = AdminApiParity::jsonRequest($method, $path, $body);
    $operation = match ($method) {
        'DELETE' => new Delete(),
        'PUT' => new Put(),
        default => new Post(),
    };
    $security = AdminApiParity::adminSecurity();
    return AdminApiParity::api($request, fn() => new $processorClass($security, new $providerClass($security))
        ->process(null, $operation, $uriVariables, ['request' => $request]));
}

function productPartsProduct(string $sku, string $typeId = Mage_Catalog_Model_Product_Type::TYPE_SIMPLE): Mage_Catalog_Model_Product
{
    /** @var Mage_Catalog_Model_Product $product */
    $product = Mage::getModel('catalog/product');
    $product->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID)
        ->setTypeId($typeId)
        ->setAttributeSetId(4)
        ->setSku($sku . '-' . uniqid())
        ->setName('Pest ' . $sku)
        ->setPrice(10.0)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setWebsiteIds([1])
        ->save();
    return $product;
}

beforeEach(function () {
    $this->products = [];
});

afterEach(function () {
    Mage::register('isSecureArea', true, true);
    foreach (array_reverse($this->products) as $product) {
        Mage::getModel('catalog/product')->load($product->getId())->delete();
    }
    Mage::unregister('isSecureArea');
});

describe('tier prices', function () {
    it('refuses a negative price on both transports', function () {
        $this->products[] = $product = productPartsProduct('parts-tier-negative');
        $row = ['website_id' => 0, 'cust_group' => Mage_Customer_Model_Group::CUST_GROUP_ALL, 'price_qty' => 2, 'price' => -1];

        $admin = productPartsViaAdmin($product, fn(Mage_Catalog_Model_Product $p) => $p->setTierPrice([$row]));
        $api = productPartsViaApi(ProductTierPriceProcessor::class, ProductTierPriceProvider::class, 'PUT', "/api/rest/v2/products/{$product->getId()}/tier-prices", [['qty' => 2, 'price' => -1]], ['productId' => (int) $product->getId()]);

        expect($admin)->toBe(['Price must not be negative'])
            ->and($api['status'])->toBe(422)
            ->and($api['errors'])->toBe($admin);
    });

    it('refuses two rows for the same website, group and quantity on both transports', function () {
        $this->products[] = $product = productPartsProduct('parts-tier-duplicate');
        $row = ['website_id' => 0, 'cust_group' => Mage_Customer_Model_Group::CUST_GROUP_ALL, 'price_qty' => 2, 'price' => 8];

        $admin = productPartsViaAdmin($product, fn(Mage_Catalog_Model_Product $p) => $p->setTierPrice([$row, $row]));
        $api = productPartsViaApi(ProductTierPriceProcessor::class, ProductTierPriceProvider::class, 'PUT', "/api/rest/v2/products/{$product->getId()}/tier-prices", [['qty' => 2, 'price' => 8], ['qty' => 2, 'price' => 8]], ['productId' => (int) $product->getId()]);

        expect($admin)->toBe(['Duplicate website tier price customer group and quantity.'])
            ->and($api['status'])->toBe(422)
            ->and($api['errors'])->toBe($admin);
    });
});

describe('custom options', function () {
    it('refuses a select option without values on both transports', function () {
        $this->products[] = $product = productPartsProduct('parts-option-select');

        $admin = productPartsViaAdmin($product, function (Mage_Catalog_Model_Product $p): void {
            $p->setProductOptions([['option_id' => '0', 'is_delete' => '', 'title' => 'Size', 'type' => 'drop_down', 'is_require' => 1, 'values' => []]]);
            $p->setCanSaveCustomOptions(true);
        });
        $api = productPartsViaApi(ProductCustomOptionProcessor::class, ProductCustomOptionProvider::class, 'POST', "/api/rest/v2/products/{$product->getId()}/custom-options", ['title' => 'Size', 'type' => 'drop_down', 'values' => []], ['productId' => (int) $product->getId()]);

        expect($admin)->toBe(['Select-type options require at least one value'])
            ->and($api['status'])->toBe(422)
            ->and($api['errors'])->toBe($admin);
    });

    it('sets has_options and required_options the same way on both transports', function () {
        $this->products[] = $adminProduct = productPartsProduct('parts-option-admin');
        $this->products[] = $apiProduct = productPartsProduct('parts-option-api');

        productPartsViaAdmin($adminProduct, function (Mage_Catalog_Model_Product $p): void {
            $p->setProductOptions([['option_id' => '0', 'is_delete' => '', 'title' => 'Note', 'type' => 'field', 'is_require' => 1, 'price' => 2, 'price_type' => 'fixed']]);
            $p->setCanSaveCustomOptions(true);
        });
        $api = productPartsViaApi(ProductCustomOptionProcessor::class, ProductCustomOptionProvider::class, 'POST', "/api/rest/v2/products/{$apiProduct->getId()}/custom-options", ['title' => 'Note', 'type' => 'field', 'required' => true, 'price' => 2], ['productId' => (int) $apiProduct->getId()]);

        $admin = Mage::getModel('catalog/product')->load($adminProduct->getId());
        $viaApi = Mage::getModel('catalog/product')->load($apiProduct->getId());
        expect($api['status'])->toBe(200)
            ->and($api['result']->title)->toBe('Note')
            ->and([(bool) $viaApi->getHasOptions(), (bool) $viaApi->getRequiredOptions()])
            ->toBe([(bool) $admin->getHasOptions(), (bool) $admin->getRequiredOptions()])
            ->and((bool) $viaApi->getRequiredOptions())->toBeTrue();
    });
});

describe('grouped and configurable children', function () {
    it('refuses a grouped child of a type that the grouped product cannot contain on both transports', function () {
        $this->products[] = $grouped = productPartsProduct('parts-grouped', Mage_Catalog_Model_Product_Type::TYPE_GROUPED);
        $this->products[] = $child = productPartsProduct('parts-grouped-child', Mage_Catalog_Model_Product_Type::TYPE_GROUPED);

        $admin = productPartsViaAdmin($grouped, fn(Mage_Catalog_Model_Product $p) => $p->setGroupedLinkData([(int) $child->getId() => ['qty' => 1, 'position' => 0]]));
        $api = productPartsViaApi(GroupedProductLinkProcessor::class, GroupedProductLinkProvider::class, 'POST', "/api/rest/v2/products/{$grouped->getId()}/grouped", ['childProductId' => (int) $child->getId()], ['productId' => (int) $grouped->getId()]);

        expect($admin)->toHaveCount(1)
            ->and($admin[0])->toStartWith("Product {$child->getId()} is a grouped product")
            ->and($api['status'])->toBe(422)
            ->and($api['errors'])->toBe($admin);
    });

    it('refuses a super attribute that a configurable product cannot use', function () {
        $this->products[] = $configurable = productPartsProduct('parts-configurable', Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE);
        $nameId = (int) Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'name')->getId();

        $admin = productPartsViaAdmin($configurable, function (Mage_Catalog_Model_Product $p) use ($nameId): void {
            $p->setConfigurableAttributesData([['attribute_id' => $nameId, 'position' => 0]]);
            $p->setCanSaveConfigurableAttributes(true);
        });
        $api = productPartsViaApi(ConfigurableSetupProcessor::class, ConfigurableSetupProvider::class, 'PUT', "/api/rest/v2/products/{$configurable->getId()}/configurable", ['superAttributes' => ['name']], ['productId' => (int) $configurable->getId()]);

        expect($admin)->toHaveCount(1)
            ->and($admin[0])->toStartWith('Attribute "name" cannot be used for a configurable product')
            ->and($api['status'])->toBe(422)
            ->and($api['errors'])->toBe($admin);
    });
});

describe('related links', function () {
    it('answers 404 to the delete of a link that does not exist', function () {
        $this->products[] = $product = productPartsProduct('parts-link');

        $api = productPartsViaApi(ProductLinkProcessor::class, ProductLinkProvider::class, 'DELETE', "/api/rest/v2/products/{$product->getId()}/links/related/999999999", [], ['productId' => (int) $product->getId()]);

        expect($api['status'])->toBe(404)
            ->and($api['errors'])->toBe(['Link not found']);
    });
});
