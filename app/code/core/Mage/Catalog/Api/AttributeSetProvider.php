<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\CrudProvider;
use Maho\ApiPlatform\Resource;

/**
 * Provider for catalog product attribute set metadata.
 *
 * Restricts every read to the catalog_product entity type and enriches the DTO
 * with the attribute codes contained in the set.
 */
final class AttributeSetProvider extends CrudProvider
{
    protected array $defaultSort = ['attribute_set_name' => 'ASC'];

    private ?int $productEntityTypeId = null;

    private function getProductEntityTypeId(): int
    {
        return $this->productEntityTypeId ??= (int) \Mage::getSingleton('eav/config')
            ->getEntityType(\Mage_Catalog_Model_Product::ENTITY)
            ->getId();
    }

    /**
     * Load a single set and verify it belongs to catalog_product.
     */
    #[\Override]
    protected function provideItem(int|string $id): ?AttributeSet
    {
        /** @var \Mage_Eav_Model_Entity_Attribute_Set $set */
        $set = \Mage::getModel('eav/entity_attribute_set')->load($id);
        if (!$set->getId() || (int) $set->getEntityTypeId() !== $this->getProductEntityTypeId()) {
            return null;
        }

        /** @var AttributeSet */
        return $this->toDto($set);
    }

    /**
     * List attribute sets scoped to the catalog_product entity type.
     *
     * @return TraversablePaginator<AttributeSet>
     */
    #[\Override]
    protected function provideCollection(array $context): TraversablePaginator
    {
        $collection = \Mage::getResourceModel('eav/entity_attribute_set_collection')
            ->setEntityTypeFilter($this->getProductEntityTypeId());

        foreach ($this->defaultSort as $field => $dir) {
            $collection->setOrder($field, $dir);
        }

        ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination(
            $context,
            $this->defaultPageSize,
            $this->maxPageSize,
        );
        $collection->setPageSize($pageSize);
        $collection->setCurPage($page);

        $total = (int) $collection->getSize();

        $items = [];
        foreach ($collection as $set) {
            $items[] = $this->toDto($set);
        }

        return new TraversablePaginator(new \ArrayIterator($items), $page, $pageSize, $total);
    }

    /**
     * Populate the attribute codes assigned to the set.
     */
    #[\Override]
    protected function afterMap(Resource $dto, object $model): void
    {
        if (!$dto instanceof AttributeSet) {
            return;
        }

        $collection = \Mage::getResourceModel('catalog/product_attribute_collection')
            ->setAttributeSetFilter((int) $model->getId());

        $codes = [];
        foreach ($collection as $attribute) {
            $codes[] = (string) $attribute->getAttributeCode();
        }
        $dto->attributeCodes = $codes;
    }
}
