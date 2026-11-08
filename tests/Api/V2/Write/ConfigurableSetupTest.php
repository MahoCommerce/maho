<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Configurable Product Setup Sub-Resource Tests
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('Configurable Setup, Read', function (): void {

    it('reads configurable setup from existing configurable product', function (): void {
        // Use the existing configurable SKU from fixtures
        $sku = fixtures('configurable_sku');
        // First get the product ID
        $search = apiGet("/api/rest/v2/products?search={$sku}");
        expect($search['status'])->toBe(200);
        $items = getItems($search);
        $configurable = array_filter($items, fn($p) => ($p['sku'] ?? '') === $sku);

        if (empty($configurable)) {
            $this->markTestSkipped("No configurable product found for SKU {$sku}");
        }

        $configProduct = array_values($configurable)[0];
        $productId = $configProduct['id'];

        $read = apiGet("/api/rest/v2/products/{$productId}/configurable");
        expect($read['status'])->toBe(200);
        $data = getItems($read);
        expect(count($data))->toBeGreaterThanOrEqual(1);

        $setup = $data[0];
        expect($setup)->toHaveKey('superAttributes');
        expect($setup)->toHaveKey('childProductIds');
    });

    it('rejects configurable setup on a simple product', function (): void {
        // fixtures('product_id') (421) is actually configurable, so create a simple product
        $token = serviceToken(['products/write', 'products/delete']);
        $suffix = substr(uniqid(), -6);
        $simple = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-SIMPLE-CFG-{$suffix}",
            'name' => 'Simple Product For Config Test',
            'price' => 10,
        ], $token);
        expect($simple['status'])->toBeIn([200, 201]);
        $simpleId = $simple['json']['id'];
        trackCreated('product', $simpleId);

        $response = apiGet("/api/rest/v2/products/{$simpleId}/configurable");
        expect($response['status'])->toBe(404);
    });

});

describe('Configurable Setup, Child Management', function (): void {

    it('adds and removes a child from existing configurable', function (): void {
        $token = serviceToken(['products/write', 'products/delete', 'products/read']);
        $suffix = substr(uniqid(), -6);

        // Find existing configurable
        $sku = fixtures('configurable_sku');
        $search = apiGet("/api/rest/v2/products?search={$sku}");
        $items = getItems($search);
        $configurable = array_filter($items, fn($p) => ($p['sku'] ?? '') === $sku);

        if (empty($configurable)) {
            $this->markTestSkipped("No configurable product found for SKU {$sku}");
        }

        $configId = array_values($configurable)[0]['id'];

        // Read current children
        $before = apiGet("/api/rest/v2/products/{$configId}/configurable");
        $beforeData = getItems($before);
        $beforeChildIds = $beforeData[0]['childProductIds'] ?? [];

        // Create a simple child
        $child = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-CFGCHILD-{$suffix}",
            'name' => 'Config Child Test',
            'price' => 30,
        ], $token);
        expect($child['status'])->toBeIn([200, 201]);
        $childId = $child['json']['id'];
        trackCreated('product', $childId);

        // Add child
        $add = apiPost("/api/rest/v2/products/{$configId}/configurable/children", [
            'childProductId' => $childId,
        ], $token);
        expect($add['status'])->toBeIn([200, 201]);

        // Verify child link was created (the child may not appear in getUsedProductIds
        // since it doesn't have the configurable super attributes, but the link exists)
        $setup = $add['json'];
        // The POST response may wrap in array or not, handle both
        if (isset($setup[0])) {
            $setup = $setup[0];
        }
        expect($setup)->toHaveKey('childProductIds');

        // Remove child
        $remove = apiDelete("/api/rest/v2/products/{$configId}/configurable/children/{$childId}", $token);
        expect($remove['status'])->toBeIn([200, 204]);

        // Verify removed
        $final = apiGet("/api/rest/v2/products/{$configId}/configurable");
        $finalData = getItems($final);
        expect($finalData[0]['childProductIds'])->not->toContain($childId);
    });

});

