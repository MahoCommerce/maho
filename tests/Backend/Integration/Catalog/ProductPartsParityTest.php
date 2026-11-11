<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Mage\Catalog\Api\BundleOptionProcessor;
use Mage\Catalog\Api\BundleOptionProvider;
use Mage\Catalog\Api\ConfigurableSetupProcessor;
use Mage\Catalog\Api\ConfigurableSetupProvider;
use Mage\Catalog\Api\DownloadableLinkProcessor;
use Mage\Catalog\Api\DownloadableLinkProvider;
use Mage\Catalog\Api\GroupedProductLinkProcessor;
use Mage\Catalog\Api\GroupedProductLinkProvider;
use Mage\Catalog\Api\ProductCustomOptionProcessor;
use Mage\Catalog\Api\ProductCustomOptionProvider;
use Mage\Catalog\Api\ProductGroupPriceProcessor;
use Mage\Catalog\Api\ProductGroupPriceProvider;
use Mage\Catalog\Api\ProductLinkProcessor;
use Mage\Catalog\Api\ProductLinkProvider;
use Mage\Catalog\Api\ProductMediaProcessor;
use Mage\Catalog\Api\ProductMediaProvider;
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

/**
 * Return a simple product with one required custom option.
 */
function productPartsProductWithRequiredOption(string $sku): Mage_Catalog_Model_Product
{
    $product = productPartsProduct($sku);
    productPartsViaAdmin($product, function (Mage_Catalog_Model_Product $p): void {
        $p->setProductOptions([['option_id' => '0', 'is_delete' => '', 'title' => 'Note', 'type' => 'field', 'is_require' => 1, 'price' => 0, 'price_type' => 'fixed']]);
        $p->setCanSaveCustomOptions(true);
    });
    return $product;
}

/**
 * @return list<int> the IDs of the custom options of the product $productId
 */
