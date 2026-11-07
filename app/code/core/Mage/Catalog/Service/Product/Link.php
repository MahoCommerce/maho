<?php

/**
 * Read and replace the related, up-sell, cross-sell and grouped links of a product.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

use Mage_Catalog_Model_Product as Product;

/**
 * The link data has the format of the admin product save: the data of each link, by the ID of the linked product.
 * The grouped product type checks the types of its children.
 */
class Mage_Catalog_Service_Product_Link
{
    public const TYPE_RELATED = 'related';
    public const TYPE_UP_SELL = 'up_sell';
    public const TYPE_CROSS_SELL = 'cross_sell';
    public const TYPE_GROUPED = 'grouped';

    /** The setter of the product for the link data of each link type. */
    private const SETTERS = [
        self::TYPE_RELATED => 'setRelatedLinkData',
        self::TYPE_UP_SELL => 'setUpSellLinkData',
        self::TYPE_CROSS_SELL => 'setCrossSellLinkData',
        self::TYPE_GROUPED => 'setGroupedLinkData',
    ];

    /**
     * @throws Mage_Core_Exception_NoSuchEntity when $type is not a link type of a simple product
     */
    public function assertType(string $type): void
    {
        if (!isset(self::SETTERS[$type]) || $type === self::TYPE_GROUPED) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('catalog')->__(
                'Invalid link type: %s. Valid types: related, cross-sell, up-sell',
                $type,
            ));
        }
    }

    /**
     * @return array<int, array{position: int, qty?: float}> the data of each link, by the ID of the linked product
     */
    public function getLinkData(Product $product, string $type): array
    {
        $links = [];
        if ($type === self::TYPE_GROUPED) {
            /** @var Mage_Catalog_Model_Product_Type_Grouped $typeInstance */
            $typeInstance = $product->getTypeInstance(true);
            foreach ($typeInstance->getAssociatedProducts($product) as $child) {
                $links[(int) $child->getId()] = ['qty' => (float) ($child->getQty() ?: 1), 'position' => (int) $child->getPosition()];
            }
            return $links;
        }

        $collection = match ($type) {
            self::TYPE_RELATED => $product->getRelatedProductCollection(),
            self::TYPE_UP_SELL => $product->getUpSellProductCollection(),
            default => $product->getCrossSellProductCollection(),
        };
        foreach ($collection as $linked) {
            $links[(int) $linked->getId()] = ['position' => (int) $linked->getPosition()];
        }
        return $links;
    }

    /**
     * Replace the links of $type of $product with $links.
     *
     * @param array<int, array{position?: int, qty?: float}> $links the data of each link, by the ID of the linked product
     * @param list<int>|null $websiteIds with a list, each linked product must be in one of these websites
     * @throws Mage_Core_Exception_Input when a linked product does not exist
     * @throws Mage_Core_Exception_Forbidden when a linked product is outside $websiteIds
     */
    public function setLinks(Product $product, string $type, array $links, ?array $websiteIds = null): void
    {
        $this->save($product, $type, $links, array_keys($links), $websiteIds);
    }

    /**
     * Add the product $linkedId to the links of $type of $product, or change the data of its link.
     *
     * @param array{position?: int, qty?: float} $data
     * @param list<int>|null $websiteIds
     * @throws Mage_Core_Exception_Input when the linked product does not exist
     * @throws Mage_Core_Exception_Forbidden when the linked product is outside $websiteIds
     */
    public function addLink(Product $product, string $type, int $linkedId, array $data, ?array $websiteIds = null): void
    {
        $links = $this->getLinkData($product, $type);
        $links[$linkedId] = $data;
        $this->save($product, $type, $links, [$linkedId], $websiteIds);
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity when $product has no link of $type to $linkedId
     */
    public function removeLink(Product $product, string $type, int $linkedId): void
    {
        $links = $this->getLinkData($product, $type);
        if (!isset($links[$linkedId])) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('catalog')->__('Link not found'));
        }
        unset($links[$linkedId]);
        $this->save($product, $type, $links, [], null);
    }

    /**
     * Check that each product of $productIds that exists is in one of the websites $websiteIds. A product that does
     * not exist is left to the check of the product type.
     *
     * @param array<int> $productIds
     * @param list<int>|null $websiteIds
     * @throws Mage_Core_Exception_Forbidden
     */
    public function assertInWebsites(array $productIds, ?array $websiteIds): void
    {
        if ($websiteIds === null || $productIds === []) {
            return;
        }
        /** @var Mage_Catalog_Model_Resource_Product $resource */
        $resource = Mage::getResourceSingleton('catalog/product');
        $productWebsites = $resource->getWebsiteIdsByProductIds($productIds);
        foreach ($resource->getProductsSku(array_values($productIds)) as $row) {
            $ids = array_map(intval(...), $productWebsites[$row['entity_id']] ?? []);
            if (array_intersect($ids, $websiteIds) === []) {
                throw new Mage_Core_Exception_Forbidden(Mage::helper('catalog')->__("Access denied for this product's websites"));
            }
        }
    }

    /**
     * @param array<int, array{position?: int, qty?: float}> $links
     * @param array<int> $checkedIds the linked products that must be in one of the websites $websiteIds
     * @param list<int>|null $websiteIds
     */
    private function save(Product $product, string $type, array $links, array $checkedIds, ?array $websiteIds): void
    {
        $ids = array_map(intval(...), array_keys($links));
        if ($type !== self::TYPE_GROUPED && $ids !== []) {
            $missing = array_diff($ids, array_map(intval(...), array_column($product->getResource()->getProductsSku($ids), 'entity_id')));
            if ($missing !== []) {
                throw new Mage_Core_Exception_Input()->addError('linked_product_id', Mage::helper('catalog')->__(
                    'Product %s does not exist.',
                    implode(', ', $missing),
                ));
            }
        }
        $this->assertInWebsites($checkedIds, $websiteIds);

        $setter = self::SETTERS[$type];
        $product->$setter($links);
        $product->save();
    }
}
