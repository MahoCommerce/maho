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
 * Provider for catalog product attribute metadata.
 *
 * Restricts every read to the catalog_product entity type and enriches the DTO
 * with source options for select/multiselect attributes.
 */
final class ProductAttributeProvider extends CrudProvider
{
    protected array $defaultSort = ['attribute_code' => 'ASC'];

    private ?int $productEntityTypeId = null;

    private function getProductEntityTypeId(): int
    {
        return $this->productEntityTypeId ??= (int) \Mage::getSingleton('eav/config')
            ->getEntityType(\Mage_Catalog_Model_Product::ENTITY)
            ->getId();
    }

    /**
     * Load a single attribute and verify it belongs to catalog_product.
     */
    #[\Override]
    protected function provideItem(int|string $id): ?ProductAttribute
    {
        /** @var \Mage_Catalog_Model_Resource_Eav_Attribute $attribute */
        $attribute = \Mage::getModel('catalog/resource_eav_attribute')->load($id);
        if (!$attribute->getId() || (int) $attribute->getEntityTypeId() !== $this->getProductEntityTypeId()) {
            return null;
        }

        /** @var ProductAttribute */
        return $this->toDto($attribute);
    }

    /**
     * List product attributes. The product attribute collection already scopes
     * itself to the catalog_product entity type.
     *
     * @return TraversablePaginator<ProductAttribute>
     */
    #[\Override]
    protected function provideCollection(array $context): TraversablePaginator
    {
        $collection = \Mage::getResourceModel('catalog/product_attribute_collection');

        $this->applyCollectionFilters($collection, $context['filters'] ?? []);

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
        foreach ($collection as $attribute) {
            $items[] = $this->toDto($attribute);
        }

        return new TraversablePaginator(new \ArrayIterator($items), $page, $pageSize, $total);
    }

    /**
     * Attribute metadata is not store-scoped; apply only the optional search
     * filter instead of the store/EAV handling in the parent.
     */
    #[\Override]
    protected function applyCollectionFilters(object $collection, array $filters): void
    {
        $search = $filters['search'] ?? $filters['q'] ?? null;
        if ($search) {
            $collection->addFieldToFilter(
                ['main_table.attribute_code', 'main_table.frontend_label'],
                [
                    ['like' => "%{$search}%"],
                    ['like' => "%{$search}%"],
                ],
            );
        }
    }

    /**
     * Resolve the productAttribute(code: …) GraphQL query.
     */
    #[\Override]
    protected function handleOperation(string $name, array $context, array $uriVariables): mixed
    {
        if ($name === 'productAttribute') {
            $code = $context['args']['code'] ?? null;
            if (!$code) {
                return null;
            }

            $attribute = \Mage::getSingleton('eav/config')
                ->getAttribute(\Mage_Catalog_Model_Product::ENTITY, $code);
            if (!$attribute || !$attribute->getId()) {
                return null;
            }

            return $this->toDto($attribute);
        }
        return null;
    }

    /**
     * Populate source options for select/multiselect attributes.
     */
    #[\Override]
    protected function afterMap(Resource $dto, object $model): void
    {
        if (!$dto instanceof ProductAttribute) {
            return;
        }

        if (!method_exists($model, 'usesSource') || !$model->usesSource()) {
            return;
        }

        try {
            $options = [];
            foreach ($model->getSource()->getAllOptions(false) as $option) {
                $options[] = [
                    'label' => (string) ($option['label'] ?? ''),
                    'value' => (string) ($option['value'] ?? ''),
                ];
            }
            $dto->options = $options;
        } catch (\Throwable) {
            $dto->options = [];
        }
    }
}
