<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use Mage;
use Mage_Catalog_Model_Product;
use Mage_Catalog_Model_Product_Type;
use Maho\ApiPlatform\Trait\ProductLoaderTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 */
final class ConfigurableSetupProcessor extends \Maho\ApiPlatform\Processor
{
    use ProductLoaderTrait;

    public function __construct(
        Security $security,
        private readonly ConfigurableSetupProvider $provider,
    ) {
        parent::__construct($security);
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?ConfigurableSetup
    {
        $user = $this->requireUser();
        $productId = (int) ($uriVariables['productId'] ?? 0);

        // Enforce website scope for store-restricted API users on every
        // sub-resource write/delete (mirrors ProductProcessor's main CRUD check).
        $this->assertProductWebsitesAllowed($this->loadProduct($productId), $user);

        if ($operation instanceof DeleteOperationInterface) {
            $childId = self::extractChildId($context);
            return $this->handleRemoveChild($productId, $childId);
        }

        $request = $context['request'] ?? null;
        $body = $this->parseRequestBody($request);

        if ($operation instanceof Post) {
            return $this->handleAddChild($productId, $body);
        }

        return $this->handleSetup($productId, $body);
    }

    /**
     * Extract childId from request path since API Platform only
     * populates declared Link uriVariables.
     */
    private static function extractChildId(array $context): int
    {
        $request = $context['request'] ?? null;
        if (!$request) {
            return 0;
        }

        $path = $request->getPathInfo();
        if (preg_match('#/configurable/children/(\d+)#', $path, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    private function handleSetup(int $productId, array $body): ConfigurableSetup
    {
        $product = $this->loadProductForWrite($productId, $this->requireUser(), Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE);
        /** @var \Mage_Catalog_Model_Product_Type_Configurable $typeInstance */
        $typeInstance = $product->getTypeInstance(true);

        $needsSave = false;

        // Set super attributes if provided and none exist yet
        $superAttributes = $body['superAttributes'] ?? $body['super_attributes'] ?? [];
        if (!empty($superAttributes)) {
            $attributeIds = [];
            foreach ($superAttributes as $code) {
                $attribute = Mage::getSingleton('eav/config')
                    ->getAttribute('catalog_product', $code);
                if (!$attribute || !$attribute->getId()) {
                    throw new UnprocessableEntityHttpException("Attribute not found: {$code}");
                }
                $attributeIds[$code] = (int) $attribute->getId();
            }

            $existingAttributeIds = array_map(
                intval(...),
                $typeInstance->getConfigurableAttributeCollection($product)->getColumnValues('attribute_id'),
            );

            if (empty($existingAttributeIds)) {
                $setAttributes = $typeInstance->getSetAttributes($product);
                $superAttributeData = [];
                $position = 0;
                foreach ($attributeIds as $code => $attributeId) {
                    // The configurable reads only the attributes of its attribute set that can be configurable
                    $setAttribute = $setAttributes[$code] ?? null;
                    if (!$setAttribute instanceof \Mage_Eav_Model_Entity_Attribute || !$typeInstance->canUseAttribute($setAttribute)) {
                        throw new UnprocessableEntityHttpException(
                            "Attribute {$code} cannot configure products of this attribute set",
                        );
                    }
                    $superAttributeData[] = [
                        'attribute_id' => $attributeId,
                        'position' => $position++,
                    ];
                }
                $product->setConfigurableAttributesData($superAttributeData);
                $product->setCanSaveConfigurableAttributes(true);
                $needsSave = true;
            } else {
                // As in the admin, the super attributes of a configurable product cannot change once saved
                $requested = array_values($attributeIds);
                sort($requested);
                sort($existingAttributeIds);
                if ($requested !== $existingAttributeIds) {
                    throw new UnprocessableEntityHttpException(
                        'The configurable product already has other super attributes, which cannot be changed',
                    );
                }
            }
        }

        $childProductIds = $body['childProductIds'] ?? $body['child_product_ids'] ?? [];
        if (!empty($childProductIds)) {
            $childData = [];
            foreach (array_map(intval(...), $childProductIds) as $childId) {
                $this->authorizeAssociatedProductWebsites($childId);
                $childData[$childId] = [];
            }
            $product->setConfigurableProductsData($childData);
            $needsSave = true;
        }

        // One save: a second save of the same product would insert its super attributes again.
        if ($needsSave) {
            $this->safeSave($product, 'set up the configurable product');
        }

        return $this->provider->getSetup($this->loadProduct($productId, Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE));
    }

    private function handleAddChild(int $productId, array $body): ConfigurableSetup
    {
        $product = $this->loadProductForWrite($productId, $this->requireUser(), Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE);

        $childId = (int) ($body['childProductId'] ?? $body['child_product_id'] ?? $body['childId'] ?? 0);
        if ($childId <= 0) {
            throw new UnprocessableEntityHttpException('childProductId is required and must be positive');
        }

        $this->authorizeAssociatedProductWebsites($childId);

        // Build data array with existing children + new one
        $existingChildren = $this->getExistingChildIds($product);
        $existingChildren[$childId] = [];
        $product->setConfigurableProductsData($existingChildren);
        $this->safeSave($product, 'add configurable child');

        return $this->provider->getSetup($this->loadProduct($productId, Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE));
    }

    private function handleRemoveChild(int $productId, int $childId): null
    {
        // Without this guard a failed child-id extraction (0) makes unset() a
        // no-op, so the product saves unchanged and the caller gets a misleading
        // 200 "removed". Mirrors the validation in handleAddChild().
        if ($childId <= 0) {
            throw new UnprocessableEntityHttpException('childProductId is required and must be positive');
        }

        $product = $this->loadProductForWrite($productId, $this->requireUser(), Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE);

        $existingChildren = $this->getExistingChildIds($product);
        unset($existingChildren[$childId]);
        $product->setConfigurableProductsData($existingChildren);
        $this->safeSave($product, 'remove configurable child');

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getExistingChildIds(Mage_Catalog_Model_Product $product): array
    {
        /** @var \Mage_Catalog_Model_Product_Type_Configurable $typeInstance */
        $typeInstance = $product->getTypeInstance(true);
        $children = $typeInstance->getUsedProducts(null, $product);
        $data = [];
        foreach ($children as $child) {
            $data[(int) $child->getId()] = [];
        }
        return $data;
    }

    /**
     * Guard against pulling a foreign-website product into the configurable set:
     * a store-restricted token may only associate children within its own
     * website scope. No-op for unrestricted users (skips the child load).
     */
    private function authorizeAssociatedProductWebsites(int $childId): void
    {
        $user = $this->requireUser();
        if ($this->getAllowedWebsiteIds($user) === null) {
            return;
        }
        $this->assertProductWebsitesAllowed($this->loadProduct($childId), $user);
    }

}