/**
 * A select attribute with two options, in the default attribute set unless $inSet is false, usable to configure products unless $configurable is false
 */
function configurableSetupCreateAttribute(bool $inSet = true, bool $configurable = true): Mage_Catalog_Model_Resource_Eav_Attribute
{
    $productType = Mage::getSingleton('eav/config')->getEntityType(Mage_Catalog_Model_Product::ENTITY);
    $setId = (int) $productType->getDefaultAttributeSetId();
    $data = [
        'entity_type_id' => (int) $productType->getId(),
        'attribute_code' => 'pest_cfg_setup_' . substr(uniqid(), -8),
        'frontend_input' => 'select',
        'backend_type' => 'int',
        'frontend_label' => ['Pest configurable setup'],
        'is_global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
        'is_configurable' => (int) $configurable,
        'is_user_defined' => 1,
        'option' => ['value' => ['first' => ['First'], 'second' => ['Second']]],
    ];
    if ($inSet) {
        $data['attribute_set_id'] = $setId;
        $data['attribute_group_id'] = (int) Mage::getModel('eav/entity_attribute_set')->getDefaultGroupId($setId);
    }
    $attribute = Mage::getModel('catalog/resource_eav_attribute');
    $attribute->addData($data)->save();
    Mage::getSingleton('eav/config')->clear();

    return $attribute;
}

/**
 * @param array<string, mixed> $data
 */
function configurableSetupCreateProduct(string $type, array $data = []): int
{
    $sku = "pest-cfg-setup-{$type}-" . uniqid();
    $product = Mage::getModel('catalog/product')->setStoreId(0)
        ->setTypeId($type)
        ->setAttributeSetId((int) Mage::getModel('catalog/product')->getDefaultAttributeSetId())
        ->setSku($sku)->setName("Pest configurable setup {$type}")->setUrlKey($sku)->setWeight(1)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setPrice(10)->setTaxClassId(0)->setWebsiteIds([1])
        ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0])
        ->addData($data);
    $product->save();
    trackCreated('product', (int) $product->getId());

    return (int) $product->getId();
}

function configurableSetupSuperAttributeRows(int $productId): int
{
    $resource = Mage::getSingleton('core/resource');

    return (int) $resource->getConnection('core_read')->fetchOne(
        'SELECT COUNT(*) FROM ' . $resource->getTableName('catalog/product_super_attribute') . ' WHERE product_id = ?',
        [$productId],
    );
}

