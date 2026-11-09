<?php

/**
 * Set the super attributes and the children of a configurable product.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

use Mage_Catalog_Model_Product as Product;

/**
 * The configurable product type checks each new super attribute and each new child, so the admin save gets the
 * same rules.
 */
class Mage_Catalog_Service_Product_Configurable
{
    /**
     * Give $product the super attributes $attributeCodes, in this order, when it has no super attribute yet.
     *
     * @param list<string> $attributeCodes
     * @throws Mage_Core_Exception_Input for an attribute that does not exist or that a configurable product cannot use
     */
    public function setSuperAttributes(Product $product, array $attributeCodes): void
    {
        /** @var Mage_Catalog_Model_Product_Type_Configurable $typeInstance */
        $typeInstance = $product->getTypeInstance(true);
        if ($attributeCodes === [] || $typeInstance->getConfigurableAttributeCollection($product)->getAllIds() !== []) {
            return;
        }

        $data = [];
        $errors = new Mage_Core_Exception_Input();
        foreach (array_values($attributeCodes) as $position => $code) {
            $attribute = Mage::getSingleton('eav/config')->getAttribute(Product::ENTITY, $code);
            if (!$attribute || !$attribute->getId()) {
                $errors->addError('super_attributes', Mage::helper('catalog')->__('Attribute not found: %s', $code));
                continue;
            }
            $data[] = ['attribute_id' => $attribute->getId(), 'position' => $position];
        }
        $errors->throwIfErrors();

        $product->setConfigurableAttributesData($data);
        $product->setCanSaveConfigurableAttributes(true);
        $product->save();
        // A later save of the same product must not insert the attributes again
        $product->unsetData('configurable_attributes_data');
        $product->setCanSaveConfigurableAttributes(false);
    }

    /**
     * @return list<int>
     */
    public function getChildIds(Product $product): array
    {
        /** @var Mage_Catalog_Model_Product_Type_Configurable $typeInstance */
        $typeInstance = $product->getTypeInstance(true);
        return array_map(fn(Product $child): int => (int) $child->getId(), array_values($typeInstance->getUsedProducts(null, $product)));
    }

    /**
     * Replace the children of $product with $childIds.
     *
     * @param list<int> $childIds
     * @param list<int>|null $websiteIds with a list, each child must be in one of these websites
     * @throws Mage_Core_Exception_Input for a child that does not exist or has a type that is not allowed
     * @throws Mage_Core_Exception_Forbidden for a child outside $websiteIds
     */
    public function setChildren(Product $product, array $childIds, ?array $websiteIds = null): void
    {
        $this->save($product, $childIds, $childIds, $websiteIds);
    }

    /**
     * @param list<int>|null $websiteIds
     * @throws Mage_Core_Exception_Input for a child that does not exist or has a type that is not allowed
     * @throws Mage_Core_Exception_Forbidden for a child outside $websiteIds
     */
    public function addChild(Product $product, int $childId, ?array $websiteIds = null): void
    {
        $this->save($product, [...$this->getChildIds($product), $childId], [$childId], $websiteIds);
    }

    /**
     * Remove the link between $product and the child $childId. A child without a value for a super attribute has a
     * link, but it is not in getChildIds(), so the save keeps its link and this method deletes it.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when $childId has no link to $product
     */
    public function removeChild(Product $product, int $childId): void
    {
        /** @var Mage_Catalog_Model_Product_Type_Configurable $typeInstance */
        $typeInstance = $product->getTypeInstance(true);
        $linkedIds = array_map(intval(...), array_values($typeInstance->getChildrenIds((int) $product->getId(), false)[0] ?? []));
        if (!in_array($childId, $linkedIds, true)) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('catalog')->__('Child product not found'));
        }
        $this->save($product, array_diff($this->getChildIds($product), [$childId]), [], null);
        /** @var Mage_Catalog_Model_Resource_Product_Type_Configurable $resource */
        $resource = Mage::getResourceSingleton('catalog/product_type_configurable');
        $resource->deleteProducts((int) $product->getId(), [$childId]);
    }

    /**
     * @param array<int> $childIds
     * @param array<int> $checkedIds the children that must be in one of the websites $websiteIds
     * @param list<int>|null $websiteIds
     */
    private function save(Product $product, array $childIds, array $checkedIds, ?array $websiteIds): void
    {
        /** @var Mage_Catalog_Service_Product_Link $links */
        $links = Mage::getService('catalog/product_link');
        $links->assertInWebsites($checkedIds, $websiteIds);
        $product->setConfigurableProductsData(array_fill_keys(array_map(intval(...), $childIds), []));
        $product->save();
    }
}
