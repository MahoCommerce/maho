<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

declare(strict_types=1);

namespace Mage\SalesRule\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Exception\ValidationException;
use Symfony\Component\HttpFoundation\JsonResponse;

final class CartPriceRuleConditionValueOptionProvider extends \Maho\ApiPlatform\Provider
{
    use ConditionMetadataTrait;

    public const MAX_VALUES = 1000;

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $this->requireUser();
        $filters = ($context['filters'] ?? []) + ($context['request']?->query->all() ?? []);
        $type = $this->stringFilter($filters, 'type');
        $attribute = $this->stringFilter($filters, 'attribute');
        if ($type === null || $attribute === null) {
            throw new ValidationException('type and attribute are required', $type === null ? 'type' : 'attribute', 'NotBlank');
        }

        $locale = $this->adminLocale();
        $description = $this->findAttribute($this->conditionMetadata($locale), $type, $attribute);
        if ($description === null) {
            throw new ValidationException('The condition metadata has no such type and attribute', 'type', 'Choice');
        }

        ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination(['filters' => $filters], $this->defaultPageSize, $this->maxPageSize);
        $search = trim((string) $this->stringFilter($filters, 'search'));
        $values = $this->readValues($this->stringFilter($filters, 'values'));
        $parentId = $this->intFilter($filters, 'parentId');
        $chooser = $description['chooser'] ?? null;
        if ($chooser === null && ($description['options'] ?? null) === null) {
            throw new ValidationException('This attribute has no value options', 'attribute', 'Choice');
        }

        [$items, $total] = \Mage_Rule_Model_Condition_Metadata::runInLocale($locale, fn(): array => match ($chooser) {
            'category' => $this->categoryOptions($search, $values, $parentId, $page, $pageSize),
            'product' => $this->productOptions($search, $values, $page, $pageSize),
            default => $this->listOptions($type, $attribute, $search, $values, $page, $pageSize),
        });

        return $this->respondRaw([
            'type' => $type,
            'attribute' => $attribute,
            'items' => $items,
            'totalItems' => $total,
            'page' => $values === null ? $page : 1,
            'itemsPerPage' => $values === null ? $pageSize : count($values),
        ]);
    }

    /**
     * @param array<string, mixed> $document
     * @return array<string, mixed>|null
     */
    private function findAttribute(array $document, string $type, string $attribute): ?array
    {
        foreach ($document['types'][$type]['attributes'] ?? [] as $description) {
            if (($description['code'] ?? null) === $attribute) {
                return $description;
            }
        }
        return null;
    }

    /**
     * @return list<string>|null
     */
    private function readValues(?string $values): ?array
    {
        if ($values === null) {
            return null;
        }
        $list = array_values(array_unique(array_filter(array_map(trim(...), explode(',', $values)), fn(string $value) => $value !== '')));
        if (count($list) > self::MAX_VALUES) {
            throw new ValidationException(sprintf('values can have no more than %d values', self::MAX_VALUES), 'values', 'Count');
        }
        return $list;
    }

    /**
     * Page through the select options, which the condition gives in memory.
     *
     * @param list<string>|null $values
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function listOptions(string $type, string $attribute, string $search, ?array $values, int $page, int $pageSize): array
    {
        $options = \Mage_Rule_Model_Condition_Metadata::flattenOptions($this->conditionMetadataModel()->getValueOptions($type, $attribute));

        if ($values !== null) {
            $options = array_values(array_filter($options, fn(array $option) => in_array($option['value'], $values, true)));
            return [$options, count($options)];
        }
        if ($search !== '') {
            $options = array_values(array_filter(
                $options,
                fn(array $option) => mb_stripos($option['label'], $search) !== false || mb_stripos($option['value'], $search) !== false,
            ));
        }
        return [array_slice($options, ($page - 1) * $pageSize, $pageSize), count($options)];
    }

    /**
     * @param list<string>|null $values
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function categoryOptions(string $search, ?array $values, ?int $parentId, int $page, int $pageSize): array
    {
        /** @var \Mage_Catalog_Model_Resource_Category_Collection $collection */
        $collection = \Mage::getResourceModel('catalog/category_collection');
        $collection->setStoreId(\Mage_Core_Model_App::ADMIN_STORE_ID)
            ->addAttributeToSelect('name')
            ->addFieldToFilter('entity_id', ['neq' => \Mage_Catalog_Model_Category::TREE_ROOT_ID]);

        if ($values !== null) {
            $collection->addFieldToFilter('entity_id', ['in' => array_map(intval(...), $values)]);
        } elseif ($search !== '') {
            $collection->addAttributeToFilter('name', ['like' => '%' . $search . '%'])
                ->setOrder('name', 'ASC');
        } else {
            $collection->addFieldToFilter('parent_id', $parentId ?? \Mage_Catalog_Model_Category::TREE_ROOT_ID)
                ->setOrder('position', 'ASC');
        }
        $collection->setOrder('entity_id', 'ASC');
        if ($values === null) {
            $collection->setPageSize($pageSize)->setCurPage($page);
        }

        $items = [];
        foreach ($collection as $category) {
            $items[] = [
                'value' => (string) $category->getId(),
                'label' => (string) $category->getName(),
                'path' => (string) $category->getPath(),
                'hasChildren' => (int) $category->getChildrenCount() > 0,
            ];
        }
        return [$items, (int) $collection->getSize()];
    }

    /**
     * @param list<string>|null $values
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function productOptions(string $search, ?array $values, int $page, int $pageSize): array
    {
        /** @var \Mage_Catalog_Model_Resource_Product_Collection $collection */
        $collection = \Mage::getResourceModel('catalog/product_collection');
        $collection->setStoreId(\Mage_Core_Model_App::ADMIN_STORE_ID)->addAttributeToSelect('name');

        if ($values !== null) {
            $collection->addAttributeToFilter('sku', ['in' => $values]);
        } elseif ($search !== '') {
            $collection->addAttributeToFilter([
                ['attribute' => 'sku', 'like' => '%' . $search . '%'],
                ['attribute' => 'name', 'like' => '%' . $search . '%'],
            ]);
        }
        $collection->setOrder('sku', 'ASC')->setOrder('entity_id', 'ASC');
        if ($values === null) {
            $collection->setPageSize($pageSize)->setCurPage($page);
        }

        $items = [];
        foreach ($collection as $product) {
            $items[] = ['value' => (string) $product->getSku(), 'label' => (string) $product->getName()];
        }
        return [$items, (int) $collection->getSize()];
    }
}