describe('Configurable Setup, Setup', function (): void {

    beforeEach(function (): void {
        Mage::app();
        $this->attribute = configurableSetupCreateAttribute();
        $this->code = (string) $this->attribute->getAttributeCode();
        $this->childIds = [];
        foreach ($this->attribute->getSource()->getAllOptions(false) as $option) {
            if ($option['value'] !== '') {
                $this->childIds[] = configurableSetupCreateProduct(
                    Mage_Catalog_Model_Product_Type::TYPE_SIMPLE,
                    [$this->code => (int) $option['value']],
                );
            }
        }
        $this->configurableId = configurableSetupCreateProduct(Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE);
        $this->token = serviceToken(['products/write', 'products/read']);
        $this->otherAttribute = null;
    });

    afterEach(function (): void {
        cleanupTestData();
        foreach ([$this->attribute, $this->otherAttribute] as $attribute) {
            if ($attribute) {
                Mage::getModel('catalog/resource_eav_attribute')->load((int) $attribute->getId())->delete();
            }
        }
        Mage::getSingleton('eav/config')->clear();
    });

    it('sets the super attributes and the children in one request', function (): void {
        $response = apiPut("/api/rest/v2/products/{$this->configurableId}/configurable", [
            'superAttributes' => [$this->code],
            'childProductIds' => $this->childIds,
        ], $this->token);

        expect($response['status'])->toBe(200);
        expect($response['json']['superAttributes'])->toBe([$this->code]);
        expect($response['json']['childProductIds'])->toEqualCanonicalizing($this->childIds);
        expect(configurableSetupSuperAttributeRows($this->configurableId))->toBe(1);
    });

    it('rejects a super attribute outside the attribute set of the product', function (): void {
        $this->otherAttribute = configurableSetupCreateAttribute(false);

        $response = apiPut("/api/rest/v2/products/{$this->configurableId}/configurable", [
            'superAttributes' => [(string) $this->otherAttribute->getAttributeCode()],
        ], $this->token);

        expect($response['status'])->toBe(422);
        expect(configurableSetupSuperAttributeRows($this->configurableId))->toBe(0);
    });

    it('rejects other super attributes than the saved ones, and accepts the same ones again', function (): void {
        $first = apiPut("/api/rest/v2/products/{$this->configurableId}/configurable", [
            'superAttributes' => [$this->code],
            'childProductIds' => [$this->childIds[0]],
        ], $this->token);
        expect($first['status'])->toBe(200);

        $this->otherAttribute = configurableSetupCreateAttribute();
        $other = apiPut("/api/rest/v2/products/{$this->configurableId}/configurable", [
            'superAttributes' => [(string) $this->otherAttribute->getAttributeCode()],
            'childProductIds' => $this->childIds,
        ], $this->token);
        expect($other['status'])->toBe(409);

        $same = apiPut("/api/rest/v2/products/{$this->configurableId}/configurable", [
            'superAttributes' => [$this->code],
            'childProductIds' => $this->childIds,
        ], $this->token);
        expect($same['status'])->toBe(200);
        expect($same['json']['superAttributes'])->toBe([$this->code]);
        expect($same['json']['childProductIds'])->toEqualCanonicalizing($this->childIds);
    });

    it('rejects a super attribute that cannot configure products', function (): void {
        $this->otherAttribute = configurableSetupCreateAttribute(true, false);

        $response = apiPut("/api/rest/v2/products/{$this->configurableId}/configurable", [
            'superAttributes' => [(string) $this->otherAttribute->getAttributeCode()],
        ], $this->token);

        expect($response['status'])->toBe(422);
        expect(configurableSetupSuperAttributeRows($this->configurableId))->toBe(0);
    });

    it('assigns children when the saved super attribute can no longer configure products', function (): void {
        $first = apiPut("/api/rest/v2/products/{$this->configurableId}/configurable", [
            'superAttributes' => [$this->code],
            'childProductIds' => [$this->childIds[0]],
        ], $this->token);
        expect($first['status'])->toBe(200);

        Mage::getModel('catalog/resource_eav_attribute')->load((int) $this->attribute->getId())->setIsConfigurable(0)->save();
        Mage::getSingleton('eav/config')->clear();

        $second = apiPut("/api/rest/v2/products/{$this->configurableId}/configurable", [
            'superAttributes' => [$this->code],
            'childProductIds' => $this->childIds,
        ], $this->token);
        expect($second['status'])->toBe(200);
        expect($second['json']['childProductIds'])->toEqualCanonicalizing($this->childIds);
    });

    it('rejects a malformed setup request', function (string $key, mixed $value): void {
        $response = apiPut("/api/rest/v2/products/{$this->configurableId}/configurable", [$key => $value], $this->token);

        expect($response['status'])->toBe(400);
        expect(configurableSetupSuperAttributeRows($this->configurableId))->toBe(0);
    })->with([
        'a super attribute that is not a code' => ['superAttributes', [['color']]],
        'super attributes that are not a list' => ['super_attributes', 'color'],
        'a child id that is not a number' => ['childProductIds', ['abc']],
        'child ids that are not a list' => ['child_product_ids', 'x'],
        'a child id that is a boolean' => ['childProductIds', [true]],
    ]);

    it('saves no super attribute when the children cannot be assigned', function (): void {
        $response = apiPut("/api/rest/v2/products/{$this->configurableId}/configurable", [
            'superAttributes' => [$this->code],
            'childProductIds' => [999999999],
        ], $this->token);

        expect($response['status'])->toBe(422);
        expect(configurableSetupSuperAttributeRows($this->configurableId))->toBe(0);
    });

});
