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

    /** @var array<int, array<string>> */
    private array $prefetchedCodes = [];

    /** @var array<int, array<array{name: string, sortOrder: int, attributes: array<array{code: string, sortOrder: int}>}>> */
    private array $prefetchedGroups = [];

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
        $set = $this->loadById('eav/entity_attribute_set', $id);
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

        $models = iterator_to_array($collection);
        $this->prefetchSetData(array_map(static fn($set): int => (int) $set->getId(), $models));

        $items = [];
        foreach ($models as $set) {
            $items[] = $this->toDto($set);
        }

        return new TraversablePaginator(new \ArrayIterator($items), $page, $pageSize, $total);
    }

    /**
     * Populate the attribute codes and grouped structure assigned to the set.
     */
    #[\Override]
    protected function afterMap(Resource $dto, object $model): void
    {
        if (!$dto instanceof AttributeSet) {
            return;
        }

        $setId = (int) $model->getId();
        if (!array_key_exists($setId, $this->prefetchedCodes)) {
            $this->prefetchSetData([$setId]);
        }
        $dto->attributeCodes = $this->prefetchedCodes[$setId];
        $dto->groups = $this->prefetchedGroups[$setId];
    }

    /**
     * Batch-load attribute codes and group structures for a page of sets in two
     * queries instead of three per set.
     *
     * @param array<int> $setIds
     */
    private function prefetchSetData(array $setIds): void
    {
        foreach ($setIds as $setId) {
            $this->prefetchedCodes[$setId] = [];
            $this->prefetchedGroups[$setId] = [];
        }
        if ($setIds === []) {
            return;
        }

        $resource = \Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');

        $attributesByGroup = [];
        $attributeSelect = $adapter->select()
            ->from(['ea' => $resource->getTableName('eav/entity_attribute')], ['attribute_set_id', 'attribute_group_id', 'sort_order'])
            ->join(['a' => $resource->getTableName('eav/attribute')], 'a.attribute_id = ea.attribute_id', ['attribute_code'])
            ->where('ea.attribute_set_id IN (?)', $setIds)
            ->order('ea.sort_order ASC');
        foreach ($adapter->fetchAll($attributeSelect) as $row) {
            $setId = (int) $row['attribute_set_id'];
            $this->prefetchedCodes[$setId][] = (string) $row['attribute_code'];
            $attributesByGroup[(int) $row['attribute_group_id']][] = [
                'code' => (string) $row['attribute_code'],
                'sortOrder' => (int) $row['sort_order'],
            ];
        }

        $groupSelect = $adapter->select()
            ->from($resource->getTableName('eav/attribute_group'), ['attribute_set_id', 'attribute_group_id', 'attribute_group_name', 'sort_order'])
            ->where('attribute_set_id IN (?)', $setIds)
            ->order('sort_order ASC');
        foreach ($adapter->fetchAll($groupSelect) as $row) {
            $this->prefetchedGroups[(int) $row['attribute_set_id']][] = [
                'name' => (string) $row['attribute_group_name'],
                'sortOrder' => (int) $row['sort_order'],
                'attributes' => $attributesByGroup[(int) $row['attribute_group_id']] ?? [],
            ];
        }
    }
}