function productPartsOptionIds(int $productId): array
{
    return array_map(intval(...), Mage::getResourceModel('catalog/product_option_collection')->addProductToFilter($productId)->getAllIds());
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

describe('group prices', function () {
    it('refuses a negative price on both transports', function () {
        $this->products[] = $product = productPartsProduct('parts-group-negative');
        $row = ['website_id' => 0, 'cust_group' => 1, 'price' => -1];

        $admin = productPartsViaAdmin($product, fn(Mage_Catalog_Model_Product $p) => $p->setGroupPrice([$row]));
        $api = productPartsViaApi(ProductGroupPriceProcessor::class, ProductGroupPriceProvider::class, 'PUT', "/api/rest/v2/products/{$product->getId()}/group-prices", [['customerGroupId' => 1, 'price' => -1]], ['productId' => (int) $product->getId()]);

        expect($admin)->toBe(['Price must not be negative'])
            ->and($api['status'])->toBe(422)
            ->and($api['errors'])->toBe($admin);
    });
});

describe('bundle options', function () {
    it('refuses an option without a title on both transports', function () {
        $this->products[] = $bundle = productPartsProduct('parts-bundle-title', Mage_Catalog_Model_Product_Type::TYPE_BUNDLE);

        $admin = productPartsViaAdmin($bundle, function (Mage_Catalog_Model_Product $p): void {
            $p->setBundleOptionsData([['option_id' => '', 'delete' => '', 'title' => '', 'type' => 'select', 'required' => 1, 'position' => 0]]);
            $p->setBundleSelectionsData([[]]);
            $p->setCanSaveBundleSelections(true);
        });
        $api = productPartsViaApi(BundleOptionProcessor::class, BundleOptionProvider::class, 'POST', "/api/rest/v2/products/{$bundle->getId()}/bundle-options", ['title' => '', 'type' => 'select'], ['productId' => (int) $bundle->getId()]);

        expect($admin)->toBe(['title is required'])
            ->and($api['status'])->toBe(422)
            ->and($api['errors'])->toBe($admin);
    });

    it('refuses a selection with a required custom option on both transports', function () {
        $this->products[] = $bundle = productPartsProduct('parts-bundle-required', Mage_Catalog_Model_Product_Type::TYPE_BUNDLE);
        $this->products[] = $child = productPartsProductWithRequiredOption('parts-bundle-required-child');
        $childId = (int) $child->getId();

        $admin = productPartsViaAdmin($bundle, function (Mage_Catalog_Model_Product $p) use ($childId): void {
            $p->setBundleOptionsData([['option_id' => '', 'delete' => '', 'title' => 'Part', 'type' => 'select', 'required' => 1, 'position' => 0]]);
            $p->setBundleSelectionsData([[[
                'selection_id' => '', 'delete' => '', 'product_id' => $childId, 'selection_qty' => 1, 'selection_price_value' => 0,
                'selection_price_type' => 0, 'selection_can_change_qty' => 1, 'is_default' => 0, 'position' => 0,
            ]]]);
            $p->setCanSaveBundleSelections(true);
        });
        $api = productPartsViaApi(BundleOptionProcessor::class, BundleOptionProvider::class, 'POST', "/api/rest/v2/products/{$bundle->getId()}/bundle-options", ['title' => 'Part', 'type' => 'select', 'selections' => [['productId' => $childId]]], ['productId' => (int) $bundle->getId()]);

        expect($admin)->toBe(["Product {$childId} has a required custom option."])
            ->and($api['status'])->toBe(422)
            ->and($api['errors'])->toBe($admin);
    });

    it('deletes the custom options of a bundle that gets a dynamic price, and refuses a new one', function () {
        $this->products[] = $bundle = productPartsProduct('parts-bundle-dynamic', Mage_Catalog_Model_Product_Type::TYPE_BUNDLE);
        $bundleId = (int) $bundle->getId();
        productPartsViaAdmin($bundle, function (Mage_Catalog_Model_Product $p): void {
            $p->setData('price_type', Mage_Bundle_Model_Product_Price::PRICE_TYPE_FIXED);
            $p->setProductOptions([['option_id' => '0', 'is_delete' => '', 'title' => 'Note', 'type' => 'field', 'is_require' => 0, 'price' => 1, 'price_type' => 'fixed']]);
            $p->setCanSaveCustomOptions(true);
        });
        expect(productPartsOptionIds($bundleId))->toHaveCount(1);

        // A save that does not come from the admin product page, so the observer of that page does not run
        $admin = productPartsViaAdmin($bundle, fn(Mage_Catalog_Model_Product $p) => $p->setData('price_type', Mage_Bundle_Model_Product_Price::PRICE_TYPE_DYNAMIC));
        expect($admin)->toBe([])
            ->and(productPartsOptionIds($bundleId))->toBe([]);

        $api = productPartsViaApi(ProductCustomOptionProcessor::class, ProductCustomOptionProvider::class, 'POST', "/api/rest/v2/products/{$bundleId}/custom-options", ['title' => 'Note', 'type' => 'field'], ['productId' => $bundleId]);
        expect($api['status'])->toBe(409)
            ->and($api['errors'])->toBe(['This product cannot have custom options.'])
            ->and(productPartsOptionIds($bundleId))->toBe([]);
    });
});

describe('children with a required custom option or another attribute set', function () {
    it('refuses a grouped child with a required custom option on both transports', function () {
        $this->products[] = $grouped = productPartsProduct('parts-grouped-required', Mage_Catalog_Model_Product_Type::TYPE_GROUPED);
        $this->products[] = $child = productPartsProductWithRequiredOption('parts-grouped-required-child');
        $childId = (int) $child->getId();

        $admin = productPartsViaAdmin($grouped, fn(Mage_Catalog_Model_Product $p) => $p->setGroupedLinkData([$childId => ['qty' => 1, 'position' => 0]]));
        $api = productPartsViaApi(GroupedProductLinkProcessor::class, GroupedProductLinkProvider::class, 'POST', "/api/rest/v2/products/{$grouped->getId()}/grouped", ['childProductId' => $childId], ['productId' => (int) $grouped->getId()]);

        expect($admin)->toBe(["Product {$childId} has a required custom option."])
            ->and($api['status'])->toBe(422)
            ->and($api['errors'])->toBe($admin);
    });

    it('refuses a configurable child with a required custom option on both transports', function () {
        $this->products[] = $configurable = productPartsProduct('parts-configurable-required', Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE);
        $this->products[] = $child = productPartsProductWithRequiredOption('parts-configurable-required-child');
        $childId = (int) $child->getId();

        $admin = productPartsViaAdmin($configurable, fn(Mage_Catalog_Model_Product $p) => $p->setConfigurableProductsData([$childId => []]));
        $api = productPartsViaApi(ConfigurableSetupProcessor::class, ConfigurableSetupProvider::class, 'POST', "/api/rest/v2/products/{$configurable->getId()}/configurable/children", ['childProductId' => $childId], ['productId' => (int) $configurable->getId()]);

        expect($admin)->toBe(["Product {$childId} has a required custom option."])
            ->and($api['status'])->toBe(422)
            ->and($api['errors'])->toBe($admin);
    });

    it('refuses a configurable child of another attribute set on both transports', function () {
        /** @var Mage_Eav_Model_Entity_Attribute_Set $set */
        $set = Mage::getModel('eav/entity_attribute_set');
        $set->setEntityTypeId((int) Mage::getResourceSingleton('catalog/product')->getTypeId())
            ->setAttributeSetName('Pest parts set ' . uniqid())
            ->save();
        $set->initFromSkeleton(4)->save();

        try {
            $this->products[] = $configurable = productPartsProduct('parts-configurable-set', Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE);
            $this->products[] = $child = productPartsProduct('parts-configurable-set-child');
            $childId = (int) $child->getId();
            Mage::getModel('catalog/product')->load($childId)->setAttributeSetId((int) $set->getId())->save();

            $admin = productPartsViaAdmin($configurable, fn(Mage_Catalog_Model_Product $p) => $p->setConfigurableProductsData([$childId => []]));
            $api = productPartsViaApi(ConfigurableSetupProcessor::class, ConfigurableSetupProvider::class, 'POST', "/api/rest/v2/products/{$configurable->getId()}/configurable/children", ['childProductId' => $childId], ['productId' => (int) $configurable->getId()]);

            expect($admin)->toBe(["Product {$childId} is not in the attribute set of the product."])
                ->and($api['status'])->toBe(422)
                ->and($api['errors'])->toBe($admin);
        } finally {
            Mage::register('isSecureArea', true, true);
            foreach (array_reverse($this->products) as $product) {
                Mage::getModel('catalog/product')->load($product->getId())->delete();
            }
            Mage::unregister('isSecureArea');
            $this->products = [];
            $set->delete();
        }
    });
});

describe('downloadable links', function () {
    it('refuses a URL link without a title and without a URL on both transports', function () {
        $this->products[] = $product = productPartsProduct('parts-downloadable', Mage_Downloadable_Model_Product_Type::TYPE_DOWNLOADABLE);

        $admin = productPartsViaAdmin($product, fn(Mage_Catalog_Model_Product $p) => $p->setDownloadableData(['link' => [[
            'link_id' => '', 'is_delete' => '', 'title' => '', 'type' => 'url', 'link_url' => '', 'price' => 0,
            'number_of_downloads' => 0, 'is_shareable' => Mage_Downloadable_Model_Link::LINK_SHAREABLE_CONFIG, 'sort_order' => 0,
        ]]]));
        $api = productPartsViaApi(DownloadableLinkProcessor::class, DownloadableLinkProvider::class, 'POST', "/api/rest/v2/products/{$product->getId()}/downloadable-links", ['title' => '', 'linkType' => 'url'], ['productId' => (int) $product->getId()]);

        expect($admin)->toBe(['title is required', 'linkUrl is required for url type links'])
            ->and($api['status'])->toBe(422)
            ->and($api['errors'])->toBe($admin)
            ->and(Mage::getResourceModel('downloadable/link_collection')->addProductToFilter($product->getId())->getAllIds())->toBe([]);
    });
});

describe('gallery images', function () {
    it('refuses a file that is not an image in the admin upload and in the API', function () {
        $this->products[] = $product = productPartsProduct('parts-media');
        $content = 'This is not an image';
        $path = tempnam(sys_get_temp_dir(), 'pest_media_') . '.jpg';
        file_put_contents($path, $content);

        // The admin upload gives each file to this validator of its uploader
        try {
            Mage::getService('catalog/product_media')->validateImage($path);
            $admin = [];
        } catch (Mage_Core_Exception $e) {
            $admin = [$e->getMessage()];
        } finally {
            @unlink($path);
        }
        $api = productPartsViaApi(ProductMediaProcessor::class, ProductMediaProvider::class, 'POST', "/api/rest/v2/products/{$product->getId()}/media", ['base64' => base64_encode($content), 'filename' => 'note.jpg'], ['productId' => (int) $product->getId()]);

        expect($admin)->toHaveCount(1)
            ->and($api['status'])->toBe(422)
            ->and($api['errors'])->toBe($admin)
            ->and(Mage::getModel('catalog/product')->load($product->getId())->getData('media_gallery')['images'] ?? [])->toBe([]);
    });
});
